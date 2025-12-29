<?php

namespace Database\Seeders;

use App\Models\Bot;
use Illuminate\Database\Seeder;

class UpdateRealBotApiKeySeeder extends Seeder
{
    public function run(): void
    {
        // Update bot store dengan API Key yang ada di .env bot
        // Key: 7a7f56f92ba61155fcc27a98e1f9e2346654bb32615ec4cd1a352d31a0245307
        
        $bot = Bot::where('name', 'bot store')->first();
        
        if ($bot) {
            $bot->update([
                'pg_api_key' => '7a7f56f92ba61155fcc27a98e1f9e2346654bb32615ec4cd1a352d31a0245307'
            ]);
            echo "✅ Bot store API Key updated!\n";
        } else {
            echo "ℹ️ Bot store not found. Please create it first.\n";
        }
    }
}
