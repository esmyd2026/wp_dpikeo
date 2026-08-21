<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('whatsapp_menus')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('whatsapp_menu_items')->onDelete('cascade');
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('action_id'); // ID para identificar la acción
            $table->string('icon')->nullable(); // Emoji o icono
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_menu_items');
    }
};
