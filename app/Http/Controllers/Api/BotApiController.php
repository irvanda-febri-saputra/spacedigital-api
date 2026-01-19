<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\Transaction;
use App\Models\UserGateway;
use App\Services\PaymentService;
use App\Services\PaymentGateways\AtlanticGateway;
use App\Events\PaymentStatusUpdated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BotApiController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Get bot settings by API key
     *
     * Usage: GET /api/bot/settings
     * Header: X-API-Key: {bot_api_key}
     */
    public function getSettings(Request $request)
    {
        $bot = $this->authenticateBot($request);

        if (!$bot) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid API key'
            ], 401);
        }

        if ($bot->status !== 'active') {
            return response()->json([
                'success' => false,
                'error' => 'Bot is not active'
            ], 403);
        }

        // Get active gateway code and credentials for the bot
        $activeGatewayCode = null;
        $gatewayCredentials = null;

        if ($bot->activeGateway) {
            $activeGatewayCode = $bot->activeGateway->gateway->code ?? null;
            // Get credentials (includes qr_string for orderkuota/qiospay)
            $creds = $bot->activeGateway->credentials ?? [];
            if (is_string($creds)) {
                $creds = json_decode($creds, true) ?? [];
            }
            // Try multiple possible field names for QRIS string
            $qrString = $creds['qr_string']
                ?? $creds['qris_string']
                ?? $creds['qris']
                ?? $creds['qrstring']
                ?? $creds['qr']
                ?? $bot->pg_qr_string
                ?? '';

            $gatewayCredentials = [
                'qr_string' => $qrString,
                'merchant_code' => $creds['merchant_code'] ?? $creds['username'] ?? '',
                // Don't expose sensitive keys to bot
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'bot_id' => $bot->id,
                'name' => $bot->name,
                'bot_username' => $bot->bot_username,
                'payment_gateway' => $bot->payment_gateway,
                'pg_merchant_code' => $bot->pg_merchant_code,
                'pg_api_key' => $bot->pg_api_key,
                'pg_qr_string' => $bot->pg_qr_string,
                'status' => $bot->status,
                'settings' => $bot->settings,
                'has_centralized_gateway' => $bot->active_gateway_id !== null,
                'active_gateway_code' => $activeGatewayCode, // e.g. 'qiospay', 'atlantic', 'pakasir', 'orderkuota'
                'gateway_credentials' => $gatewayCredentials, // includes qr_string for Worker API mode
            ]
        ]);
    }

    /**
     * Create a new transaction
     *
     * Usage: POST /api/bot/transactions
     * Header: X-API-Key: {bot_api_key}
     */
    public function createTransaction(Request $request)
    {
        $bot = $this->authenticateBot($request);

        if (!$bot) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid API key'
            ], 401);
        }

        $validated = $request->validate([
            'order_id' => 'nullable|string',
            'telegram_user_id' => 'nullable|string',
            'telegram_username' => 'nullable|string',
            'product_name' => 'required|string',
            'variant' => 'nullable|string',
            'quantity' => 'integer|min:1',
            'price' => 'required|numeric|min:0',
            'total_price' => 'required|numeric|min:0',
            'payment_ref' => 'nullable|string',
            'expired_at' => 'nullable|date',
        ]);

        // Get active gateway code from centralized gateway system
        $activeGatewayCode = $bot->payment_gateway; // fallback to old field
        if ($bot->activeGateway && $bot->activeGateway->gateway) {
            $activeGatewayCode = $bot->activeGateway->gateway->code;
        }

        $transaction = Transaction::updateOrCreate(
            ['order_id' => $validated['order_id'] ?? ('ORD-' . strtoupper(Str::random(10)))],
            [
                'bot_id' => $bot->id,
                'telegram_user_id' => $validated['telegram_user_id'] ?? null,
                'telegram_username' => $validated['telegram_username'] ?? null,
                'product_name' => $validated['product_name'],
                'variant' => $validated['variant'] ?? null,
                'quantity' => $validated['quantity'] ?? 1,
                'price' => $validated['price'],
                'total_price' => $validated['total_price'],
                'payment_gateway' => $activeGatewayCode,
                'payment_ref' => $validated['payment_ref'] ?? null,
                'status' => 'pending',
                'expired_at' => $validated['expired_at'] ?? now()->addMinutes(30),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'total_price' => $transaction->total_price,
                'status' => $transaction->status,
                'expired_at' => $transaction->expired_at,
            ]
        ], 200);
    }

    /**
     * Create a payment (QRIS) via centralized gateway
     *
     * Usage: POST /api/bot/payments/create
     * Header: X-API-Key: {bot_api_key}
     * Body: { amount, order_id, customer_name? }
     */
    public function createPayment(Request $request)
    {
        $bot = $this->authenticateBot($request);

        if (!$bot) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid API key'
            ], 401);
        }

        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
            'order_id' => 'required|string',
            'customer_name' => 'nullable|string',
        ]);

        $result = $this->paymentService->createPayment($bot, [
            'amount' => $validated['amount'],
            'order_id' => $validated['order_id'],
            'customer_name' => $validated['customer_name'] ?? 'Customer',
        ]);

        if ($result['success']) {
            // Save transaction to database for webhook matching
            try {
                \App\Models\Transaction::updateOrCreate(
                    ['order_id' => $validated['order_id']],
                    [
                        'bot_id' => $bot->id,
                        'product_name' => 'Pending Payment', // Placeholder, will be updated by syncPaymentCreated
                        'telegram_username' => $validated['customer_name'] ?? null,
                        'total_price' => $validated['amount'],
                        'price' => $validated['amount'],
                        'status' => 'pending',
                        'payment_ref' => $result['payment_id'] ?? $validated['order_id'],
                        'payment_gateway' => $result['gateway_code'] ?? 'unknown',
                        'expired_at' => now()->addMinutes(15),
                    ]
                );
            } catch (\Exception $e) {
                \Log::error('Transaction save error: ' . $e->getMessage());
                // Continue even if save fails - payment was still created
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_id' => $result['payment_id'],
                    'qr_string' => $result['qr_string'],
                    'qr_image' => $result['qr_image'] ?? null,
                    'amount' => $result['amount'],
                    'expires_at' => $result['expires_at'],
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to create payment'
        ], 500);
    }

    /**
     * Check payment status via centralized gateway
     *
     * Usage: GET /api/bot/payments/{paymentId}/status
     * Header: X-API-Key: {bot_api_key}
     */
    public function checkPaymentStatus(Request $request, $paymentId)
    {
        $bot = $this->authenticateBot($request);

        if (!$bot) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid API key'
            ], 401);
        }

        $result = $this->paymentService->checkStatus($bot, $paymentId);

        return response()->json($result);
    }

    /**
     * Handle payment webhook from gateway
     *
     * Usage: POST /api/payments/webhook/{gateway}
     */
    public function handleWebhook(Request $request, $gateway)
    {
        // Log everything possible for debugging
        $rawBody = file_get_contents('php://input');
        $contentType = $request->header('Content-Type');

        Log::info("WEBHOOK RAW DEBUG: Gateway=$gateway", [
            'content_type' => $contentType,
            'raw_body' => substr($rawBody, 0, 1000),  // First 1000 chars
            'request_all' => $request->all(),
            'post_data' => $_POST,
            'get_data' => $_GET,
        ]);

        $payload = $request->all();

        // Fallback 1: Try JSON decode from raw body
        if (empty($payload) && !empty($rawBody)) {
            $payload = json_decode($rawBody, true) ?? [];
        }

        // Fallback 2: Try form-urlencoded parse
        if (empty($payload) && !empty($rawBody)) {
            parse_str($rawBody, $payload);
        }

        // Fallback 3: Use POST data directly
        if (empty($payload) && !empty($_POST)) {
            $payload = $_POST;
        }

        Log::info("WEBHOOK HIT: Gateway=$gateway", ['payload' => $payload]);

        $signature = $request->header('X-Signature') ?? $request->header('X-Callback-Signature');

        $result = $this->paymentService->handleWebhook(
            $gateway,
            $payload,
            $signature
        );

        if ($result['success']) {
            return response()->json(['status' => 'ok']);
        }

        return response()->json(['error' => $result['error']], 400);
    }

    /**
     * Update transaction status (webhook callback)
     *
     * Usage: POST /api/bot/transactions/{order_id}/status
     * Header: X-API-Key: {bot_api_key}
     */
    public function updateTransactionStatus(Request $request, $orderId)
    {
        $bot = $this->authenticateBot($request);

        if (!$bot) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid API key'
            ], 401);
        }

        $transaction = Transaction::where('order_id', $orderId)
            ->where('bot_id', $bot->id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'error' => 'Transaction not found'
            ], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,success,expired,failed,cancelled',
            'payment_ref' => 'nullable|string',
        ]);

        $transaction->update([
            'status' => $validated['status'],
            'payment_ref' => $validated['payment_ref'] ?? $transaction->payment_ref,
            'paid_at' => $validated['status'] === 'success' ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $transaction->order_id,
                'status' => $transaction->status,
                'paid_at' => $transaction->paid_at,
            ]
        ]);
    }

    /**
     * Get transaction by order ID
     *
     * Usage: GET /api/bot/transactions/{order_id}
     * Header: X-API-Key: {bot_api_key}
     */
    public function getTransaction(Request $request, $orderId)
    {
        $bot = $this->authenticateBot($request);

        if (!$bot) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid API key'
            ], 401);
        }

        $transaction = Transaction::where('order_id', $orderId)
            ->where('bot_id', $bot->id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'error' => 'Transaction not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'telegram_user_id' => $transaction->telegram_user_id,
                'telegram_username' => $transaction->telegram_username,
                'product_name' => $transaction->product_name,
                'variant' => $transaction->variant,
                'quantity' => $transaction->quantity,
                'price' => $transaction->price,
                'total_price' => $transaction->total_price,
                'status' => $transaction->status,
                'paid_at' => $transaction->paid_at,
                'created_at' => $transaction->created_at,
            ]
        ]);
    }

    /**
     * EXPERIMENTAL: Manual check status (triggered by user button)
     * Replaces the need for constant polling daemon.
     *
     * Usage: POST /api/bot/transactions/{order_id}/check
     */
    public function checkTransactionStatus(Request $request, $orderId)
    {
        $bot = $this->authenticateBot($request);
        if (!$bot) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $transaction = Transaction::where('order_id', $orderId)->where('bot_id', $bot->id)->first();
        if (!$transaction) {
            return response()->json(['success' => false, 'error' => 'Transaction not found'], 404);
        }

        // If already success/expired, just return info
        if (in_array($transaction->status, ['success', 'expired'])) {
            return response()->json(['success' => true, 'status' => $transaction->status, 'already_final' => true]);
        }

        $gatewayCode = $transaction->payment_gateway;

        // --- ATLANTIC LOGIC ---
        if (str_contains($gatewayCode, 'atlantic')) {
            // user_gateways uses user_id, not bot_id - get via bot owner
            $userGateway = UserGateway::where('user_id', $bot->user_id)
                ->where('is_active', true)
                ->whereHas('gateway', fn($q) => $q->where('code', 'like', 'atlantic%'))
                ->first();

            if ($userGateway && $transaction->payment_ref) {
                try {
                    $creds = $userGateway->credentials;
                    $gateway = new AtlanticGateway([
                        'api_key' => $creds['api_key'] ?? '',
                        'metode' => 'qris', // Default, logic same for fast
                    ]);

                    $status = $gateway->checkStatus($transaction->payment_ref);

                    if ($status['success'] && in_array($status['status'], ['processing', 'success'])) {
                        // Trigger instant cair
                        $gateway->triggerInstant($transaction->payment_ref);

                        // Mark success
                        $transaction->update(['status' => 'success', 'paid_at' => now()]);

                        // Broadcast WebSocket
                        event(new PaymentStatusUpdated($bot->id, $orderId, 'success', (int)$transaction->total_price, now()->toIso8601String()));

                        return response()->json(['success' => true, 'status' => 'success', 'message' => 'Payment confirmed via Atlantic']);
                    }
                } catch (\Exception $e) {
                    Log::error("Atlantic check error: " . $e->getMessage());
                }
            }
        }

        // --- QIOSPAY LOGIC ---
        if (str_contains($gatewayCode, 'qiospay')) {
            $userGateway = $bot->activeGateway; // Should be QiosPay if active

            if ($userGateway) {
                try {
                    $creds = $userGateway->credentials;
                    $apiKey = $creds['api_key'] ?? '';
                    $merchant = $creds['merchant_code'] ?? '';

                    if ($apiKey && $merchant) {
                        $url = "https://qiospay.id/api/mutasi/qris/{$merchant}/{$apiKey}";
                        $response = Http::timeout(10)->get($url);

                        if ($response->successful()) {
                            $mutasiData = $response->json()['data'] ?? [];
                            $amount = (int) $transaction->total_price;
                            $trxCreated = $transaction->created_at;

                            foreach ($mutasiData as $mutasi) {
                                if ($mutasi['type'] !== 'CR') continue;
                                if ((int)$mutasi['amount'] !== $amount) continue;

                                // Timestamp validation
                                $mutasiTime = null;
                                if (isset($mutasi['created_at'])) $mutasiTime = \Carbon\Carbon::parse($mutasi['created_at']);
                                elseif (isset($mutasi['date'])) $mutasiTime = \Carbon\Carbon::parse($mutasi['date']);
                                elseif (isset($mutasi['time'])) $mutasiTime = \Carbon\Carbon::parse($mutasi['time']);

                                if ($mutasiTime && $mutasiTime->lt($trxCreated)) continue;

                                // QiosPay ID Validation (prevent double match)
                                $qiosPayTrxId = $mutasi['id'] ?? $mutasi['refnum'] ?? null;
                                if ($qiosPayTrxId) {
                                    // Check if this QiosPay ID already claimed by another transaction
                                    $existingTrx = Transaction::where('qiospay_trx_id', $qiosPayTrxId)
                                        ->first();
                                    if ($existingTrx) {
                                        Log::info("QiosPay ID already used: {$qiosPayTrxId} by order {$existingTrx->order_id}");
                                        continue;
                                    }
                                }

                                // Valid match!
                                $transaction->update([
                                    'status' => 'success',
                                    'paid_at' => $mutasiTime ?? now(),
                                    'qiospay_trx_id' => $qiosPayTrxId  // Save QiosPay ID to prevent re-use
                                ]);

                                Log::info("QiosPay payment matched!", [
                                    'order_id' => $orderId,
                                    'amount' => $amount,
                                    'qiospay_trx_id' => $qiosPayTrxId,
                                ]);

                                // Broadcast via WebSocket Hub
                                try {
                                    $wsHub = new \App\Services\WebSocketHubService();
                                    $wsHub->broadcastPaymentStatus(
                                        $bot->id,
                                        $orderId,
                                        'success',
                                        (int)$transaction->total_price,
                                        now()->toIso8601String(),
                                        'qiospay'
                                    );
                                } catch (\Exception $wsErr) {
                                    Log::warning("WS Hub broadcast failed: " . $wsErr->getMessage());
                                }

                                return response()->json(['success' => true, 'status' => 'success', 'message' => 'Payment confirmed via QiosPay']);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("QiosPay check error: " . $e->getMessage());
                }
            }
        }

        // --- ORDERKUOTA LOGIC ---
        if (str_contains(strtolower($gatewayCode), 'orderkuota') || str_contains(strtolower($gatewayCode), 'order_kuota')) {
            Log::info("OrderKuota check triggered for bot", ['order_id' => $orderId, 'bot_id' => $bot->id]);

            // Get user gateway for Order Kuota
            $userGateway = UserGateway::where('user_id', $bot->user_id)
                ->where('is_active', true)
                ->whereHas('gateway', fn($q) => $q->where('code', 'like', '%orderkuota%'))
                ->first();

            if ($userGateway) {
                try {
                    $creds = $userGateway->credentials;
                    $token = $creds['token'] ?? $creds['api_token'] ?? null;
                    $username = $creds['username'] ?? $creds['email'] ?? null;

                    Log::info("OrderKuota credentials check (bot)", [
                        'hasToken' => $token ? 'Yes (len: '.strlen($token).')' : 'No',
                        'hasUsername' => $username ? 'Yes' : 'No',
                    ]);

                    if ($token && $username) {
                        // Use OrderKuotaClient service with Cloudflare Worker proxy
                        $client = new \App\Services\OrderKuotaClient($username, $token);
                        $result = $client->getMutations();

                        Log::info("OrderKuota getMutations result (bot)", [
                            'success' => $result['success'] ?? false,
                            'error' => $result['error'] ?? null,
                            'mutationsCount' => isset($result['mutations']) ? count($result['mutations']) : 0,
                        ]);

                        if ($result['success'] && !empty($result['mutations'])) {
                            $mutations = $result['mutations'];
                            $amount = (int) $transaction->total_price;
                            $trxCreated = $transaction->created_at;

                            Log::info("Looking for amount (bot): {$amount}, mutations count: " . count($mutations));

                            foreach ($mutations as $mutasi) {
                                // Order Kuota API uses 'kredit' for incoming amount, 'status' for type (IN = masuk)
                                $mutasiAmount = (int) str_replace(['.', ','], '', $mutasi['kredit'] ?? $mutasi['nominal'] ?? $mutasi['amount'] ?? '0');
                                $mutasiType = $mutasi['status'] ?? $mutasi['jenis'] ?? $mutasi['type'] ?? '';

                                // Only check incoming payments (IN = masuk)
                                if (!empty($mutasiType) && !in_array(strtoupper($mutasiType), ['IN', 'MASUK', 'CREDIT', 'CR'])) {
                                    continue;
                                }

                                Log::debug("Checking mutation (bot): kredit={$mutasiAmount}, status={$mutasiType}");

                                if ($mutasiAmount !== $amount) continue;

                                // Check timestamp - Order Kuota format: "27/12/2025 20:37" in WIB (Asia/Jakarta)
                                // NOTE: Order Kuota only provides minute precision (no seconds)
                                // So we add 1 minute tolerance to avoid false negatives
                                $mutasiTime = null;
                                if (isset($mutasi['tanggal'])) {
                                    try {
                                        // Parse as WIB timezone then convert to UTC for comparison
                                        $mutasiTime = \Carbon\Carbon::createFromFormat('d/m/Y H:i', $mutasi['tanggal'], 'Asia/Jakarta');
                                        $mutasiTime->setTimezone('UTC');
                                    } catch (\Exception $e) {
                                        $mutasiTime = \Carbon\Carbon::parse($mutasi['tanggal'], 'Asia/Jakarta');
                                        $mutasiTime->setTimezone('UTC');
                                    }
                                } elseif (isset($mutasi['created_at'])) {
                                    $mutasiTime = \Carbon\Carbon::parse($mutasi['created_at']);
                                } elseif (isset($mutasi['waktu'])) {
                                    $mutasiTime = \Carbon\Carbon::parse($mutasi['waktu']);
                                }

                                // Add 1 minute tolerance because Order Kuota doesn't have seconds precision
                                // If payment is within 1 minute BEFORE transaction created, still consider valid
                                $trxCreatedWithTolerance = $trxCreated->copy()->subMinutes(1);

                                Log::debug("Timestamp comparison (bot)", [
                                    'mutation_time_utc' => $mutasiTime?->toDateTimeString(),
                                    'trx_created_utc' => $trxCreated->toDateTimeString(),
                                    'trx_with_tolerance' => $trxCreatedWithTolerance->toDateTimeString(),
                                    'mutation_is_after' => $mutasiTime ? ($mutasiTime->gte($trxCreatedWithTolerance) ? 'yes' : 'no') : 'N/A',
                                ]);

                                if ($mutasiTime && $mutasiTime->lt($trxCreatedWithTolerance)) continue;

                                // OrderKuota ID to prevent double match
                                $orderKuotaTrxId = $mutasi['id'] ?? null;
                                if ($orderKuotaTrxId) {
                                    $existingTrx = Transaction::where('orderkuota_trx_id', $orderKuotaTrxId)->first();
                                    if ($existingTrx) {
                                        Log::info("OrderKuota ID already used: {$orderKuotaTrxId} by order {$existingTrx->order_id}");
                                        continue;
                                    }
                                }

                                // Match found!
                                $transaction->update([
                                    'status' => 'success',
                                    'paid_at' => $mutasiTime ?? now(),
                                    'orderkuota_trx_id' => $orderKuotaTrxId,
                                ]);

                                Log::info("OrderKuota payment matched (bot)!", [
                                    'order_id' => $orderId,
                                    'amount' => $amount,
                                    'orderkuota_trx_id' => $orderKuotaTrxId,
                                ]);

                                // Broadcast via WebSocket Hub
                                try {
                                    $wsHub = new \App\Services\WebSocketHubService();
                                    $wsHub->broadcastPaymentStatus(
                                        $bot->id,
                                        $orderId,
                                        'success',
                                        (int)$transaction->total_price,
                                        now()->toIso8601String(),
                                        'orderkuota'
                                    );
                                } catch (\Exception $wsErr) {
                                    Log::warning("WS Hub broadcast failed: " . $wsErr->getMessage());
                                }

                                return response()->json(['success' => true, 'status' => 'success', 'message' => 'Payment confirmed via OrderKuota']);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("OrderKuota check error (bot): " . $e->getMessage());
                }
            } else {
                Log::warning("No active OrderKuota gateway found for user: " . $bot->user_id);
            }
        }

        return response()->json(['success' => true, 'status' => 'pending', 'message' => 'Payment not yet received']);
    }

    /**
     * Authenticate bot by API key from header
     * Uses the user authenticated by api.key middleware
     */
    private function authenticateBot(Request $request): ?Bot
    {
        // Get user from middleware (api.key validates User.api_key)
        $user = $request->user();

        if (!$user) {
            // Fallback: try direct lookup via Bot.pg_api_key (legacy)
            $apiKey = $request->header('X-API-Key');
            if ($apiKey) {
                return Bot::where('pg_api_key', $apiKey)->first();
            }
            return null;
        }

        // Get the first active bot for this user
        return Bot::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Sync products from bot to dashboard
     * Bot pushes its products here, dashboard stores them
     *
     * Usage: POST /api/bot/products/sync
     */
    public function syncProducts(Request $request)
    {
        $bot = $this->authenticateBot($request);
        if (!$bot) {
            return response()->json(['success' => false, 'error' => 'Invalid API key'], 401);
        }

        $products = $request->input('products', []);
        $synced = 0;

        foreach ($products as $productData) {
            try {
                $productName = $productData['name'] ?? 'Unknown';
                
                // Find by name (CASE-INSENSITIVE) to prevent duplicates
                $existingProduct = \App\Models\Product::where('bot_id', $bot->id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($productName)])
                    ->first();
                
                if ($existingProduct) {
                    // UPDATE existing product
                    $existingProduct->update([
                        'bot_external_id' => $productData['id'] ?? $existingProduct->bot_external_id,
                        'product_code' => $productData['product_code'] ?? $existingProduct->product_code,
                        'price' => $productData['price'] ?? $existingProduct->price,
                        'description' => $productData['description'] ?? $existingProduct->description,
                        'category' => $productData['category'] ?? $existingProduct->category,
                        'stock_count' => $productData['stock_count'] ?? $existingProduct->stock_count,
                        'variants' => json_encode($productData['variants'] ?? []),
                        'is_active' => $productData['is_active'] ?? true,
                    ]);
                } else {
                    // CREATE new product only if not exists
                    \App\Models\Product::create([
                        'bot_id' => $bot->id,
                        'bot_external_id' => $productData['id'] ?? null,
                        'name' => $productName,
                        'product_code' => $productData['product_code'] ?? null,
                        'price' => $productData['price'] ?? 0,
                        'description' => $productData['description'] ?? null,
                        'category' => $productData['category'] ?? null,
                        'stock_count' => $productData['stock_count'] ?? 0,
                        'variants' => json_encode($productData['variants'] ?? []),
                        'is_active' => $productData['is_active'] ?? true,
                    ]);
                }
                $synced++;
            } catch (\Exception $e) {
                Log::warning("Product sync failed: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'synced' => $synced,
            'message' => "Synced {$synced} products"
        ]);
    }

    /**
     * Sync single product change (create/update/delete)
     *
     * Usage: POST /api/bot/products/sync-single
     */
    public function syncProductSingle(Request $request)
    {
        $bot = $this->authenticateBot($request);
        if (!$bot) {
            return response()->json(['success' => false, 'error' => 'Invalid API key'], 401);
        }

        $productData = $request->input('product');
        $action = $request->input('action', 'update');

        if (!$productData) {
            return response()->json(['success' => false, 'error' => 'Product data required'], 400);
        }

        try {
            if ($action === 'delete') {
                \App\Models\Product::where('bot_id', $bot->id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($productData['name'])])
                    ->delete();
            } else {
                // Find by name (CASE-INSENSITIVE) to prevent duplicates
                $product = \App\Models\Product::where('bot_id', $bot->id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($productData['name'])])
                    ->first();

                $data = [
                    'product_code' => $productData['product_code'] ?? null,
                    'price' => $productData['price'] ?? 0,
                    'description' => $productData['description'] ?? null,
                    'category' => $productData['category'] ?? null,
                    'stock' => $productData['stock_count'] ?? 0,
                    'stock_count' => $productData['stock_count'] ?? 0,
                    'variants' => json_encode($productData['variants'] ?? []),
                    'is_active' => $productData['is_active'] ?? true,
                ];

                if ($product) {
                    // Update existing product
                    $product->update($data);
                } else {
                    // Create new product
                    $product = \App\Models\Product::create(array_merge($data, [
                        'bot_id' => $bot->id,
                        'name' => $productData['name'],
                    ]));
                }

                \Log::info("Product synced from bot: {$product->name}, stock: {$product->stock}");
            }

            return response()->json([
                'success' => true,
                'action' => $action,
                'message' => "Product {$action} successful"
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update product stock count ONLY (no product creation)
     * This prevents duplicate products when syncing from bot
     *
     * Usage: POST /api/bot/products/update-stock
     */
    public function updateProductStock(Request $request)
    {
        $bot = $this->authenticateBot($request);
        if (!$bot) {
            return response()->json(['success' => false, 'error' => 'Invalid API key'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'stock_count' => 'required|integer|min:0',
            'variants' => 'nullable|array',
            'variants.*.variant_code' => 'nullable|string',
            'variants.*.name' => 'nullable|string',
            'variants.*.stock_count' => 'required|integer|min:0',
        ]);

        try {
            // Find product by name (CASE-INSENSITIVE) - DO NOT create if not exists
            $product = \App\Models\Product::where('bot_id', $bot->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($validated['name'])])
                ->first();

            if (!$product) {
                \Log::warning("Stock update failed: Product '{$validated['name']}' not found for bot {$bot->id}");
                return response()->json([
                    'success' => false,
                    'error' => "Product '{$validated['name']}' not found in dashboard. Create it from dashboard first."
                ], 404);
            }

            // Update product stock
            $product->stock = $validated['stock_count'];
            $product->stock_count = $validated['stock_count'];

            // DEBUG: Log what we received
            \Log::info("updateProductStock DEBUG for {$product->name}:", [
                'variants_in_request' => $validated['variants'] ?? 'NULL',
                'variants_in_db_type' => gettype($product->variants),
                'variants_in_db_is_array' => is_array($product->variants),
                'variants_in_db_count' => is_array($product->variants) ? count($product->variants) : 'N/A',
            ]);

            // Handle variants if provided
            if (!empty($validated['variants']) && is_array($product->variants)) {
                $currentVariants = $product->variants;
                $updated = false;

                \Log::info("Processing " . count($validated['variants']) . " variants for product {$product->name}");

                foreach ($validated['variants'] as $newVariant) {
                    $found = false;
                    foreach ($currentVariants as &$key) {
                        // Normalize names for comparison (trim + lowercase)
                        $currName = trim(strtolower($key['name'] ?? ''));
                        $newName = trim(strtolower($newVariant['name'] ?? ''));

                        // Strict code match (case-insensitive) OR loose name match
                        $matchByCode = !empty($newVariant['variant_code']) &&
                                      strtolower($key['variant_code'] ?? '') === strtolower($newVariant['variant_code']);

                        $matchByName = $currName === $newName;

                        if ($matchByCode || $matchByName) {
                            $oldStock = $key['stock_count'] ?? 0;
                            $newStock = $newVariant['stock_count'];

                            $key['stock_count'] = $newStock;
                            // Also update 'stock' if it exists in variant structure
                            if (isset($key['stock'])) {
                                $key['stock'] = $newStock;
                            }
                            $updated = true;
                            $found = true;

                            \Log::info("Variant matched: updated '{$key['name']}' stock {$oldStock} -> {$newStock}");
                            break;
                        }
                    }
                    if (!$found) {
                        \Log::warning("Variant '{$newVariant['name']}' (code: " . ($newVariant['variant_code']??'-') . ") sent by bot but NOT found in dashboard product '{$product->name}'");
                    }
                }

                if ($updated) {
                    $product->variants = $currentVariants;
                }
            }

            $product->save();

            \Log::info("Stock updated: {$product->name} = {$validated['stock_count']} (bot: {$bot->id})");

            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully',
                'product_id' => $product->id,
                'name' => $product->name,
                'stock' => $product->stock
            ]);

        } catch (\Exception $e) {
            \Log::error("Stock update error: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark stocks as sold (called by bot when items are sold)
     *
     * Usage: POST /api/bot/stocks/sold
     * Body: { stock_ids: [1,2,3], trx_id: "...", telegram_id: "..." }
     */
    public function markStocksSold(Request $request)
    {
        try {
            $request->validate([
                'stock_ids' => 'required|array',
                'stock_ids.*' => 'integer',
                'trx_id' => 'nullable|string',
                'telegram_id' => 'nullable|string',
            ]);

            $stockIds = $request->stock_ids;
            $trxId = $request->trx_id;
            $telegramId = $request->telegram_id;

            $updated = \App\Models\StockItem::whereIn('id', $stockIds)
                ->update([
                    'is_sold' => true,
                    'sold_at' => now(),
                    'sold_to_telegram_id' => $telegramId,
                    'sold_order_id' => $trxId,
                ]);

            Log::info("Marked {$updated} stocks as sold. TRX: {$trxId}");

            return response()->json([
                'success' => true,
                'message' => "Marked {$updated} stocks as sold",
                'updated_count' => $updated,
            ]);

        } catch (\Exception $e) {
            Log::error("Mark stocks sold error: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}

