<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Interruptor general del bot para TODOS los números de la
            // empresa a la vez, por encima del interruptor por número
            // (WhatsappChatbotConfig->is_active). Se guarda aparte de
            // "status" a propósito: "status" gobierna si la cuenta existe/
            // funciona en absoluto (incluye el micrositio), mientras que
            // este campo solo pausa el bot temporalmente sin tocar nada más.
            $table->boolean('bot_enabled')->default(true)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('bot_enabled');
        });
    }
};
