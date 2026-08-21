<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reduce los íconos por línea en la plantilla de costos confirmados (solo
 * queda uno en el encabezado), a pedido del negocio: se veía saturado.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('message_templates')
            ->where('key', 'fulfillment_costs_confirmed')
            ->update([
                'body' => "💰 *Costo adicional confirmado*\n\n{{address_line}}{{recipient_line}}{{delivery_line}}{{pickup_line}}Total final del pedido: \${{total}}",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('message_templates')
            ->where('key', 'fulfillment_costs_confirmed')
            ->update([
                'body' => "💰 *Costo adicional confirmado*\n\n{{address_line}}{{recipient_line}}{{delivery_line}}{{pickup_line}}💵 *Total final del pedido:* \${{total}}",
                'updated_at' => now(),
            ]);
    }
};
