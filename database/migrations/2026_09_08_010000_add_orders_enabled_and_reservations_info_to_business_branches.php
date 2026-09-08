<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido explícito: mostrar en el bot ("Información") TODAS las sucursales
 * (dirección, teléfono, horarios, reservas), aunque no todas reciban
 * pedidos por WhatsApp -- hasta ahora `is_active` controlaba las dos cosas
 * a la vez. Se agrega `orders_enabled` para desacoplar "aparece en el bot
 * de información" (is_active) de "se puede elegir para pedidos/delivery"
 * (orders_enabled). Default true para no desactivar pedidos en sucursales
 * ya existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_branches', function (Blueprint $table) {
            $table->boolean('orders_enabled')->default(true)->after('is_active');
            $table->text('reservations_info')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('business_branches', function (Blueprint $table) {
            $table->dropColumn(['orders_enabled', 'reservations_info']);
        });
    }
};
