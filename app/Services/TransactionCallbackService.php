<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Bot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transaction Callback Service
 * Sends payment notifications to user's external webhook URL
 */
class TransactionCallbackService
{
    /**
     * Send callback notification to external system
     */
    public function sendCallback(Transaction $transaction): bool
    {
        $bot = $transaction->bot;
        
        // Check if bot has callback URL configured
        $callbackUrl = $bot->settings['callback_url'] ?? null;
        
        if (!$callbackUrl) {
            Log::info('No callback URL configured for bot', ['bot_id' => $bot->id]);
            return true; // Not an error, just no callback configured
        }

        $payload = $this->buildPayload($transaction);
        $signature = $this->generateSignature($payload, $bot);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-ID' => $transaction->id,
                    'User-Agent' => 'SpaceDigital-Webhook/1.0',
                ])
                ->post($callbackUrl, $payload);

            if ($response->successful()) {
                Log::info('Callback sent successfully', [
                    'transaction_id' => $transaction->id,
                    'order_id' => $transaction->order_id,
                    'callback_url' => $callbackUrl,
                    'response_status' => $response->status(),
                ]);

                // Update transaction with callback status
                $transaction->update([
                    'callback_sent_at' => now(),
                    'callback_response' => $response->status(),
                ]);

                return true;
            }

            Log::warning('Callback failed', [
                'transaction_id' => $transaction->id,
                'callback_url' => $callbackUrl,
                'response_status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Callback error', [
                'transaction_id' => $transaction->id,
                'callback_url' => $callbackUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build webhook payload
     */
    protected function buildPayload(Transaction $transaction): array
    {
        return [
            'event' => 'payment.success',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'amount' => (int) $transaction->total_price,
                'status' => $transaction->status,
                'payment_ref' => $transaction->payment_ref,
                'paid_at' => $transaction->paid_at?->toIso8601String(),
                'customer' => [
                    'telegram_user_id' => $transaction->telegram_user_id,
                    'telegram_username' => $transaction->telegram_username,
                    'name' => $transaction->product_name,
                ],
                'product' => [
                    'name' => $transaction->product_name,
                    'variant' => $transaction->variant,
                    'quantity' => $transaction->quantity,
                    'price' => (int) $transaction->price,
                ],
            ],
        ];
    }

    /**
     * Generate HMAC signature for webhook verification
     */
    protected function generateSignature(array $payload, Bot $bot): string
    {
        $secret = $bot->pg_api_key ?? $bot->id;
        $data = json_encode($payload);
        
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Retry failed callbacks (for scheduled job)
     */
    public function retryFailedCallbacks(): int
    {
        $failedTransactions = Transaction::whereNotNull('paid_at')
            ->whereNull('callback_sent_at')
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subHours(24)) // Only retry within 24 hours
            ->limit(50)
            ->get();

        $successCount = 0;
        
        foreach ($failedTransactions as $transaction) {
            if ($this->sendCallback($transaction)) {
                $successCount++;
            }
        }

        return $successCount;
    }
}
