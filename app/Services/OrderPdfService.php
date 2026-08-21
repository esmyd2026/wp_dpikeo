<?php

namespace App\Services;

use App\Models\WhatsappCart;
use App\Models\WhatsappPrice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class OrderPdfService
{
    public function __construct(
        private OrderAdminService $orderAdmin,
        private OrderPdfSettingsService $settings,
    ) {}

    public function download(WhatsappCart $order): Response
    {
        $payload = $this->buildPayload($order);
        $filename = $this->filename($payload['order']['number']);

        return $this->ticketPdf($payload)
            ->download($filename);
    }

    public function saveToTempFile(WhatsappCart $order): string
    {
        $payload = $this->buildPayload($order);
        $filename = $this->filename($payload['order']['number']);
        $dir = storage_path('app/temp/orders');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents(
            $path,
            $this->ticketPdf($payload)->output()
        );

        return $path;
    }

    public function signedDownloadUrl(WhatsappCart $order): string
    {
        $days = max(1, (int) config('order_pdf.signed_url_ttl_days', 30));

        return URL::temporarySignedRoute(
            'order.pdf.signed',
            now()->addDays($days),
            ['order' => $order->id]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(WhatsappCart $order): array
    {
        $order->load(['items.product', 'contact']);
        $billing = $this->orderAdmin->resolveBillingData($order, $order->contact);
        $company = $this->companyProfile();
        $lines = $this->buildLines($order);
        $subtotal = round(array_sum(array_column($lines, 'subtotal')), 2);
        $ivaRate = $this->settings->ivaRate();
        $pricesIncludeIva = $this->settings->pricesIncludeIva();

        if ($pricesIncludeIva && $ivaRate > 0) {
            $subtotalNet = round($subtotal / (1 + $ivaRate), 2);
            $ivaAmount = round($subtotal - $subtotalNet, 2);
            $total = $subtotal;
        } else {
            $subtotalNet = $subtotal;
            $ivaAmount = round($subtotalNet * $ivaRate, 2);
            $total = round($subtotalNet + $ivaAmount, 2);
        }

        $tz = $this->settings->timezone();
        $createdAt = $order->created_at
            ? Carbon::parse($order->created_at)->timezone($tz)
            : now($tz);

        $pdfSettings = $this->settings->get();

        return [
            'company' => $company,
            'order' => [
                'id' => $order->id,
                'number' => $order->getOrderNumber(),
                'date' => $createdAt->format('d/m/Y'),
                'time' => $createdAt->format('H:i'),
                'status' => $this->statusLabel($order->status),
                'payment_method' => $this->paymentLabel($order->payment_method),
                'payment_status' => $order->payment_status,
                'note' => $this->cleanNote($order->note),
                'requires_invoice' => (bool) $order->requires_invoice,
            ],
            'client' => [
                'name' => $billing['billing_legal_name'] ?: ($order->contact?->name ?? 'Cliente'),
                'identification_type' => ($billing['billing_type'] ?? 'cedula') === 'ruc' ? 'RUC' : 'Cédula',
                'identification' => $billing['billing_id'] ?: ($order->contact?->national_id ?? '—'),
                'phone' => $order->contact?->phone_number ?? '—',
                'address' => $billing['address'] ?: ($order->contact?->address ?? '—'),
                'email' => $order->contact?->metadata['email'] ?? '—',
            ],
            'lines' => $lines,
            'totals' => [
                'subtotal' => $subtotalNet,
                'iva_rate_percent' => (int) round($ivaRate * 100),
                'iva' => $ivaAmount,
                'total' => $total,
                'prices_include_iva' => $pricesIncludeIva,
            ],
            'currency_symbol' => config('order_pdf.currency_symbol', '$'),
            'document_title' => $pdfSettings['document_title'],
            'document_subtitle' => $pdfSettings['document_subtitle'],
            'legal_footer' => $pdfSettings['legal_footer'],
        ];
    }

    private function filename(string $orderNumber): string
    {
        $safe = preg_replace('/[^A-Za-z0-9\-_]/', '-', $orderNumber) ?: 'pedido';

        return "Orden-{$safe}.pdf";
    }

    /**
     * Ticket de 80 mm. La altura crece según las líneas para evitar que una
     * orden larga se corte al imprimir o al abrirse como documento digital.
     */
    private function ticketPdf(array $payload)
    {
        $lineCount = max(1, count($payload['lines'] ?? []));
        $height = max(540, 420 + ($lineCount * 82));

        return Pdf::loadView('pdf.order-ticket', $payload)
            ->setPaper([0, 0, 226.77, $height], 'portrait');
    }

    private function companyProfile(): array
    {
        return $this->settings->companyProfile();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildLines(WhatsappCart $order): array
    {
        $lines = [];
        $index = 1;

        foreach ($order->items as $item) {
            $product = $item->product;
            $unit = (float) $item->getAttributes()['price'];
            $qty = max(1, (int) $item->quantity);
            $subtotal = round($unit * $qty, 2);

            $description = $product?->description;
            $measurements = $product instanceof WhatsappPrice
                ? $this->productMeasurements($product)
                : null;

            $lines[] = [
                'index' => $index++,
                'sku' => $product?->sku ?? '—',
                'name' => $item->name,
                'description' => $this->cleanText($description),
                'measurements' => $measurements,
                'quantity' => $qty,
                'unit_price' => $unit,
                'subtotal' => $subtotal,
                'line_note' => $this->cleanText($item->line_note),
            ];
        }

        return $lines;
    }

    private function productMeasurements(WhatsappPrice $product): ?string
    {
        $parts = array_values(array_filter([
            $this->cleanText($product->quantity ?? null),
            $this->cleanText($product->format ?? null),
            $this->cleanText($product->flavor ?? null),
        ]));

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    private function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $text !== '' ? $text : null;
    }

    private function cleanNote(?string $note): ?string
    {
        if ($note === null || trim($note) === '' || strtolower(trim($note)) === 'sin nota') {
            return null;
        }

        return trim($note);
    }

    private function statusLabel(?string $status): string
    {
        return config("order_pdf.status_labels.{$status}", ucfirst((string) $status));
    }

    private function paymentLabel(?string $method): string
    {
        if (!$method) {
            return 'Por definir';
        }

        return config("order_pdf.payment_methods.{$method}", ucfirst(str_replace('_', ' ', $method)));
    }
}
