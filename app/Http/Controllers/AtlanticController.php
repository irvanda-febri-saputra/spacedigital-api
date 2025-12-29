<?php

namespace App\Http\Controllers;

use App\Models\UserGateway;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AtlanticController extends Controller
{
    private $baseUrl = 'https://atlantich2h.com';
    private $proxyUrl;
    private $timeout = 15;
    private $withdrawFee = 3500;

    public function __construct()
    {
        $this->proxyUrl = env('ATLANTIC_PROXY_URL', '');
    }

    /**
     * Make request via proxy or direct
     */
    private function makeApiRequest(string $endpoint, array $data)
    {
        if (!empty($this->proxyUrl)) {
            $data['endpoint'] = $endpoint;
            return Http::timeout($this->timeout)->asForm()->post($this->proxyUrl, $data);
        }
        return Http::timeout($this->timeout)->asForm()->post($this->baseUrl . $endpoint, $data);
    }

    /**
     * Get the Atlantic API key for current user
     */
    private function getApiKey(Request $request)
    {
        $userGateway = UserGateway::where('user_id', $request->user()->id)
            ->whereHas('gateway', function ($q) {
                $q->where('code', 'like', 'atlantic%');
            })
            ->where('is_active', true)
            ->first();

        if (!$userGateway) {
            return null;
        }

        $credentials = $userGateway->credentials;
        return $credentials['api_key'] ?? null;
    }

    /**
     * Fetch profile/balance from Atlantic API
     */
    private function fetchProfile($apiKey)
    {
        try {
            $response = $this->makeApiRequest('/get_profile', [
                'api_key' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] === 'true' || $data['status'] === true) {
                    $profileData = $data['data'] ?? [];

                    $pendingBalance =
                        $profileData['settlement_balance'] ??
                        $profileData['pending_balance'] ??
                        $profileData['pending_saldo'] ??
                        null;

                    return [
                        'balance' => (int) ($profileData['balance'] ?? 0),
                        'pending_balance' => $pendingBalance !== null ? (int) $pendingBalance : null,
                        'phone' => $profileData['phone'] ?? '',
                        'email' => $profileData['email'] ?? '',
                        'name' => $profileData['name'] ?? '',
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Atlantic get_profile error: ' . $e->getMessage());
        }

        return ['balance' => 0, 'pending_balance' => null, 'phone' => '', 'email' => '', 'name' => ''];
    }

    /**
     * Fetch bank list with caching (1 hour cache)
     */
    private function fetchBankListCached($apiKey)
    {
        $cacheKey = 'atlantic_bank_list_' . md5($apiKey);

        return Cache::remember($cacheKey, 3600, function () use ($apiKey) {
            return $this->fetchBankList($apiKey);
        });
    }

    /**
     * Fetch bank list from Atlantic API
     */
    private function fetchBankList($apiKey)
    {
        try {
            $response = $this->makeApiRequest('/transfer/bank_list', [
                'api_key' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] === 'true' || $data['status'] === true) {
                    return collect($data['data'])->map(function ($bank) {
                        return [
                            'code' => $bank['bank_code'] ?? $bank['code'] ?? '',
                            'name' => $bank['bank_name'] ?? $bank['name'] ?? '',
                            'type' => $bank['type'] ?? 'bank',
                        ];
                    })->toArray();
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Atlantic bank_list error: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * API: Get balance
     */
    public function apiBalance(Request $request)
    {
        $apiKey = $this->getApiKey($request);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Atlantic gateway not configured',
                'balance' => 0,
                'pendingBalance' => null,
            ]);
        }

        $profile = $this->fetchProfile($apiKey);

        return response()->json([
            'success' => true,
            'balance' => $profile['balance'],
            'pendingBalance' => $profile['pending_balance'],
        ]);
    }

    /**
     * API: Get bank list
     */
    public function apiBanks(Request $request)
    {
        $apiKey = $this->getApiKey($request);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Atlantic gateway not configured',
                'banks' => [],
            ]);
        }

        $banks = $this->fetchBankListCached($apiKey);

        return response()->json([
            'success' => true,
            'banks' => $banks,
        ]);
    }

    /**
     * API: Verify account
     */
    public function apiVerify(Request $request)
    {
        $request->validate([
            'bank_code' => 'required|string',
            'account_number' => 'required|string',
        ]);

        $apiKey = $this->getApiKey($request);

        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'Atlantic gateway not configured'], 400);
        }

        try {
            $response = $this->makeApiRequest('/transfer/cek_rekening', [
                'api_key' => $apiKey,
                'bank_code' => $request->bank_code,
                'account_number' => $request->account_number,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                if ($result['status'] === true || $result['status'] === 'true') {
                    $data = $result['data'] ?? [];
                    return response()->json([
                        'success' => true,
                        'account_name' => $data['nama_pemilik'] ?? $data['account_name'] ?? $data['name'] ?? '',
                        'account_number' => $data['nomor_akun'] ?? $request->account_number,
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => $response->json('message') ?? 'Account verification failed',
            ]);
        } catch (\Exception $e) {
            \Log::error('Atlantic verify error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Connection error'], 500);
        }
    }

    /**
     * API: Submit withdrawal
     */
    public function apiWithdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:10000',
            'bank_code' => 'required|string',
            'account_name' => 'required|string',
            'account_number' => 'required|string',
        ]);

        $apiKey = $this->getApiKey($request);

        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'Atlantic gateway not configured'], 400);
        }

        $refId = 'WD-' . strtoupper(Str::random(12));

        try {
            $profile = $this->fetchProfile($apiKey);
            $phone = $profile['phone'] ?? '';
            if (str_starts_with($phone, '62')) {
                $phone = '0' . substr($phone, 2);
            }

            $requestData = [
                'api_key' => $apiKey,
                'ref_id' => $refId,
                'kode_bank' => strtoupper($request->bank_code),
                'nomor_akun' => $request->account_number,
                'nama_pemilik' => $request->account_name,
                'nominal' => (string) $request->amount,
                'email' => $request->user()->email,
                'phone' => $phone,
                'note' => 'Withdrawal from SpaceDigital Dashboard',
            ];

            $response = $this->makeApiRequest('/transfer/create', $requestData);

            if ($response->successful()) {
                $result = $response->json();
                if ($result['status'] === true || $result['status'] === 'true') {
                    $data = $result['data'] ?? [];

                    // Clear bank list cache
                    Cache::forget('atlantic_bank_list_' . md5($apiKey));

                    // Create notification
                    Notification::create([
                        'user_id' => $request->user()->id,
                        'type' => 'withdraw',
                        'title' => 'Withdrawal Submitted',
                        'message' => 'Withdrawal of Rp ' . number_format($request->amount, 0, ',', '.') . ' to ' . $request->bank_code . ' ' . $request->account_number . ' is being processed.',
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Withdrawal submitted! ID: ' . ($data['id'] ?? $refId),
                        'ref_id' => $refId,
                    ]);
                }
            }

            $errorMsg = $response->json('message') ?? 'Withdrawal failed';
            return response()->json(['success' => false, 'message' => $errorMsg]);
        } catch (\Exception $e) {
            \Log::error('Atlantic withdraw error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Connection error. Please try again.'], 500);
        }
    }
}
