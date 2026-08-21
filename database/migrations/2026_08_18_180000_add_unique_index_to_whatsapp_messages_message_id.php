<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * message_id no tenía restricción única en la base de datos: solo se
     * evitaba duplicarlo con un SELECT previo en la app, lo que dejaba una
     * ventana de condición de carrera (dos webhooks casi simultáneos para el
     * mismo wamid podían pasar ambos la verificación). Antes de imponer la
     * restricción se eliminan duplicados que hayan quedado de esa ventana,
     * conservando la fila más antigua de cada message_id.
     */
    public function up(): void
    {
        $duplicateIds = DB::table('whatsapp_messages')
            ->select('message_id')
            ->groupBy('message_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('message_id');

        foreach ($duplicateIds as $messageId) {
            $ids = DB::table('whatsapp_messages')
                ->where('message_id', $messageId)
                ->orderBy('id')
                ->pluck('id');

            DB::table('whatsapp_messages')
                ->whereIn('id', $ids->slice(1))
                ->delete();
        }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->unique('message_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropUnique(['message_id']);
        });
    }
};
