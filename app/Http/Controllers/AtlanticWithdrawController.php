<?php

namespace App\Http\Controllers;

use App\Models\UserGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AtlanticWithdrawController extends Controller
{
    private $baseUrl = 'https://atlantich2h.com';
    private $proxyUrl;
    private $timeout = 15;

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
     * Searches for any Atlantic variant (atlantic, atlantic_fast, etc.)
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
            \Log::warning('Atlantic Withdraw: No Atlantic gateway found for user ' . $request->user()->id);
            return null;
        }

        $credentials = $userGateway->credentials;
        $apiKey = $credentials['api_key'] ?? null;
        
        if (!$apiKey) {
            \Log::warning('Atlantic Withdraw: API key not found in credentials for gateway ' . $userGateway->id);
        }
        
        return $apiKey;
    }

    /**
     * Show the withdraw page (renders immediately, data loaded via AJAX)
     */
    public function index(Request $request)
    {
        $apiKey = $this->getApiKey($request);

        if (!$apiKey) {
            return redirect()->route('payment-gateways.index')
                ->with('error', 'Please configure Atlantic H2H gateway first.');
        }

        // Render page immediately - data will be fetched via AJAX
        return Inertia::render('Atlantic/Withdraw', [
            'balance' => null, // Will be fetched via AJAX
            'pendingBalance' => null,
            'banks' => [], // Will be fetched via AJAX
            'withdrawFee' => 2000,
            'hasApiKey' => true,
        ]);
    }

    /**
     * AJAX: Get balance (called after page load)
     */
    public function getBalance(Request $request)
    {
        $apiKey = $this->getApiKey($request);

        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'API key not configured'], 400);
        }

        $profile = $this->fetchProfile($apiKey);

        return response()->json([
            'success' => true,
            'balance' => $profile['balance'],
            'pendingBalance' => $profile['pending_balance'],
        ]);
    }

    /**
     * AJAX: Get bank list (called after page load)
     */
    public function getBanks(Request $request)
    {
        $apiKey = $this->getApiKey($request);

        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'API key not configured'], 400);
        }

        $banks = $this->fetchBankListCached($apiKey);

        return response()->json([
            'success' => true,
            'banks' => $banks,
        ]);
    }

    /**
     * Fetch profile/balance from Atlantic API (with timeout)
     */
    private function fetchProfile($apiKey)
    {
        try {
            \Log::info('Atlantic fetchProfile: Calling API with key ' . substr($apiKey, 0, 10) . '... (proxy: ' . (!empty($this->proxyUrl) ? 'yes' : 'no') . ')');
            
            $response = $this->makeApiRequest('/get_profile', [
                'api_key' => $apiKey,
            ]);

            \Log::info('Atlantic fetchProfile: Full response: ' . $response->body());

            if ($response->successful()) {
                $data = $response->json();
                // Atlantic API returns 'true' as string
                if ($data['status'] === 'true' || $data['status'] === true) {
                    $profileData = $data['data'] ?? [];
                    
                    // Try multiple possible field names for pending balance
                    $pendingBalance = 
                        $profileData['settlement_balance'] ??  // <-- This is the correct field!
                        $profileData['pending_balance'] ?? 
                        $profileData['pending_saldo'] ?? 
                        $profileData['saldo_tertahan'] ?? 
                        $profileData['settlement'] ??
                        $profileData['pending'] ??
                        null;
                    
                    \Log::info('Atlantic fetchProfile: balance=' . ($profileData['balance'] ?? 'null') . ', pending=' . ($pendingBalance ?? 'null'));
                    
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
            \Log::warning('Atlantic get_profile timeout/error: ' . $e->getMessage());
        }

        return ['balance' => 0, 'pending_balance' => null, 'phone' => '', 'email' => '', 'name' => ''];
    }

    /**
     * Fetch bank list with caching (1 hour cache)
     */
    private function fetchBankListCached($apiKey)
    {
        $cacheKey = 'atlantic_bank_list_' . md5($apiKey);
        
        // Clear cache first to get fresh data (for debugging)
        // Cache::forget($cacheKey);  // Uncomment to force refresh
        
        return Cache::remember($cacheKey, 3600, function () use ($apiKey) {
            return $this->fetchBankList($apiKey);
        });
    }

    /**
     * Fetch bank list from Atlantic API (with timeout)
     */
    private function fetchBankList($apiKey)
    {
        try {
            \Log::info('Atlantic fetchBankList: Calling API...');
            
            $response = $this->makeApiRequest('/transfer/bank_list', [
                'api_key' => $apiKey,
            ]);

            \Log::info('Atlantic fetchBankList: Response: ' . $response->body());

            if ($response->successful()) {
                $data = $response->json();
                // Handle status as string 'true' or boolean true
                if ($data['status'] === 'true' || $data['status'] === true) {
                    $banks = collect($data['data'])->map(function ($bank) {
                        return [
                            'code' => $bank['bank_code'] ?? $bank['code'] ?? '',
                            'name' => $bank['bank_name'] ?? $bank['name'] ?? '',
                            'type' => $bank['type'] ?? 'bank',
                        ];
                    })->toArray();
                    
                    \Log::info('Atlantic fetchBankList: Found ' . count($banks) . ' banks');
                    return $banks;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Atlantic bank_list timeout/error: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Verify account number (AJAX) - with timeout
     */
    public function verifyAccount(Request $request)
    {
        $request->validate([
            'bank_code' => 'required|string',
            'account_number' => 'required|string',
        ]);

        $apiKey = $this->getApiKey($request);

        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'API key not configured'], 400);
        }

        try {
            \Log::info('Atlantic verifyAccount: Calling API for bank=' . $request->bank_code . ', account=' . $request->account_number);
            
            $response = $this->makeApiRequest('/transfer/cek_rekening', [
                'api_key' => $apiKey,
                'bank_code' => $request->bank_code,
                'account_number' => $request->account_number,
            ]);

            \Log::info('Atlantic verifyAccount: Response: ' . $response->body());

            if ($response->successful()) {
                $result = $response->json();
                // Handle status as string 'true' or boolean true
                if ($result['status'] === true || $result['status'] === 'true') {
                    $data = $result['data'] ?? [];
                    return response()->json([
                        'success' => true,
                        'account_name' => $data['nama_pemilik'] ?? $data['account_name'] ?? $data['name'] ?? '',
                        'account_number' => $data['nomor_akun'] ?? $request->account_number, // Normalized account number from API
                        'status' => $data['status'] ?? 'unknown',
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => $response->json('message') ?? 'Account verification failed',
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['success' => false, 'message' => 'Connection timeout. Please try again.'], 504);
        } catch (\Exception $e) {
            \Log::error('Atlantic cek_rekening error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Connection error'], 500);
        }
    }

    /**
     * Submit withdrawal request - with timeout
     */
    public function submit(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:10000',
            'bank_code' => 'required|string',
            'account_name' => 'required|string',
            'account_number' => 'required|string',
        ]);

        $apiKey = $this->getApiKey($request);

        if (!$apiKey) {
            return back()->with('error', 'API key not configured');
        }

        // Generate unique reference ID
        $refId = 'WD-' . strtoupper(Str::random(12));

        try {
            // Fetch profile to get phone number
            $profile = $this->fetchProfile($apiKey);
            $phone = $profile['phone'] ?? '';
            // Convert 62xxx format to 0xxx format (e.g. 6281336710869 -> 081336710869)
            if (str_starts_with($phone, '62')) {
                $phone = '0' . substr($phone, 2);
            }
            
            \Log::info('Atlantic submit: Starting withdrawal', [
                'ref_id' => $refId,
                'bank_code' => $request->bank_code,
                'account_number' => $request->account_number,
                'amount' => $request->amount,
            ]);
            
            $requestData = [
                'api_key' => $apiKey,
                'ref_id' => $refId,
                'kode_bank' => strtoupper($request->bank_code), // Must be UPPERCASE
                'nomor_akun' => $request->account_number,
                'nama_pemilik' => $request->account_name,
                'nominal' => (string) $request->amount, // Must be string according to docs
                'email' => $request->user()->email,
                'phone' => $phone,
                'note' => 'Withdrawal from SpaceDigital Dashboard',
            ];
            
            \Log::info('Atlantic submit: Full request data', array_merge($requestData, ['api_key' => substr($apiKey, 0, 10) . '...']));
            
            $response = $this->makeApiRequest('/transfer/create', $requestData);

            \Log::info('Atlantic submit: Response: ' . $response->body());

            if ($response->successful()) {
                $result = $response->json();
                if ($result['status'] === true || $result['status'] === 'true') {
                    $data = $result['data'] ?? [];
                    
                    // Clear bank list cache on successful withdrawal
                    Cache::forget('atlantic_bank_list_' . md5($apiKey));
                    
                    return back()->with('success', "Withdrawal submitted! ID: " . ($data['id'] ?? $refId) . ". Total: Rp " . number_format($data['total'] ?? $request->amount, 0, ',', '.'));
                }
            }

            $errorMsg = $response->json('message') ?? 'Withdrawal failed';
            \Log::warning('Atlantic submit: Failed - ' . $errorMsg);
            return back()->with('error', $errorMsg);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('Atlantic submit: Connection timeout');
            return back()->with('error', 'Connection timeout. Please try again.');
        } catch (\Exception $e) {
            \Log::error('Atlantic transfer/create error: ' . $e->getMessage());
            return back()->with('error', 'Connection error. Please try again.');
        }
    }
}
