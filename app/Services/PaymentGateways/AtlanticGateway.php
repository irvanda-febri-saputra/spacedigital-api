<?php

namespace App\Services\PaymentGateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Atlantic Pedia Payment Gateway
 * API Docs: https://docs.atlantic-pedia.co.id
 * 
 * Creates QRIS payments via Atlantic H2H
 */
class AtlanticGateway implements PaymentGatewayInterface
{
    protected string $apiKey;
    protected string $baseUrl = 'https://atlantich2h.com';
    protected string $proxyUrl;
    protected string $metode;  // 'qris' or 'QRISFAST'
    protected string $gatewayName;

    public function __construct(array $credentials)
    {
        $this->apiKey = $credentials['api_key'] ?? '';
        $this->metode = $credentials['metode'] ?? 'qris';  // Default: regular QRIS
        $this->gatewayName = $credentials['gateway_name'] ?? 'Atlantic Pedia';
        $this->proxyUrl = env('ATLANTIC_PROXY_URL', '');
    }

    /**
     * Make request via Cloudflare Worker proxy or direct
     */
    protected function makeRequest(string $endpoint, array $data): array
    {
        $data['api_key'] = $this->apiKey;

        if (!empty($this->proxyUrl)) {
            // Use Cloudflare Worker proxy
            $data['endpoint'] = $endpoint;
            $response = Http::asForm()->timeout(30)->post($this->proxyUrl, $data);
        } else {
            // Direct call to Atlantic
            $response = Http::asForm()->timeout(30)->post($this->baseUrl . $endpoint, $data);
        }

        $body = $response->body();
        
        // Check for HTML response (Cloudflare challenge)
        if (str_contains($body, '<!DOCTYPE html>') || str_contains($body, '<html')) {
            return ['success' => false, 'error' => 'Cloudflare challenge detected', 'html' => true];
        }

        return $response->json() ?? ['success' => false, 'error' => 'Invalid JSON response'];
    }

    /**
     * Get gateway code
     */
    public function getCode(): string
    {
        return $this->metode === 'QRISFAST' ? 'atlantic_fast' : 'atlantic';
    }

    /**
     * Get gateway name
     */
    public function getName(): string
    {
        return $this->gatewayName;
    }

