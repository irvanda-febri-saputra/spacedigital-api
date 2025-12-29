<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('gateway_id')->constrained('payment_gateways')->onDelete('cascade');
            $table->text('credentials'); // Encrypted array - stored as text
            $table->string('label')->nullable(); // User's custom label for this config
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Each user can only have one config per gateway type
            $table->unique(['user_id', 'gateway_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_gateways');
    }
};
