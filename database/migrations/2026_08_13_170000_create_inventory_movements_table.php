<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_price_id')->nullable()->constrained('whatsapp_prices')->nullOnDelete();
            $table->foreignId('whatsapp_cart_id')->nullable()->constrained('whatsapp_carts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->integer('quantity'); // positivo ingresa, negativo reserva/libera venta.
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['whatsapp_price_id', 'created_at']);
            $table->index(['whatsapp_cart_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
