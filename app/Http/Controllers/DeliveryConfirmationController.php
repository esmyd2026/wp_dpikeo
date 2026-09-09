<?php

namespace App\Http\Controllers;

use App\Services\DeliveryConfirmationService;
use App\Services\OrderLifecycleService;
use App\Services\ProductImageService;
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
        ]);
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
