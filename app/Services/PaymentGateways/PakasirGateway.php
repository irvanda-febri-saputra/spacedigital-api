<?php

namespace App\Services\PaymentGateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pakasir Payment Gateway Implementation
 * Based on AUTOORDER-PAKASIR/src/services/pakasir.js
 * 
 * Dokumentasi: https://app.pakasir.com/docs
 */
class PakasirGateway implements PaymentGatewayInterface
{
    private string $projectSlug;
    private string $apiKey;
    private string $baseUrl = 'https://app.pakasir.com';

    public function __construct(array $credentials)
    {
        $this->projectSlug = $credentials['slug'] ?? $credentials['project_slug'] ?? $credentials['merchant_id'] ?? '';
        $this->apiKey = $credentials['api_key'] ?? '';
    }

    /**
     * Create Payment via Pakasir API
     */
    public function createPayment(array $data): array
    {
        try {
            $amount = (int) $data['amount'];
            $orderId = $data['order_id'];
            $method = $data['method'] ?? 'qris';

            Log::info('Pakasir createPayment request', [
                'project' => $this->projectSlug,
                'order_id' => $orderId,
                'amount' => $amount,
                'method' => $method,
            ]);

            $url = "{$this->baseUrl}/api/transactioncreate/{$method}";

            $response = Http::withoutVerifying()
                ->timeout(30)
                ->post($url, [
                    'project' => $this->projectSlug,
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'api_key' => $this->apiKey,
                ]);

            $result = $response->json();

            Log::info('Pakasir createPayment response', [
                'status' => $response->status(),
                'body' => $result,
            ]);

            if ($response->successful() && isset($result['payment'])) {
                $payment = $result['payment'];

                return [
                    'success' => true,
                    'payment_id' => $orderId,
                    'qr_string' => $payment['payment_number'] ?? null, // QR String atau VA Number
                    'qr_image' => null,
                    'amount' => $payment['total_payment'] ?? $amount,
                    'fee' => $payment['fee'] ?? 0,
                    'expires_at' => $payment['expired_at'] ?? now()->addMinutes(15)->toIso8601String(),
                ];
            }

            return [
                'success' => false,
                'error' => $result['message'] ?? 'Failed to create transaction',
            ];
        } catch (\Exception $e) {
            Log::error('Pakasir createPayment error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Gateway error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check Transaction Status via Pakasir API
     */
    public function checkStatus(string $paymentId): array
    {
        try {
            $url = "{$this->baseUrl}/api/transactiondetail";

            // Note: paymentId should be in format "orderId:amount" or we need to track amount separately
            $parts = explode(':', $paymentId);
            $orderId = $parts[0];
            $amount = $parts[1] ?? 0;

            $response = Http::withoutVerifying()
                ->timeout(15)
                ->get($url, [
                    'project' => $this->projectSlug,
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'api_key' => $this->apiKey,
                ]);

            $result = $response->json();

            if ($response->successful() && isset($result['transaction'])) {
                $trx = $result['transaction'];

                return [
                    'success' => true,
                    'status' => $this->mapStatus($trx['status'] ?? ''),
                    'paid_at' => $trx['completed_at'] ?? null,
                    'amount' => $trx['amount'] ?? 0,
                ];
            }

            return [
                'success' => false,
                'status' => 'pending',
                'error' => 'Transaction not found',
            ];
        } catch (\Exception $e) {
            Log::error('Pakasir checkStatus error: ' . $e->getMessage());
            return [
                'success' => false,
                'status' => 'unknown',
                'error' => 'Gateway error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate Webhook from Pakasir
     * 
     * Webhook Body:
     * {
     *   "amount": 22000,
     *   "order_id": "240910HDE7C9",
     *   "project": "depodomain",
     *   "status": "completed",
     *   "payment_method": "qris",
     *   "completed_at": "2024-09-10T08:07:02.819+07:00"
     * }
     */
    public function validateWebhook(array $payload, ?string $signature = null): bool
    {
        // Validate required fields
        $required = ['amount', 'order_id', 'project', 'status'];
        foreach ($required as $field) {
            if (!isset($payload[$field])) {
                Log::warning("Pakasir webhook missing field: {$field}");
                return false;
            }
        }

        // Validate project matches
        if ($payload['project'] !== $this->projectSlug) {
            Log::warning("Pakasir webhook project mismatch: {$payload['project']} vs {$this->projectSlug}");
            return false;
        }

        return true;
    }

    public function parseWebhook(array $payload): array
    {
        return [
            'payment_id' => $payload['order_id'] ?? '',
            'status' => $this->mapStatus($payload['status'] ?? ''),
            'amount' => (int) ($payload['amount'] ?? 0),
            'paid_at' => $payload['completed_at'] ?? now()->toIso8601String(),
        ];
    }

    public function getCode(): string
    {
        return 'pakasir';
    }

    public function getName(): string
    {
        return 'Pakasir';
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'success', 'paid' => 'success',
            'pending', 'waiting' => 'pending',
            'expired' => 'expired',
            'cancelled', 'failed' => 'failed',
            default => 'pending',
        };
    }
}
