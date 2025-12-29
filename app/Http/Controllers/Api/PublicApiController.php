<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\UserGateway;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicApiController extends Controller
{
    /**
     * Create a new payment
     * POST /api/public/payments/create
     */
    public function createPayment(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'amount' => 'required|integer|min:1',  // Basic validation, per-gateway check below
            'gateway' => 'required|string',
            'order_id' => 'nullable|string|max:100',
            'customer_name' => 'nullable|string|max:255',
            'product_name' => 'nullable|string|max:255',
        ]);
        
        // Find user's configured gateway
        $userGateway = UserGateway::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('gateway', function ($q) use ($validated) {
                $q->where('code', $validated['gateway']);
            })
            ->with('gateway')
            ->first();
        
        if (!$userGateway) {
            return response()->json([
                'success' => false,
                'error' => 'Gateway not found',
                'message' => "Gateway '{$validated['gateway']}' is not configured or not active for your account"
            ], 400);
        }
        
        // Per-gateway minimum amount validation
        $gatewayCode = $userGateway->gateway->code;
        $minAmounts = [
            'qiospay' => 1,       // QiosPay: min Rp 1
            'atlantic' => 1000,   // Atlantic: min Rp 1.000
            'pakasir' => 1000,    // Pakasir: min Rp 1.000
        ];
        $minAmount = $minAmounts[$gatewayCode] ?? 100;
        
        if ($validated['amount'] < $minAmount) {
            return response()->json([
                'success' => false,
                'error' => 'Amount too low',
                'message' => "Minimum amount for {$gatewayCode} is Rp " . number_format($minAmount)
            ], 400);
        }
        
        // Generate order ID if not provided
        $orderId = $validated['order_id'] ?? 'TRX-' . strtoupper(Str::random(12));
        
        try {
            // Create payment via gateway
            $gateway = PaymentGatewayFactory::fromUserGateway($userGateway);
            $result = $gateway->createPayment([
                'amount' => $validated['amount'],
                'order_id' => $orderId,
                'customer_name' => $validated['customer_name'] ?? 'API User',
            ]);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Payment creation failed',
                    'message' => $result['error'] ?? 'Unknown error from gateway'
                ], 500);
            }
            
            // Get active bot for user (needed for transaction storage)
            $bot = $user->bots()->where('status', 'active')->first();
            
            // Save transaction
            $transaction = Transaction::create([
                'bot_id' => $bot?->id,
                'order_id' => $orderId,
                'product_name' => $validated['product_name'] ?? 'API Transaction',
                'telegram_username' => $validated['customer_name'] ?? null,
                'quantity' => 1,
                'price' => $validated['amount'],
                'total_price' => $validated['amount'],
                'payment_gateway' => $userGateway->gateway->code,
                'status' => 'pending',
                'payment_ref' => $result['payment_id'] ?? $orderId,
                'expired_at' => now()->addMinutes(15),
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'transaction_id' => $orderId,
                    'payment_id' => $result['payment_id'] ?? $orderId,
                    'qr_string' => $result['qr_string'] ?? null,
                    'qr_image' => $result['qr_url'] ?? null,
                    'amount' => $validated['amount'],
                    'gateway' => $userGateway->gateway->code,
                    'status' => 'pending',
                    'expires_at' => $transaction->expired_at->toIso8601String(),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Payment creation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check payment status
     * GET /api/public/payments/{transaction_id}/status
     */
    public function checkStatus(Request $request, string $transactionId)
    {
        $user = $request->user();
        
        // Find transaction by order_id
        $transaction = Transaction::where('order_id', $transactionId)
            ->whereHas('bot', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->first();
        
        // Also check if transaction has no bot (API-created without bot)
        if (!$transaction) {
            $transaction = Transaction::where('order_id', $transactionId)
                ->whereNull('bot_id')
                ->first();
        }
        
        if (!$transaction) {
            return response()->json([
                'success' => false,
                'error' => 'Transaction not found',
                'message' => "Transaction '{$transactionId}' was not found"
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'transaction_id' => $transaction->order_id,
                'status' => $transaction->status,
                'amount' => $transaction->total_price,
                'gateway' => $transaction->payment_gateway,
                'product_name' => $transaction->product_name,
                'customer_name' => $transaction->telegram_username,
                'created_at' => $transaction->created_at->toIso8601String(),
                'paid_at' => $transaction->paid_at?->toIso8601String(),
                'expired_at' => $transaction->expired_at?->toIso8601String(),
            ]
        ]);
    }
    
    /**
     * Get payment history
     * GET /api/public/payments/history
     */
    public function getHistory(Request $request)
    {
        $user = $request->user();
        
        $query = Transaction::query();
        
        // Get transactions from user's bots
        $botIds = $user->bots()->pluck('id');
        $query->whereIn('bot_id', $botIds);
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Paginate
        $perPage = min($request->input('per_page', 20), 100);
        $transactions = $query->latest()->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions->items(),
                'total' => $transactions->total(),
                'page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'last_page' => $transactions->lastPage(),
            ]
        ]);
    }
    
    /**
     * Get available gateways for user
     * GET /api/public/gateways
     */
    public function getGateways(Request $request)
    {
        $user = $request->user();
        
        $gateways = UserGateway::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('gateway')
            ->get()
            ->map(function ($ug) {
                return [
                    'code' => $ug->gateway->code,
                    'name' => $ug->gateway->name,
                    'label' => $ug->label,
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => [
                'gateways' => $gateways
            ]
        ]);
    }
}
