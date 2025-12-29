<?php

namespace Database\Seeders;

use App\Models\Bot;
use Illuminate\Database\Seeder;

class UpdateBotApiKeySeeder extends Seeder
{
    public function run(): void
    {
        // Update semua bot yang belum punya API key
        Bot::whereNull('pg_api_key')
            ->orWhere('pg_api_key', '')
            ->update([
                'pg_api_key' => '7a7f56f92ba61155fcc27a98e1f9e2346654bb32615ec4cd1a352d31a0245307',
            ]);
            
        echo "Bot API keys updated!\n";
    }
}
