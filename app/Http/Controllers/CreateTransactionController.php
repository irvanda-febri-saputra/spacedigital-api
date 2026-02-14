<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CreateTransactionController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Show Create Transaction page
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get user's configured gateways
        $userGateways = \App\Models\UserGateway::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('gateway')
            ->get()
            ->map(function ($ug) {
                return [
                    'id' => $ug->id,
                    'name' => strtoupper($ug->label ?? $ug->gateway->name),
                    'gateway_code' => $ug->gateway->code,
                    'gateway_name' => $ug->gateway->name,
                ];
            });

        return Inertia::render('CreateTransaction/Index', [
            'gateways' => $userGateways,
        ]);
    }

    /**
     * Create a new transaction via Dashboard
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'gateway_id' => 'required|exists:user_gateways,id',
            'amount' => 'required|integer|min:1',
            'product_name' => 'required|string|max:255',
            'customer_name' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $userGateway = \App\Models\UserGateway::where('id', $validated['gateway_id'])
            ->where('user_id', $user->id)
            ->with('gateway')
            ->first();

        if (!$userGateway) {
            return back()->withErrors(['gateway_id' => 'Gateway not found']);
        }

        // Get first active bot for this user (to save transaction)
        $bot = $user->bots()->where('status', 'active')->first();
        if (!$bot) {
            return back()->withErrors(['gateway_id' => 'No active bot found']);
        }

        // Generate order ID
        $orderId = 'TRX-' . strtoupper(uniqid());

        try {
            // Create payment directly using gateway
            $gateway = \App\Services\PaymentGateways\PaymentGatewayFactory::fromUserGateway($userGateway);
            $result = $gateway->createPayment([
                'amount' => $validated['amount'],
                'order_id' => $orderId,
                'customer_name' => $validated['customer_name'],
            ]);

            if ($result['success']) {
                // Use the amount returned by gateway (may include unique suffix for Order Kuota)
                $finalAmount = $result['amount'] ?? $validated['amount'];

                // Save transaction with the final amount for matching
                \App\Models\Transaction::create([
                    'bot_id' => $bot->id,
                    'order_id' => $orderId,
                    'product_name' => $validated['product_name'],
                    'telegram_username' => $validated['customer_name'],
                    'quantity' => 1,
                    'price' => $validated['amount'], // Keep original price for display
                    'total_price' => $finalAmount, // Use gateway amount for matching
                    'payment_gateway' => $userGateway->gateway->code,
                    'status' => 'pending',
                    'payment_ref' => $result['payment_id'] ?? $orderId,
                    'expired_at' => now()->addMinutes(15),
                ]);

                return back()->with('transaction', [
                    'success' => true,
                    'order_id' => $orderId,
                    'payment_id' => $result['payment_id'],
                    'qr_string' => $result['qr_string'],
                    'qr_image' => $result['qr_image'] ?? null,
                    'amount' => $validated['amount'],
                    'expires_at' => now()->addMinutes(15)->toISOString(),
                ]);
            }

            return back()->withErrors(['payment' => $result['error'] ?? 'Failed to create payment']);
        } catch (\Exception $e) {
            return back()->withErrors(['payment' => 'Payment error: ' . $e->getMessage()]);
        }
    }

    /**
     * Check transaction status (for polling)
     */
    public function checkStatus(Request $request, $orderId)
    {
        $user = $request->user();
        $botIds = $user->bots()->pluck('id');

        $transaction = \App\Models\Transaction::where('order_id', $orderId)
            ->whereIn('bot_id', $botIds)
            ->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'error' => 'Transaction not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $transaction->order_id,
                'status' => $transaction->status,
                'amount' => $transaction->total_price,
                'paid_at' => $transaction->paid_at,
            ],
        ]);
    }

    // ============================================================
    // API METHODS (for SPA Dashboard)
    // ============================================================

    /**
     * API: Create test transaction
     */
    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'gateway_id' => 'required|exists:user_gateways,id',
            'amount' => 'required|integer|min:1',
            'product_name' => 'required|string|max:255',
            'customer_name' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $userGateway = \App\Models\UserGateway::where('id', $validated['gateway_id'])
            ->where('user_id', $user->id)
            ->with('gateway')
            ->first();

        if (!$userGateway) {
            return response()->json(['success' => false, 'message' => 'Gateway not found'], 404);
        }

        // Get first active bot for this user
        $bot = $user->bots()->where('status', 'active')->first();
        if (!$bot) {
            return response()->json(['success' => false, 'message' => 'No active bot found'], 400);
        }

        $orderId = 'TRX-' . strtoupper(uniqid());

        try {
            $gateway = \App\Services\PaymentGateways\PaymentGatewayFactory::fromUserGateway($userGateway);
            $result = $gateway->createPayment([
                'amount' => $validated['amount'],
                'order_id' => $orderId,
                'customer_name' => $validated['customer_name'],
            ]);

            if ($result['success']) {
                $finalAmount = $result['amount'] ?? $validated['amount'];

                \App\Models\Transaction::create([
                    'bot_id' => $bot->id,
                    'order_id' => $orderId,
                    'product_name' => $validated['product_name'],
                    'telegram_username' => $validated['customer_name'],
                    'quantity' => 1,
                    'price' => $validated['amount'],
                    'total_price' => $finalAmount,
                    'payment_gateway' => $userGateway->gateway->code,
                    'status' => 'pending',
                    'payment_ref' => $result['payment_id'] ?? $orderId,
                    'expired_at' => now()->addMinutes(15),
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_id' => $orderId,
                        'payment_id' => $result['payment_id'] ?? null,
                        'qr_string' => $result['qr_string'] ?? null,
                        'qr_image' => $result['qr_image'] ?? null,
                        'amount' => $validated['amount'],
                        'final_amount' => $finalAmount,
                        'gateway' => $userGateway->gateway->name,
                        'expires_at' => now()->addMinutes(15)->toIso8601String(),
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to create payment',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Check transaction status
     */
    public function apiCheckStatus(Request $request, $orderId)
    {
        $user = $request->user();
        $botIds = $user->bots()->pluck('id');

        $transaction = \App\Models\Transaction::where('order_id', $orderId)
            ->whereIn('bot_id', $botIds)
            ->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $transaction->order_id,
                'status' => $transaction->status,
                'amount' => $transaction->total_price,
                'paid_at' => $transaction->paid_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * API: Manually check payment status from gateway
     */
    public function apiCheckPayment(Request $request, $orderId)
    {
        $user = $request->user();
        $botIds = $user->bots()->pluck('id');

        $transaction = \App\Models\Transaction::where('order_id', $orderId)
            ->whereIn('bot_id', $botIds)
            ->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        // If already success/expired, just return
        if (in_array($transaction->status, ['success', 'expired', 'failed'])) {
            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $transaction->order_id,
                    'status' => $transaction->status,
                    'amount' => $transaction->total_price,
                    'paid_at' => $transaction->paid_at?->toIso8601String(),
                ],
            ]);
        }

        $gatewayCode = $transaction->payment_gateway;
        $bot = $user->bots()->where('status', 'active')->first();

        if (!$bot) {
            return response()->json(['success' => false, 'message' => 'No active bot'], 400);
        }

        \Illuminate\Support\Facades\Log::info("Checking payment for order: {$orderId}, gateway: {$gatewayCode}, user_id: {$user->id}");

        // --- ORDERKUOTA / PAKASIR LOGIC (Check mutations) ---
        if (str_contains(strtolower($gatewayCode), 'orderkuota') || str_contains(strtolower($gatewayCode), 'pakasir') || str_contains(strtolower($gatewayCode), 'order_kuota')) {
            // Get the EXACT gateway matching the transaction's payment_gateway code
            $paymentGateway = \App\Models\PaymentGateway::where('code', $gatewayCode)->first();

            if (!$paymentGateway) {
                \Illuminate\Support\Facades\Log::warning("PaymentGateway not found for code: {$gatewayCode}");
                // Try partial match
                $paymentGateway = \App\Models\PaymentGateway::where('code', 'like', "%{$gatewayCode}%")->first();
            }

            $userGateway = null;
            if ($paymentGateway) {
                $userGateway = \App\Models\UserGateway::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->where('gateway_id', $paymentGateway->id)
                    ->first();
            }

            \Illuminate\Support\Facades\Log::info("Gateway lookup", [
                'gatewayCode' => $gatewayCode,
                'paymentGatewayId' => $paymentGateway?->id,
                'userGatewayId' => $userGateway?->id,
            ]);

            if ($userGateway) {
                try {
                    $creds = $userGateway->credentials;

                    \Illuminate\Support\Facades\Log::info("Credentials type: " . gettype($creds) . ", keys: " . (is_array($creds) ? implode(',', array_keys($creds)) : 'N/A'));

                    // Token format: "accountId:actualToken"
                    $token = $creds['token'] ?? $creds['api_token'] ?? null;
                    $username = $creds['username'] ?? $creds['email'] ?? null;

                    \Illuminate\Support\Facades\Log::info("Order Kuota credentials", [
                        'hasToken' => $token ? 'Yes (len: '.strlen($token).')' : 'No',
                        'hasUsername' => $username ? 'Yes' : 'No',
                    ]);

                    if ($token && $username) {
                        // Use OrderKuotaClient service with Cloudflare Worker proxy
                        $client = new \App\Services\OrderKuotaClient($username, $token);
                        $result = $client->getMutations();

                        \Illuminate\Support\Facades\Log::info("Order Kuota getMutations result", [
                            'success' => $result['success'] ?? false,
                            'error' => $result['error'] ?? null,
                            'mutationsCount' => isset($result['mutations']) ? count($result['mutations']) : 0,
                        ]);

                        if ($result['success'] && !empty($result['mutations'])) {
                            $mutations = $result['mutations'];
                            $amount = (int) $transaction->total_price;
                            $trxCreated = $transaction->created_at;

                            \Illuminate\Support\Facades\Log::info("Looking for amount: {$amount}, mutations count: " . count($mutations));

                            foreach ($mutations as $mutasi) {
                                // Order Kuota API uses 'kredit' for incoming amount, 'status' for type
                                // Format: {"kredit": "2", "status": "IN", "tanggal": "27/12/2025 20:37"}
                                $mutasiAmount = (int) str_replace(['.', ','], '', $mutasi['kredit'] ?? $mutasi['nominal'] ?? $mutasi['amount'] ?? '0');
                                $mutasiType = $mutasi['status'] ?? $mutasi['jenis'] ?? $mutasi['type'] ?? '';

                                // Only check incoming payments (IN = masuk)
                                if (!empty($mutasiType) && !in_array(strtoupper($mutasiType), ['IN', 'MASUK', 'CREDIT', 'CR'])) {
                                    continue;
                                }

                                \Illuminate\Support\Facades\Log::debug("Checking mutation: kredit={$mutasiAmount}, status={$mutasiType}");

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
                                $trxCreatedWithTolerance = $trxCreated->copy()->subMinutes(1);

                                if ($mutasiTime && $mutasiTime->lt($trxCreatedWithTolerance)) continue;

                                // Check if OrderKuota mutation ID already used
                                $orderKuotaTrxId = $mutasi['id'] ?? null;
                                if ($orderKuotaTrxId) {
                                    $existingTrx = \App\Models\Transaction::where('orderkuota_trx_id', $orderKuotaTrxId)->first();
                                    if ($existingTrx) {
                                        \Illuminate\Support\Facades\Log::info("OrderKuota ID already used: {$orderKuotaTrxId} by order {$existingTrx->order_id}");
                                        continue;
                                    }
                                }

                                // Match found!
                                $transaction->update([
                                    'status' => 'success',
                                    'paid_at' => $mutasiTime ?? now(),
                                    'orderkuota_trx_id' => $orderKuotaTrxId,
                                ]);

                                // Broadcast payment success to bot
                                $this->broadcastPaymentSuccess($transaction);

                                \Illuminate\Support\Facades\Log::info("Payment matched via dashboard check!", [
                                    'order_id' => $orderId,
                                    'amount' => $amount,
                                ]);

                                return response()->json([
                                    'success' => true,
                                    'data' => [
                                        'order_id' => $transaction->order_id,
                                        'status' => 'success',
                                        'amount' => $transaction->total_price,
                                        'paid_at' => now()->toIso8601String(),
                                    ],
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Check payment error: " . $e->getMessage());
                }
            }
        }

        // --- QIOSPAY LOGIC ---
        if (str_contains($gatewayCode, 'qiospay')) {
            $userGateway = \App\Models\UserGateway::where('user_id', $user->id)
                ->where('is_active', true)
                ->whereHas('gateway', fn($q) => $q->where('code', 'like', '%qiospay%'))
                ->first();

            if ($userGateway) {
                try {
                    $creds = $userGateway->credentials;
                    $apiKey = $creds['api_key'] ?? '';
                    $merchant = $creds['merchant_code'] ?? '';

                    if ($apiKey && $merchant) {
                        $url = "https://qiospay.id/api/mutasi/qris/{$merchant}/{$apiKey}";
                        $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url);

                        if ($response->successful()) {
                            $mutasiData = $response->json()['data'] ?? [];
                            $amount = (int) $transaction->total_price;
                            $trxCreated = $transaction->created_at;

                            foreach ($mutasiData as $mutasi) {
                                if (($mutasi['type'] ?? '') !== 'CR') continue;
                                if ((int)($mutasi['amount'] ?? 0) !== $amount) continue;

                                $mutasiTime = null;
                                if (isset($mutasi['created_at'])) $mutasiTime = \Carbon\Carbon::parse($mutasi['created_at']);
                                elseif (isset($mutasi['date'])) $mutasiTime = \Carbon\Carbon::parse($mutasi['date']);

                                if ($mutasiTime && $mutasiTime->lt($trxCreated)) continue;

                                // Match found!
                                $transaction->update([
                                    'status' => 'success',
                                    'paid_at' => $mutasiTime ?? now(),
                                ]);

                                // Broadcast payment success to bot
                                $this->broadcastPaymentSuccess($transaction);

                                return response()->json([
                                    'success' => true,
                                    'data' => [
                                        'order_id' => $transaction->order_id,
                                        'status' => 'success',
                                        'amount' => $transaction->total_price,
                                        'paid_at' => now()->toIso8601String(),
                                    ],
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("QiosPay check error: " . $e->getMessage());
                }
            }
        }

        // No match yet, return pending
        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $transaction->order_id,
                'status' => 'pending',
                'amount' => $transaction->total_price,
                'paid_at' => null,
            ],
        ]);
    }

    /**
     * Broadcast payment success to bot via WebSocket
     */
    private function broadcastPaymentSuccess(\App\Models\Transaction $transaction)
    {
        try {
            $wsUrl = config('app.ws_hub_url', 'http://localhost:8080');
            $wsSecret = config('app.ws_broadcast_secret');
            $bot = $transaction->bot;

            if (!$bot) {
                \Illuminate\Support\Facades\Log::warning("Transaction {$transaction->order_id} has no bot");
                return;
            }

            \Illuminate\Support\Facades\Http::timeout(5)->post("{$wsUrl}/broadcast", [
                'secret' => $wsSecret,
                'channel' => "bot.{$bot->id}",
                'event' => 'payment.status.updated',
                'data' => [
                    'order_id' => $transaction->order_id,
                    'status' => 'success',
                    'amount' => (int) $transaction->total_price,
                    'paid_at' => $transaction->paid_at?->toIso8601String(),
                    'gateway' => $transaction->payment_gateway,
                    'bot_id' => $bot->id,
                ],
            ]);

            \Illuminate\Support\Facades\Log::info("Payment success broadcasted to bot.{$bot->id}", [
                'order_id' => $transaction->order_id,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to broadcast payment success: " . $e->getMessage());
        }
    }
