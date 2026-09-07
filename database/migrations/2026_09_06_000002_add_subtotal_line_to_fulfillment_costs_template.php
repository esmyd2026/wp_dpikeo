<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sin el subtotal de productos, el cliente veía saltar de "Costo de envío"/
 * "Costo para llevar" directo al total final, sin poder ver de dónde salía
 * el resto del monto.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('message_templates')
            ->where('key', 'fulfillment_costs_confirmed')
            ->update([
                'body' => "📦 Pedido *{{order_number}}*\n\n{{address_line}}{{recipient_line}}{{subtotal_line}}{{delivery_line}}{{pickup_line}}Total a pagar: \${{total}}{{bank_line}}",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('message_templates')
            ->where('key', 'fulfillment_costs_confirmed')
            ->update([
                'body' => "📦 Pedido *{{order_number}}*\n\n{{address_line}}{{recipient_line}}{{delivery_line}}{{pickup_line}}Total a pagar: \${{total}}{{bank_line}}",
                'updated_at' => now(),
            ]);
    }
};
