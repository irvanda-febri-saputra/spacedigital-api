<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\UserGateway;
use App\Services\PaymentGateways\AtlanticGateway;
use Illuminate\Support\Facades\Log;

class PollAtlanticInstant extends Command
{
    protected $signature = 'atlantic:poll-instant {--daemon : Run continuously with 15-second interval}';
    protected $description = 'Check pending Atlantic transactions and auto-trigger Cair Instant for processing payments';

    public function handle()
    {
        $isDaemon = $this->option('daemon');
        
        if ($isDaemon) {
            $this->info('🔄 Starting Atlantic smart daemon...');
            $this->info('  - 15s interval when pending transactions exist');
            $this->info('  - 60s interval when idle');
            $this->info('Press Ctrl+C to stop');
        }
        
        do {
            $hasPending = $this->pollTransactions();
            
            if ($isDaemon) {
                // Smart interval: 15s when pending, 60s when idle
                $interval = $hasPending ? 15 : 60;
                sleep($interval);
            }
        } while ($isDaemon);
    }
    
    private function pollTransactions(): bool
    {
        // Get pending transactions from last 2 hours that use Atlantic
        $transactions = Transaction::where('status', 'pending')
            ->where('payment_gateway', 'like', '%atlantic%')
            ->where('created_at', '>=', now()->subHours(2))
            ->whereNotNull('payment_ref')
            ->get();

        if ($transactions->count() > 0) {
            $this->line("[" . now()->format('H:i:s') . "] Checking {$transactions->count()} pending...");
        }

        foreach ($transactions as $transaction) {
            try {
                $this->processTransaction($transaction);
            } catch (\Exception $e) {
                $this->error("Error processing {$transaction->order_id}: " . $e->getMessage());
                Log::error('Atlantic poll-instant error', [
                    'order_id' => $transaction->order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $transactions->count() > 0;
    }

    private function processTransaction(Transaction $transaction)
    {
        $this->line("Processing: {$transaction->order_id} (ref: {$transaction->payment_ref})");
        
        // Get user gateway - user_gateways table uses user_id, not bot_id
        // First get the bot to find user_id
        $bot = \App\Models\Bot::find($transaction->bot_id);
        
        $userGateway = null;
        if ($bot) {
            $userGateway = UserGateway::where('user_id', $bot->user_id)
                ->where('is_active', true)
                ->whereHas('gateway', function ($q) {
                    $q->where('code', 'like', 'atlantic%');
                })
                ->first();
        }

        if (!$userGateway) {
            $this->warn("No Atlantic gateway found for bot_id: {$transaction->bot_id}");
            return;
        }

        $credentials = $userGateway->credentials;
        $gateway = new AtlanticGateway([
            'api_key' => $credentials['api_key'] ?? '',
            'metode' => $userGateway->gateway->code === 'atlantic_fast' ? 'QRISFAST' : 'qris',
        ]);

        // Check status with Atlantic
        $depositId = $transaction->payment_ref;
        $result = $gateway->checkStatus($depositId);

        $this->line("  Status from Atlantic: " . ($result['status'] ?? 'unknown'));

        if (!$result['success']) {
            $this->warn("  Failed to check status: " . ($result['error'] ?? 'Unknown'));
            return;
        }

        // If status is "processing", trigger instant
        if ($result['status'] === 'processing') {
            $this->info("Triggering Cair Instant for {$transaction->order_id}...");
            
            $instantResult = $gateway->triggerInstant($depositId);
            
            if ($instantResult['success']) {
                $this->info("✓ Cair Instant triggered successfully!");
                
                // After instant, mark as success and notify bot
                $transaction->update([
                    'status' => 'success',
                    'paid_at' => now(),
                ]);
                
                $this->notifyBot($transaction);
                $this->info("✓ Transaction {$transaction->order_id} marked as SUCCESS!");
            } else {
                $this->warn("✗ Failed: " . ($instantResult['error'] ?? 'Unknown error'));
            }
        }
        // If already success, update our transaction
        elseif ($result['status'] === 'success') {
            $transaction->update([
                'status' => 'success',
                'paid_at' => $result['paid_at'] ?? now(),
            ]);

            $this->notifyBot($transaction);
            $this->info("✓ Transaction {$transaction->order_id} marked as SUCCESS!");
        }
    }

    private function notifyBot(Transaction $transaction)
    {
        try {
            // PRIMARY: Broadcast via WebSocket (Laravel Event)
            event(new \App\Events\PaymentStatusUpdated(
                $transaction->bot_id,
                $transaction->order_id,
                'success',
                (int) $transaction->total_price,
                now()->toIso8601String()
            ));
            $this->info("📡 Broadcasted via WebSocket!");
            
            // Create dashboard notification for user
            $bot = \App\Models\Bot::find($transaction->bot_id);
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
                $this->info("🔔 Dashboard notification created!");
            }
            
        } catch (\Exception $e) {
            Log::warning('WebSocket broadcast failed, using HTTP fallback', ['error' => $e->getMessage()]);
            
            // FALLBACK: HTTP callback to bot
            try {
                $response = \Http::timeout(10)->post('http://localhost:3000/webhook/payment-callback', [
                    'order_id' => $transaction->order_id,
                    'status' => 'success',
                    'amount' => $transaction->total_price,
                    'paid_at' => now()->toIso8601String(),
                ]);
                
                if ($response->successful()) {
                    $this->info("📤 Bot notified via HTTP fallback!");
                } else {
                    $this->warn("⚠️ HTTP fallback failed: " . $response->body());
                }
            } catch (\Exception $httpError) {
                Log::error('Both WebSocket and HTTP notification failed', ['error' => $httpError->getMessage()]);
                $this->error("❌ All notification methods failed!");
            }
        }
    }
}
