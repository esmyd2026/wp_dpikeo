<?php

namespace App\Services;

use App\Models\OrderAlertEvent;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use Illuminate\Http\UploadedFile;

/** Sincroniza pagos y facturación entre el ecommerce, Pedidos y el bot. */
class StorefrontOrderSelfService
{
    public function __construct(private PaymentProofArchiveService $proofs) {}

    public function saveInvoicePreference(WhatsappCart $order, WhatsappContact $contact, array $data): void
    {
        $requiresInvoice = (bool) ($data['requires_invoice'] ?? false);
        $order->requires_invoice = $requiresInvoice;
        $order->invoice_status = $requiresInvoice ? 'data_ready' : 'none';
        $order->invoice_data = $requiresInvoice ? [
            'billing_type' => $data['billing_type'],
            'billing_id' => trim($data['billing_id']),
            'billing_legal_name' => trim($data['billing_legal_name']),
            'address' => trim($data['billing_address']),
            'email' => strtolower(trim($data['billing_email'])),
        ] : null;

        $metadata = $order->metadata ?? [];
        unset($metadata['awaiting_invoice_choice'], $metadata['awaiting_invoice_reuse_confirmation'], $metadata['awaiting_invoice_data']);
        $metadata['invoice_preference_source'] = 'storefront_web';
        $order->metadata = $metadata;
        $order->save();

        if ($requiresInvoice) {
            $contact->fill([
                'billing_type' => $data['billing_type'],
                'billing_id' => trim($data['billing_id']),
                'billing_legal_name' => trim($data['billing_legal_name']),
                'billing_email' => strtolower(trim($data['billing_email'])),
                'address' => trim($data['billing_address']),
            ]);
        }
        $contactMetadata = $contact->metadata ?? [];
        $contactMetadata['invoice_preference'] = $requiresInvoice ? 'invoice' : 'consumer';
        $contact->metadata = $contactMetadata;
        $contact->save();

        OrderAlertEvent::create([
            'business_profile_id' => $contact->business_profile_id,
            'whatsapp_cart_id' => $order->id,
            'event_type' => OrderAlertEvent::TYPE_INVOICE_CONFIRMED,
            'payload' => [
                'order_number' => $order->getOrderNumber(),
                'contact_name' => $contact->name ?: $contact->phone_number,
                'invoice_type' => $requiresInvoice ? 'factura' : 'consumidor_final',
            ],
        ]);
    }

    public function savePaymentProof(WhatsappCart $order, WhatsappContact $contact, UploadedFile $file): array
    {
        if (! in_array($order->payment_method, ['transferencia', 'tarjeta'], true)) {
            throw new \InvalidArgumentException('Este pedido no requiere comprobante de transferencia.');
        }
        if ($order->isCancelled() || $order->isPaid()) {
            throw new \InvalidArgumentException('El pedido ya no admite un nuevo comprobante.');
        }

        $proof = $this->proofs->archiveUpload($order, $file);
        OrderAlertEvent::create([
            'business_profile_id' => $contact->business_profile_id,
            'whatsapp_cart_id' => $order->id,
            'event_type' => OrderAlertEvent::TYPE_PAYMENT_PROOF,
            'payload' => [
                'order_number' => $order->getOrderNumber(),
                'contact_name' => $contact->name ?: $contact->phone_number,
            ],
        ]);

        return $proof;
    }
}
