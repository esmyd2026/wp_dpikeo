<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_cart_id')->constrained('whatsapp_carts')->onDelete('cascade');
            $table->foreignId('whatsapp_price_id')->constrained('whatsapp_prices')->onDelete('cascade');
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_cart_items');
    }
};
