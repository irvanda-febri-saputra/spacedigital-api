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
        Schema::table('products', function (Blueprint $table) {
            // Add columns for bot product sync
            $table->unsignedBigInteger('bot_external_id')->nullable()->after('bot_id');
            $table->string('product_code')->nullable()->after('name');
            $table->integer('stock_count')->default(0)->after('stock');
            
            // Add unique index for bot_id + bot_external_id to prevent duplicates
            $table->unique(['bot_id', 'bot_external_id'], 'products_bot_external_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_bot_external_unique');
            $table->dropColumn(['bot_external_id', 'product_code', 'stock_count']);
        });
    }
};
