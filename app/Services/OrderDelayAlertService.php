<?php

namespace App\Services;

use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Detecta pedidos de delivery que quedaron esperando que caja confirme el
 * costo de envío (porque no se pudo calcular solo con la tabla de tramos
 * km->$) por más de $thresholdMinutes, y actúa de forma proactiva en vez de
 * dejar al cliente sin novedades: le avisa que seguimos procesando su
 * pedido, y le avisa al equipo (WhatsApp a los números de despacho + contador
 * visible en el panel admin, ver AppServiceProvider) para que no se quede
 * "perdido". Pensado para correr cada pocos minutos (ver
 * App\Console\Commands\AlertDelayedFulfillmentCosts).
 */
class OrderDelayAlertService
{
    public const DEFAULT_THRESHOLD_MINUTES = 15;

    /** Non-terminal: un pedido ya entregado/cancelado no puede seguir "demorado". */
    private const RELEVANT_STATUSES = [
        WhatsappCart::STATUS_PENDING,
        WhatsappCart::STATUS_CONFIRMED,
        WhatsappCart::STATUS_PAYMENT_PENDING,
        WhatsappCart::STATUS_PAID,
        WhatsappCart::STATUS_PREPARING,
    ];

    public function __construct(private WhatsappService $whatsapp) {}

    /** Para el badge del panel admin (ver AppServiceProvider) -- solo cuenta, no envía nada. */
    public function countOverdueForCompany(int $businessProfileId, int $thresholdMinutes = self::DEFAULT_THRESHOLD_MINUTES): int
    {
        $cutoff = now()->subMinutes($thresholdMinutes);

        return WhatsappCart::query()
            ->whereIn('status', self::RELEVANT_STATUSES)
            ->whereHas('contact', fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->get()
            ->filter(function (WhatsappCart $cart) use ($cutoff) {
                if (! $cart->hasPendingFulfillmentCosts()) {
                    return false;
                }
                $since = $cart->pendingFulfillmentSince();

                return $since && $since->lte($cutoff);
            })
            ->count();
    }

    /** @return int Cantidad de pedidos demorados sobre los que se avisó (cliente y/o staff). */
    public function alertOverdue(int $thresholdMinutes = self::DEFAULT_THRESHOLD_MINUTES): int
    {
        $cutoff = now()->subMinutes($thresholdMinutes);
        $alerted = 0;

        $carts = WhatsappCart::query()
            ->whereIn('status', self::RELEVANT_STATUSES)
            ->with(['contact.businessProfile'])
            ->get();

        foreach ($carts as $cart) {
            // Se revalida en vivo (no solo se confía en el query inicial):
            // el estado pudo haber cambiado entre que se armó la colección y
            // que le toca el turno a este carrito.
            if (! $cart->hasPendingFulfillmentCosts()) {
                continue;
            }

            $since = $cart->pendingFulfillmentSince();
            if (! $since || $since->gt($cutoff)) {
                continue;
            }

            $contact = $cart->contact;
            $profile = $contact?->businessProfile;
            if (! $contact || ! $profile) {
                continue;
            }

            // Una alerta por cada vez que un pedido queda "atascado" en este
            // punto -- si se resuelve y por algún motivo vuelve a quedar
            // pendiente después, el timestamp cambia y se vuelve a avisar.
            $dedupeKey = 'order-delay-alerted:'.$cart->id.':'.$since->timestamp;
            if (Cache::has($dedupeKey)) {
                continue;
            }

            try {
                $this->whatsapp->useBusinessProfile($profile);
            } catch (\Throwable $e) {
                Log::warning('[OrderDelayAlert] No se pudo resolver el perfil de WhatsApp del pedido', [
                    'cart_id' => $cart->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $this->notifyCustomer($cart, $contact);
            $this->notifyStaff($cart, $contact, $profile->id);

            Cache::put($dedupeKey, true, now()->addDay());
            $alerted++;
        }

        return $alerted;
    }

    private function notifyCustomer(WhatsappCart $cart, $contact): void
    {
        if (! $contact->phone_number || str_starts_with($contact->phone_number, 'POS-')) {
            return;
        }

        $this->whatsapp->sendBotPayload($contact, [
            'type' => 'text',
            'text' => ['body' =>
                "🙏 Seguimos procesando tu pedido *{$cart->getOrderNumber()}*.\n\n"
                .'Ya le avisamos a nuestro equipo para confirmarte el costo final lo antes posible. Gracias por tu paciencia.',
            ],
        ]);
    }

    private function notifyStaff(WhatsappCart $cart, $contact, int $businessProfileId): void
    {
        $numbers = WhatsappChatbotConfig::where('business_profile_id', $businessProfileId)
            ->first()?->delivery_dispatch_numbers ?? [];

        if ($numbers === []) {
            return;
        }

        $clientLabel = $contact->name ?: $contact->phone_number;
        $body = "⏰ *Pedido demorado*\n\n"
            ."📦 Pedido: {$cart->getOrderNumber()}\n"
            ."👤 Cliente: {$clientLabel}\n"
            .'⌛ Esperando confirmación de costo hace más de '.self::DEFAULT_THRESHOLD_MINUTES." minutos.\n\n"
            .'Revísalo en el panel de Pedidos.';

        foreach ($numbers as $number) {
            $this->whatsapp->sendStaffAlert($number, $body);
        }
    }
}
