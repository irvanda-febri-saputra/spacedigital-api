<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->string('order_id')->unique();
            $table->string('telegram_user_id')->nullable();
            $table->string('telegram_username')->nullable();
            
            // Product info
            $table->string('product_name');
            $table->string('variant')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 12, 2);
            $table->decimal('total_price', 12, 2);
            
            // Payment info
            $table->string('payment_gateway');
            $table->string('payment_ref')->nullable();
            $table->enum('status', ['pending', 'success', 'expired', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
