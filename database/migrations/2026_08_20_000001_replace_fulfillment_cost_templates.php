<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Antes había dos plantillas separadas (costo de envío / costo para llevar)
 * que mandaban dos mensajes distintos con dos totales distintos -- ver
 * OrderLifecycleService::sendFulfillmentCostsMessage(). Se reemplazan por
 * una sola plantilla que cubre ambos costos en un único mensaje.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('message_templates')->whereIn('key', ['delivery_cost_confirmed', 'pickup_fee_confirmed'])->delete();

        DB::table('message_templates')->insert([
            'key' => 'fulfillment_costs_confirmed',
            'name' => 'Costos adicionales confirmados (envío / para llevar)',
            'body' => "💰 *Costo adicional confirmado*\n\n{{address_line}}{{recipient_line}}{{delivery_line}}{{pickup_line}}💵 *Total final del pedido:* \${{total}}",
            'placeholders' => json_encode(['address_line', 'recipient_line', 'delivery_line', 'pickup_line', 'total']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', 'fulfillment_costs_confirmed')->delete();

        DB::table('message_templates')->insert([
            [
                'key' => 'delivery_cost_confirmed',
                'name' => 'Costo de envío confirmado',
                'body' => "🛵 *Costo de envío confirmado*\n\n{{address_line}}{{recipient_line}}💵 *Costo de envío:* \${{fee}}\n💰 *Total del pedido:* \${{total}}",
                'placeholders' => json_encode(['address_line', 'recipient_line', 'fee', 'total']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'pickup_fee_confirmed',
                'name' => 'Costo adicional para llevar confirmado',
                'body' => "🥡 *Costo adicional confirmado*\n\n💵 *Costo adicional:* \${{fee}}\n💰 *Total del pedido:* \${{total}}",
                'placeholders' => json_encode(['fee', 'total']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
};
