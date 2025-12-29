<?php

namespace App\Services\PaymentGateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Order Kuota QRIS Gateway Implementation
 * Uses static QR string converted to dynamic QRIS with unique amount
 * Payment verification via mutation polling through Cloudflare Worker proxy
 */
class OrderKuotaGateway implements PaymentGatewayInterface
{
    private string $username;
    private string $token;
    private string $qrString;
    private string $proxyUrl;

    public function __construct(array $credentials)
    {
        $this->username = $credentials['username'] ?? '';
        $this->token = $credentials['token'] ?? '';
        $this->qrString = $credentials['qris_string'] ?? '';
        $this->proxyUrl = $credentials['proxy_url'] ?? env('ORDERKUOTA_PROXY_URL', 'https://workers.czel.me');
    }

    /**
     * Calculate CRC16-CCITT for QRIS
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
     * Create Dynamic QRIS from Static QRIS String with specific amount
     */
    private function createDynamicQris(int $amount): array
    {
        try {
            if (empty($this->qrString)) {
                throw new \Exception('Static QRIS String is not configured');
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
            
            Log::info('OrderKuota Dynamic QRIS created', [
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
            Log::error('OrderKuota createDynamicQris error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate unique amount by adding random suffix
     * This helps identify which payment belongs to which order
     */
    private function generateUniqueAmount(int $baseAmount): int
    {
        // Add random 1-102 to the end (unique code range)
        $suffix = rand(1, 102);
        return $baseAmount + $suffix;
    }

    public function createPayment(array $data): array
    {
        try {
            // Amount already includes unique code from bot, use as-is (like QiosPay)
            $amount = (int) $data['amount'];
            $orderId = $data['order_id'];

            Log::info('OrderKuota createPayment request', [
                'amount' => $amount,
                'order_id' => $orderId,
                'has_qr_string' => !empty($this->qrString),
            ]);

            // Generate dynamic QRIS from static string with the amount (already includes unique code)
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
                'qr_image' => null,
                'amount' => $amount,
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::error('OrderKuota createPayment error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Gateway error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check mutations from Order Kuota via proxy
     */
    public function getMutations(): array
    {
        try {
            $response = Http::timeout(30)->post("{$this->proxyUrl}/api/mutasi", [
                'username' => $this->username,
                'token' => $this->token,
                'jenis' => 'masuk',
            ]);

            $result = $response->json();

            if ($result['success'] ?? false) {
                return [
                    'success' => true,
                    'data' => $result['qris_history']['results'] ?? [],
                    'account' => $result['account']['results'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $result['message'] ?? 'Failed to get mutations',
            ];
        } catch (\Exception $e) {
            Log::error('OrderKuota getMutations error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check payment status by looking at mutations
     */
    public function checkStatus(string $paymentId): array
    {
        // For Order Kuota, we check via polling daemon, not direct check
        return [
            'success' => false,
            'status' => 'pending',
            'message' => 'Use polling daemon for status check',
        ];
    }

    /**
     * Request OTP for login
     */
    public function requestOtp(string $username, string $password): array
    {
        try {
            $response = Http::timeout(30)->post("{$this->proxyUrl}/api/login", [
                'username' => $username,
                'password' => $password,
            ]);

            return $response->json();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify OTP and get token
     */
    public function verifyOtp(string $username, string $otp): array
    {
        try {
            $response = Http::timeout(30)->post("{$this->proxyUrl}/api/get-token", [
                'username' => $username,
                'otp' => $otp,
            ]);

            return $response->json();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function validateWebhook(array $payload, ?string $signature = null): bool
    {
        // Order Kuota doesn't have webhooks, we use polling
        return false;
    }

    public function parseWebhook(array $payload): array
    {
        // Not used for Order Kuota
        return [
            'payment_id' => '',
            'status' => 'pending',
            'amount' => 0,
            'paid_at' => null,
        ];
    }

    public function getCode(): string
    {
        return 'orderkuota';
    }

    public function getName(): string
    {
        return 'Order Kuota QRIS';
    }
}
