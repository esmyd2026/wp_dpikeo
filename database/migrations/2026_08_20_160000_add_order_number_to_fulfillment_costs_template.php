<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reestructura la plantilla de costo confirmado: el número de pedido pasa
 * al inicio del mensaje (antes solo aparecía repetido más abajo, en el
 * bloque de "Comprobante de pago" que ya se eliminó de este flujo -- ver
 * WhatsappService::maybeRequestPaymentProofAfterCosts), y "Total final del
 * pedido" pasa a llamarse "Total a pagar".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('message_templates')
            ->where('key', 'fulfillment_costs_confirmed')
            ->update([
                'body' => "📦 Pedido *{{order_number}}*\n\n💰 *Costo adicional confirmado*\n\n{{address_line}}{{recipient_line}}{{delivery_line}}{{pickup_line}}Total a pagar: \${{total}}{{bank_line}}",
                'placeholders' => json_encode([
                    'order_number', 'address_line', 'recipient_line', 'delivery_line', 'pickup_line', 'total', 'bank_line',
                ]),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
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
};
