<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AtlanticQrisFastSeeder extends Seeder
{
    public function run(): void
    {
        // Check if QRIS Fast already exists
        $exists = DB::table('payment_gateways')->where('code', 'atlantic_fast')->exists();
        
        if (!$exists) {
            DB::table('payment_gateways')->insert([
                'code' => 'atlantic_fast',
                'name' => 'Atlantic QRIS Fast',
                'logo' => '/images/gateways/atlantic.png',
                'fee_percent' => 1.40,
                'fee_flat' => 200,
                'description' => 'Atlantic QRIS Instant - Min Rp 2.000, lebih cepat',
                'required_fields' => json_encode(['api_key']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->command->info('Atlantic QRIS Fast gateway added!');
        } else {
            $this->command->info('Atlantic QRIS Fast already exists.');
        }

        // Update existing Atlantic to clarify it's regular QRIS
        DB::table('payment_gateways')
            ->where('code', 'atlantic')
            ->update([
                'name' => 'Atlantic QRIS',
                'description' => 'Atlantic QRIS - Min Rp 500',
                'fee_percent' => 0.70,
                'fee_flat' => 200,
            ]);
        
        $this->command->info('Updated Atlantic QRIS description.');
    }
}