    /**
     * Create a QRIS payment
     * 
     * API Endpoint: POST /deposit/create
     * Response includes qr_string and qr_image for QRIS
     */
    public function createPayment(array $data): array
    {
        try {
            $requestData = [
                'reff_id' => $data['order_id'],
                'nominal' => $data['amount'],
                'type' => 'ewallet',
                'metode' => $this->metode,
                'callback_url' => $data['callback_url'] ?? config('app.url') . '/api/payments/webhook/atlantic',
            ];

            Log::info('Atlantic createPayment request', [
                'proxy' => !empty($this->proxyUrl),
                'order_id' => $data['order_id'],
            ]);

            $result = $this->makeRequest('/deposit/create', $requestData);

            // Check if proxy/request failed
            if (isset($result['html']) && $result['html'] === true) {
                Log::warning('Atlantic createPayment: Cloudflare challenge detected', [
                    'order_id' => $data['order_id'],
                ]);
                return [
                    'success' => false,
                    'error' => 'Atlantic API blocked by Cloudflare. Please check proxy configuration.',
                ];
            }

            if (($result['status'] ?? false) === true && isset($result['data'])) {
                $paymentData = $result['data'];
                
                return [
                    'success' => true,
                    'payment_id' => $paymentData['id'] ?? $data['order_id'],
                    'qr_string' => $paymentData['qr_string'] ?? '',
                    'qr_image' => $paymentData['qr_image'] ?? null,
                    'amount' => $paymentData['nominal'] ?? $data['amount'],
                    'fee' => $paymentData['fee'] ?? 0,
                    'net_amount' => $paymentData['get_balance'] ?? $data['amount'],
                    'expires_at' => $paymentData['expired_at'] ?? now()->addHour()->toIso8601String(),
                    'raw_response' => $paymentData,
                ];
            }

            return [
                'success' => false,
                'error' => $result['message'] ?? 'Failed to create QRIS payment',
            ];

        } catch (\Exception $e) {
            Log::error('Atlantic createPayment error', [
                'order_id' => $data['order_id'],
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to connect to Atlantic: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check payment status
     * 
     * API Endpoint: GET /deposit/status
     */
    public function checkStatus(string $paymentId): array
    {
        try {
            $result = $this->makeRequest('/deposit/status', [
                'id' => $paymentId,
            ]);

            if (($result['status'] ?? false) === true && isset($result['data'])) {
                $statusMap = [
                    'pending' => 'pending',
                    'processing' => 'processing',
                    'success' => 'success',
                    'expired' => 'expired',
                    'failed' => 'failed',
                ];

                $atlanticStatus = strtolower($result['data']['status'] ?? 'pending');
                
                return [
                    'success' => true,
                    'status' => $statusMap[$atlanticStatus] ?? 'pending',
                    'payment_id' => $result['data']['id'] ?? $paymentId,
                    'amount' => $result['data']['nominal'] ?? 0,
                    'paid_at' => $atlanticStatus === 'success' ? ($result['data']['paid_at'] ?? now()->toIso8601String()) : null,
                ];
            }

            return [
                'success' => false,
                'status' => 'unknown',
                'error' => $result['message'] ?? 'Failed to check status',
            ];

        } catch (\Exception $e) {
            Log::error('Atlantic checkStatus error', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'unknown',
                'error' => 'Failed to check status: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Parse incoming webhook data
     */
    public function parseWebhook(array $payload): array
    {
        // Atlantic may send data in nested format: {"data": {...}} or {"event": "...", "data": {...}}
        // Unwrap if needed
        $data = $payload;
        if (isset($payload['data']) && is_array($payload['data'])) {
            $data = $payload['data'];
        }
        
        Log::info('Atlantic parseWebhook debug', [
            'original_payload_keys' => array_keys($payload),
            'data_keys' => array_keys($data),
            'reff_id' => $data['reff_id'] ?? 'NOT FOUND',
            'id' => $data['id'] ?? 'NOT FOUND',
            'status' => $data['status'] ?? 'NOT FOUND',
        ]);
        
        return [
            // payment_id = reff_id (our order ID) for database matching
            'payment_id' => $data['reff_id'] ?? $payload['reff_id'] ?? '',
            // deposit_id = Atlantic internal ID for triggerInstant API call
            'deposit_id' => $data['id'] ?? $payload['id'] ?? '',
            // Map Atlantic status to our status
            'status' => match(strtolower($data['status'] ?? $payload['status'] ?? 'pending')) {
                'success' => 'success',
                'processing' => 'processing',
                'failed', 'cancel', 'error' => 'failed',
                'expired' => 'expired',
                default => 'pending'
            },
            'amount' => $data['nominal'] ?? $payload['nominal'] ?? 0,
            'paid_at' => $data['paid_at'] ?? $payload['paid_at'] ?? null,
        ];
    }

    /**
     * Validate webhook signature
     * Atlantic may use different validation method - check their docs
     */
    public function validateWebhook(array $payload, ?string $signature = null): bool
    {
        // Atlantic webhook validation
        // If they provide a signature method, implement here
        // For now, we trust the webhook if it comes from Atlantic's IP or has valid data
        
        if (empty($payload)) {
            return false;
        }
        
        // Unwrap nested data if present
        $data = $payload;
        if (isset($payload['data']) && is_array($payload['data'])) {
            $data = $payload['data'];
        }

        // Basic validation - check required fields exist (in data or top-level)
        if (!isset($data['id']) && !isset($data['reff_id']) && 
            !isset($payload['id']) && !isset($payload['reff_id'])) {
            return false;
        }

        return true;
    }

    /**
     * Trigger instant withdrawal (Cair Instant)
     * This is needed for regular QRIS to complete the transaction
     * 
     * API Endpoint: POST /deposit/instant
     */
    public function triggerInstant(string $depositId): array
    {
        try {
            Log::info('Atlantic triggerInstant', ['deposit_id' => $depositId]);

            $result = $this->makeRequest('/deposit/instant', [
                'id' => $depositId,
                'action' => 'true',  // Enable instant
            ]);

            Log::info('Atlantic triggerInstant response', [
                'deposit_id' => $depositId,
                'result' => $result,
            ]);

            if (($result['status'] ?? false) === true) {
                return [
                    'success' => true,
                    'message' => 'Instant withdrawal triggered',
                    'data' => $result['data'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $result['message'] ?? 'Failed to trigger instant',
            ];

        } catch (\Exception $e) {
            Log::error('Atlantic triggerInstant error', [
                'deposit_id' => $depositId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to trigger instant: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get available deposit methods
     */
    public function getDepositMethods(): array
    {
        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post("{$this->baseUrl}/deposit/method", [
                    'api_key' => $this->apiKey,
                ]);

            $result = $response->json();

            if ($result['status'] === true && isset($result['data'])) {
                return [
                    'success' => true,
                    'methods' => $result['data'],
                ];
            }

            return [
                'success' => false,
                'methods' => [],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'methods' => [],
                'error' => $e->getMessage(),
            ];
        }
    }
}
