<?php

namespace App\Services\PaymentGateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * QiosPay QRIS Gateway Implementation
 * Uses static QR string converted to dynamic QRIS (like the bot does)
 */
class QiosPayGateway implements PaymentGatewayInterface
{
    private string $apiKey;
    private string $merchantCode;
    private string $qrString; // Static QRIS string
    private string $baseUrl = 'https://qiospay.id/api';

    public function __construct(array $credentials)
    {
        $this->apiKey = $credentials['api_key'] ?? '';
        $this->merchantCode = $credentials['merchant_code'] ?? '';
        $this->qrString = $credentials['qr_string'] ?? '';
    }

    /**
     * Calculate CRC16-CCITT for QRIS
     * Same algorithm as the bot's qiospay.js
     */
    private function calculateCRC16(string $str): string
    {
        $crc = 0xFFFF;

        for ($c = 0; $c < strlen($str); $c++) {
            $crc ^= ord($str[$c]) << 8;

            for ($i = 0; $i < 8; $i++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc = $crc << 1;
                }
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Create Dynamic QRIS from Static QRIS String
     * Same logic as bot's createDynamicQris()
     */
    private function createDynamicQris(int $amount): array
    {
        try {
            if (empty($this->qrString)) {
                throw new \Exception('Static QR String is not configured');
            }

            if ($amount <= 0) {
                throw new \Exception('Amount must be greater than 0');
            }

            // Remove old CRC (last 4 chars) and CRC tag (6304)
            $qrBase = substr($this->qrString, 0, -8); // Remove "6304XXXX"

            // Change from static (010211) to dynamic (010212)
            $qrBase = str_replace('010211', '010212', $qrBase);

            // Build amount tag: 54 [length] [amount]
            $amountStr = (string) $amount;
            $amountLength = str_pad((string) strlen($amountStr), 2, '0', STR_PAD_LEFT);
            $amountTag = '54' . $amountLength . $amountStr;

            // Find position to insert amount (before 5802ID)
            $countryCodePos = strpos($qrBase, '5802ID');

            if ($countryCodePos === false) {
                throw new \Exception('Invalid QRIS: Country code 5802ID not found');
            }

            // Insert amount tag before country code
            $beforeCountry = substr($qrBase, 0, $countryCodePos);
            $afterCountry = substr($qrBase, $countryCodePos);
            $qrWithAmount = $beforeCountry . $amountTag . $afterCountry;

            // Add CRC tag and calculate CRC16
            $qrWithCRCTag = $qrWithAmount . '6304';
            $crc = $this->calculateCRC16($qrWithCRCTag);

            // Final QRIS string
            $finalQris = $qrWithCRCTag . $crc;

            Log::info('Dynamic QRIS created', [
                'amount' => $amount,
                'qris_preview' => substr($finalQris, 0, 50) . '...',
                'length' => strlen($finalQris),
            ]);

            return [
                'success' => true,
                'qr_string' => $finalQris,
                'amount' => $amount,
            ];
        } catch (\Exception $e) {
            Log::error('QiosPay createDynamicQris error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function createPayment(array $data): array
    {
        try {
            $amount = (int) $data['amount'];
            $orderId = $data['order_id'];

            Log::info('QiosPay createPayment request', [
                'amount' => $amount,
                'order_id' => $orderId,
                'has_qr_string' => !empty($this->qrString),
            ]);

            // Generate dynamic QRIS from static string
            $dynamicQris = $this->createDynamicQris($amount);

            if (!$dynamicQris['success']) {
                return [
                    'success' => false,
                    'error' => $dynamicQris['error'] ?? 'Failed to create dynamic QRIS',
                ];
            }

            return [
                'success' => true,
                'payment_id' => $orderId,
                'qr_string' => $dynamicQris['qr_string'],
                'qr_image' => null, // Generate separately if needed
                'amount' => $amount,
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::error('QiosPay createPayment error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Gateway error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check payment status via QiosPay mutasi API
     * Match by amount + timestamp (since static QRIS has no transaction_id)
     */
    public function checkStatus(string $paymentId, ?int $expectedAmount = null, ?int $timeRangeSeconds = 300): array
    {
        try {
            $url = "{$this->baseUrl}/mutasi/qris/{$this->merchantCode}/{$this->apiKey}";

            Log::info('QiosPay checkStatus request', [
                'payment_id' => $paymentId,
                'expected_amount' => $expectedAmount,
                'time_range' => $timeRangeSeconds,
            ]);

            $response = Http::withoutVerifying()->timeout(30)->get($url);
            $result = $response->json();

            if ($response->successful() && isset($result['data']) && is_array($result['data'])) {
                $now = now();

                // Filter and find matching transaction
                foreach ($result['data'] as $trx) {
                    // Only credit transactions (uang masuk)
                    if (($trx['type'] ?? '') !== 'CR') {
                        continue;
                    }

                    $trxAmount = (int) ($trx['amount'] ?? 0);
                    $trxDate = isset($trx['date']) ? \Carbon\Carbon::parse($trx['date']) : null;

                    // If amount provided, must match
                    if ($expectedAmount !== null && $trxAmount !== $expectedAmount) {
                        continue;
                    }

                    // If time range provided, check timestamp
                    if ($trxDate && $timeRangeSeconds > 0) {
                        $timeDiff = abs($now->diffInSeconds($trxDate));
                        if ($timeDiff > $timeRangeSeconds) {
                            Log::info('QiosPay transaction time out of range', [
                                'trx_date' => $trxDate->toIso8601String(),
                                'time_diff' => $timeDiff,
                                'max_range' => $timeRangeSeconds,
                            ]);
                            continue;
                        }
                    }

                    Log::info('QiosPay transaction matched!', [
                        'amount' => $trxAmount,
                        'date' => $trx['date'] ?? 'N/A',
                        'issuer_reff' => $trx['issuer_reff'] ?? 'N/A',
                        'buyer_reff' => $trx['buyer_reff'] ?? 'N/A',
                    ]);

                    return [
                        'success' => true,
                        'status' => 'success',
                        'paid_at' => $trx['date'] ?? now()->toIso8601String(),
                        'amount' => $trxAmount,
                        'issuer_reff' => $trx['issuer_reff'] ?? null,
                        'buyer_reff' => $trx['buyer_reff'] ?? null,
                    ];
                }

                Log::info('QiosPay no matching transaction found', [
                    'total_transactions' => count($result['data']),
                    'expected_amount' => $expectedAmount,
                ]);
            }

            return [
                'success' => false,
                'status' => 'pending',
                'error' => 'Transaction not found or not paid yet',
            ];
        } catch (\Exception $e) {
            Log::error('QiosPay checkStatus error: ' . $e->getMessage());
            return [
                'success' => false,
                'status' => 'unknown',
                'error' => 'Gateway error: ' . $e->getMessage(),
            ];
        }
    }

    public function validateWebhook(array $payload, ?string $signature = null): bool
    {
        // QiosPay validates via API key in header or payload matching
        // Be flexible about different possible payload formats
        $data = $payload;
        if (isset($payload['data']) && is_array($payload['data'])) {
            $data = $payload['data'];
        }

        // Accept if amount is present (QiosPay static QRIS only sends amount)
        // OR if any identifying field exists
        $hasAmount = !empty($data['amount']) || !empty($data['nominal']) || !empty($payload['amount']);
        $hasId = !empty($data['transaction_id']) || !empty($data['trx_id']) ||
                 !empty($data['id']) || !empty($data['reference']) ||
                 !empty($payload['transaction_id']);

        return $hasAmount || $hasId;
    }

    public function parseWebhook(array $payload): array
    {
        // QiosPay may send data in nested format, unwrap if needed
        $data = $payload;
        if (isset($payload['data']) && is_array($payload['data'])) {
            $data = $payload['data'];
        }

        Log::info('QiosPay parseWebhook debug', [
            'payload_keys' => array_keys($payload),
            'data_keys' => array_keys($data),
            'payload' => $payload,
        ]);

        // Try multiple possible field names for payment ID
        $paymentId = $data['transaction_id'] ?? $data['trx_id'] ?? $data['reff_id'] ??
                     $data['reference'] ?? $payload['transaction_id'] ?? '';

        // QiosPay mutation ID (the unique ID from QiosPay mutasi list) - CRITICAL for preventing double match
        $qiosPayTrxId = $data['id'] ?? $payload['id'] ?? null;

        $status = $data['status'] ?? $payload['status'] ?? 'pending';
        $amount = (int) ($data['amount'] ?? $data['nominal'] ?? $payload['amount'] ?? 0);

        return [
            'payment_id' => $paymentId,
            'qiospay_trx_id' => $qiosPayTrxId, // QiosPay mutation ID
            'status' => $this->mapStatus($status),
            'amount' => $amount,
            'paid_at' => $data['paid_at'] ?? $data['date'] ?? $payload['paid_at'] ?? now()->toIso8601String(),
        ];
    }

    public function getCode(): string
    {
        return 'qiospay';
    }

    public function getName(): string
    {
        return 'QiosPay QRIS';
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'paid', 'success', 'completed' => 'success',
            'pending', 'waiting' => 'pending',
            'expired' => 'expired',
            'failed', 'cancelled' => 'failed',
            default => 'pending',
        };
    }
}
