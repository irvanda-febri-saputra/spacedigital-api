<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateGatewayFeesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update QiosPay
        DB::table('payment_gateways')
            ->where('code', 'qiospay')
            ->update([
                'fee_percent' => 0.70,
                'fee_flat' => 0,
            ]);

        // Update Pakasir
        DB::table('payment_gateways')
            ->where('code', 'pakasir')
            ->update([
                'fee_percent' => 0.70,
                'fee_flat' => 310,
            ]);

        // Update Atlantic (0.7% + Rp 200 - fee dari provider)
        DB::table('payment_gateways')
            ->where('code', 'atlantic')
            ->update([
                'fee_percent' => 0.70,
                'fee_flat' => 200,
            ]);

        // Update Order Kuota (0.7% - sama seperti QiosPay)
        DB::table('payment_gateways')
            ->where('code', 'orderkuota')
            ->update([
                'fee_percent' => 0.70,
                'fee_flat' => 0,
            ]);
            
        $this->command->info('Payment gateway fees updated successfully!');
    }
}

