<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->nullable()
                ->constrained('whatsapp_business_profiles')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()
                ->constrained('whatsapp_contacts')->nullOnDelete();
            // Se guarda aparte del contacto: si no lo encontramos (webhook con
            // datos raros, contacto borrado, etc.) igual sabemos a quién no
            // le llegó el mensaje.
            $table->string('phone_number')->nullable();
            $table->string('message_type', 40);
            // Qué método/acción intentaba enviar (sendTextMessage, recordatorio
            // de comprobante, etc.) — para ubicar rápido en qué parte del bot
            // pasó, sin tener que interpretar el error a ciegas.
            $table->string('source', 80)->nullable();
            $table->text('error_message');
            $table->json('context')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['resolved_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_failures');
    }
};
