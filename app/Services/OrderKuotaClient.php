<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Direct Order Kuota API Client
 * Calls Order Kuota API directly with SHA512 signature (no Worker needed)
 */
class OrderKuotaClient
{
    private string $username;
    private string $token;

    private const APP_PARAMS = [
        'app_reg_id' => 'feWAyrROTHe_RYH3Sbruw8:APA91bFbdiCCuyMLLTtieOr4W5fiSlzPHwUOe9w75UwmiHt7zywlgKi_zlKi5WUSq6pJdqHNkRD7J98p2hU7UBKK5R2wh5xcOQRhLoyb9PNWXTDiFmjrua4',
        'phone_uuid' => 'feWAyrROTHe_RYH3Sbruw8',
        'phone_model' => '23124RA7EO',
        'phone_android_version' => '15',
        'app_version_code' => '251029',
        'app_version_name' => '25.10.29',
        'ui_mode' => 'light',
    ];

    public function __construct(string $username, string $token)
    {
        $this->username = $username;
        $this->token = $token;
    }

    /**
     * Generate SHA512 signature for Order Kuota API
     */
    private function generateSignature(array $params, string $timestamp): string
    {
        $formattedParams = [];

        foreach ($params as $key => $value) {
            $val = (string) $value;
            $formattedParams[] = strlen($val) . $val;
        }

        sort($formattedParams);
        $varA = implode('', $formattedParams);
        $hashInput = $timestamp . $varA;

        return hash('sha512', $hashInput);
    }

    /**
     * Make signed request to Order Kuota API (via Cloudflare Worker proxy)
     */
    private function makeSignedRequest(string $path, array $params): array
    {
        $timestamp = (string) (int) (microtime(true) * 1000);
        $params['request_time'] = $timestamp;

        $signature = $this->generateSignature($params, $timestamp);

        // Use Cloudflare Worker proxy to avoid IP blocking
        $proxyUrl = env('ORDERKUOTA_PROXY_URL', 'https://workers.czel.me');

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'User-Agent' => 'okhttp/4.12.0',
                    'Signature' => $signature,
                    'Timestamp' => $timestamp,
                ])
                ->asForm()
                ->post("{$proxyUrl}/proxy{$path}", $params);

            $data = $response->json();

            // Check for proxy error or API blocking message
            if (isset($data['message']) && str_contains($data['message'], 'Gunakan Jaringan')) {
                return [
                    'success' => false,
                    'error' => 'Order Kuota API blocked - try again later',
                ];
            }

            return [
                'success' => true,
                'status' => $response->status(),
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error("OrderKuota API error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get QRIS mutations (incoming payments) via Cloudflare Worker proxy
     */
    public function getMutations(): array
    {
        // Use Cloudflare Worker proxy endpoint
        $proxyUrl = env('ORDERKUOTA_PROXY_URL', 'https://workers.czel.me');

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post("{$proxyUrl}/api/mutasi", [
                    'username' => $this->username,
                    'token' => $this->token,
                    'jenis' => 'masuk',
                ]);

            $data = $response->json();

            // Log raw response for debugging
            Log::debug("OrderKuota raw response", [
                'status' => $response->status(),
                'keys' => $data ? array_keys($data) : [],
                'sample' => $data ? json_encode(array_slice($data, 0, 3)) : 'null',
            ]);

            if (!$data) {
                return ['success' => false, 'error' => 'Empty response from proxy'];
            }

            // Check for error message
            if (isset($data['error'])) {
                return ['success' => false, 'error' => $data['error']];
            }

            // Check for success response
            if ($data['success'] ?? false) {
                $mutations = $data['qris_history']['results'] ?? $data['mutations'] ?? [];

                // Log mutation structure
                if (!empty($mutations)) {
                    Log::debug("OrderKuota mutation sample", [
                        'firstMutation' => json_encode($mutations[0] ?? []),
                    ]);
                }

                return [
                    'success' => true,
                    'mutations' => $mutations,
                    'account' => $data['account'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $data['message'] ?? 'API returned error',
            ];

        } catch (\Exception $e) {
            Log::error("OrderKuota getMutations error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Request OTP for login
     */
    public static function requestOtp(string $username, string $password): array
    {
        $client = new self($username, '');

        $params = array_merge(self::APP_PARAMS, [
            'username' => $username,
            'password' => $password,
        ]);

        return $client->makeSignedRequest('/api/v2/login', $params);
    }

    /**
     * Verify OTP and get token
     */
    public static function verifyOtp(string $username, string $otp): array
    {
        $client = new self($username, '');

        $params = array_merge(self::APP_PARAMS, [
            'username' => $username,
            'password' => $otp,
        ]);

        return $client->makeSignedRequest('/api/v2/login', $params);
    }
}
