<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_chatbot_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained('whatsapp_business_profiles')->onDelete('cascade');
            $table->string('welcome_message')->nullable();
            $table->string('default_response')->nullable();
            $table->json('greetings')->nullable(); // Lista de saludos reconocidos
            $table->json('menu_commands')->nullable(); // Comandos para volver al menú
            $table->json('metadata')->nullable(); // Configuraciones adicionales
            $table->boolean('is_active')->default(true);
            $table->boolean('chatgpt_enabled')->default(false);
            $table->string('chatgpt_api_key')->nullable();
            $table->string('chatgpt_model')->default('gpt-3.5-turbo');
            $table->text('chatgpt_system_prompt')->nullable(); // Cambiado de json a text
            $table->integer('chatgpt_max_tokens')->default(150);
            $table->decimal('chatgpt_temperature', 3, 2)->default(0.7);
            $table->json('chatgpt_additional_params')->nullable(); // Parámetros adicionales de configuración
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_chatbot_configs');
    }
};
