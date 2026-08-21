<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_order_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('whatsapp_contacts')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('whatsapp_cart_id')->nullable()->constrained('whatsapp_carts')->nullOnDelete();
            $table->timestamps();

            $table->index(['contact_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_order_tokens');
    }
};
