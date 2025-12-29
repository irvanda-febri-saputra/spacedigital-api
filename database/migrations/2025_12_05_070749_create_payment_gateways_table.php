<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // qiospay, pakasir, saweria
            $table->string('name'); // Display name
            $table->string('logo')->nullable(); // Logo URL
            $table->decimal('fee_percent', 5, 2)->default(0); // % fee
            $table->decimal('fee_flat', 10, 2)->default(0); // Flat fee
            $table->text('description')->nullable();
            $table->json('required_fields')->nullable(); // Fields needed for config
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default gateways
        DB::table('payment_gateways')->insert([
            [
                'code' => 'qiospay',
                'name' => 'QiosPay QRIS',
                'logo' => '/images/gateways/qiospay.png',
                'fee_percent' => 0.70,
                'fee_flat' => 0,
                'description' => 'Payment gateway with 0.7% fee per transaction',
                'required_fields' => json_encode(['api_key', 'merchant_code', 'qr_string']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'pakasir',
                'name' => 'Pakasir QRIS',
                'logo' => '/images/gateways/pakasir.png',
                'fee_percent' => 0.7,
                'fee_flat' => 310,
                'description' => 'Pakasir payment gateway with webhook support',
                'required_fields' => json_encode(['api_key', 'slug']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'saweria',
                'name' => 'Saweria QRIS',
                'logo' => '/images/gateways/saweria.png',
                'fee_percent' => 2.50,
                'fee_flat' => 0,
                'description' => 'Saweria donation-based payment',
                'required_fields' => json_encode(['stream_key']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'atlantic',
                'name' => 'Atlantic H2H',
                'logo' => '/images/gateways/atlantic.png',
                'fee_percent' => 0.70, // Adjust as needed
                'fee_flat' => 0,
                'description' => 'Atlantic H2H Payment Gateway',
                'required_fields' => json_encode(['api_key']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
