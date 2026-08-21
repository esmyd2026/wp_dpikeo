<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quita el encabezado "💰 Costo adicional confirmado" del mensaje: ya sobra
 * con el número de pedido arriba y el detalle de costos debajo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('message_templates')
            ->where('key', 'fulfillment_costs_confirmed')
            ->update([
                'body' => "📦 Pedido *{{order_number}}*\n\n{{address_line}}{{recipient_line}}{{delivery_line}}{{pickup_line}}Total a pagar: \${{total}}{{bank_line}}",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('message_templates')
            ->where('key', 'fulfillment_costs_confirmed')
            ->update([
                'body' => "📦 Pedido *{{order_number}}*\n\n💰 *Costo adicional confirmado*\n\n{{address_line}}{{recipient_line}}{{delivery_line}}{{pickup_line}}Total a pagar: \${{total}}{{bank_line}}",
                'updated_at' => now(),
            ]);
    }
};
