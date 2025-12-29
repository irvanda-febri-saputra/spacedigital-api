<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update saweria to orderkuota with correct column names
        DB::table('payment_gateways')
            ->where('code', 'saweria')
            ->update([
                'code' => 'orderkuota',
                'name' => 'Order Kuota QRIS',
                'logo' => '/images/gateways/orderkuota.png',
                'fee_percent' => 0,
                'fee_flat' => 0,
                'description' => 'Order Kuota QRIS payment with mutation polling',
                'required_fields' => json_encode([
                    'username' => ['type' => 'text', 'label' => 'Username Order Kuota', 'required' => true],
                    'token' => ['type' => 'text', 'label' => 'Auth Token', 'required' => true],
                    'qris_string' => ['type' => 'textarea', 'label' => 'QRIS String', 'required' => true],
                ]),
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to saweria
        DB::table('payment_gateways')
            ->where('code', 'orderkuota')
            ->update([
                'code' => 'saweria',
                'name' => 'Saweria QRIS',
                'logo' => '/images/gateways/saweria.png',
                'fee_percent' => 2.5,
                'fee_flat' => 0,
                'description' => 'Saweria donation-based payment',
                'required_fields' => json_encode([
                    'api_key' => ['type' => 'text', 'label' => 'API Key', 'required' => true],
                ]),
                'updated_at' => now(),
            ]);
    }
};
