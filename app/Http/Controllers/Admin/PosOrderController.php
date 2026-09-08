<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessBranch;
use App\Models\WhatsappContact;
use App\Services\BulkOrderService;
use App\Services\OrderPdfService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pantalla simplificada de punto de venta para pedidos de mostrador/mesa:
 * solo nombre del cliente, productos, llevar/servir y forma de pago. El
 * pedido cae en la misma ventana de "Pedidos" para que caja lo confirme.
 */
class PosOrderController extends Controller
{
    public function __construct(
        private BulkOrderService $bulkOrders
    ) {}

    public function create(Request $request): View
    {
        $activeCompany = CompanyContext::current()->company;

        $branches = BusinessBranch::query()
            ->forUserAccess($request->user(), CompanyContext::current()->businessProfileId())
            ->availableForOrders()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $sessionBranchId = (int) $request->session()->get('pos_branch_id');
        $defaultBranchId = $branches->contains('id', $sessionBranchId)
            ? $sessionBranchId
            : $branches->first()?->id;

        return view('pos.kiosk', [
            'catalogUrl' => route('admin.orders.bulk.catalog'),
            'contactsCreateUrl' => route('admin.orders.bulk.contacts.store'),
            'submitUrl' => route('pos.submit'),
            'branches' => $branches,
            'defaultBranchId' => $defaultBranchId,
            'headerTitle' => $activeCompany?->name,
        ]);
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
            'service_type' => ['required', 'string', 'in:llevar,servir'],
            'table_reference' => ['nullable', 'string', 'max:120'],
            'payment_method' => ['required', 'string', 'in:efectivo,transferencia,tarjeta'],
            'branch_id' => ['nullable', 'integer', Rule::exists('business_branches', 'id')
                ->where('business_profile_id', CompanyContext::current()->businessProfileId())
                ->where('is_active', true)
                ->where('orders_enabled', true)],
        ]);

        $contact = WhatsappContact::query()
            ->where('business_profile_id', CompanyContext::current()->businessProfileId())
            ->findOrFail($validated['contact_id']);

        $accessibleBranches = BusinessBranch::query()
            ->forUserAccess($request->user(), CompanyContext::current()->businessProfileId())
            ->availableForOrders()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get(['id']);
        $requestedBranchId = $validated['branch_id'] ?? null;
        $sessionBranchId = (int) $request->session()->get('pos_branch_id');
        $branchId = $requestedBranchId
            ?? ($accessibleBranches->contains('id', $sessionBranchId) ? $sessionBranchId : null)
            ?? ($accessibleBranches->count() === 1 ? $accessibleBranches->first()->id : null);

        if ($branchId) {
            $branch = BusinessBranch::findOrFail($branchId);
            abort_unless($request->user()->canAccessBranch($branch), 403);
        }

        try {
            $cart = $this->bulkOrders->submitFromAdmin(
                $contact,
                $validated['items'],
                $validated['order_note'] ?? null,
                (int) $request->user()->id,
                false,
                $branchId,
                [
                    'service_type' => $validated['service_type'],
                    // "Para llevar" en el POS solo admite retiro en barra;
                    // "para servir" se indica con la mesa/referencia dada.
                    'pickup_mode' => $validated['service_type'] === 'llevar' ? 'retiro' : null,
                    'table_reference' => $validated['table_reference'] ?? null,
                ],
                $validated['payment_method'],
            );

            $request->session()->put('pos_branch_id', $branchId);

            return response()->json([
                'ok' => true,
                'order_number' => $cart->getOrderNumber(),
                'order_id' => $cart->id,
                'total' => (float) $cart->total,
                'pdf_url' => $pdf->signedDownloadUrl($cart),
                'contact_name' => $contact->name,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
