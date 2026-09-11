<?php

namespace App\Services;

use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappPrice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
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

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.DIRECTORY_SEPARATOR.$filename;
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
        $order->load(['items.product', 'contact.businessProfile.company', 'branch']);
        $billing = $this->orderAdmin->resolveBillingData($order, $order->contact);
        $company = $this->companyProfile($order);
        $lines = $this->buildLines($order);
        $productsSubtotal = round(array_sum(array_column($lines, 'subtotal')), 2);
        $deliveryFee = $this->appliedDeliveryFee($order);
        $ivaRate = $this->settings->ivaRate();
        $pricesIncludeIva = $this->settings->pricesIncludeIva();
        $recordedTotal = round((float) $order->total, 2);
        $total = $recordedTotal > 0
            ? $recordedTotal
            : round($productsSubtotal + $deliveryFee, 2);
        $ivaAmount = $pricesIncludeIva && $ivaRate > 0
            ? round($productsSubtotal - ($productsSubtotal / (1 + $ivaRate)), 2)
            : max(0, round($total - $productsSubtotal - $deliveryFee, 2));

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
                'payment_method' => $this->paymentLabel($order->payment_method),
                'payment_status_label' => $this->paymentStatusLabel($order),
                'payment_explanation' => $this->paymentExplanation($order),
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
            'fulfillment' => $this->fulfillment($order, $billing),
            'totals' => [
                'subtotal' => $productsSubtotal,
                'iva_rate_percent' => (int) round($ivaRate * 100),
                'iva' => $ivaAmount,
                'delivery_fee' => $deliveryFee,
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
        $hasDeliveryDetails = array_filter($payload['fulfillment'] ?? []) !== [];
        $hasLogo = ! empty($payload['company']['logo_data_uri']);
        $hasNote = ! empty($payload['order']['note']);
        $height = max(
            540,
            365
                + ($lineCount * 72)
                + ($hasDeliveryDetails ? 45 : 0)
                + ($hasLogo ? 55 : 0)
                + ($hasNote ? 45 : 0)
        );

        return Pdf::loadView('pdf.order-ticket', $payload)
            ->setPaper([0, 0, 226.77, $height], 'portrait');
    }

    private function companyProfile(WhatsappCart $order): array
    {
        $company = $this->settings->companyProfile();
        $profile = $order->contact?->businessProfile;

        if (! $profile) {
            return array_merge($company, ['logo_data_uri' => null]);
        }

        $metadata = is_array($profile->metadata) ? $profile->metadata : [];
        $company['legal_name'] = $metadata['legal_name']
            ?? $profile->company?->name
            ?? $profile->business_name
            ?? $company['legal_name'];
        $company['trade_name'] = $metadata['trade_name']
            ?? $profile->display_name
            ?? $profile->business_name
            ?? $company['trade_name'];
        $company['phone'] = $profile->phone_number ?: $company['phone'];

        foreach (['ruc', 'address', 'city', 'email', 'website'] as $field) {
            if (! empty($metadata[$field])) {
                $company[$field] = $metadata[$field];
            }
        }

        $config = WhatsappChatbotConfig::query()
            ->where('business_profile_id', $profile->id)
            ->first();
        $logoPath = $config?->metadata['landing']['logo_path'] ?? null;
        $company['logo_data_uri'] = $this->logoDataUri($logoPath);

        return $company;
    }

    private function logoDataUri(?string $path): ?string
    {
        $path = ltrim(trim((string) $path), '/');
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: '';
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
    }

    private function appliedDeliveryFee(WhatsappCart $order): float
    {
        $metadata = $order->metadata ?? [];
        if (($metadata['pickup_mode'] ?? null) !== 'delivery') {
            return 0.0;
        }

        if (array_key_exists('delivery_fee_applied', $metadata)) {
            return max(0, round((float) $metadata['delivery_fee_applied'], 2));
        }

        if (! ($metadata['delivery_fee_pending_review'] ?? false) && array_key_exists('delivery_fee', $metadata)) {
            return max(0, round((float) $metadata['delivery_fee'], 2));
        }

        return 0.0;
    }

    /** @return array<string, string|null> */
    private function fulfillment(WhatsappCart $order, array $billing): array
    {
        $metadata = $order->metadata ?? [];
        $pickupMode = $metadata['pickup_mode'] ?? null;
        $serviceType = $metadata['service_type'] ?? null;

        return [
            'branch' => $order->branch?->name,
            'type' => match (true) {
                $pickupMode === 'delivery' => 'Delivery',
                $pickupMode === 'retiro' => 'Retiro en el local',
                $serviceType === 'servir' => 'Para servir en el local',
                $serviceType === 'llevar' => 'Para llevar',
                default => null,
            },
            'recipient' => $pickupMode === 'delivery'
                ? ($metadata['delivery_recipient_name'] ?? null)
                : null,
            'address' => $pickupMode === 'delivery'
                ? ($metadata['delivery_location']['manual_address']
                    ?? ($billing['address'] ?: ($order->contact?->address ?? null)))
                : $order->branch?->address,
        ];
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

    private function paymentStatusLabel(WhatsappCart $order): string
    {
        if (in_array($order->status, [
            WhatsappCart::STATUS_PAID,
            WhatsappCart::STATUS_PREPARING,
            WhatsappCart::STATUS_READY,
            WhatsappCart::STATUS_COMPLETED,
        ], true) && in_array($order->payment_method, ['transferencia', 'tarjeta'], true)) {
            return 'Pago confirmado';
        }

        return match ($order->payment_status) {
            'awaiting_proof' => 'Esperando comprobante',
            'proof_submitted' => 'Comprobante recibido · en revisión',
            'confirmed' => 'Pago confirmado',
            'cash_on_delivery' => 'Pago al recibir el pedido',
            default => 'Pago pendiente',
        };
    }

    private function paymentExplanation(WhatsappCart $order): string
    {
        return match ($order->payment_method) {
            'efectivo' => 'El cliente pagará en efectivo al recibir o retirar el pedido.',
            'transferencia' => $this->paymentStatusLabel($order) === 'Pago confirmado'
                ? 'La transferencia fue verificada. No se debe cobrar nuevamente.'
                : 'Revisa el comprobante antes de considerar el pago confirmado.',
            'tarjeta' => $this->paymentStatusLabel($order) === 'Pago confirmado'
                ? 'El pago con tarjeta fue confirmado. No se debe cobrar nuevamente.'
                : 'El pago con tarjeta todavía está pendiente de confirmación.',
            default => 'La forma de pago todavía no ha sido definida.',
        };
    }

    private function paymentLabel(?string $method): string
    {
        if (! $method) {
            return 'Por definir';
        }

        return config("order_pdf.payment_methods.{$method}", ucfirst(str_replace('_', ' ', $method)));
    }
}
