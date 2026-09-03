<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappContact;
use App\Models\BusinessBranch;
use App\Services\BulkOrderService;
use App\Services\OrderPdfService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminBulkOrderController extends Controller
{
    public function __construct(
        private BulkOrderService $bulkOrders
    ) {}

    /** Ver nota equivalente en ProductController::businessProfileId(). */
    private function businessProfileId(): ?int
    {
        return CompanyContext::current()->businessProfileId();
    }

    public function create(Request $request): View
    {
        $initialContact = null;
        if ($request->filled('contact')) {
            $contact = WhatsappContact::query()
                ->where('business_profile_id', $this->businessProfileId())
                ->find($request->integer('contact'));
            if ($contact) {
                $initialContact = [
                    'id' => $contact->id,
                    'name' => $contact->name ?: 'Cliente',
                    'phone' => $contact->phone_number,
                ];
            }
        }

        return view('admin.bulk-order.create', [
            'initialContact' => $initialContact,
            'catalogUrl' => route('admin.orders.bulk.catalog'),
            'submitUrl' => route('admin.orders.bulk.submit'),
            'contactsSearchUrl' => route('admin.orders.bulk.contacts'),
            'contactsCreateUrl' => route('admin.orders.bulk.contacts.store'),
            'ordersUrl' => route('admin.orders'),
            'branches' => BusinessBranch::query()->where('is_active', true)->where('business_profile_id', $this->businessProfileId())->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function searchContacts(Request $request): JsonResponse
    {
        $contacts = $this->bulkOrders->searchContacts(
            $request->string('q')->toString(),
            min(30, max(5, $request->integer('limit', 20))),
            $this->businessProfileId()
        );

        return response()->json(['contacts' => $contacts]);
    }

    /**
     * Alta mínima desde caja. El teléfono es opcional para ventas presenciales;
     * cuando se registra, se normaliza para evitar fichas duplicadas.
     */
    public function storeContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'requires_invoice' => ['nullable', 'boolean'],
            'billing_type' => ['required_if:requires_invoice,1', 'nullable', 'string', 'in:cedula,ruc,pasaporte'],
            'billing_id' => ['required_if:requires_invoice,1', 'nullable', 'string', 'max:20'],
            'billing_email' => ['required_if:requires_invoice,1', 'nullable', 'email:rfc', 'max:255'],
        ]);

        $phone = preg_replace('/\D+/', '', (string) ($validated['phone'] ?? '')) ?? '';
        if ($phone !== '' && (strlen($phone) < 8 || strlen($phone) > 15)) {
            return response()->json([
                'ok' => false,
                'message' => 'El número de WhatsApp debe tener entre 8 y 15 dígitos.',
            ], 422);
        }

        $requiresInvoice = $request->boolean('requires_invoice');
        if ($requiresInvoice && !filled($validated['address'] ?? null)) {
            return response()->json(['ok' => false, 'message' => 'La dirección es obligatoria para factura.'], 422);
        }

        $attributes = [
            'name' => trim($validated['name']),
            'address' => filled($validated['address'] ?? null) ? trim($validated['address']) : null,
            'status' => 'active',
            'bot_enabled' => $phone !== '',
            'business_profile_id' => $this->businessProfileId(),
        ];
        if ($requiresInvoice) {
            $attributes = array_merge($attributes, [
                'billing_type' => $validated['billing_type'],
                'billing_id' => preg_replace('/\s+/', '', trim($validated['billing_id'])),
                'billing_legal_name' => trim($validated['name']),
                'billing_email' => strtolower(trim($validated['billing_email'])),
            ]);
        }

        $contact = $phone !== ''
            ? WhatsappContact::query()->firstOrCreate(['phone_number' => $phone, 'business_profile_id' => $this->businessProfileId()], $attributes)
            : WhatsappContact::query()->create(array_merge($attributes, ['phone_number' => 'POS-'.now()->format('YmdHis').'-'.Str::lower(Str::random(5))]));

        if (!$contact->wasRecentlyCreated && $requiresInvoice) {
            // Si la ficha ya existe, actualizamos únicamente datos solicitados
            // explícitamente para la factura; no alteramos su historial.
            $contact->update([
                'address' => $attributes['address'],
                'billing_type' => $attributes['billing_type'],
                'billing_id' => $attributes['billing_id'],
                'billing_legal_name' => $attributes['billing_legal_name'],
                'billing_email' => $attributes['billing_email'],
            ]);
        }

        return response()->json([
            'ok' => true,
            'created' => $contact->wasRecentlyCreated,
            'message' => $contact->wasRecentlyCreated ? 'Cliente agregado.' : 'Ese número ya estaba registrado; se seleccionó su ficha.',
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name ?: 'Cliente',
                'phone' => $contact->phone_number,
                'requires_invoice' => $requiresInvoice,
            ],
        ]);
    }

    public function catalog(Request $request): JsonResponse
    {
        return response()->json(
            $this->bulkOrders->catalogPayload(
                $request->integer('category') ?: null,
                $request->string('q')->toString() ?: null,
                $this->businessProfileId()
            )
        );
    }

    public function submit(Request $request, OrderPdfService $pdf): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer', 'exists:whatsapp_contacts,id'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.variation' => ['nullable', 'string', 'max:120'],
            'items.*.extras' => ['nullable', 'array', 'max:20'],
            'items.*.extras.*' => ['string', 'max:120'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'order_note' => ['nullable', 'string', 'max:1000'],
            'notify_whatsapp' => ['sometimes', 'boolean'],
            'requires_invoice' => ['sometimes', 'boolean'],
            'branch_id' => ['nullable', 'integer', 'exists:business_branches,id'],
        ]);

        $contact = WhatsappContact::query()
            ->where('business_profile_id', $this->businessProfileId())
            ->findOrFail($validated['contact_id']);

        try {
            $cart = $this->bulkOrders->submitFromAdmin(
                $contact,
                $validated['items'],
                $validated['order_note'] ?? null,
                (int) $request->user()->id,
                // Pedidos registrados desde caja son internos por defecto.
                // Solo se notifica si el operador marca explícitamente la opción.
                $request->boolean('notify_whatsapp', false),
                $validated['branch_id'] ?? null,
            );

            if ($request->boolean('requires_invoice', false)) {
                $cart->forceFill([
                    'requires_invoice' => true,
                    'invoice_status' => 'pending',
                    'invoice_data' => [
                        'billing_type' => $contact->billing_type,
                        'billing_id' => $contact->billing_id,
                        'billing_legal_name' => $contact->billing_legal_name,
                        'billing_email' => $contact->billing_email,
                        'address' => $contact->address,
                    ],
                ])->save();
            }

            return response()->json([
                'ok' => true,
                'message' => 'Pedido registrado correctamente.',
                'order_number' => $cart->getOrderNumber(),
                'order_id' => $cart->id,
                'pdf_url' => $pdf->signedDownloadUrl($cart),
                'total' => (float) $cart->total,
                'items_count' => $cart->items->count(),
                'contact_name' => $contact->name,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
