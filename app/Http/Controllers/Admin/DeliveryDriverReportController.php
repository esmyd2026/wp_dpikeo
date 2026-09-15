<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesReportPeriod;
use App\Http\Controllers\Controller;
use App\Models\DeliveryDriver;
use App\Models\WhatsappCart;
use Illuminate\Http\Request;

/**
 * Pedido explícito: "indicadores de los envíos por repartidor... cuántos
 * envíos ha hecho un repartidor con el total en cantidades de envío y los
 * totales de los costos de envío... por rangos de fecha". No existe un
 * driver_id en whatsapp_carts -- el vínculo pedido→repartidor es
 * metadata['last_dispatch_driver_id'], que se sobrescribe en cada
 * redespacho (ver DeliveryController::dispatchToDriver()), así que esto
 * refleja "quién entregó" para el caso normal (un despacho por pedido), no
 * un historial completo de reasignaciones.
 */
class DeliveryDriverReportController extends Controller
{
    use ResolvesReportPeriod;

    public function index(Request $request)
    {
        [$from, $to, $periodPreset] = $this->resolveReportPeriod($request);

        $deliveredOrders = WhatsappCart::reportable()->forActiveCompany()
            ->where('status', WhatsappCart::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'total', 'metadata', 'created_at'])
            ->filter(fn (WhatsappCart $order) => ($order->metadata['pickup_mode'] ?? null) === 'delivery');

        $driverIds = $deliveredOrders
            ->map(fn (WhatsappCart $order) => $order->metadata['last_dispatch_driver_id'] ?? null)
            ->filter()
            ->unique();
        $drivers = DeliveryDriver::whereIn('id', $driverIds)->get()->keyBy('id');

        $driverName = function (?int $driverId) use ($drivers): string {
            if (!$driverId || !$drivers->has($driverId)) {
                return 'Sin repartidor asignado';
            }

            return $drivers->get($driverId)->full_name ?: 'Repartidor sin nombre';
        };

        $feeFor = fn (WhatsappCart $order) => (float) ($order->metadata['delivery_fee_applied'] ?? $order->metadata['delivery_fee'] ?? 0);

        $stats = $deliveredOrders
            ->groupBy(fn (WhatsappCart $order) => $order->metadata['last_dispatch_driver_id'] ?? 0)
            ->map(function ($group, $driverId) use ($driverName, $drivers, $feeFor) {
                $driverId = (int) $driverId ?: null;

                return [
                    'driver_id' => $driverId,
                    'driver_name' => $driverName($driverId),
                    'driver_phone' => $driverId ? $drivers->get($driverId)?->phone_number : null,
                    'deliveries' => $group->count(),
                    'delivery_fee_total' => (float) $group->sum($feeFor),
                    'orders_total' => (float) $group->sum('total'),
                    'pending_review_count' => $group->filter(
                        fn (WhatsappCart $order) => (bool) ($order->metadata['delivery_fee_pending_review'] ?? false)
                    )->count(),
                ];
            })
            ->sortByDesc('deliveries')
            ->values();

        $ordersDetail = $deliveredOrders
            ->map(fn (WhatsappCart $order) => [
                'id' => $order->id,
                'order_number' => $order->getOrderNumber(),
                'created_at' => $order->created_at,
                'driver_name' => $driverName($order->metadata['last_dispatch_driver_id'] ?? null),
                'delivery_fee' => $feeFor($order),
                'delivery_fee_pending_review' => (bool) ($order->metadata['delivery_fee_pending_review'] ?? false),
                'total' => (float) $order->total,
            ])
            ->sortByDesc('created_at')
            ->values();

        $summary = [
            'deliveries' => $deliveredOrders->count(),
            'fee_total' => $stats->sum('delivery_fee_total'),
            'active_drivers' => $stats->whereNotNull('driver_id')->count(),
        ];

        return view('admin.reports.delivery', compact('stats', 'ordersDetail', 'summary', 'from', 'to', 'periodPreset'));
    }
}
