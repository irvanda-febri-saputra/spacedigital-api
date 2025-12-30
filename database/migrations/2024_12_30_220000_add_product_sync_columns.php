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
            $table->unsignedBigInteger('bot_external_id')->nullable()->after('bot_id');
            $table->string('product_code')->nullable()->after('bot_external_id');
            $table->integer('stock_count')->default(0)->after('price');
            $table->json('variants')->nullable()->after('description');
            
            // Index for faster lookups
            $table->index(['bot_id', 'bot_external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['bot_id', 'bot_external_id']);
            $table->dropColumn(['bot_external_id', 'product_code', 'stock_count', 'variants']);
        });
    }
};
