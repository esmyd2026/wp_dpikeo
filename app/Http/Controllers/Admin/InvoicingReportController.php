<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappCart;
use App\Services\OrderAdminService;

/**
 * Pedido explícito: "una lista de las solicitudes de facturación para
 * darle a un equipo que hace facturación electrónica" -- pendientes y ya
 * facturadas, con los datos fiscales necesarios para emitir. Marcar como
 * facturado reutiliza el mismo endpoint que ya usa el modal de pedidos
 * (PUT /admin/orders/{id} con invoice_status=issued, ver
 * AdminController::updateOrder()) en vez de duplicar esa lógica aquí.
 */
class InvoicingReportController extends Controller
{
    public function index(OrderAdminService $orders)
    {
        $base = WhatsappCart::reportable()->forActiveCompany()
            ->where('requires_invoice', true)
            ->with('contact:id,name,phone_number,address,billing_type,billing_id,billing_legal_name,billing_email,national_id');

        $pending = (clone $base)
            ->where('invoice_status', '!=', 'issued')
            ->oldest('created_at')
            ->get()
            ->map(fn (WhatsappCart $order) => $this->rowFor($order, $orders));

        $issued = (clone $base)
            ->where('invoice_status', 'issued')
            ->latest('created_at')
            ->limit(200)
            ->get()
            ->map(fn (WhatsappCart $order) => $this->rowFor($order, $orders));

        return view('admin.reports.invoicing', compact('pending', 'issued'));
    }

    /** @return array<string, mixed> */
    private function rowFor(WhatsappCart $order, OrderAdminService $orders): array
    {
        $billing = $orders->resolveBillingData($order, $order->contact);
        $metadata = $order->metadata ?? [];

        return [
            'id' => $order->id,
            'order_number' => $order->getOrderNumber(),
            'created_at' => $order->created_at,
            'total' => (float) $order->total,
            'delivery_fee' => ($metadata['pickup_mode'] ?? null) === 'delivery'
                ? (float) ($metadata['delivery_fee_applied'] ?? $metadata['delivery_fee'] ?? 0)
                : null,
            'billing_type' => $billing['billing_type'],
            'billing_id' => $billing['billing_id'],
            'billing_legal_name' => $billing['billing_legal_name'],
            'address' => $billing['address'],
            'email' => $billing['email'],
            'phone' => $order->contact?->phone_number,
        ];
    }
}
