<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessBranch;
use App\Models\DeliveryDriver;
use App\Models\WhatsappCart;
use App\Services\DeliveryConfirmationService;
use App\Services\GeoDistanceService;
use App\Services\OrderLifecycleService;
use App\Services\ProductImageService;
use App\Services\WhatsappService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Módulo de delivery: lista los pedidos con envío a domicilio (dirección,
 * receptor, costo, ruta) y permite confirmar la entrega con una foto como
 * comprobante. Solo aplica a pedidos con pickup_mode = 'delivery'.
 */
class DeliveryController extends Controller
{
    public function index(): View
    {
        return view('admin.delivery.index');
    }

    public function data(GeoDistanceService $geo): JsonResponse
    {
        return response()->json([
            'updated_at' => now()->toIso8601String(),
            'orders' => $this->ordersPayload($geo),
        ]);
    }

    public function confirmDelivery(Request $request, int $id, DeliveryConfirmationService $confirmations, OrderLifecycleService $lifecycle, ProductImageService $images, GeoDistanceService $geo): JsonResponse
    {
        $order = WhatsappCart::reportable()->forActiveCompany()->with(['contact', 'branch'])->findOrFail($id);

        if (($order->metadata['pickup_mode'] ?? null) !== 'delivery') {
            return response()->json(['success' => false, 'message' => 'Este pedido no es de delivery.'], 422);
        }

        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $order = $confirmations->confirm(
                $order,
                $request->file('photo'),
                $validated['note'] ?? null,
                (int) $request->user()->id,
                $images,
                $lifecycle
            );
        } catch (\InvalidArgumentException $e) {
            // La foto y la nota ya quedaron guardadas aunque el estado no
            // pudiera avanzar (por ejemplo si alguien más ya lo canceló);
            // se lo hacemos saber al vendedor en vez de perder el comprobante.
            return response()->json([
                'success' => false,
                'message' => 'Comprobante guardado, pero no se pudo marcar como entregado: '.$e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Entrega confirmada.',
            'order' => $this->mapOrder($order->fresh(['contact', 'branch']), $geo),
        ]);
    }

    /** Lista de repartidores activos, para el selector al despachar un pedido. */
    public function drivers(): JsonResponse
    {
        $drivers = DeliveryDriver::query()
            ->where('business_profile_id', CompanyContext::current()->businessProfileId())
            ->active()
            ->orderByDesc('last_dispatched_at')
            ->orderBy('first_name')
            ->get()
            ->map(fn (DeliveryDriver $d) => [
                'id' => $d->id,
                'name' => $d->full_name,
                'phone_number' => $d->phone_number,
            ]);

        return response()->json(['drivers' => $drivers]);
    }

