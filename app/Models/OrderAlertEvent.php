<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log liviano de eventos "sonoros" del panel de Pedidos (pago enviado,
 * factura elegida, solicitud de asesor) -- ver la migración
 * create_order_alert_events_table para el porqué existe aparte del cursor
 * de pedidos nuevos.
 */
class OrderAlertEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_PAYMENT_PROOF = 'payment_proof';

    public const TYPE_INVOICE_CONFIRMED = 'invoice_confirmed';

    public const TYPE_AGENT_REQUEST = 'agent_request';

    protected $fillable = [
        'business_profile_id',
        'whatsapp_cart_id',
        'event_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function cart()
    {
        return $this->belongsTo(WhatsappCart::class, 'whatsapp_cart_id');
    }
}
