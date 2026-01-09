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
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->text('data'); // The actual stock data (email|password, serial, etc)
            $table->boolean('is_sold')->default(false);
            $table->timestamp('sold_at')->nullable();
            $table->string('sold_to_telegram_id')->nullable(); // Buyer's Telegram ID
            $table->string('sold_order_id')->nullable(); // Transaction/order reference
            $table->timestamps();
            
            // Indexes for common queries
            $table->index(['product_id', 'is_sold']);
            $table->index(['variant_id', 'is_sold']);
            $table->index('sold_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
