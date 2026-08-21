<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained('whatsapp_business_profiles')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('button'); // button, list, text
            $table->text('content');
            $table->string('button_text')->nullable(); // Solo para tipo button
            $table->string('icon')->nullable(); // Emoji o icono
            $table->string('action_id')->nullable(); // ID para identificar la acción
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Datos adicionales como secciones, etc.
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_menus');
    }
};
