<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanupOldTransactions extends Command
{
    protected $signature = 'transactions:cleanup {--days=30 : Number of days to keep}';
    protected $description = 'Delete transactions older than specified days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $count = Transaction::where('created_at', '<', $cutoffDate)->count();

        if ($count === 0) {
            $this->info("No transactions older than {$days} days found.");
            return self::SUCCESS;
        }

        $this->info("Found {$count} transactions older than {$days} days.");

        // Delete in chunks to avoid memory issues
        $deleted = 0;
        Transaction::where('created_at', '<', $cutoffDate)
            ->chunkById(100, function ($transactions) use (&$deleted) {
                foreach ($transactions as $tx) {
                    $tx->delete();
                    $deleted++;
                }
            });

        $this->info("Deleted {$deleted} old transactions.");

        return self::SUCCESS;
    }
}
