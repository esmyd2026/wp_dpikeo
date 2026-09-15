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
        Schema::table('chatbot_keyword_replies', function (Blueprint $table) {
            // Pedido explícito: clientes que compraron en la plataforma web
            // externa (dpikeos.ec, ecommerce aparte del bot/micrositio) y
            // escriben pidiendo ayuda con esa orden -- se les responde el
            // texto fijo (sin mandarles el menú) y, si se marca esta opción,
            // además se apaga el bot para ESE cliente (mismo campo que ya usa
            // el toggle individual de "Conversaciones") para que el equipo lo
            // atienda a mano en vez de que el bot le siga respondiendo.
            $table->boolean('disable_bot_after_reply')->default(false)->after('response_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chatbot_keyword_replies', function (Blueprint $table) {
            $table->dropColumn('disable_bot_after_reply');
        });
    }
};
