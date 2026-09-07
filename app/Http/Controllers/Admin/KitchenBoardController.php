<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappCart;
use App\Services\DailyOrderNumberService;
use App\Services\OrderLifecycleService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tablero operativo de comandas.
 *
 * Caja confirma, cocina prepara y despacho entrega. El tablero no muestra
 * datos sensibles del cliente en la pantalla pública interna (TV); solo lo
 * necesario para producir y llamar el número del pedido.
 */
class KitchenBoardController extends Controller
{
    public function index(): View
    {
        return view('admin.kitchen.index', [
            'activeCompany' => CompanyContext::current()->company,
        ]);
    }

    public function display(): View
    {
        return view('admin.kitchen.display', [
            'activeCompany' => CompanyContext::current()->company,
        ]);
    }

    /**
     * Comanda térmica para cocina. Se abre en una ventana limpia y activa
     * el diálogo del navegador para la impresora configurada en cada estación.
     */
    public function print(int $id): View
    {
        $order = WhatsappCart::reportable()
            ->forActiveCompany()
            ->with(['items.product', 'contact', 'branch'])
            ->findOrFail($id);

        return view('admin.kitchen.ticket', [
            'order' => $this->mapOrder($order),
            'activeCompany' => CompanyContext::current()->company,
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json([
            'updated_at' => now()->toIso8601String(),
            'orders' => $this->ordersPayload(),
        ]);
    }

    public function transition(Request $request, int $id, OrderLifecycleService $lifecycle): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:preparing,ready,completed'],
        ]);

        $order = WhatsappCart::reportable()->forActiveCompany()->findOrFail($id);

        try {
            $order = $lifecycle->transition($order, $validated['status'], (int) $request->user()->id);

            return response()->json([
                'success' => true,
                'order' => $this->mapOrder($order->load('items.product')),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function resetTurns(Request $request, DailyOrderNumberService $turns): JsonResponse
    {
        $operationalStatuses = [
            WhatsappCart::STATUS_PENDING,
            WhatsappCart::STATUS_PAYMENT_PENDING,
            WhatsappCart::STATUS_CONFIRMED,
            WhatsappCart::STATUS_PAID,
            WhatsappCart::STATUS_PREPARING,
            WhatsappCart::STATUS_READY,
        ];

        if (WhatsappCart::query()->forActiveCompany()->whereIn('status', $operationalStatuses)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes reiniciar: todavía existen pedidos operativos. Entrégalos o cancélalos primero.',
            ], 422);
        }

        $turns->resetToday((int) $request->user()->id);

        return response()->json(['success' => true, 'message' => 'Contador diario reiniciado. El próximo pedido será el turno 001.']);
    }

    /** @return array<int, array<string, mixed>> */
    private function ordersPayload(): array
    {
        return WhatsappCart::reportable()->forActiveCompany()
            ->whereIn('status', [
                WhatsappCart::STATUS_CONFIRMED,
                WhatsappCart::STATUS_PAID,
                WhatsappCart::STATUS_PREPARING,
                WhatsappCart::STATUS_READY,
            ])
            ->with(['items.product', 'contact', 'branch'])
            ->orderByRaw("CASE status WHEN 'confirmed' THEN 1 WHEN 'paid' THEN 2 WHEN 'preparing' THEN 3 WHEN 'ready' THEN 4 ELSE 5 END")
            ->orderBy('created_at')
            ->get()
            ->map(fn (WhatsappCart $order) => $this->mapOrder($order))
            ->all();
    }

    /** @return array<string, mixed> */
    private function mapOrder(WhatsappCart $order): array
    {
        $statusLabels = [
            WhatsappCart::STATUS_CONFIRMED => 'En cola',
            WhatsappCart::STATUS_PAID => 'En cola',
            WhatsappCart::STATUS_PREPARING => 'En preparación',
            WhatsappCart::STATUS_READY => 'Listo',
            WhatsappCart::STATUS_COMPLETED => 'Entregado',
        ];

        // Carbon 3 entrega diffInMinutes como decimal. Para operación y TV
        // siempre mostramos tiempos completos, no fracciones interminables.
        $elapsedMinutes = $order->created_at
            ? max(0, (int) floor($order->created_at->diffInMinutes(now())))
            : 0;

        return [
            'id' => $order->id,
            'number' => $order->getOrderNumber(),
            'turn_number' => $this->turnNumber($order),
            'display_number' => $this->turnNumber($order),
            'status' => $order->status,
            'status_label' => $statusLabels[$order->status] ?? ucfirst((string) $order->status),
            'created_at' => $order->created_at?->toIso8601String(),
            'elapsed_minutes' => $elapsedMinutes,
            'elapsed_label' => $this->elapsedLabel($elapsedMinutes),
            'customer' => [
                'name' => $order->contact?->name ?: 'Cliente de mostrador',
                'phone' => $order->contact?->phone_number,
                'address' => $order->contact?->address,
            ],
            'branch' => $order->branch?->name ?: 'Matriz',
            'order_note' => trim((string) $order->note),
            'items' => $order->items->map(fn ($item) => [
                'quantity' => (int) $item->quantity,
                'name' => $item->name,
                'note' => trim((string) $item->line_note),
            ])->values()->all(),
        ];
    }

    private function turnNumber(WhatsappCart $order): string
    {
        $assigned = (string) ($order->metadata['order_details']['turn_number'] ?? '');
        if (preg_match('/^\d{1,3}$/', $assigned)) {
            return str_pad($assigned, 3, '0', STR_PAD_LEFT);
        }

        preg_match('/(\d{1,3})$/', $order->getOrderNumber(), $matches);

        return isset($matches[1]) ? str_pad($matches[1], 3, '0', STR_PAD_LEFT) : '---';
    }

    private function elapsedLabel(int $minutes): string
    {
        if ($minutes < 1) {
            return 'Recién ingresado';
        }

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $remainingMinutes > 0
            ? $hours.' h '.$remainingMinutes.' min'
            : $hours.' h';
    }
}
