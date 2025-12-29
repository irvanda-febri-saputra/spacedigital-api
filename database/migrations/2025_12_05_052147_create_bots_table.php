<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('bot_token')->nullable();
            $table->string('bot_username')->nullable();
            
            // Payment Gateway Settings
            $table->string('payment_gateway')->default('qiospay'); // qiospay, pakasir, etc
            $table->string('pg_merchant_code')->nullable();
            $table->string('pg_api_key')->nullable();
            $table->string('pg_qr_string')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            
            // Additional settings (JSON)
            $table->json('settings')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
