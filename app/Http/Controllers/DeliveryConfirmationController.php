<?php

namespace App\Http\Controllers;

use App\Services\DeliveryConfirmationService;
use App\Services\OrderLifecycleService;
use App\Services\ProductImageService;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Confirmación de entrega por el repartidor, sin usuario del panel: entra
 * por un link público que se le manda por WhatsApp al despacharlo (ver
 * DeliveryConfirmationService::urlFor()).
 */
class DeliveryConfirmationController extends Controller
{
    public function show(string $token, DeliveryConfirmationService $confirmations): View
    {
        $record = $confirmations->findToken($token);
        abort_unless($record, 404);

        $order = $record->cart()->with('branch')->first();
        abort_unless($order, 404);

        $metadata = $order->metadata ?? [];

        return view('delivery-confirmation.show', [
            'state' => match (true) {
                $record->used_at !== null => 'used',
                ! $record->isValid() => 'expired',
                default => 'form',
            },
            'orderNumber' => $order->getOrderNumber(),
            'branchName' => $order->branch?->name,
            'recipientName' => $metadata['delivery_recipient_name'] ?? $order->contact?->name ?: 'Cliente',
            'address' => $metadata['delivery_location']['manual_address'] ?? null,
            'paymentLabel' => match ($order->payment_method) {
                'efectivo' => 'Efectivo — cobrar $'.number_format((float) $order->total, 2).' al entregar',
                'transferencia' => 'Transferencia o depósito (ya pagado, no cobrar)',
                'tarjeta' => 'Tarjeta (ya pagado, no cobrar)',
                default => 'No especificado',
            },
            'submitUrl' => route('delivery-confirmation.confirm', ['token' => $token]),
            'onTheWayUrl' => route('delivery-confirmation.on-the-way', ['token' => $token]),
            'onTheWayNotifiedAt' => $metadata['on_the_way_notified_at'] ?? null,
        ]);
    }

    /**
     * Pedido explícito en vivo: el repartidor (o el operador desde el
     * panel, ver DeliveryController::notifyCustomerOnTheWay) puede avisarle
     * al cliente que su pedido va en camino -- una sola vez, la guarda
     * WhatsappService::notifyCustomerOrderOnTheWay() vía metadata. No marca
     * el token como usado: el repartidor todavía tiene que confirmar la
     * entrega con foto después.
     */
    public function notifyOnTheWay(string $token, DeliveryConfirmationService $confirmations, WhatsappService $whatsapp): JsonResponse
    {
        $record = $confirmations->findToken($token);
        abort_unless($record, 404);

        if (! $record->isValid()) {
            return response()->json(['ok' => false, 'message' => 'Este link ya no es válido.'], 422);
        }

        $order = $record->cart;
        abort_unless($order, 404);

        $result = $confirmations->notifyOnTheWay($order, 'driver', $whatsapp);

        if ($result['reason'] === 'already_notified') {
            return response()->json(['ok' => true, 'already' => true, 'message' => 'Ya se le había avisado al cliente antes.']);
        }

        if (! $result['sent']) {
            return response()->json(['ok' => false, 'message' => match ($result['reason']) {
                'notification_disabled' => 'Este aviso está desactivado en la configuración del negocio.',
                'no_phone' => 'El cliente no tiene un número de WhatsApp válido registrado.',
                'window_closed' => 'Pasaron más de 24 h desde el último mensaje del cliente; WhatsApp ya no permite enviarle un mensaje libre.',
                'no_driver_assigned' => 'No hay un repartidor asignado a este pedido todavía.',
                default => 'No se pudo avisar al cliente.',
            }], 422);
        }

        return response()->json(['ok' => true, 'already' => false, 'message' => 'Se le avisó al cliente que su pedido va en camino.']);
    }

    public function confirm(string $token, Request $request, DeliveryConfirmationService $confirmations, ProductImageService $images, OrderLifecycleService $lifecycle): JsonResponse
    {
        $record = $confirmations->findToken($token);
        abort_unless($record, 404);

        if (! $record->isValid()) {
            return response()->json(['ok' => false, 'message' => 'Este link ya no es válido.'], 422);
        }

        $order = $record->cart;
        if (! $order || ($order->metadata['pickup_mode'] ?? null) !== 'delivery') {
            return response()->json(['ok' => false, 'message' => 'Este pedido no es de delivery.'], 422);
        }

        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            // PHP puede rechazar el archivo antes de que llegue a la regla
            // "max" (por upload_max_filesize). Evitamos mostrarle al
            // repartidor el mensaje técnico en inglés que trae Laravel.
            'photo.required' => 'Debes seleccionar una foto de la entrega.',
            'photo.uploaded' => 'La foto no pudo subir al servidor. Intenta tomarla nuevamente o selecciona una imagen más liviana.',
            'photo.image' => 'El archivo seleccionado no es una imagen válida.',
            'photo.mimes' => 'La foto debe estar en formato JPG, PNG o WEBP.',
            'photo.max' => 'La foto es demasiado pesada. El máximo permitido es 8 MB.',
            'note.max' => 'La nota no puede superar los 500 caracteres.',
        ]);

        try {
            $confirmations->confirm($order, $request->file('photo'), $validated['note'] ?? null, 'driver', $images, $lifecycle);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => 'No se pudo confirmar: '.$e->getMessage()], 422);
        }

        $record->markUsed();

        return response()->json(['ok' => true, 'message' => 'Entrega confirmada. ¡Gracias!']);
    }
}
