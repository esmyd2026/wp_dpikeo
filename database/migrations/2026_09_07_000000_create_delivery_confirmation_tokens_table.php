<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_confirmation_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_cart_id')->constrained('whatsapp_carts')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_cart_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_confirmation_tokens');
    }
};
