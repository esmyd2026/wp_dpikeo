<?php

namespace App\Services;

use App\Models\WhatsappCart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderExportService
{
    private const STATUS_LABELS = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'completed' => 'Completado',
        'cancelled' => 'Cancelado',
        'payment_pending' => 'Pago pendiente',
        'paid' => 'Pagado',
    ];

    public function __construct(private readonly OrderAdminService $orderAdmin) {}

    /** @return list<string> */
    public static function headers(): array
    {
        return [
            'Codigo pedido',
            'Fecha pedido',
            'Estado pedido',
            'Codigo producto',
            'Nombre producto',
            'Cantidad',
            'Precio unitario',
            'Subtotal linea',
            'Total pedido',
            'Telefono',
            'Tipo identificacion',
            'Cedula / RUC',
            'Nombre cliente',
            'Direccion',
            'Requiere factura',
            'Estado factura',
            'Metodo pago',
            'Referencia pago',
            'Nota del pedido',
        ];
    }

    public function queryFromRequest(Request $request): Builder
    {
        $query = WhatsappCart::reportable()
            ->with(['items.price', 'contact'])
            ->orderBy('created_at', 'desc');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function ($orderQuery) use ($like) {
                $orderQuery->whereHas('contact', function ($contactQuery) use ($like) {
                    $contactQuery
                        ->where('name', 'like', $like)
                        ->orWhere('phone_number', 'like', $like)
                        ->orWhere('billing_id', 'like', $like)
                        ->orWhere('national_id', 'like', $like)
                        ->orWhere('billing_legal_name', 'like', $like);
                });
            });
        }

        return $query;
    }

    /**
     * Una fila por producto del pedido; el cliente se repite en cada linea.
     *
     * @return list<list<string|float|int>>
     */
    public function buildRows(iterable $orders): array
    {
        $rows = [];

        foreach ($orders as $order) {
            $contact = $order->contact;
            $billing = $this->orderAdmin->resolveBillingData($order, $contact);

            $shared = [
                $order->getOrderNumber(),
                $order->created_at?->format('Y-m-d H:i') ?? '',
                self::STATUS_LABELS[$order->status] ?? $order->status,
            ];

            $clientBlock = [
                (float) $order->total,
                (string) ($contact?->phone_number ?? ''),
                strtoupper((string) ($billing['billing_type'] ?? '')),
                (string) ($billing['billing_id'] ?? ''),
                (string) ($billing['billing_legal_name'] ?? $contact?->name ?? ''),
                (string) ($billing['address'] ?? ''),
                $order->requires_invoice ? 'Si' : 'No',
                OrderAdminService::INVOICE_STATUSES[$order->invoice_status ?? 'none'] ?? ($order->invoice_status ?? ''),
                (string) ($order->payment_method ?? ''),
                (string) ($order->payment_reference ?? ''),
                (string) ($order->note ?? ''),
            ];

            if ($order->items->isEmpty()) {
                $rows[] = array_merge($shared, ['', '', 0, 0.0, 0.0], $clientBlock);

                continue;
            }

            foreach ($order->items as $item) {
                $quantity = (int) $item->quantity;
                $unitPrice = (float) $item->price;
                $lineTotal = round($unitPrice * $quantity, 2);

                $rows[] = array_merge($shared, [
                    (string) ($item->price?->sku ?? ''),
                    (string) $item->name,
                    $quantity,
                    $unitPrice,
                    $lineTotal,
                ], $clientBlock);
            }
        }

        return $rows;
    }

    public function downloadResponse(Request $request): StreamedResponse
    {
        $orders = $this->queryFromRequest($request)->get();
        $rows = $this->buildRows($orders);
        $filename = 'pedidos-detalle-' . now()->format('Y-m-d_His') . '.xlsx';

        return response()->streamDownload(function () use ($rows) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Pedidos');

            $headers = self::headers();
            foreach ($headers as $colIndex => $header) {
                $sheet->setCellValue([$colIndex + 1, 1], $header);
            }

            $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '075E54'],
                ],
            ]);

            foreach ($rows as $rowIndex => $row) {
                foreach ($row as $colIndex => $value) {
                    $sheet->setCellValue([$colIndex + 1, $rowIndex + 2], $value);
                }
            }

            foreach (range(1, count($headers)) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }

            $sheet->freezePane('A2');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }
}
