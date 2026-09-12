<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedido explícito en vivo: reescribir el "ticket de confirmación de pedido"
 * (el primer mensaje con botones que ve el cliente al armar su pedido desde
 * "Armar lista"/micrositio) para que muestre envío, entrega y total antes
 * del link al PDF -- hoy ese mensaje no incluye ninguno de los tres. Es una
 * tabla global (sin business_profile_id), así que el cambio aplica a todas
 * las empresas por igual; el texto es genérico, sin datos de ninguna
 * empresa en particular.
 */
return new class extends Migration
{
    private const KEY = 'order_confirmation_ticket';

    private const NEW_BODY = "📋 *¡Tu pedido está casi listo!*\n\n📦 Pedido {{order_number}}\n\n{{items_list}}{{shipping_line}}💰 *Total a pagar:* \${{total}}\n\n{{fulfillment}}📄 *Ver detalle del pedido:*\n{{pdf_url}}\n\n{{note_line}}{{agent_note_line}}Revisa que todo esté correcto y elige una opción para continuar. 👇";

    private const NEW_PLACEHOLDERS = ['order_number', 'total', 'pdf_url', 'items_list', 'shipping_line', 'fulfillment', 'note_line', 'agent_note_line'];

    private const OLD_BODY = "📋 *Confirma tu pedido*\n\n📦 *Número:* {{order_number}}\n💰 *Total:* \${{total}}\n📄 *PDF:* {{pdf_url}}\n\n{{items_list}}{{note_line}}{{agent_note_line}}Revisa el PDF y elige una opción:";

    private const OLD_PLACEHOLDERS = ['order_number', 'total', 'pdf_url', 'items_list', 'note_line', 'agent_note_line'];

    public function up(): void
    {
        DB::table('message_templates')->where('key', self::KEY)->update([
            'body' => self::NEW_BODY,
            'placeholders' => json_encode(self::NEW_PLACEHOLDERS),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', self::KEY)->update([
            'body' => self::OLD_BODY,
            'placeholders' => json_encode(self::OLD_PLACEHOLDERS),
            'updated_at' => now(),
        ]);
    }
};
