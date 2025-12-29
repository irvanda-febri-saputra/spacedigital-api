<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use App\Models\UserGateway;
use App\Models\PaymentGateway;

class OrderKuotaToolsController extends Controller
{
    private string $proxyUrl;

    public function __construct()
    {
        $this->proxyUrl = env('ORDERKUOTA_PROXY_URL', 'https://workers.czel.me');
    }

    /**
     * Show Order Kuota Tools page
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get user's Order Kuota gateway config
        $gateway = PaymentGateway::where('code', 'orderkuota')->first();
        $userGateway = null;

        if ($gateway) {
            $userGateway = UserGateway::where('user_id', $user->id)
                ->where('gateway_id', $gateway->id)
                ->first();
        }

        $credentials = $userGateway?->credentials ?? [];

        return Inertia::render('OrderKuotaTools/Index', [
            'currentToken' => $credentials['token'] ?? null,
            'currentUsername' => $credentials['username'] ?? null,
            'qrisString' => $credentials['qris_string'] ?? null,
            'tokenSavedAt' => $credentials['token_saved_at'] ?? null,
            'hasCredentials' => !empty($credentials['token']),
        ]);
    }

    /**
     * Request OTP for login
     */
    public function requestOtp(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $response = Http::timeout(30)->post("{$this->proxyUrl}/api/login", [
                'username' => $validated['username'],
                'password' => $validated['password'],
            ]);

            $result = $response->json();

            if ($result['success'] ?? false) {
                // Temporarily store username for OTP verification
                session(['orderkuota_username' => $validated['username']]);

                return back()->with('success', 'OTP sent to your email!');
            }

            return back()->withErrors(['login' => $result['message'] ?? 'Failed to request OTP']);
        } catch (\Exception $e) {
            return back()->withErrors(['login' => 'Connection error: ' . $e->getMessage()]);
        }
    }

    /**
     * Verify OTP and save token
     */
    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'otp' => 'required|string|size:5',
        ]);

        $username = session('orderkuota_username');

        if (!$username) {
            return back()->withErrors(['otp' => 'Session expired. Please login again.']);
        }

        try {
            $response = Http::timeout(30)->post("{$this->proxyUrl}/api/get-token", [
                'username' => $username,
                'otp' => $validated['otp'],
            ]);

            $result = $response->json();

            if ($result['success'] ?? false) {
                // Token can be in different places depending on API version
                $token = $result['results']['token']
                    ?? $result['auth_token']
                    ?? $result['token']
                    ?? null;

                if ($token) {
                    // Save token to user gateway
                    $this->saveToken($request->user(), $username, $token);
                    session()->forget('orderkuota_username');

                    return back()->with('success', 'Token saved successfully!');
                }
            }

            return back()->withErrors(['otp' => $result['message'] ?? 'Failed to verify OTP']);
        } catch (\Exception $e) {
            return back()->withErrors(['otp' => 'Connection error: ' . $e->getMessage()]);
        }
    }

    /**
     * Check token validity
     */
    public function checkToken(Request $request)
    {
        $user = $request->user();
        $credentials = $this->getCredentials($user);

        if (empty($credentials['token']) || empty($credentials['username'])) {
            return response()->json([
                'valid' => false,
                'message' => 'No token configured',
            ]);
        }

        try {
            $response = Http::timeout(30)->post("{$this->proxyUrl}/api/mutasi", [
                'username' => $credentials['username'],
                'token' => $credentials['token'],
                'jenis' => 'masuk',
            ]);

            $result = $response->json();

            if ($result['success'] ?? false) {
                return response()->json([
                    'valid' => true,
                    'message' => 'Token is valid',
                    'balance' => $result['account']['results']['saldo'] ?? null,
                ]);
            }

            return response()->json([
                'valid' => false,
                'message' => $result['message'] ?? 'Token invalid',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get mutations
     */
    public function getMutations(Request $request)
    {
        $user = $request->user();
        $credentials = $this->getCredentials($user);

        if (empty($credentials['token'])) {
            return response()->json(['error' => 'No token configured'], 400);
        }

        try {
            $response = Http::timeout(30)->post("{$this->proxyUrl}/api/mutasi", [
                'username' => $credentials['username'],
                'token' => $credentials['token'],
                'jenis' => $request->get('type', 'masuk'),
            ]);

            $result = $response->json();

            if ($result['success'] ?? false) {
                return response()->json([
                    'success' => true,
                    'mutations' => $result['qris_history']['results'] ?? [],
                    'account' => $result['account']['results'] ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['message'] ?? 'Failed to get mutations',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate dynamic QRIS
     */
    public function generateQris(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $credentials = $this->getCredentials($user);

        if (empty($credentials['qris_string'])) {
            return response()->json(['error' => 'QRIS string not configured'], 400);
        }

        try {
            $qrString = $credentials['qris_string'];
            $amount = $validated['amount'];

            // Generate dynamic QRIS
            $dynamicQris = $this->createDynamicQris($qrString, $amount);

            if ($dynamicQris['success']) {
                return response()->json([
                    'success' => true,
                    'qr_string' => $dynamicQris['qr_string'],
                    'amount' => $amount,
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $dynamicQris['error'] ?? 'Failed to generate QRIS',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save QRIS string
     */
    public function saveQrisString(Request $request)
    {
        $validated = $request->validate([
            'qris_string' => 'required|string|min:50',
        ]);

        $user = $request->user();
        $this->updateCredentials($user, ['qris_string' => $validated['qris_string']]);

        return back()->with('success', 'QRIS string saved!');
    }

    // Helper methods

    private function getCredentials($user): array
    {
        $gateway = PaymentGateway::where('code', 'orderkuota')->first();
        if (!$gateway) return [];

        $userGateway = UserGateway::where('user_id', $user->id)
            ->where('gateway_id', $gateway->id)
            ->first();

        return $userGateway?->credentials ?? [];
    }

    private function saveToken($user, string $username, string $token): void
    {
        $this->updateCredentials($user, [
            'username' => $username,
            'token' => $token,
            'token_saved_at' => now()->toIso8601String(),
        ]);
    }

    private function updateCredentials($user, array $newData): void
    {
        $gateway = PaymentGateway::where('code', 'orderkuota')->first();
        if (!$gateway) return;

        $userGateway = UserGateway::firstOrCreate(
            ['user_id' => $user->id, 'gateway_id' => $gateway->id],
            ['is_active' => true, 'credentials' => []]
        );

        $credentials = $userGateway->credentials ?? [];
        $credentials = array_merge($credentials, $newData);

        $userGateway->update(['credentials' => $credentials]);
    }

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

    private function createDynamicQris(string $qrString, int $amount): array
    {
        try {
            // Remove old CRC
            $qrBase = substr($qrString, 0, -8);

            // Change static to dynamic
            $qrBase = str_replace('010211', '010212', $qrBase);

            // Build amount tag
            $amountStr = (string) $amount;
            $amountLength = str_pad((string) strlen($amountStr), 2, '0', STR_PAD_LEFT);
            $amountTag = '54' . $amountLength . $amountStr;

            // Find position to insert
            $countryCodePos = strpos($qrBase, '5802ID');
            if ($countryCodePos === false) {
                throw new \Exception('Invalid QRIS: Country code not found');
            }

            // Insert amount tag
            $beforeCountry = substr($qrBase, 0, $countryCodePos);
            $afterCountry = substr($qrBase, $countryCodePos);
            $qrWithAmount = $beforeCountry . $amountTag . $afterCountry;

            // Add CRC
            $qrWithCRCTag = $qrWithAmount . '6304';
            $crc = $this->calculateCRC16($qrWithCRCTag);

            return [
                'success' => true,
                'qr_string' => $qrWithCRCTag . $crc,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ============================================================
    // API METHODS (for SPA Dashboard)
    // ============================================================

    /**
     * API: Get Order Kuota status
     */
    public function apiStatus(Request $request)
    {
        $user = $request->user();
        $credentials = $this->getCredentials($user);

        return response()->json([
            'success' => true,
            'data' => [
                'has_token' => !empty($credentials['token']),
                'username' => $credentials['username'] ?? null,
                'token_saved_at' => $credentials['token_saved_at'] ?? null,
                'has_qris' => !empty($credentials['qris_string']),
            ],
        ]);
    }

    /**
     * API: Request OTP
     */
    public function apiRequestOtp(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        \Illuminate\Support\Facades\Log::info("OrderKuota OTP Request", [
            'username' => $validated['username'],
            'proxyUrl' => $this->proxyUrl,
        ]);

        try {
            $response = Http::timeout(30)->post("{$this->proxyUrl}/api/login", [
                'username' => $validated['username'],
                'password' => $validated['password'],
            ]);

            $result = $response->json();

            \Illuminate\Support\Facades\Log::info("OrderKuota OTP Response", [
                'status' => $response->status(),
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? null,
            ]);

            if ($result['success'] ?? false) {
                // Store username in cache for OTP verification
                cache()->put('orderkuota_otp_' . $request->user()->id, $validated['username'], now()->addMinutes(10));

                return response()->json([
                    'success' => true,
                    'message' => 'OTP sent to your email!',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to request OTP',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Verify OTP
     */
    public function apiVerifyOtp(Request $request)
    {
        $validated = $request->validate([
            'otp' => 'required|string|size:5',
        ]);

        $username = cache()->get('orderkuota_otp_' . $request->user()->id);

        if (!$username) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please login again.',
            ], 400);
        }

        try {
            $response = Http::timeout(30)->post("{$this->proxyUrl}/api/get-token", [
                'username' => $username,
                'otp' => $validated['otp'],
            ]);

            $result = $response->json();

            if ($result['success'] ?? false) {
                $token = $result['results']['token']
                    ?? $result['auth_token']
                    ?? $result['token']
                    ?? null;

                if ($token) {
                    $this->saveToken($request->user(), $username, $token);
                    cache()->forget('orderkuota_otp_' . $request->user()->id);

                    return response()->json([
                        'success' => true,
                        'message' => 'Token saved successfully!',
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to verify OTP',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Check token validity
     */
    public function apiCheckToken(Request $request)
    {
        $user = $request->user();
        $credentials = $this->getCredentials($user);

        if (empty($credentials['token']) || empty($credentials['username'])) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'No token configured',
            ]);
        }

        try {
            $response = Http::timeout(30)->post("{$this->proxyUrl}/api/mutasi", [
                'username' => $credentials['username'],
                'token' => $credentials['token'],
                'jenis' => 'masuk',
            ]);

            $result = $response->json();

            if ($result['success'] ?? false) {
                return response()->json([
                    'success' => true,
                    'valid' => true,
                    'message' => 'Token is valid',
                    'balance' => $result['account']['results']['saldo'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'valid' => false,
                'message' => $result['message'] ?? 'Token invalid',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Get mutations
     */
    public function apiGetMutations(Request $request)
    {
        $user = $request->user();
        $credentials = $this->getCredentials($user);

        if (empty($credentials['token'])) {
            return response()->json([
                'success' => false,
                'message' => 'No token configured',
            ], 400);
        }

        try {
            $response = Http::timeout(30)->post("{$this->proxyUrl}/api/mutasi", [
                'username' => $credentials['username'],
                'token' => $credentials['token'],
                'jenis' => $request->get('type', 'masuk'),
            ]);

            $result = $response->json();

            if ($result['success'] ?? false) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'mutations' => $result['qris_history']['results'] ?? [],
                        'account' => $result['account']['results'] ?? null,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to get mutations',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Generate QRIS
     */
    public function apiGenerateQris(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $credentials = $this->getCredentials($user);

        if (empty($credentials['qris_string'])) {
            return response()->json([
                'success' => false,
                'message' => 'QRIS string not configured',
            ], 400);
        }

        try {
            $dynamicQris = $this->createDynamicQris($credentials['qris_string'], $validated['amount']);

            if ($dynamicQris['success']) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'qr_string' => $dynamicQris['qr_string'],
                        'amount' => $validated['amount'],
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $dynamicQris['error'] ?? 'Failed to generate QRIS',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Save QRIS string
     */
    public function apiSaveQrisString(Request $request)
    {
        $validated = $request->validate([
            'qris_string' => 'required|string|min:50',
        ]);

        $user = $request->user();
        $this->updateCredentials($user, ['qris_string' => $validated['qris_string']]);

        return response()->json([
            'success' => true,
            'message' => 'QRIS string saved!',
        ]);
    }
}
