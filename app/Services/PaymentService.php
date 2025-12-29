<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\UserGateway;
use App\Models\Transaction;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentGateways\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;

/**
 * Main Payment Service
 * Handles all payment operations for bots
 */
class PaymentService
{
    /**
     * Create a payment for a bot
     */
    public function createPayment(Bot $bot, array $data): array
    {
        // Get the bot's active gateway
        $userGateway = $bot->activeGateway;

        if (!$userGateway) {
            // Fallback to legacy payment config
            return $this->createLegacyPayment($bot, $data);
        }

        try {
            $gateway = PaymentGatewayFactory::fromUserGateway($userGateway);

            // Set callback URL for webhook
            $data['callback_url'] = $data['callback_url'] ?? config('app.url') . '/api/payments/webhook';

            $result = $gateway->createPayment($data);

            if ($result['success']) {
                // Add gateway code to result for tracking
                $result['gateway_code'] = $gateway->getCode();

                Log::info("Payment created via {$gateway->getCode()}", [
                    'order_id' => $data['order_id'],
                    'amount' => $data['amount'],
                    'payment_id' => $result['payment_id'],
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('PaymentService createPayment error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create payment: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check payment status
     */
    public function checkStatus(Bot $bot, string $paymentId): array
    {
        $userGateway = $bot->activeGateway;

        if (!$userGateway) {
            return [
                'success' => false,
                'error' => 'No payment gateway configured for this bot',
            ];
        }

        try {
            $gateway = PaymentGatewayFactory::fromUserGateway($userGateway);
            return $gateway->checkStatus($paymentId);
        } catch (\Exception $e) {
            Log::error('PaymentService checkStatus error: ' . $e->getMessage());
            return [
                'success' => false,
                'status' => 'unknown',
                'error' => 'Failed to check status: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle incoming webhook
     */
    public function handleWebhook(string $gatewayCode, array $payload, ?string $signature = null): array
    {
        try {
            // Find the gateway by code
            $gateway = PaymentGatewayFactory::create($gatewayCode, []);

            // For webhook validation, we need to find the transaction first
            // to get the correct credentials
            $parsedData = $gateway->parseWebhook($payload);

            // Find the transaction by order_id (Pakasir) or payment_ref
            $transaction = Transaction::where('order_id', $parsedData['payment_id'])
                ->orWhere('payment_ref', $parsedData['payment_id'])
                ->first();

            // [QIOSPAY FALLBACK] If payment_id is empty, try matching by amount
            // QiosPay static QRIS doesn't send transaction_id, only amount
            if (!$transaction && empty($parsedData['payment_id']) && !empty($parsedData['amount'])) {
                Log::info('Attempting QiosPay amount-based matching', ['amount' => $parsedData['amount']]);

                // Get QiosPay mutation ID from parsed data or payload
                $qiosPayTrxId = $parsedData['qiospay_trx_id'] ?? $payload['id'] ?? $payload['trx_id'] ?? null;

                // Find pending transaction with matching amount created in last 30 minutes
                // IMPORTANT: Exclude transactions where this QiosPay ID was already used
                $query = Transaction::where('status', 'pending')
                    ->where('total_price', $parsedData['amount'])
                    ->where('payment_gateway', 'like', '%qiospay%')
                    ->where('created_at', '>=', now()->subMinutes(30));

                // If we have QiosPay ID, make sure it's not already claimed
                if ($qiosPayTrxId) {
                    $alreadyClaimed = Transaction::where('qiospay_trx_id', $qiosPayTrxId)->exists();
                    if ($alreadyClaimed) {
                        Log::info('QiosPay ID already claimed, skipping', ['qiospay_trx_id' => $qiosPayTrxId]);
                        return [
                            'success' => false,
                            'error' => 'QiosPay transaction already processed',
                        ];
                    }
                }

                // Get OLDEST pending transaction (FIFO - first order should be matched first)
                $transaction = $query->orderBy('created_at', 'asc')->first();

                if ($transaction) {
                    Log::info('QiosPay transaction matched by amount (FIFO)', [
                        'order_id' => $transaction->order_id,
                        'amount' => $parsedData['amount'],
                        'qiospay_trx_id' => $qiosPayTrxId,
                    ]);

                    // Store QiosPay ID to prevent double matching
                    if ($qiosPayTrxId) {
                        $parsedData['qiospay_trx_id'] = $qiosPayTrxId;
                    }
                }
            }

            Log::info('Webhook received', [
                'gateway' => $gatewayCode,
                'payment_id' => $parsedData['payment_id'],
                'amount' => $parsedData['amount'] ?? 0,
                'transaction_found' => $transaction ? true : false,
            ]);

            if (!$transaction) {
                return [
                    'success' => false,
                    'error' => 'Transaction not found',
                ];
            }

            // Get the bot's gateway config for validation
            $userGateway = $transaction->bot->activeGateway;
            if ($userGateway) {
                $gateway = PaymentGatewayFactory::fromUserGateway($userGateway);

                if (!$gateway->validateWebhook($payload, $signature)) {
                    Log::warning('Invalid webhook signature', ['gateway' => $gatewayCode, 'payload' => $payload]);
                    return [
                        'success' => false,
                        'error' => 'Invalid signature',
                    ];
                }
            }

            Log::info('DEBUG ATLANTIC CHECK:', [
                'parsed_status' => $parsedData['status'],
                'gateway_code' => $gatewayCode,
                'is_atlantic' => str_contains($gatewayCode, 'atlantic'),
                'is_pending_processing' => in_array($parsedData['status'], ['processing', 'pending']),
                'payload_raw_status' => $payload['status'] ?? 'N/A'
            ]);

            // [ATLANTIC] Auto-trigger Instant Withdrawal if status is 'processing' or 'pending'
            if (in_array($parsedData['status'], ['processing', 'pending']) && str_contains($gatewayCode, 'atlantic')) {
                if (method_exists($gateway, 'triggerInstant')) {
                    // Use deposit_id (Atlantic's internal ID) for triggerInstant, not payment_id (our order ID)
                    $depositId = $parsedData['deposit_id'] ?? $parsedData['payment_id'];
                    Log::info('Atlantic pending/processing status received. Triggering instant...', [
                        'deposit_id' => $depositId,
                        'payment_id' => $parsedData['payment_id'],
                    ]);
                    $trigger = $gateway->triggerInstant($depositId);

                    if ($trigger['success']) {
                        // Override status to success immediately
                        $parsedData['status'] = 'success';
                        $parsedData['paid_at'] = now()->toIso8601String();
                        Log::info('Atlantic instant triggered successfully via webhook');
                    }
                }
            }

            // Update transaction status
            if ($parsedData['status'] === 'success') {
                $updateData = [
                    'status' => 'success',
                    'paid_at' => $parsedData['paid_at'] ?? now(),
                ];

                // Save QiosPay transaction ID to prevent double matching
                if (!empty($parsedData['qiospay_trx_id'])) {
                    $updateData['qiospay_trx_id'] = $parsedData['qiospay_trx_id'];
                }

                $transaction->update($updateData);

                Log::info('Payment successful via webhook', [
                    'order_id' => $transaction->order_id,
                    'payment_ref' => $parsedData['payment_id'],
                    'qiospay_trx_id' => $parsedData['qiospay_trx_id'] ?? null,
                ]);

                // Broadcast event to bot via WebSocket Hub (Node.js)
                try {
                    $wsHub = new WebSocketHubService();
                    $wsHub->broadcastPaymentStatus(
                        $transaction->bot_id,
                        $transaction->order_id,
                        'success',
                        (int) $transaction->total_price,
                        now()->toIso8601String(),
                        $gatewayCode
                    );
                    Log::info('Broadcasted to WS Hub', [
                        'bot_id' => $transaction->bot_id,
                        'order_id' => $transaction->order_id,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('WS Hub broadcast failed (non-critical)', ['error' => $e->getMessage()]);
                }

                // Legacy: Broadcast event via Laravel Reverb (will be deprecated)
                // event(new \App\Events\PaymentStatusUpdated(
                //     $transaction->bot_id,
                //     $transaction->order_id,
                //     'success',
                //     (int) $transaction->total_price,
                //     now()->toIso8601String()
                // ));

                Log::info('Payment status update completed', [
                    'bot_id' => $transaction->bot_id,
                    'order_id' => $transaction->order_id,
                ]);

                // Create notification for user
                try {
                    $bot = $transaction->bot;
                    if ($bot && $bot->user_id) {
                        \App\Http\Controllers\NotificationController::createAndBroadcast(
                            $bot->user_id,
                            'success',
                            'Payment Received',
                            "Transaction {$transaction->order_id} completed - Rp " . number_format($transaction->total_price, 0, ',', '.'),
                            [
                                'order_id' => $transaction->order_id,
                                'amount' => $transaction->total_price,
                                'product' => $transaction->product_name,
                            ]
                        );
                    }
                } catch (\Exception $e) {
                    Log::warning('Notification creation failed (non-critical)', ['error' => $e->getMessage()]);
                }

                // Send callback to user's external system
                try {
                    $callbackService = app(TransactionCallbackService::class);
                    $callbackService->sendCallback($transaction);
                } catch (\Exception $e) {
                    Log::warning('Callback send failed (non-critical)', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'success' => true,
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'status' => $parsedData['status'],
            ];
        } catch (\Exception $e) {
            Log::error('PaymentService handleWebhook error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Webhook processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Fallback for bots using legacy payment config (pg_api_key, pg_merchant_code)
     */
    private function createLegacyPayment(Bot $bot, array $data): array
    {
        // Use the bot's direct payment gateway config
        $gatewayCode = $bot->payment_gateway ?? 'qiospay';

        $credentials = [
            'api_key' => $bot->pg_api_key,
            'merchant_code' => $bot->pg_merchant_code,
            'merchant_id' => $bot->pg_merchant_code, // For pakasir
        ];

        try {
            $gateway = PaymentGatewayFactory::create($gatewayCode, $credentials);
            return $gateway->createPayment($data);
        } catch (\Exception $e) {
            Log::error('Legacy payment creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Payment creation failed',
            ];
        }
    }

    /**
     * Get available gateways for a user
     */
    public function getAvailableGateways(int $userId): array
    {
        return UserGateway::where('user_id', $userId)
            ->where('is_active', true)
            ->with('gateway')
            ->get()
            ->map(fn($ug) => [
                'id' => $ug->id,
                'code' => $ug->gateway->code,
                'name' => $ug->gateway->name,
                'label' => $ug->label,
            ])
            ->toArray();
    }
}
