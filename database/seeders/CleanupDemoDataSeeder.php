<?php

namespace Database\Seeders;

use App\Models\Bot;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class CleanupDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $demoBot = Bot::where('name', 'Demo Store Bot')->first();
        
        if ($demoBot) {
            // Delete all transactions associated with demo bot
            Transaction::where('bot_id', $demoBot->id)->delete();
            
            // Delete the demo bot
            $demoBot->delete();
            
            echo "✅ Demo Store Bot and its transactions have been deleted.\n";
        } else {
            echo "ℹ️ Demo Store Bot not found.\n";
        }
    }
}
