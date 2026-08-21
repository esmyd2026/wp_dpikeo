<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mensaje que el cliente recibe cuando se despacha su pedido a un
 * repartidor (ver WhatsappService::notifyCustomerOrderOnTheWay). Antes
 * estaba fijo en el código; se vuelve editable como el resto de mensajes
 * automáticos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('message_templates')->updateOrInsert(
            ['key' => 'order_on_the_way'],
            [
                'name' => 'Pedido en camino (se despachó a un repartidor)',
                'body' => "🛵 *¡Tu pedido va en camino!*\n\nPedido *{{order_number}}*\n\nTe compartimos el contacto de tu repartidor, *{{driver_name}}*, por si necesitas comunicarte con él.\n\n¡Esperamos que disfrutes tu compra! 🎉😋",
                'placeholders' => json_encode(['order_number', 'driver_name']),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', 'order_on_the_way')->delete();
    }
};
