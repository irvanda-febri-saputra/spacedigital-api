<?php

namespace App\Console\Commands;

use App\Events\PaymentStatusUpdated;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PollQiosPayPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:poll-qiospay {--interval=3 : Polling interval in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll QiosPay mutasi API for pending payments and broadcast success events';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $interval = (int) $this->option('interval');
        $this->info("Starting QiosPay payment polling (interval: {$interval}s)...");
        $this->info("Press Ctrl+C to stop\n");

        while (true) {
            $this->pollPendingPayments();
            sleep($interval);
        }
    }

    /**
     * Poll all pending QiosPay payments
     */
    protected function pollPendingPayments()
    {
        // Get all pending transactions (simpler query)
        $pendingTransactions = Transaction::where('status', 'pending')
            ->whereNotNull('bot_id')
            ->with(['bot.activeGateway.gateway'])
            ->get()
            ->filter(function ($trx) {
                // Filter only QiosPay transactions
                $gateway = $trx->bot?->activeGateway?->gateway;
                return $gateway && $gateway->code === 'qiospay';
            });

        if ($pendingTransactions->isEmpty()) {
            $this->line("[" . now()->format('H:i:s') . "] No pending QiosPay transactions. Sleeping...");
            sleep(7); // Sleep extra 7s + 3s default = 10s total
            return;
        }

        $this->line("[" . now()->format('H:i:s') . "] Checking {$pendingTransactions->count()} pending QiosPay payment(s)...");

        // Group by bot to optimize API calls (1 call per bot/merchant)
        $transactionsByBot = $pendingTransactions->groupBy('bot_id');

        foreach ($transactionsByBot as $botId => $transactions) {
            // Use first transaction to get credentials
            $firstTrx = $transactions->first();
            $this->checkPaymentStatusForBot($firstTrx, $transactions);
        }
    }

    /**
     * Check payment status for a batch of transactions for one bot
     */
    protected function checkPaymentStatusForBot(Transaction $sampleTrx, $transactions)
    {
        try {
            $gateway = $sampleTrx->bot->activeGateway;
            if (!$gateway) return;

            // Fix: use credentials instead of config
            $credentials = $gateway->credentials ?? [];
            $apiKey = $credentials['api_key'] ?? null;
            $merchantCode = $credentials['merchant_code'] ?? null;

            if (!$apiKey || !$merchantCode) {
                Log::warning("QiosPay config missing for bot {$sampleTrx->bot_id}");
                return;
            }

            // Call QiosPay mutasi API
            $url = "https://qiospay.id/api/mutasi/qris/{$merchantCode}/{$apiKey}";
            
            $response = Http::get($url);

            if (!$response->successful()) {
                $this->error("API Error: " . $response->status());
                return;
            }

            $data = $response->json();
            $payments = $data['data'] ?? [];
            
            // Check each pending transaction against payments
            foreach ($transactions as $transaction) {
                $amount = (int) $transaction->total_price;
                
                foreach ($payments as $payment) {
                    // 1. Check if Credit (Uang Masuk)
                    if (($payment['type'] ?? '') !== 'CR') {
                        continue;
                    }

                    // 2. Check Amount Match
                    $paymentAmount = (int) ($payment['amount'] ?? 0);
                    
                    if ($paymentAmount === $amount) {
                        $this->info("✅ Payment detected: {$transaction->order_id} - Rp. " . number_format($amount));
                        
                        // Use issuer_reff as Payment Reference
                        $paymentRef = $payment['issuer_reff'] ?? $payment['buyer_reff'] ?? null;
                        
                        // Update transaction
                        $transaction->update([
                            'status' => 'success',
                            'paid_at' => now(),
                            'payment_ref' => $paymentRef,
                        ]);

                        // Broadcast via WebSocket
                        event(new PaymentStatusUpdated(
                            $transaction->bot_id,
                            $transaction->order_id,
                            'success',
                            $amount,
                            now()->toIso8601String()
                        ));

                        $this->info("Broadcasted success event for {$transaction->order_id}");
                        break; // Stop checking payments for this transaction
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage());
        }
    }
    
    // Deprecated single check method
    protected function checkPaymentStatus(Transaction $transaction) {}


}
