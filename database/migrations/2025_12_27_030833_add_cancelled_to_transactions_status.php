<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Modify ENUM to add 'cancelled' status
     */
    public function up(): void
    {
        // MySQL: Alter ENUM to add 'cancelled'
        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'success', 'expired', 'failed', 'cancelled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original ENUM (without cancelled)
        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'success', 'expired', 'failed') DEFAULT 'pending'");
    }
};
