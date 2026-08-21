<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('message_templates')->insert([
            'key' => 'pickup_fee_confirmed',
            'name' => 'Costo adicional para llevar confirmado',
            'body' => "🥡 *Costo adicional confirmado*\n\n💵 *Costo adicional:* \${{fee}}\n💰 *Total del pedido:* \${{total}}",
            'placeholders' => json_encode(['fee', 'total']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', 'pickup_fee_confirmed')->delete();
    }
};
