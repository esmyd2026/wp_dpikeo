<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido explícito: la pantalla de Pedidos debía sonar/actualizarse ante
 * CUALQUIER cambio importante del cliente (pago enviado, factura elegida,
 * pedido de asesor), no solo pedidos nuevos. El sondeo de pedidos nuevos
 * (AdminController::pollNewOrders) usa un cursor por id de WhatsappCart, que
 * solo detecta filas nuevas -- un cambio de metadata en un pedido YA
 * conocido nunca vuelve a aparecer ahí. Esta tabla es un log liviano de esos
 * eventos, con su propio cursor, para poder sondearlos igual que los
 * pedidos nuevos sin tocar esa lógica que ya funciona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_alert_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_profile_id');
            $table->unsignedBigInteger('whatsapp_cart_id');
            $table->string('event_type', 40);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_profile_id', 'id']);
            $table->foreign('whatsapp_cart_id')->references('id')->on('whatsapp_carts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_alert_events');
    }
};
