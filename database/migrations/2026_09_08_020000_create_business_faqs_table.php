<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preguntas frecuentes ("¿qué pasa si ya no quiero mi pedido?",
 * devoluciones, contactos de vendedoras, etc.) que el bot muestra como una
 * lista en "Información" -- el cliente toca una pregunta y el bot responde
 * con su texto de respuesta. Independiente de WhatsappChatbotResponse
 * (keywords sueltos): esto es una lista curada y ordenada por el negocio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained('whatsapp_business_profiles')->cascadeOnDelete();
            $table->string('question', 200);
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_profile_id', 'is_active', 'sort_order'], 'business_faqs_profile_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_faqs');
    }
};
