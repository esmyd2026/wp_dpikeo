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
 *
 * El timeout automático solo aplica cuando el que quedó a medias es el
 * cliente -- ver WhatsappCart::isWaitingOnBusiness(). Un pedido en
 * "payment_pending" donde ya se mandó el comprobante (falta que caja lo
 * verifique) o donde falta confirmar el costo de envío no se cancela por
 * aquí ni le manda al cliente el aviso de "no continuarás": el cliente ya
 * hizo su parte y la demora es nuestra.
 */
class AbandonedCartService
{
    private const STALE_STATUSES = ['active', WhatsappCart::STATUS_PAYMENT_PENDING];

    /**
     * Minutos configurados por el admin para considerar un pedido
     * abandonado, o null si la función está desactivada (valor por defecto).
     * Cada empresa tiene su propia configuración -- sin $businessProfileId
     * (uso legacy/sin tenant resoluble) cae al primer registro global como
     * único fallback razonable.
     */
    public function timeoutMinutes(?int $businessProfileId = null): ?int
    {
        $config = $businessProfileId
            ? WhatsappChatbotConfig::where('business_profile_id', $businessProfileId)->first()
            : WhatsappChatbotConfig::first();

        $minutes = (int) ($config?->metadata['abandoned_cart_timeout_minutes'] ?? 0);

        return $minutes > 0 ? $minutes : null;
    }

    /**
     * Cancela los carritos que llevan más tiempo sin actividad que el límite
     * configurado y avisa al cliente. Cada carrito se mide contra el límite
     * de SU PROPIA empresa (ver timeoutMinutes()) -- antes se usaba un único
     * límite global (el de la primera empresa de la tabla) para todos los
     * carritos de toda la plataforma, así que una empresa sin este límite
     * configurado podía desactivarlo sin querer para las demás, y una
     * empresa con un límite distinto nunca lo veía aplicado. Devuelve
     * cuántos carritos cerró.
     */
    public function cancelTimedOut(): int
    {
        $staleCarts = WhatsappCart::query()
            ->whereIn('status', self::STALE_STATUSES)
            ->with('contact')
            ->get();

        $count = 0;
        foreach ($staleCarts as $cart) {
            $minutes = $this->timeoutMinutes($cart->contact?->business_profile_id);
            if (! $minutes) {
                continue;
            }

            // Si lo que falta depende del negocio (costo de envío sin
            // confirmar, o comprobante ya recibido y sin verificar), el
            // cliente ya hizo su parte -- cancelarlo y decirle "parece que
            // no continuarás" le echa la culpa de una demora que es nuestra.
            // Ese pedido lo cierra el módulo de Pedidos, no este timeout.
            if ($cart->isWaitingOnBusiness()) {
                continue;
            }

            if ($cart->updated_at && $cart->updated_at->gt(now()->subMinutes($minutes))) {
                continue;
            }

            if ($this->close($cart, WhatsappCart::CANCEL_REASON_TIMEOUT)) {
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
    public function close(WhatsappCart $cart, string $reason = WhatsappCart::CANCEL_REASON_OPERATOR_RESET): bool
    {
        if (! in_array($cart->status, self::STALE_STATUSES, true)) {
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
            $this->clearLingeringInteractionFlags($contact);

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

    /**
     * Bug real reportado en vivo: reiniciar la conversación cancela el
     * carrito "active"/"payment_pending" del contacto, pero un pedido ya
     * armado por "Armar lista" queda en "pending" (fuera de STALE_STATUSES a
     * propósito -- ya es un pedido real, no se cancela solo por reiniciar el
     * chat) y podía quedar con una bandera de "esperando dirección" o
     * "esperando nombre de quien recibe" sin borrar. Con esa bandera viva,
     * el próximo mensaje del cliente -- aunque fuera un simple "hola" --
     * se interpretaba como la respuesta a esa pregunta vieja en vez de
     * pasar por el saludo normal. Se limpian esas banderas de "esperando
     * texto" de TODOS los carritos no finalizados del contacto, sin tocar
     * su estado ni cancelarlos.
     */
    public function clearLingeringInteractionFlags(WhatsappContact $contact): void
    {
        $contactMetadata = $contact->metadata ?? [];
        if (array_key_exists('pending_custom_quantity', $contactMetadata)) {
            unset($contactMetadata['pending_custom_quantity']);
            $contact->metadata = $contactMetadata;
            $contact->save();
        }

        $flags = ['awaiting_delivery_address', 'awaiting_delivery_recipient_name', 'pending_note', 'pending_payment_method'];

        WhatsappCart::where('contact_id', $contact->id)
            ->whereNotIn('status', [WhatsappCart::STATUS_CANCELLED, WhatsappCart::STATUS_COMPLETED])
            ->get()
            ->each(function (WhatsappCart $cart) use ($flags) {
                $metadata = $cart->metadata ?? [];
                $changed = false;
                foreach ($flags as $flag) {
                    if (array_key_exists($flag, $metadata)) {
                        unset($metadata[$flag]);
                        $changed = true;
                    }
                }

                if ($changed) {
                    $cart->metadata = $metadata;
                    $cart->save();
                }
            });
    }

    private function notifyContact(WhatsappContact $contact, string $reason): void
    {
        if (! $contact->phone_number || str_starts_with($contact->phone_number, 'POS-')) {
            return;
        }

        $body = $reason === WhatsappCart::CANCEL_REASON_TIMEOUT
            ? '🕐 Parece que no continuarás con esta orden, así que la cerramos por ahora. Cuando quieras puedes generar un pedido nuevo escribiéndonos por aquí.'
            : '🔄 Reiniciamos tu conversación con nosotros. Cuando quieras puedes generar un pedido nuevo escribiéndonos por aquí.';

        $whatsapp = app(WhatsappService::class);
        $whatsapp->useBusinessProfile($contact->businessProfile);
        $whatsapp->sendBotPayload($contact, [
            'type' => 'text',
            'text' => ['body' => $body],
        ]);
    }
}
