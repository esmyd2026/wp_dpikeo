<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `whatsapp_messages` ya tenía un índice sobre contact_id solo (el que
 * genera automáticamente la FK) y otro sobre (admin_user_id, created_at),
 * pero ninguno cubre "los mensajes de un contacto ordenados por fecha" ni
 * "los mensajes de un contacto y tipo (client/system) en un rango de
 * fechas" -- exactamente lo que golpea el panel de chat en cada consulta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->index(['contact_id', 'sender_type', 'created_at'], 'whatsapp_messages_contact_sender_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('whatsapp_messages_contact_sender_created_idx');
        });
    }
};
