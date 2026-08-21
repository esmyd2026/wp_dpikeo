<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('whatsapp_menu_items')->onDelete('cascade');
            $table->string('sku', 4)->unique();
            $table->string('category');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('promo_price', 10, 2)->nullable();
            $table->string('currency')->default('USD');
            $table->boolean('is_promo')->default(false);
            $table->date('promo_start_date')->nullable();
            $table->date('promo_end_date')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_prices');
    }
};
