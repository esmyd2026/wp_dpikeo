<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesReportPeriod;
use App\Http\Controllers\Controller;
use App\Models\DeliveryDriver;
use App\Models\WhatsappCart;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pedido explícito: "indicadores de los envíos por repartidor... cuántos
 * envíos ha hecho un repartidor con el total en cantidades de envío y los
 * totales de los costos de envío... por rangos de fecha", con la opción de
 * descargarlo en Excel. No existe un driver_id en whatsapp_carts -- el
 * vínculo pedido→repartidor es metadata['last_dispatch_driver_id'], que se
 * sobrescribe en cada redespacho (ver DeliveryController::dispatchToDriver()),
 * así que esto refleja "quién entregó" para el caso normal (un despacho por
 * pedido), no un historial completo de reasignaciones.
 */
class DeliveryDriverReportController extends Controller
{
    use ResolvesReportPeriod;

    public function index(Request $request)
    {
        [$from, $to, $periodPreset] = $this->resolveReportPeriod($request);
        ['stats' => $stats, 'ordersDetail' => $ordersDetail, 'summary' => $summary] = $this->buildReport($from, $to);

        return view('admin.reports.delivery', compact('stats', 'ordersDetail', 'summary', 'from', 'to', 'periodPreset'));
    }

    /** Mismo período que la pantalla (respeta el filtro activo) -- el Excel siempre calza con lo que se ve en pantalla. */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->resolveReportPeriod($request);
        ['stats' => $stats, 'ordersDetail' => $ordersDetail] = $this->buildReport($from, $to);

        $filename = 'reporte-repartidores-'.$from->format('Y-m-d').'_a_'.$to->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($stats, $ordersDetail) {
            $spreadsheet = new Spreadsheet;
            $spreadsheet->removeSheetByIndex(0);

            $this->addSheet($spreadsheet, 'Repartidores', [
                'Repartidor', 'Teléfono', 'Entregas', 'Total cobrado por envío', 'Valor de esos pedidos', 'Envíos sin confirmar',
            ], $stats->map(fn (array $row) => [
                $row['driver_name'], $row['driver_phone'], $row['deliveries'], $row['delivery_fee_total'], $row['orders_total'], $row['pending_review_count'],
            ])->all());

            $this->addSheet($spreadsheet, 'Pedidos', [
                'Pedido', 'Fecha', 'Repartidor', 'Envío', 'Total',
            ], $ordersDetail->map(fn (array $row) => [
                $row['order_number'], $row['created_at']->format('d/m/Y H:i'), $row['driver_name'], $row['delivery_fee'], $row['total'],
            ])->all());

            $spreadsheet->setActiveSheetIndex(0);
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    /** @return array{stats: Collection, ordersDetail: Collection, summary: array} */
    private function buildReport(Carbon $from, Carbon $to): array
    {
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

        return compact('stats', 'ordersDetail', 'summary');
    }

    /** @param list<string> $headers @param list<list<mixed>> $rows */
    private function addSheet(Spreadsheet $spreadsheet, string $title, array $headers, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);

        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, 1], $header);
        }
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '075E54']],
        ]);

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex + 2], $value);
            }
        }

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
    }
}
