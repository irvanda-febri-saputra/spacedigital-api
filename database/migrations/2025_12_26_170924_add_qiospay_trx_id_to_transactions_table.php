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
        Schema::table('transactions', function (Blueprint $table) {
            // Store QiosPay mutation ID to prevent double matching
            $table->string('qiospay_trx_id')->nullable()->after('payment_ref');
            $table->index('qiospay_trx_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['qiospay_trx_id']);
            $table->dropColumn('qiospay_trx_id');
        });
    }
};
