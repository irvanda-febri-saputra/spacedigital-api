<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AtlanticGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if Atlantic gateway already exists
        $exists = DB::table('payment_gateways')->where('code', 'atlantic')->exists();
        
        if (!$exists) {
            DB::table('payment_gateways')->insert([
                'code' => 'atlantic',
                'name' => 'Atlantic Pedia',
                'logo' => '/images/gateways/atlantic.png',
                'fee_percent' => 0.70,
                'fee_flat' => 350,
                'description' => 'Atlantic Pedia H2H - QRIS, E-Wallet, Virtual Account',
                'required_fields' => json_encode(['api_key']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->command->info('Atlantic Pedia gateway added successfully!');
        } else {
            $this->command->info('Atlantic Pedia gateway already exists.');
        }
    }
}
