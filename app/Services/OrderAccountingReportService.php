<?php

namespace App\Services;

use App\Models\WhatsappCart;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Desglose contable de pedidos: por método de pago, tipo de entrega,
 * facturación (con datos fiscales vs consumidor final) y costos de envío
 * cobrados. Una sola fuente de verdad (summarize()) para que el resumen que
 * se ve en pantalla y el Excel descargable muestren siempre los mismos
 * números. Los pedidos cancelados se excluyen de estos montos -- no
 * representan ingreso real -- igual que ya hace dailyOrderTrend() en
 * OrdersReportsController.
 */
class OrderAccountingReportService
{
    private const PAYMENT_LABELS = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'tarjeta' => 'Tarjeta',
    ];

    private const FULFILLMENT_LABELS = [
        'delivery' => 'Delivery',
        'retiro' => 'Retiro en el local',
        'servir' => 'Para servir en el local',
        'llevar' => 'Para llevar',
        'sin_especificar' => 'Sin especificar',
    ];

    /** @return array<string, mixed> */
    public function summarize(Carbon $from, Carbon $to): array
    {
        $orders = WhatsappCart::reportable()->forActiveCompany()
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'status', 'total', 'payment_method', 'requires_invoice', 'metadata']);

        $cancelled = $orders->where('status', WhatsappCart::STATUS_CANCELLED);
        $counted = $orders->where('status', '!=', WhatsappCart::STATUS_CANCELLED);

        return [
            'from' => $from,
            'to' => $to,
            'totals' => [
                'orders' => $counted->count(),
                'revenue' => (float) $counted->sum('total'),
                'cancelled_orders' => $cancelled->count(),
                'cancelled_amount' => (float) $cancelled->sum('total'),
            ],
            'payment_methods' => $this->groupByPaymentMethod($counted),
            'fulfillment' => $this->groupByFulfillment($counted),
            'delivery_costs' => $this->deliveryCosts($counted),
            'invoicing' => $this->groupByInvoicing($counted),
        ];
    }

    /**
     * @param  Collection<int, WhatsappCart>  $orders
     * @return list<array{key: string, label: string, count: int, amount: float}>
     */
    private function groupByPaymentMethod(Collection $orders): array
    {
        return $orders->groupBy(fn (WhatsappCart $o) => $o->payment_method ?: 'sin_especificar')
            ->map(fn ($group, $key) => [
                'key' => $key,
                'label' => self::PAYMENT_LABELS[$key] ?? 'Sin especificar',
                'count' => $group->count(),
                'amount' => (float) $group->sum('total'),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    public function fulfillmentKey(WhatsappCart $order): string
    {
        $metadata = $order->metadata ?? [];
        $pickupMode = $metadata['pickup_mode'] ?? null;
        $serviceType = $metadata['service_type'] ?? null;

        return match (true) {
            $pickupMode === 'delivery' => 'delivery',
            $pickupMode === 'retiro' => 'retiro',
            $serviceType === 'servir' => 'servir',
            $serviceType === 'llevar' => 'llevar',
            default => 'sin_especificar',
        };
    }

    /**
     * @param  Collection<int, WhatsappCart>  $orders
     * @return list<array{key: string, label: string, count: int, amount: float}>
     */
    private function groupByFulfillment(Collection $orders): array
    {
        return $orders->groupBy(fn (WhatsappCart $o) => $this->fulfillmentKey($o))
            ->map(fn ($group, $key) => [
                'key' => $key,
                'label' => self::FULFILLMENT_LABELS[$key] ?? 'Sin especificar',
                'count' => $group->count(),
                'amount' => (float) $group->sum('total'),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, WhatsappCart>  $orders
     * @return array{orders: int, total: float, average: float}
     */
    private function deliveryCosts(Collection $orders): array
    {
        $deliveryOrders = $orders->filter(fn (WhatsappCart $o) => $this->fulfillmentKey($o) === 'delivery');

        $total = (float) $deliveryOrders->sum(function (WhatsappCart $o) {
            $metadata = $o->metadata ?? [];

            return (float) ($metadata['delivery_fee_applied'] ?? $metadata['delivery_fee'] ?? 0);
        });

        $count = $deliveryOrders->count();

        return [
            'orders' => $count,
            'total' => $total,
            'average' => $count > 0 ? round($total / $count, 2) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, WhatsappCart>  $orders
     * @return list<array{key: string, label: string, count: int, amount: float}>
     */
    private function groupByInvoicing(Collection $orders): array
    {
        $withInvoice = $orders->where('requires_invoice', true);
        $finalConsumer = $orders->where('requires_invoice', false);

        return [
            ['key' => 'with_invoice', 'label' => 'Con factura (datos fiscales)', 'count' => $withInvoice->count(), 'amount' => (float) $withInvoice->sum('total')],
            ['key' => 'final_consumer', 'label' => 'Consumidor final', 'count' => $finalConsumer->count(), 'amount' => (float) $finalConsumer->sum('total')],
        ];
    }

    public function downloadResponse(Carbon $from, Carbon $to): StreamedResponse
    {
        $summary = $this->summarize($from, $to);
        $filename = 'reporte-contable-'.$from->format('Y-m-d').'_a_'.$to->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($summary) {
            $spreadsheet = new Spreadsheet;
            $spreadsheet->removeSheetByIndex(0);

            $this->addSummarySheet($spreadsheet, $summary);
            $this->addTableSheet($spreadsheet, 'Metodo de pago', $summary['payment_methods']);
            $this->addTableSheet($spreadsheet, 'Tipo de entrega', $summary['fulfillment']);
            $this->addTableSheet($spreadsheet, 'Facturacion', $summary['invoicing']);

            $spreadsheet->setActiveSheetIndex(0);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    /** @return array<string, mixed> */
    private function headerStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '075E54']],
        ];
    }

    /** @param array<string, mixed> $summary */
    private function addSummarySheet(Spreadsheet $spreadsheet, array $summary): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Resumen');
        $sheet->setCellValue('A1', 'Reporte contable de pedidos');
        $sheet->setCellValue('A2', 'Periodo: '.$summary['from']->format('d/m/Y').' - '.$summary['to']->format('d/m/Y'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $rows = [
            ['Pedidos contabilizados (sin cancelados)', $summary['totals']['orders']],
            ['Ingresos totales', $summary['totals']['revenue']],
            ['Pedidos cancelados', $summary['totals']['cancelled_orders']],
            ['Monto cancelado (no contabilizado)', $summary['totals']['cancelled_amount']],
            ['Pedidos con delivery', $summary['delivery_costs']['orders']],
            ['Total cobrado por envios', $summary['delivery_costs']['total']],
            ['Promedio de envio', $summary['delivery_costs']['average']],
        ];

        foreach ($rows as $i => $row) {
            [$label, $value] = $row;
            $sheet->setCellValue([1, $i + 4], $label);
            $sheet->setCellValue([2, $i + 4], $value);
        }

        $sheet->getColumnDimension('A')->setWidth(42);
        $sheet->getColumnDimension('B')->setWidth(18);
    }

    /** @param list<array{key: string, label: string, count: int, amount: float}> $rows */
    private function addTableSheet(Spreadsheet $spreadsheet, string $title, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);

        $headers = ['Categoria', 'Pedidos', 'Monto'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, 1], $header);
        }
        $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($headers)).'1')->applyFromArray($this->headerStyle());

        foreach ($rows as $rowIndex => $row) {
            $sheet->setCellValue([1, $rowIndex + 2], $row['label']);
            $sheet->setCellValue([2, $rowIndex + 2], $row['count']);
            $sheet->setCellValue([3, $rowIndex + 2], $row['amount']);
        }

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
    }
}
