<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Actualizar mensajes existentes que tienen human_sent en metadata a sender_type = 'humano'
        DB::table('whatsapp_messages')
            ->where('sender_type', 'system')
            ->whereNotNull('metadata')
            ->whereRaw("JSON_EXTRACT(metadata, '$.human_sent') = true")
            ->update(['sender_type' => 'humano']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir: cambiar 'humano' de vuelta a 'system'
        DB::table('whatsapp_messages')
            ->where('sender_type', 'humano')
            ->update(['sender_type' => 'system']);
    }
};
