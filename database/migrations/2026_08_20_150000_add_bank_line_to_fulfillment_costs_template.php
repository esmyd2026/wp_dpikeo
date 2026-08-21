<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega el placeholder {{bank_line}} al mensaje de costo confirmado: si el
 * pedido se paga por transferencia/depósito y el negocio configuró sus datos
 * bancarios (panel > Configuración del bot), el cliente los recibe junto con
 * el monto final en vez de un mensaje que no le dice a dónde pagar.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('message_templates')
            ->where('key', 'fulfillment_costs_confirmed')
            ->update([
                'body' => "💰 *Costo adicional confirmado*\n\n{{address_line}}{{recipient_line}}{{delivery_line}}{{pickup_line}}Total final del pedido: \${{total}}{{bank_line}}",
                'placeholders' => json_encode([
                    'address_line', 'recipient_line', 'delivery_line', 'pickup_line', 'total', 'bank_line',
                ]),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('message_templates')
            ->where('key', 'fulfillment_costs_confirmed')
            ->update([
                'body' => "💰 *Costo adicional confirmado*\n\n{{address_line}}{{recipient_line}}{{delivery_line}}{{pickup_line}}Total final del pedido: \${{total}}",
                'placeholders' => json_encode([
                    'address_line', 'recipient_line', 'delivery_line', 'pickup_line', 'total',
                ]),
                'updated_at' => now(),
            ]);
    }
};
