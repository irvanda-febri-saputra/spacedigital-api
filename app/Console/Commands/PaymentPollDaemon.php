<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\UserGateway;
use App\Models\Bot;
use App\Services\PaymentGateways\AtlanticGateway;
use App\Services\OrderKuotaClient;
use App\Events\PaymentStatusUpdated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PaymentPollDaemon extends Command
{
    protected $signature = 'payment:poll-daemon {--interval=5 : Polling interval in seconds}';
    protected $description = 'Unified polling daemon for all payment gateways (Atlantic, QiosPay, OrderKuota)';

    public function handle()
    {
        $interval = (int) $this->option('interval');
        
        $this->info('🚀 Starting Unified Payment Daemon...');
        $this->info("  - Polling interval: {$interval}s");
        $this->info("  - Gateways: Atlantic, QiosPay, OrderKuota");
        $this->info('  - Notification: WebSocket (Reverb)');
        $this->info('Press Ctrl+C to stop');
        $this->newLine();
        
        while (true) {
            // Check if ANY pending transactions exist (across all gateways)
            $hasPending = Transaction::where('status', 'pending')
                ->where('created_at', '>=', now()->subHours(2))
                ->exists();
            
            // Smart interval: fast when pending, slow when idle (saves resources)
            $sleepTime = $hasPending ? $interval : 30; // 5s when pending, 30s when idle
            
            $processed = 0;
            
            // Only poll if there are pending transactions
            if ($hasPending) {
                // Poll Atlantic transactions
                $processed += $this->pollAtlanticTransactions();
                
                // Poll QiosPay transactions
                $processed += $this->pollQiosPayTransactions();
                
                // Poll OrderKuota transactions
                $processed += $this->pollOrderKuotaTransactions();
            }
            
            sleep($sleepTime);
        }
    }

    /**
     * Poll Atlantic transactions - FALLBACK ONLY
     * 
     * Primary detection: Webhook (/api/payments/webhook/atlantic)
     * This polling only catches missed webhooks (rare cases)
     * 
     * Atlantic webhook already triggers instant cair automatically
     */
    private function pollAtlanticTransactions(): int
    {
        // Only check transactions older than 30 seconds (give webhook time to process)
        $transactions = Transaction::where('status', 'pending')
            ->where('payment_gateway', 'like', '%atlantic%')
            ->where('created_at', '>=', now()->subHours(2))
            ->where('created_at', '<=', now()->subSeconds(30)) // Fallback delay
            ->whereNotNull('payment_ref')
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        $this->line("[" . now()->format('H:i:s') . "] 🔵 Atlantic (fallback): {$transactions->count()} pending");
        $processed = 0;

        foreach ($transactions as $transaction) {
            try {
                if ($this->processAtlanticTransaction($transaction)) {
                    $processed++;
                }
            } catch (\Exception $e) {
                $this->error("Atlantic error {$transaction->order_id}: " . $e->getMessage());
            }
        }

        return $processed;
    }

    private function processAtlanticTransaction(Transaction $transaction): bool
    {
        // Get gateway
        $userGateway = UserGateway::where('bot_id', $transaction->bot_id)
            ->where('is_active', true)
            ->whereHas('gateway', fn($q) => $q->where('code', 'like', 'atlantic%'))
            ->first();

        if (!$userGateway) {
            $bot = Bot::find($transaction->bot_id);
            if ($bot) {
                $userGateway = UserGateway::where('user_id', $bot->user_id)
                    ->where('is_active', true)
                    ->whereHas('gateway', fn($q) => $q->where('code', 'like', 'atlantic%'))
                    ->first();
            }
        }

        if (!$userGateway) return false;

        $credentials = $userGateway->credentials;
        $gateway = new AtlanticGateway([
            'api_key' => $credentials['api_key'] ?? '',
            'metode' => $userGateway->gateway->code === 'atlantic_fast' ? 'QRISFAST' : 'qris',
        ]);

        // Check status
        $result = $gateway->checkStatus($transaction->payment_ref);

        if (!$result['success']) return false;

        // If processing, trigger instant
        if ($result['status'] === 'processing') {
            $this->line("  ⏳ {$transaction->order_id}: Processing → Triggering Instant...");
            
            $instantResult = $gateway->triggerInstant($transaction->payment_ref);
            
            if ($instantResult['success']) {
                $this->markSuccessAndBroadcast($transaction);
                return true;
            }
        }
        // If already success
        elseif ($result['status'] === 'success') {
            $this->markSuccessAndBroadcast($transaction, $result['paid_at'] ?? null);
            return true;
        }

        return false;
    }

    /**
     * Poll QiosPay transactions via mutasi API
     */
    private function pollQiosPayTransactions(): int
    {
        $transactions = Transaction::where('status', 'pending')
            ->where('payment_gateway', 'like', '%qiospay%')
            ->where('created_at', '>=', now()->subHours(2))
            ->with(['bot.activeGateway.gateway'])
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        $this->line("[" . now()->format('H:i:s') . "] 🟢 QiosPay: {$transactions->count()} pending");
        $processed = 0;

        // Group by bot to minimize API calls
        $byBot = $transactions->groupBy('bot_id');

        foreach ($byBot as $botId => $botTransactions) {
            $processed += $this->checkQiosPayMutasi($botTransactions);
        }

        return $processed;
    }

    private function checkQiosPayMutasi($transactions): int
    {
        $firstTrx = $transactions->first();
        $gateway = $firstTrx->bot?->activeGateway;
        
        if (!$gateway) return 0;

        $credentials = $gateway->credentials ?? [];
        $apiKey = $credentials['api_key'] ?? null;
        $merchantCode = $credentials['merchant_code'] ?? null;

        if (!$apiKey || !$merchantCode) return 0;

        try {
            // Call unified endpoint from Worker
            $proxyUrl = env('ORDERKUOTA_PROXY_URL', 'https://workers.czel.me');
            
            $response = Http::timeout(30)->post("{$proxyUrl}/api/unified-mutations", [
                'gateway' => 'qiospay',
                'merchant_code' => $merchantCode,
                'api_key' => $apiKey,
            ]);

            $result = $response->json();

            if (!($result['success'] ?? false)) {
                $this->error("  Unified API error: " . ($result['error'] ?? 'Unknown error'));
                return 0;
            }

            $mutations = $result['mutations'] ?? [];
            $processed = 0;
            
            // CRITICAL: Track which mutations have been used in this poll cycle
            $usedMutationRefs = [];

            // Debug
            $this->line("  📋 Found " . count($mutations) . " mutations (unified format)");

            // Match transactions with mutations
            foreach ($transactions as $transaction) {
                $amount = (int) $transaction->total_price;
                $transactionCreatedAt = $transaction->created_at;

                foreach ($mutations as $mutasi) {
                    $mutasiAmount = (int) ($mutasi['amount'] ?? 0);
                    $refId = (string) ($mutasi['ref_id'] ?? '');
                    
                    // CRITICAL: Skip if this mutation was already used in this poll cycle
                    if (in_array($refId, $usedMutationRefs)) {
                        continue;
                    }
                    
                    // CRITICAL: Only match if amounts are equal
                    if ($mutasiAmount !== $amount) continue;
                    
                    // Parse paid_at time
                    $mutasiTime = null;
                    if (isset($mutasi['paid_at'])) {
                        try {
                            $mutasiTime = \Carbon\Carbon::parse($mutasi['paid_at']);
                        } catch (\Exception $e) {
                            $mutasiTime = now();
                        }
                    }
                    
                    // CRITICAL: Verify mutasi is AFTER transaction creation (prevent old payment matching)
                    if ($mutasiTime && $mutasiTime->lt($transactionCreatedAt)) {
                        $this->line("  ⏭️ Skipped old payment: Rp. {$mutasiAmount} (before transaction created)");
                        continue;
                    }
                    
                    // Check if ref_id was already used in DB (primary protection against duplicates)
                    if ($refId) {
                        $existingTrx = Transaction::where('payment_ref', $refId)
                            ->where('status', 'success')
                            ->first();
                        if ($existingTrx) {
                            $this->line("  ⏭️ Skipped already-used ref_id: {$refId}");
                            $usedMutationRefs[] = $refId;
                            continue;
                        }
                    }
                    
                    // All checks passed - valid match!
                    $this->line("  💵 Matched: {$transaction->order_id} = Rp. {$amount} (ref_id: {$refId})");
                    
                    // CRITICAL: Mark this mutation as used in this poll cycle
                    if ($refId) {
                        $usedMutationRefs[] = $refId;
                    }
                    
                    $transaction->update([
                        'status' => 'success',
                        'paid_at' => $mutasiTime ?? now(),
                        'payment_ref' => $refId,
                    ]);

                    $this->broadcastPayment($transaction);
                    $processed++;
                    break;
                }
            }

            return $processed;

        } catch (\Exception $e) {
            $this->error("QiosPay unified API error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Poll OrderKuota transactions via mutation API
     */
    private function pollOrderKuotaTransactions(): int
    {
        $transactions = Transaction::where('status', 'pending')
            ->where('payment_gateway', 'like', '%orderkuota%')
            ->where('created_at', '>=', now()->subHours(2))
            ->with(['bot.activeGateway.gateway'])
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        $this->line("[" . now()->format('H:i:s') . "] 🟡 OrderKuota: {$transactions->count()} pending");
        $processed = 0;

        // Group by bot to minimize API calls
        $byBot = $transactions->groupBy('bot_id');

        foreach ($byBot as $botId => $botTransactions) {
            $processed += $this->checkOrderKuotaMutasi($botTransactions);
        }

        return $processed;
    }

    private function checkOrderKuotaMutasi($transactions): int
    {
        $firstTrx = $transactions->first();
        $bot = $firstTrx->bot;
        
        if (!$bot) return 0;

        // Find OrderKuota gateway for this bot's user
        $userGateway = UserGateway::where('user_id', $bot->user_id)
            ->where('is_active', true)
            ->whereHas('gateway', fn($q) => $q->where('code', 'orderkuota'))
            ->first();

        if (!$userGateway) return 0;

        $credentials = $userGateway->credentials ?? [];
        $username = $credentials['username'] ?? null;
        $token = $credentials['token'] ?? null;

        if (!$username || !$token) {
            $this->error("  OrderKuota credentials missing for user {$bot->user_id}");
            return 0;
        }

        try {
            // Call unified endpoint from Worker
            $proxyUrl = env('ORDERKUOTA_PROXY_URL', 'https://workers.czel.me');
            
            $response = Http::timeout(30)->post("{$proxyUrl}/api/unified-mutations", [
                'gateway' => 'orderkuota',
                'username' => $username,
                'token' => $token,
            ]);

            $result = $response->json();

            if (!($result['success'] ?? false)) {
                $this->error("  Unified API error: " . ($result['error'] ?? 'Unknown error'));
                return 0;
            }

            $mutations = $result['mutations'] ?? [];
            $processed = 0;
            
            // CRITICAL: Track which mutations have been used in this poll cycle
            $usedMutationRefs = [];

            // Debug: show mutation data
            $this->line("  📋 Found " . count($mutations) . " mutations (unified format)");

            // Match transactions with mutations
            foreach ($transactions as $transaction) {
                $amount = (int) $transaction->total_price;
                $transactionCreatedAt = $transaction->created_at;
                $this->line("  🔍 Looking for amount: {$amount} (order: {$transaction->order_id})");

                foreach ($mutations as $mutasi) {
                    $mutasiAmount = (int) ($mutasi['amount'] ?? 0);
                    $refId = (string) ($mutasi['ref_id'] ?? '');  // This is mutation id (e.g. 187444642)
                    $keterangan = (string) ($mutasi['keterangan'] ?? '');  // Sender name, for logging only
                    
                    // CRITICAL: Skip if this mutation was already used in this poll cycle
                    if (in_array($refId, $usedMutationRefs)) {
                        continue;
                    }
                    
                    // Match by amount only (OrderKuota uses static QRIS, no order_id in payment)
                    if ($mutasiAmount !== $amount) continue;
                    
                    // Parse paid_at time
                    $mutasiTime = null;
                    if (isset($mutasi['paid_at'])) {
                        try {
                            $mutasiTime = \Carbon\Carbon::parse($mutasi['paid_at']);
                        } catch (\Exception $e) {
                            $mutasiTime = now();
                        }
                    }
                    
                    // Verify mutasi is after transaction creation
                    if ($mutasiTime && $mutasiTime->lt($transactionCreatedAt)) {
                        $this->line("  ⏭️ Skipped old payment: {$mutasiAmount} (before transaction)");
                        continue;
                    }
                    
                    // Check if this mutation was already used in DB - CRITICAL for anti-double-drop
                    if ($refId) {
                        $existingTrx = Transaction::where('payment_ref', $refId)
                            ->where('status', 'success')
                            ->first();
                        if ($existingTrx) {
                            $this->line("  ⏭️ Skipped already-used mutation: {$refId}");
                            $usedMutationRefs[] = $refId;
                            continue;
                        }
                    }
                    
                    // Match found!
                    $this->line("  💵 Matched: {$transaction->order_id} = Rp. {$amount} (mutation_id: {$refId}, sender: {$keterangan})");
                    
                    // CRITICAL: Mark this mutation as used in this poll cycle
                    if ($refId) {
                        $usedMutationRefs[] = $refId;
                    }
                    
                    $transaction->update([
                        'status' => 'success',
                        'paid_at' => $mutasiTime ?? now(),
                        'payment_ref' => $refId,  // Store unique mutation_id as payment_ref
                    ]);

                    $this->broadcastPayment($transaction);
                    $processed++;
                    break; // Move to next transaction
                }
            }

            return $processed;

        } catch (\Exception $e) {
            $this->error("OrderKuota unified API error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mark transaction as success and broadcast via WebSocket
     */
    private function markSuccessAndBroadcast(Transaction $transaction, ?string $paidAt = null)
    {
        $transaction->update([
            'status' => 'success',
            'paid_at' => $paidAt ? \Carbon\Carbon::parse($paidAt) : now(),
        ]);

        $this->broadcastPayment($transaction);
        $this->info("  ✅ {$transaction->order_id} → SUCCESS (broadcast sent)");
    }

    /**
     * Broadcast payment via WebSocket Hub (HTTP POST)
     */
    private function broadcastPayment(Transaction $transaction)
    {
        try {
            $wsUrl = config('app.ws_hub_url', 'http://localhost:8080');
            $wsSecret = config('app.ws_broadcast_secret');

            $response = Http::timeout(10)->withHeaders([
                'X-Broadcast-Secret' => $wsSecret,
            ])->post("{$wsUrl}/broadcast", [
                'secret' => $wsSecret,
                'channel' => "bot.{$transaction->bot_id}",
                'event' => 'payment.status.updated',
                'data' => [
                    'bot_id' => $transaction->bot_id,
                    'order_id' => $transaction->order_id,
                    'status' => 'success',
                    'amount' => (int) $transaction->total_price,
                    'paid_at' => now()->toIso8601String(),
                ],
            ]);

            if ($response->successful()) {
                Log::info("Payment broadcast sent: {$transaction->order_id}");
            } else {
                Log::warning("Payment broadcast response: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Failed to broadcast payment: " . $e->getMessage());
        }
    }
}
