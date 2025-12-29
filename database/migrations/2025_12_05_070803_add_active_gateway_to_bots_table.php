<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->foreignId('active_gateway_id')
                ->nullable()
                ->after('settings')
                ->constrained('user_gateways')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropForeign(['active_gateway_id']);
            $table->dropColumn('active_gateway_id');
        });
    }
};
