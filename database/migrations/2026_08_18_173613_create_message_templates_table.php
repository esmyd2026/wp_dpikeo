<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('body');
            $table->json('placeholders')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('message_templates')->insert([
            [
                'key' => 'order_status_changed',
                'name' => 'Cambio de estado del pedido',
                'body' => "📦 Tu pedido *{{order_number}}* cambió de estado:\n\n*{{status_label}}*",
                'placeholders' => json_encode(['order_number', 'status_label']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'delivery_cost_confirmed',
                'name' => 'Costo de envío confirmado',
                'body' => "🛵 *Costo de envío confirmado*\n\n{{address_line}}{{recipient_line}}💵 *Costo de envío:* \${{fee}}\n💰 *Total del pedido:* \${{total}}",
                'placeholders' => json_encode(['address_line', 'recipient_line', 'fee', 'total']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'bulk_order_submitted',
                'name' => 'Pedido registrado desde el catálogo web',
                'body' => "✅ *Pedido registrado*\n\n📦 *Número de pedido:* {{order_number}}\n💰 *Total:* \${{total}}\n\nGuarda este número para consultar el estado. Te contactaremos si hace falta algo más.",
                'placeholders' => json_encode(['order_number', 'total']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'order_confirmation_ticket',
                'name' => 'Ticket de confirmación de pedido (con botones)',
                'body' => "📋 *Confirma tu pedido*\n\n📦 *Número:* {{order_number}}\n💰 *Total:* \${{total}}\n📄 *PDF:* {{pdf_url}}\n\n{{items_list}}{{note_line}}{{agent_note_line}}Revisa el PDF y elige una opción:",
                'placeholders' => json_encode(['order_number', 'total', 'pdf_url', 'items_list', 'note_line', 'agent_note_line']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