    /**
     * Sucursales activas de la empresa, para que el operador confirme desde
     * cuál se retira el pedido antes de despacharlo a un repartidor -- por
     * seguridad, ya que de ahí sale la ruta (origen) que se le manda.
     */
    public function branchesForOrder(int $id): JsonResponse
    {
        $order = WhatsappCart::reportable()->forActiveCompany()->findOrFail($id);
        $businessProfileId = CompanyContext::current()->businessProfileId();

        $branches = BusinessBranch::query()
            ->forUserAccess(auth()->user(), $businessProfileId)
            ->availableForOrders()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'address']);

        return response()->json([
            'branches' => $branches,
            'current_branch_id' => $order->branch_id,
        ]);
    }

    /**
     * Guarda (o reutiliza) el repartidor, y le avisa al cliente por
     * WhatsApp que su pedido va en camino, compartiéndole el contacto del
     * repartidor. La parte de avisarle al repartidor sigue siendo manual
     * (el frontend abre WhatsApp con el mensaje ya armado dirigido a su
     * número): la API de WhatsApp no permite mandarle texto libre a un
     * número que nunca le escribió al bot.
     */
    public function dispatchToDriver(Request $request, int $id, WhatsappService $whatsapp, GeoDistanceService $geo): JsonResponse
    {
        $order = WhatsappCart::reportable()->forActiveCompany()->with(['contact', 'branch'])->findOrFail($id);

        if (($order->metadata['pickup_mode'] ?? null) !== 'delivery') {
            return response()->json(['success' => false, 'message' => 'Este pedido no es de delivery.'], 422);
        }

        $validated = $request->validate([
            'driver_id' => ['nullable', 'integer', Rule::exists('delivery_drivers', 'id')
                ->where('business_profile_id', CompanyContext::current()->businessProfileId())],
            'first_name' => ['required_without:driver_id', 'nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone_number' => ['required_without:driver_id', 'nullable', 'string', 'max:30'],
            // El operador confirma desde qué sucursal se retira el pedido --
            // de ahí sale el origen de la ruta que se le manda al repartidor.
            'branch_id' => ['required', 'integer', Rule::exists('business_branches', 'id')
                ->where('business_profile_id', CompanyContext::current()->businessProfileId())
                ->where('is_active', true)
                ->where('orders_enabled', true)],
        ]);

        $selectedBranch = BusinessBranch::findOrFail($validated['branch_id']);
        abort_unless($request->user()->canAccessBranch($selectedBranch), 403);

        if (! empty($validated['driver_id'])) {
            $driver = DeliveryDriver::findOrFail($validated['driver_id']);
        } else {
            $driver = DeliveryDriver::findOrCreateByPhone(
                $order->contact?->business_profile_id,
                $validated['phone_number'],
                trim((string) $validated['first_name']),
                $validated['last_name'] ?? null
            );
        }

        $driver->forceFill(['last_dispatched_at' => now()])->save();

        if ((int) $order->branch_id !== (int) $validated['branch_id']) {
            $order->branch_id = $validated['branch_id'];
            $order->save();
            $order->load('branch');
        }

        $notification = $whatsapp->notifyCustomerOrderOnTheWay($order, $driver);

        // Se guarda en el pedido para poder reenviarle SOLO al repartidor
        // (sin volver a avisarle al cliente) si el mensaje no le llegó --
        // ver el botón "Reenviar al repartidor" en el detalle del pedido.
        $metadata = $order->metadata ?? [];
        $metadata['last_dispatch_driver_id'] = $driver->id;
        $order->metadata = $metadata;
        $order->save();

        $route = $this->routeInfo($order, $geo);

        return response()->json([
            'success' => true,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->full_name,
                'phone_number' => $driver->phone_number,
            ],
            'branch_name' => $order->branch?->name,
            'distance_km' => $route['distance_km'],
            'maps_url' => $route['maps_url'],
            'customer_notified' => $notification['sent'],
            'customer_notified_reason' => $notification['reason'],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function ordersPayload(GeoDistanceService $geo): array
    {
        return WhatsappCart::reportable()
            ->forActiveCompany()
            ->where('metadata->pickup_mode', 'delivery')
            ->with(['contact', 'branch'])
            ->orderByRaw("CASE status
                WHEN 'ready' THEN 1
                WHEN 'preparing' THEN 2
                WHEN 'confirmed' THEN 3
                WHEN 'paid' THEN 4
                WHEN 'pending' THEN 5
                WHEN 'payment_pending' THEN 6
                WHEN 'completed' THEN 7
                ELSE 8 END")
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (WhatsappCart $order) => $this->mapOrder($order, $geo))
            ->all();
    }

    /** @return array<string, mixed> */
    private function mapOrder(WhatsappCart $order, GeoDistanceService $geo): array
    {
        $statusLabels = [
            WhatsappCart::STATUS_PENDING => 'Pendiente',
            WhatsappCart::STATUS_PAYMENT_PENDING => 'Pago pendiente',
            WhatsappCart::STATUS_CONFIRMED => 'Confirmado',
            WhatsappCart::STATUS_PAID => 'Pagado',
            WhatsappCart::STATUS_PREPARING => 'En preparación',
            WhatsappCart::STATUS_READY => 'Listo para despachar',
            WhatsappCart::STATUS_COMPLETED => 'Entregado',
            WhatsappCart::STATUS_CANCELLED => 'Cancelado',
        ];

        $metadata = $order->metadata ?? [];
        $location = $metadata['delivery_location'] ?? [];
        $route = $this->routeInfo($order, $geo);

        $proof = $metadata['delivery_proof'] ?? null;

        $confirmationUrl = in_array($order->status, [WhatsappCart::STATUS_COMPLETED, WhatsappCart::STATUS_CANCELLED], true)
            ? null
            : app(DeliveryConfirmationService::class)->urlFor($order);

        return [
            'id' => $order->id,
            'order_number' => $order->getOrderNumber(),
            'status' => $order->status,
            'status_label' => $statusLabels[$order->status] ?? ucfirst((string) $order->status),
            'can_confirm' => $order->status === WhatsappCart::STATUS_READY,
            'created_at' => $order->created_at?->toIso8601String(),
            'total' => (float) $order->total,
            'branch' => $order->branch?->name,
            'customer' => [
                'name' => $order->contact?->name ?: 'Cliente',
                'phone' => $order->contact?->phone_number,
            ],
            'address' => $location['manual_address'] ?? null,
            'recipient_name' => $metadata['delivery_recipient_name'] ?? null,
            'last_dispatch_driver' => DeliveryDriver::summaryFor($metadata['last_dispatch_driver_id'] ?? null, $order->contact?->business_profile_id),
            // Pensado para el repartidor: si es efectivo, cuánto cobrar al
            // entregar; si ya se pagó por transferencia/tarjeta, que no cobre nada.
            'payment_dispatch_label' => match ($order->payment_method) {
                'efectivo' => 'Efectivo — cobrar $'.number_format((float) $order->total, 2).' al entregar',
                'transferencia' => 'Transferencia o depósito (ya pagado, no cobrar)',
                'tarjeta' => 'Tarjeta (ya pagado, no cobrar)',
                default => 'No especificado',
            },
            'delivery_fee' => $metadata['delivery_fee'] ?? null,
            'delivery_fee_pending_review' => (bool) ($metadata['delivery_fee_pending_review'] ?? false),
            // Link público para que el repartidor confirme la entrega él
            // mismo desde su celular, sin usuario del panel -- se lo
            // mandamos por WhatsApp junto con los datos del pedido.
            'confirmation_url' => $confirmationUrl,
            'distance_km' => $route['distance_km'],
            'maps_url' => $route['maps_url'],
            'proof' => $proof ? [
                'photo_url' => app(ProductImageService::class)->resolveUrl($proof['photo_path'] ?? null),
                'note' => $proof['note'] ?? null,
                'confirmed_at' => $proof['confirmed_at'] ?? null,
            ] : null,
        ];
    }

    /**
     * Ruta completa para el repartidor: origen (la sucursal de retirada,
     * confirmada al despachar) -> destino (la ubicación o dirección que
     * mandó el cliente). Antes solo se mandaba un pin del destino; ahora,
     * si hay coordenadas de ambos lados, arma un link de indicaciones de
     * Google Maps con los dos puntos.
     *
     * @return array{distance_km: ?float, maps_url: ?string}
     */
    private function routeInfo(WhatsappCart $order, GeoDistanceService $geo): array
    {
        $metadata = $order->metadata ?? [];
        $location = $metadata['delivery_location'] ?? [];
        $destLat = $location['latitude'] ?? null;
        $destLon = $location['longitude'] ?? null;
        $destAddress = $location['manual_address'] ?? null;

        $branch = $order->branch;
        $hasOriginCoords = $branch?->latitude && $branch?->longitude;
        $hasDestCoords = $destLat !== null && $destLon !== null;

        // Preferí la distancia ya calculada en el checkout (real, por
        // calle, con el margen de seguridad -- ver
        // WhatsappService::applyDeliveryRecipientName). Esta pantalla se
        // refresca sola cada 15s, así que NO se vuelve a consultar el
        // servicio externo acá: si no quedó guardada (pedido armado desde
        // el panel, o dirección escrita a mano), se muestra la línea recta
        // solo como referencia visual para el operador.
        $distanceKm = $metadata['delivery_distance_km']
            ?? (($hasOriginCoords && $hasDestCoords)
                ? round($geo->distanceKm((float) $branch->latitude, (float) $branch->longitude, (float) $destLat, (float) $destLon), 1)
                : null);

        $origin = $hasOriginCoords
            ? "{$branch->latitude},{$branch->longitude}"
            : ($branch?->address ? rawurlencode($branch->address) : null);

        $destination = $hasDestCoords
            ? "{$destLat},{$destLon}"
            : ($destAddress ? rawurlencode($destAddress) : null);

        $mapsUrl = match (true) {
            $origin && $destination => "https://www.google.com/maps/dir/?api=1&origin={$origin}&destination={$destination}",
            (bool) $destination => $hasDestCoords
                ? "https://maps.google.com/?q={$destination}"
                : "https://www.google.com/maps/search/?api=1&query={$destination}",
            default => null,
        };

        return ['distance_km' => $distanceKm, 'maps_url' => $mapsUrl];
    }
}
