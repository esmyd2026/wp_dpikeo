<?php

namespace App\Services;

use App\Models\MessageTemplate;
use App\Models\WhatsappCart;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class OrderConfirmationService
{
    public function __construct(
        private OrderPdfService $pdf,
        private WhatsappService $whatsapp,
    ) {}

    public function sendToClient(WhatsappCart $order, ?int $agentUserId = null, ?string $agentNote = null): bool
    {
        $order->loadMissing(['contact', 'items']);
        $contact = $order->contact;

        if (! $contact) {
            throw new InvalidArgumentException('El pedido no tiene un cliente asociado.');
        }

        $this->whatsapp->useBusinessProfile($contact->businessProfile);

        if (! in_array($order->status, [WhatsappCart::STATUS_PENDING, WhatsappCart::STATUS_PAYMENT_PENDING], true)) {
            throw new InvalidArgumentException('Solo se puede solicitar confirmación en pedidos pendientes.');
        }

        $config = WhatsappChatbotConfig::where('business_profile_id', $contact->business_profile_id)->first();
        if (! MessageTemplate::isEnabledFor($config, 'order_confirmation_ticket')) {
            throw new InvalidArgumentException('El mensaje de confirmación está desactivado en Configuración del chatbot.');
        }

        $pdfPath = null;

        try {
            // Pedido explícito: el mensaje de confirmación ya trae un enlace
            // para ver/descargar el PDF (más abajo) -- adjuntarlo además
            // como documento es opcional y se puede apagar desde
            // Configuración del chatbot (config->send_order_pdf_document).
            if ($config?->send_order_pdf_document ?? true) {
                $pdfPath = $this->pdf->saveToTempFile($order);
                $orderNumber = $order->getOrderNumber();
                $caption = "📋 Orden de pedido {$orderNumber}";

                $docSent = $this->whatsapp->sendDocumentMessage(
                    $contact,
                    $pdfPath,
                    basename($pdfPath),
                    $caption,
                    true
                );

                if (! $docSent) {
                    throw new InvalidArgumentException('No se pudo enviar el PDF por WhatsApp.');
                }
            }

            $pdfUrl = $this->pdf->signedDownloadUrl($order);
            $payload = $this->whatsapp->buildOrderConfirmationPayload($order, $pdfUrl, $agentNote);
            $interactiveSent = (bool) $this->whatsapp->sendBotPayload($contact, $payload, true);

            if (! $interactiveSent) {
                throw new InvalidArgumentException('No se pudieron enviar los botones de confirmación.');
            }

            $metadata = $order->metadata ?? [];
            $metadata['awaiting_client_confirmation'] = true;
            $metadata['confirmation_sent_at'] = now()->toIso8601String();
            $metadata['confirmation_pdf_url'] = $pdfUrl;
            if ($agentUserId) {
                $metadata['confirmation_sent_by'] = $agentUserId;
            }
            $order->metadata = $metadata;
            $order->save();

            return true;
        } finally {
            if ($pdfPath && is_file($pdfPath)) {
                @unlink($pdfPath);
            }
        }
    }

    public function notifyBulkOrderSubmitted(WhatsappContact $contact, WhatsappCart $cart): void
    {
        try {
            $this->sendToClient($cart);
        } catch (\Throwable $e) {
            Log::error('[OrderConfirmation] Fallback a mensaje simple', [
                'cart_id' => $cart->id,
                'error' => $e->getMessage(),
            ]);
            $this->whatsapp->notifyBulkWebOrderSubmitted(
                $contact,
                $cart,
                $cart->getOrderNumber()
            );
        }
    }
}
