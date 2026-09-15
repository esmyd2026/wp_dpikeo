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
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            // Distinto de bot_enabled (pausa del día a día): este contacto
            // nunca debe reactivarse solo -- ni por el job diario que
            // reactiva a todo el mundo, ni por accidente. Solo se quita a
            // mano, desde el panel de Clientes o desde la conversación.
            $table->boolean('bot_blacklisted')->default(false)->after('bot_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropColumn('bot_blacklisted');
        });
    }
};
