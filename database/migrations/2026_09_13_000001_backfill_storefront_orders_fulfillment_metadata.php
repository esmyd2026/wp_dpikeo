<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migración de datos (no de esquema). El micrositio guardaba el tipo de
     * entrega con su propio vocabulario ('service_type' => 'pickup'/'delivery',
     * dirección en 'delivery.address') en vez del que ya usa el bot
     * ('service_type' => 'llevar' + 'pickup_mode' => 'retiro'/'delivery',
     * dirección en 'delivery_location.manual_address'). El panel de admin, el
     * PDF y los reportes solo reconocen el vocabulario del bot -- por eso
     * todo pedido hecho desde el micrositio aparecía como "Para servir" y sin
     * la dirección del cliente. Esto normaliza los pedidos ya guardados con
     * el vocabulario viejo; BulkOrderService::submitFromStorefront() ya
     * guarda los nuevos con el vocabulario correcto desde el inicio.
     */
    public function up(): void
    {
        DB::table('whatsapp_carts')
            ->select(['id', 'contact_id', 'metadata'])
            ->orderBy('id')
            ->chunkById(500, function ($orders) {
                foreach ($orders as $order) {
                    $metadata = json_decode($order->metadata ?? '', true);
                    if (! is_array($metadata)) {
                        continue;
                    }

                    $serviceType = $metadata['service_type'] ?? null;
                    if (! in_array($serviceType, ['pickup', 'delivery'], true)) {
                        continue;
                    }

                    $isDelivery = $serviceType === 'delivery';
                    $metadata['service_type'] = 'llevar';
                    $metadata['pickup_mode'] = $isDelivery ? 'delivery' : 'retiro';

                    $legacyDelivery = is_array($metadata['delivery'] ?? null) ? $metadata['delivery'] : [];
                    if ($isDelivery) {
                        if (empty($metadata['delivery_location']) && $legacyDelivery) {
                            $metadata['delivery_location'] = array_filter([
                                'latitude' => $legacyDelivery['latitude'] ?? null,
                                'longitude' => $legacyDelivery['longitude'] ?? null,
                                'manual_address' => $legacyDelivery['address'] ?? null,
                            ], fn ($value) => $value !== null && $value !== '');
                        }
                        if (empty($metadata['delivery_reference']) && filled($legacyDelivery['reference'] ?? null)) {
                            $metadata['delivery_reference'] = $legacyDelivery['reference'];
                        }
                        if (empty($metadata['delivery_recipient_name'])) {
                            $contactName = DB::table('whatsapp_contacts')->where('id', $order->contact_id)->value('name');
                            if (filled($contactName)) {
                                $metadata['delivery_recipient_name'] = $contactName;
                            }
                        }
                    }
                    unset($metadata['delivery']);

                    DB::table('whatsapp_carts')->where('id', $order->id)->update([
                        'metadata' => json_encode($metadata),
                    ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        // Los datos ya normalizados no se revierten -- volver al vocabulario
        // viejo rompería otra vez las pantallas que ya esperan el nuevo.
    }
};
