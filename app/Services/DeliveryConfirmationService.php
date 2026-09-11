<?php

namespace App\Services;

use App\Models\DeliveryConfirmationToken;
use App\Models\DeliveryDriver;
use App\Models\WhatsappCart;
use Illuminate\Http\UploadedFile;

/**
 * Deja que el repartidor confirme la entrega él mismo desde su celular, con
 * un link público (sin usuario del panel) que se le manda por WhatsApp al
 * despacharlo -- pedido explícito porque hoy solo se sabe que un pedido
 * "llegó" si alguien entra al panel a marcarlo manualmente.
 */
class DeliveryConfirmationService
{
    private const TOKEN_LIFETIME_HOURS = 72;

    public function findToken(string $token): ?DeliveryConfirmationToken
    {
        return DeliveryConfirmationToken::where('token', $token)->first();
    }

    /** Reutiliza un token vigente del pedido si ya existe, o crea uno nuevo. */
    public function urlFor(WhatsappCart $order): string
    {
        $record = DeliveryConfirmationToken::query()
            ->where('whatsapp_cart_id', $order->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        $record ??= DeliveryConfirmationToken::create([
            'whatsapp_cart_id' => $order->id,
            'token' => DeliveryConfirmationToken::generateToken(),
            'expires_at' => now()->addHours(self::TOKEN_LIFETIME_HOURS),
        ]);

        return route('delivery-confirmation.show', ['token' => $record->token]);
    }

    /**
     * @param int|string $confirmedBy id del usuario del panel, o 'driver'
     *                                cuando lo confirma el repartidor desde
     *                                el link público (sin sesión de panel).
     */
    public function confirm(WhatsappCart $order, UploadedFile $photo, ?string $note, int|string $confirmedBy, ProductImageService $images, OrderLifecycleService $lifecycle): WhatsappCart
    {
        $path = $images->store($photo, null, 'delivery-proofs');

        $metadata = $order->metadata ?? [];
        $metadata['delivery_proof'] = [
            'photo_path' => $path,
            'note' => $note,
            'confirmed_by' => $confirmedBy,
            'confirmed_at' => now()->toIso8601String(),
        ];
        $order->metadata = $metadata;
        $order->save();

        return $lifecycle->transition($order, WhatsappCart::STATUS_COMPLETED, is_int($confirmedBy) ? $confirmedBy : null);
    }

    /**
     * Avisa al cliente que su pedido va en camino, con el contacto del
     * repartidor. Pedido explícito en vivo: se puede accionar tanto desde
     * el enlace público del repartidor como desde el panel (el operador),
     * pero solo debe pasar una vez -- ver el guardián en
     * WhatsappService::notifyCustomerOrderOnTheWay().
     *
     * @param int|string $notifiedBy id del usuario del panel, o 'driver'
     *                                cuando lo acciona el repartidor desde
     *                                el link público.
     * @return array{sent: bool, reason: ?string}
     */
    public function notifyOnTheWay(WhatsappCart $order, int|string $notifiedBy, WhatsappService $whatsapp): array
    {
        $order->loadMissing('contact.businessProfile');
        $contact = $order->contact;
        if (! $contact?->businessProfile) {
            return ['sent' => false, 'reason' => 'no_business_profile'];
        }

        $driver = DeliveryDriver::find($order->metadata['last_dispatch_driver_id'] ?? null);
        if (! $driver) {
            return ['sent' => false, 'reason' => 'no_driver_assigned'];
        }

        $whatsapp->useBusinessProfile($contact->businessProfile);

        return $whatsapp->notifyCustomerOrderOnTheWay($order, $driver, $notifiedBy);
    }
}
