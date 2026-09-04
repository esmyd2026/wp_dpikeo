<?php

namespace App\Services;

use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pedidos que el cliente dejó a medias (eligió productos, o incluso el
 * método de pago, pero nunca confirmó o nunca mandó el comprobante) se
 * quedan indefinidamente en estado "active"/"payment_pending". Mientras
 * siguen así, WhatsappService::addToCart reutiliza ese mismo carrito para
 * cualquier pedido nuevo que el cliente intente después -- sumando ambos
 * pedidos bajo la fecha del primero. Este servicio cierra esos carritos
 * (automático por timeout, o manual desde el panel de chat) para que el
 * cliente arranque limpio la próxima vez.
 */
class AbandonedCartService
{
    private const STALE_STATUSES = ['active', WhatsappCart::STATUS_PAYMENT_PENDING];

    /**
     * Minutos configurados por el admin para considerar un pedido
     * abandonado, o null si la función está desactivada (valor por defecto).
     */
    public function timeoutMinutes(): ?int
    {
        $config = WhatsappChatbotConfig::first();
        $minutes = (int) ($config?->metadata['abandoned_cart_timeout_minutes'] ?? 0);

        return $minutes > 0 ? $minutes : null;
    }

    /**
     * Cancela los carritos que llevan más tiempo sin actividad que el límite
     * configurado y avisa al cliente. No hace nada si no hay límite
     * configurado. Devuelve cuántos carritos cerró.
     */
    public function cancelTimedOut(): int
    {
        $minutes = $this->timeoutMinutes();
        if (!$minutes) {
            return 0;
        }

        $staleCarts = WhatsappCart::query()
            ->whereIn('status', self::STALE_STATUSES)
            ->where('updated_at', '<', now()->subMinutes($minutes))
            ->with('contact')
            ->get();

        $count = 0;
        foreach ($staleCarts as $cart) {
            if ($this->close($cart, 'auto_timeout')) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Cancela un carrito abandonado/atascado y reinicia la posición del
     * cliente en el flujo, para que su próximo mensaje empiece limpio. Se
     * usa tanto desde el comando automático como desde el botón manual del
     * panel de chat. No hace nada (devuelve false) si el carrito ya no está
     * en un estado "cancelable de esta forma" (ej. ya confirmado/pagado: eso
     * se cancela desde el módulo de Pedidos, no desde aquí).
     */
    public function close(WhatsappCart $cart, string $reason = 'manual_admin_reset'): bool
    {
        if (!in_array($cart->status, self::STALE_STATUSES, true)) {
            return false;
        }

        $contact = $cart->contact;

        try {
            app(OrderLifecycleService::class)->transition($cart, WhatsappCart::STATUS_CANCELLED, null, $reason);
        } catch (Throwable $e) {
            Log::error('[AbandonedCartService] No se pudo cancelar el carrito', [
                'cart_id' => $cart->id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($contact) {
            $contact->forgetFlowPosition();
            $contact->forgetPrivacyNoticeSent();

            try {
                $this->notifyContact($contact, $reason);
            } catch (Throwable $e) {
                // El carrito ya se canceló arriba (transition() no depende de
                // esto); que falle solo el aviso por WhatsApp no debe abortar
                // el resto del lote en cancelTimedOut().
                Log::error('[AbandonedCartService] No se pudo avisar al cliente del cierre del carrito', [
                    'cart_id' => $cart->id,
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('[AbandonedCartService] Carrito cerrado', [
            'cart_id' => $cart->id,
            'contact_id' => $contact?->id,
            'reason' => $reason,
        ]);

        return true;
    }

    private function notifyContact(WhatsappContact $contact, string $reason): void
    {
        if (!$contact->phone_number || str_starts_with($contact->phone_number, 'POS-')) {
            return;
        }

        $body = $reason === 'auto_timeout'
            ? "🕐 Parece que no continuarás con esta orden, así que la cerramos por ahora. Cuando quieras puedes generar un pedido nuevo escribiéndonos por aquí."
            : "🔄 Reiniciamos tu conversación con nosotros. Cuando quieras puedes generar un pedido nuevo escribiéndonos por aquí.";

        $whatsapp = app(WhatsappService::class);
        $whatsapp->useBusinessProfile($contact->businessProfile);
        $whatsapp->sendBotPayload($contact, [
            'type' => 'text',
            'text' => ['body' => $body],
        ]);
    }
}
