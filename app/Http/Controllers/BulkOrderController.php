<?php

namespace App\Http\Controllers;

use App\Models\BusinessBranch;
use App\Services\BulkOrderService;
use App\Services\OrderPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BulkOrderController extends Controller
{
    public function show(string $token, BulkOrderService $bulkOrders): View
    {
        $record = $bulkOrders->findValidToken($token);

        abort_unless($record, 404);

        $contact = $record->contact;
        $businessProfile = $contact->businessProfile;
        $branches = BusinessBranch::query()
            ->where('business_profile_id', $contact->business_profile_id)
            ->availableForOrders()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_default']);

        return view('bulk-order.show', [
            'token' => $token,
            'contactName' => $contact->name ?? 'Cliente',
            'businessName' => $businessProfile?->business_name ?: 'Pedido en línea',
            'expiresAt' => $record->expires_at,
            'existingCartItems' => $bulkOrders->existingCartItems($contact),
            'branches' => $branches,
            // Keep the API calls on the exact host where the storefront was opened.
            // This avoids a public ngrok page trying to fetch its catalog from localhost.
            'catalogUrl' => route('bulk-order.catalog', ['token' => $token], false),
            'submitUrl' => route('bulk-order.submit', ['token' => $token], false),
        ]);
    }

    public function catalog(string $token, Request $request, BulkOrderService $bulkOrders): JsonResponse
    {
        $record = $bulkOrders->findValidToken($token);
        abort_unless($record, 404);

        $payload = $bulkOrders->catalogPayload(
            $request->integer('category') ?: null,
            $request->string('q')->toString() ?: null,
            $record->contact->business_profile_id
        );

        return response()->json($payload);
    }

    public function submit(string $token, Request $request, BulkOrderService $bulkOrders, OrderPdfService $pdf): JsonResponse
    {
        $record = $bulkOrders->findValidToken($token);
        abort_unless($record, 404);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.variation' => ['nullable', 'string', 'max:120'],
            'items.*.extras' => ['nullable', 'array', 'max:20'],
            'items.*.extras.*' => ['string', 'max:120'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'order_note' => ['nullable', 'string', 'max:1000'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        try {
            $cart = $bulkOrders->submitFromForm(
                $record,
                $validated['items'],
                $validated['order_note'] ?? null,
                $validated['branch_id'] ?? null,
            );

            $bulkOrders->notifyContactViaWhatsapp($cart);

            return response()->json([
                'ok' => true,
                'message' => 'Pedido registrado. Revisa WhatsApp: te enviamos el número de pedido.',
                'order_number' => $cart->getOrderNumber(),
                'order_id' => $cart->id,
                'pdf_url' => $pdf->signedDownloadUrl($cart),
                'total' => (float) $cart->total,
                'items_count' => $cart->items->count(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
