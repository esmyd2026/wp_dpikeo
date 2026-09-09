<?php

namespace App\Http\Controllers;

use App\Helpers\WhatsappMessageFormatter;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappCart;
use App\Models\WhatsappCartNote;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\AbandonedCartService;
use App\Services\OrderAdminService;
use App\Services\OrderConfirmationService;
use App\Services\OrderExportService;
use App\Services\OrderLifecycleService;
use App\Services\PaymentProofArchiveService;
use App\Services\WhatsappMediaService;
use App\Services\WhatsappService;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function dashboard()
    {
        $businessProfileId = CompanyContext::current()->businessProfileId();

        $orders = WhatsappCart::forActiveCompany()->with(['items', 'contact'])->latest()->get();
        $messages = WhatsappMessage::when($businessProfileId, fn ($q) => $q->whereHas(
            'contact',
            fn ($c) => $c->where('business_profile_id', $businessProfileId)
        ))->with(['contact', 'conversation'])->latest()->get();

        return view('admin.dashboard', compact('orders', 'messages'));
    }

    public function orders(Request $request)
    {
        $orderSegments = [
            'all' => ['label' => 'Todos', 'statuses' => []],
            'new' => ['label' => 'Nuevos', 'statuses' => [WhatsappCart::STATUS_PENDING]],
            'payment' => ['label' => 'Por pagar', 'statuses' => [WhatsappCart::STATUS_PAYMENT_PENDING]],
            // Es la cola operativa previa a cocina: incluye pedidos ya
            // confirmados y pagos verificados que todavía no se preparan.
            'accepted' => ['label' => 'Por preparar', 'statuses' => [WhatsappCart::STATUS_CONFIRMED, WhatsappCart::STATUS_PAID]],
            'preparing' => ['label' => 'En cocina', 'statuses' => [WhatsappCart::STATUS_PREPARING]],
            'ready' => ['label' => 'Listos', 'statuses' => [WhatsappCart::STATUS_READY]],
            'closed' => ['label' => 'Finalizados', 'statuses' => [WhatsappCart::STATUS_COMPLETED, WhatsappCart::STATUS_CANCELLED]],
        ];
        $activeSegment = array_key_exists((string) $request->query('segment'), $orderSegments)
            ? (string) $request->query('segment')
            : 'all';

        $baseOrders = WhatsappCart::reportable()->forActiveCompany();
        $summaryQuery = (clone $baseOrders)
            ->selectRaw('COUNT(*) AS total_count, MAX(id) AS latest_order_id');

        foreach (OrderLifecycleService::STATUSES as $status) {
            $summaryQuery->selectRaw(
                "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS status_{$status}",
                [$status]
            );
        }

        $summary = $summaryQuery
            ->selectRaw(
                'SUM(CASE WHEN requires_invoice = 1 AND invoice_status IN (?, ?) THEN 1 ELSE 0 END) AS invoice_pending_count',
                ['requested', 'data_ready']
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status IN (?, ?, ?) THEN total ELSE 0 END), 0) AS revenue_total',
                [WhatsappCart::STATUS_CONFIRMED, WhatsappCart::STATUS_COMPLETED, WhatsappCart::STATUS_PAID]
            )
            ->first();

        $statusCounts = collect(OrderLifecycleService::STATUSES)->mapWithKeys(
            fn (string $status) => [$status => (int) ($summary->{"status_{$status}"} ?? 0)]
        );
        $segmentCounts = collect($orderSegments)->mapWithKeys(function (array $segment, string $key) use ($statusCounts, $summary) {
            $count = $segment['statuses'] === []
                ? (int) ($summary->total_count ?? 0)
                : collect($segment['statuses'])->sum(fn (string $status) => $statusCounts->get($status, 0));

            return [$key => $count];
        })->all();

        $ordersQuery = clone $baseOrders;
        if ($orderSegments[$activeSegment]['statuses'] !== []) {
            $ordersQuery->whereIn('status', $orderSegments[$activeSegment]['statuses']);
        }

        $orders = $ordersQuery
            ->with(['items', 'contact', 'branch:id,name,code'])
            ->withCount([
                'notes as internal_notes_count' => fn ($q) => $q->where('type', WhatsappCartNote::TYPE_INTERNAL),
                'notes as feedback_count' => fn ($q) => $q->where('type', WhatsappCartNote::TYPE_FEEDBACK),
            ])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'total' => (int) ($summary->total_count ?? 0),
            'pending' => $statusCounts->get(WhatsappCart::STATUS_PENDING, 0),
            'confirmed' => collect([
                WhatsappCart::STATUS_CONFIRMED, WhatsappCart::STATUS_PREPARING, WhatsappCart::STATUS_READY,
            ])->sum(fn (string $status) => $statusCounts->get($status, 0)),
            'completed' => $statusCounts->get(WhatsappCart::STATUS_COMPLETED, 0),
            'invoice_pending' => (int) ($summary->invoice_pending_count ?? 0),
            'revenue' => (float) ($summary->revenue_total ?? 0),
        ];

        $latestOrderId = (int) ($summary->latest_order_id ?? 0);

        return view('admin.orders', compact(
            'orders',
            'stats',
            'latestOrderId',
            'orderSegments',
            'segmentCounts',
            'activeSegment'
        ));
    }

    /**
     * Sondeo liviano para avisar en el listado de pedidos cuando entra uno
     * nuevo (sin websockets/broadcasting disponibles en este proyecto).
     */
    public function pollNewOrders(Request $request)
    {
        $sinceId = (int) $request->query('since_id', 0);

        // No tiene sentido alertar (ni seguir mostrando en el timbre) un
        // pedido que ya se canceló o ya se entregó -- esos ya no requieren
        // que nadie los abra.
        $orders = WhatsappCart::reportable()->forActiveCompany()
            ->whereNotIn('status', [WhatsappCart::STATUS_CANCELLED, WhatsappCart::STATUS_COMPLETED])
            ->with('contact:id,name')
            ->where('id', '>', $sinceId)
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'contact_id', 'total', 'metadata', 'created_at']);

        $latestId = max($sinceId, (int) (WhatsappCart::reportable()->forActiveCompany()->max('id') ?? 0));

        return response()->json([
            'latest_id' => $latestId,
            'orders' => $orders->map(fn (WhatsappCart $order) => [
                'id' => $order->id,
                'order_number' => $order->getOrderNumber(),
                'contact_name' => $order->contact->name ?? 'Cliente',
                'total' => (float) $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
            ])->values(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function exportOrders(Request $request, OrderExportService $export)
    {
        return $export->downloadResponse($request);
    }

    public function messages()
    {
        $messages = WhatsappMessage::where('business_profile_id', CompanyContext::current()->businessProfileId())
            ->with(['contact', 'conversation'])
            ->latest()
            ->paginate(20);

        return view('admin.messages', compact('messages'));
    }

    public function orderDetails($id, OrderAdminService $orders)
    {
        $order = WhatsappCart::reportable()->forActiveCompany()->findOrFail($id);

        $payload = $orders->orderPayload($order);
        $user = auth()->user();

        // El detalle devuelve únicamente las secciones habilitadas para el
        // rol. Ocultarlas en Blade no debe dejar sus datos expuestos en JSON.
        if (! $user?->hasPermission('orders.billing')) {
            unset(
                $payload['billing'],
                $payload['invoice_data'],
                $payload['requires_invoice'],
                $payload['invoice_status'],
                $payload['invoice_status_label'],
                $payload['agent_checklist'],
                $payload['contact']['billing_type'],
                $payload['contact']['billing_id'],
                $payload['contact']['billing_legal_name'],
            );
        } elseif (! $user?->hasPermission('orders.followup')) {
            $payload['agent_checklist'] = collect($payload['agent_checklist'] ?? [])
                ->reject(fn (array $step) => ($step['key'] ?? null) === 'send')
                ->values();
        }

        $payload['notes'] = collect($payload['notes'] ?? [])
            ->filter(function (array $note) use ($user) {
                return match ($note['type'] ?? null) {
                    WhatsappCartNote::TYPE_INTERNAL => $user?->hasPermission('orders.internal_notes') ?? false,
                    WhatsappCartNote::TYPE_FEEDBACK => $user?->hasPermission('orders.followup') ?? false,
                    default => false,
                };
            })
            ->values();
        $payload['internal_notes_count'] = $user?->hasPermission('orders.internal_notes')
            ? $payload['internal_notes_count']
            : 0;
        $payload['feedback_count'] = $user?->hasPermission('orders.followup')
            ? $payload['feedback_count']
            : 0;

        return response()->json($payload);
    }

    public function orderPaymentProof($id, WhatsappMediaService $mediaService)
    {
        $order = WhatsappCart::reportable()->forActiveCompany()->with(['branch', 'contact'])->findOrFail($id);

        if (! $order->hasPaymentProof()) {
            abort(404);
        }

        $media = app(PaymentProofArchiveService::class)->read($order);
        $message = $order->paymentProofMessage();
        if (! $media && (! $message || ! in_array($message->type, ['image', 'document'], true))) {
            abort(404);
        }

        // Compatibilidad con comprobantes recibidos antes de activar respaldo.
        $media ??= $mediaService->fetchMedia($message);
        if (! $media) {
            abort(404, 'Comprobante no disponible. No se encontró el respaldo y el archivo de WhatsApp pudo expirar.');
        }

        $disposition = str_starts_with($media['content_type'], 'application/pdf') ? 'attachment' : 'inline';
        $filename = $this->buildPaymentProofFilename($order, $media);

        return response($media['body'], 200)
            ->header('Content-Type', $media['content_type'])
            ->header('Content-Disposition', "{$disposition}; filename=\"{$filename}\"")
            ->header('Cache-Control', 'private, max-age=3600');
    }

    /**
     * Nombre descriptivo del comprobante: fecha, número de pedido, sucursal
     * y cliente, con la extensión real del archivo (nunca ".bin").
     */
    private function buildPaymentProofFilename(WhatsappCart $order, array $media): string
    {
        $extension = strtolower(pathinfo($media['filename'] ?? '', PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
            $extension = match (strtolower(explode(';', $media['content_type'] ?? '')[0])) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf',
                default => 'jpg',
            };
        }

        $parts = [
            'Comprobante',
            $order->created_at?->format('Ymd') ?? now()->format('Ymd'),
            $order->getOrderNumber(),
            $order->branch?->name,
            $order->contact?->name,
        ];

        $slug = collect($parts)
            ->filter()
            ->map(fn ($part) => preg_replace('/[^A-Za-z0-9]+/', '-', $part))
            ->map(fn ($part) => trim($part, '-'))
            ->filter()
            ->implode('-');

        return ($slug ?: 'Comprobante').'.'.$extension;
    }

    public function updateOrder(Request $request, $id, OrderAdminService $orders)
    {
        $order = WhatsappCart::reportable()->forActiveCompany()->findOrFail($id);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,confirmed,payment_pending,paid,completed,cancelled'],
            'requires_invoice' => ['nullable', 'boolean'],
            'invoice_status' => ['nullable', 'string', 'in:none,requested,data_ready,issued'],
            'billing_type' => ['nullable', 'string', 'in:cedula,ruc'],
            'billing_id' => ['nullable', 'string', 'max:20'],
            'billing_legal_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'sync_profile' => ['nullable', 'boolean'],
        ]);

        $billingFields = [
            'requires_invoice',
            'invoice_status',
            'billing_type',
            'billing_id',
            'billing_legal_name',
            'address',
            'sync_profile',
        ];
        $updatesBilling = array_intersect($billingFields, array_keys($validated)) !== [];
        abort_if(
            $updatesBilling && ! (auth()->user()?->hasPermission('orders.billing') ?? false),
            403
        );

        $orders->updateOrder($order, $validated, (bool) ($validated['sync_profile'] ?? true));

        return response()->json([
            'success' => true,
            'order' => $orders->orderPayload($order->fresh()),
        ]);
    }

    public function storeOrderNote(Request $request, $id)
    {
        $order = WhatsappCart::reportable()->forActiveCompany()->findOrFail($id);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:internal,feedback'],
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $requiredPermission = $validated['type'] === WhatsappCartNote::TYPE_INTERNAL
            ? 'orders.internal_notes'
            : 'orders.followup';
        abort_unless(auth()->user()?->hasPermission($requiredPermission) ?? false, 403);

        $note = WhatsappCartNote::create([
            'whatsapp_cart_id' => $order->id,
            'user_id' => auth()->id(),
            'type' => $validated['type'],
            'body' => trim($validated['body']),
        ]);

        $note->load('user:id,name');

        return response()->json([
            'success' => true,
            'note' => [
                'id' => $note->id,
                'type' => $note->type,
                'type_label' => $note->typeLabel(),
                'body' => $note->body,
                'author' => $note->user?->name ?? 'Agente',
                'created_at' => $note->created_at,
            ],
        ]);
    }

    public function sendOrderConfirmation(Request $request, $id, OrderConfirmationService $confirmation)
    {
        $order = WhatsappCart::reportable()->forActiveCompany()->with('contact')->findOrFail($id);

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $confirmation->sendToClient(
                $order,
                (int) $request->user()->id,
                $validated['message'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Confirmación enviada al cliente por WhatsApp (PDF + botones).',
                'order' => app(OrderAdminService::class)->orderPayload($order->fresh(['items', 'contact', 'notes.user'])),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('[Orders] No se pudo enviar confirmación', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar la confirmación por WhatsApp.',
            ], 500);
        }
    }

    /**
     * Acción manual de caja: confirma/ajusta el costo de envío y/o el costo
     * para llevar (tarrinas/empaque) de un pedido en un solo paso, y manda
     * un único mensaje de WhatsApp con el total final -- antes eran dos
     * botones separados (uno por costo) que mandaban dos mensajes con dos
     * totales distintos, lo cual confundía al cliente. Cualquiera de los dos
     * campos puede venir vacío si no aplica para este pedido.
     */
    public function sendFulfillmentCosts(Request $request, $id, OrderLifecycleService $lifecycle)
    {
        $order = WhatsappCart::reportable()->forActiveCompany()->with('contact')->findOrFail($id);

        $validated = $request->validate([
            'delivery_fee' => ['required', 'numeric', 'min:0', 'max:1000'],
        ]);

        try {
            $result = $lifecycle->sendFulfillmentCostsMessage($order, (float) $validated['delivery_fee'], (int) $request->user()->id);

            $message = match ($result['reason']) {
                'notification_disabled' => 'Costo guardado. La notificación automática de costos está desactivada para esta empresa.',
                'no_phone' => 'Costo guardado. No se envió mensaje: el pedido no tiene un número de WhatsApp real.',
                'window_closed' => 'Costo guardado. No se envió mensaje: pasaron más de 24h desde el último mensaje del cliente.',
                default => 'Costo guardado y enviado al cliente por WhatsApp.',
            };

            return response()->json([
                'success' => true,
                'sent' => $result['sent'],
                'message' => $message,
                'order' => app(OrderAdminService::class)->orderPayload($result['order']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('[Orders] No se pudo enviar el costo del pedido', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'No se pudo procesar el costo.'], 500);
        }
    }

    public function chats()
    {
        $contacts = $this->getSidebarContacts();
        // Si hay al menos un contacto, redirigir al primer chat
        if ($contacts->count() > 0) {
            return redirect()->route('admin.chat', $contacts->first()->id);
        }

        $botWhatsApp = WhatsappBusinessProfile::publicWhatsAppLink();
        $chatbotConfig = WhatsappChatbotConfig::first();
        if ($botWhatsApp && $chatbotConfig?->bot_name) {
            $botWhatsApp['label'] = $chatbotConfig->bot_name;
        }

        return view('admin.chats', compact('contacts', 'botWhatsApp'));
    }

    public function chat($contactId)
    {
        $contacts = $this->getSidebarContacts((int) $contactId);
        $contact = WhatsappContact::findOrFail($contactId);

        // El id del contacto llega en la URL: sin este chequeo, un admin
        // podría abrir la conversación de otra empresa con solo cambiar el
        // número, aunque el sidebar no se la muestre.
        $activeBusinessProfileId = CompanyContext::current()->businessProfileId();
        abort_unless(
            ! $activeBusinessProfileId || (int) $contact->business_profile_id === (int) $activeBusinessProfileId,
            404
        );
        $messages = WhatsappMessage::where('contact_id', $contactId)
            ->with('adminUser:id,name')
            ->orderBy('created_at')
            ->get();

        // Todas las estadísticas de abajo se calculan en memoria sobre esta
        // misma colección -- antes cada métrica hacía su propia consulta a
        // la base de datos (más de 20 en total), y "tiempo de respuesta"
        // hacía además una consulta POR CADA mensaje del cliente (repetido
        // dentro de un loop de 7 días, y otra vez por cada mensaje del
        // sistema para la tasa de respuesta del cliente): con una
        // conversación de actividad moderada eran cientos de consultas
        // extra solo para abrir un chat. La tabla de mensajes de un
        // contacto es chica (decenas de filas), así que traerla una sola
        // vez y filtrar en memoria es muchísimo más rápido.
        $now = Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sixtyDaysAgo = $now->copy()->subDays(60);

        $isClient = fn ($m) => $m->sender_type === 'client';
        $isSystem = fn ($m) => $m->sender_type === 'system';

        $totalMessages = $messages->count();
        $lastMonth = $messages->filter(fn ($m) => $m->created_at->gte($thirtyDaysAgo))->values();
        $previousMonth = $messages->filter(fn ($m) => $m->created_at->gte($sixtyDaysAgo) && $m->created_at->lt($thirtyDaysAgo))->values();
        $lastMonthMessages = $lastMonth->count();
        $previousMonthMessages = $previousMonth->count();
        $messageGrowth = $previousMonthMessages > 0
            ? round((($lastMonthMessages - $previousMonthMessages) / $previousMonthMessages) * 100, 1)
            : 0;

        // Tasa de respuesta (mensajes del sistema / mensajes del cliente)
        $totalResponses = $lastMonth->filter($isSystem)->count();
        $totalInbound = $lastMonth->filter($isClient)->count();
        $responseRate = $totalInbound > 0 ? round(($totalResponses / $totalInbound) * 100, 1) : 0;

        $previousResponses = $previousMonth->filter($isSystem)->count();
        $previousInbound = $previousMonth->filter($isClient)->count();
        $previousResponseRate = $previousInbound > 0 ? ($previousResponses / $previousInbound) * 100 : 0;
        $responseRateGrowth = $previousResponseRate > 0
            ? round((($responseRate - $previousResponseRate) / $previousResponseRate) * 100, 1)
            : 0;

        // Tiempo promedio de respuesta: para cada mensaje del cliente
        // (últimos 30 días), el próximo mensaje del sistema que le sigue,
        // sin importar el día.
        $nextSystemAfter = $this->nextMessageBySenderType($messages, 'system');
        $responseTimes = [];
        foreach ($messages as $i => $msg) {
            if (! $isClient($msg) || $msg->created_at->lt($thirtyDaysAgo)) {
                continue;
            }
            if ($nextSystemMsg = $nextSystemAfter[$i] ?? null) {
                $responseTimes[] = $msg->created_at->diffInMinutes($nextSystemMsg->created_at);
            }
        }
        $avgResponseTime = count($responseTimes) > 0
            ? round(array_sum($responseTimes) / count($responseTimes), 1).'m'
            : '0m';

        // Mensajes con botones/interactivos
        $isButtonMessage = function ($m) {
            if (in_array($m->type, ['button', 'interactive'], true)) {
                return true;
            }
            $decoded = json_decode((string) $m->content, true);

            return is_array($decoded) && in_array($decoded['type'] ?? null, ['button_reply', 'list_reply'], true);
        };
        $buttonMessages = $lastMonth->filter($isButtonMessage)->count();
        $buttonMessagesRate = $lastMonthMessages > 0 ? round(($buttonMessages / $lastMonthMessages) * 100, 1) : 0;

        // Tasa de interacción
        $interactions = $totalInbound;
        $totalOutbound = $totalResponses;
        $interactionRate = $totalOutbound > 0 ? round(($interactions / $totalOutbound) * 100, 1) : 0;

        // Mensajes por día de la semana (últimos 7 días)
        $messagesByDate = $messages->groupBy(fn ($m) => $m->created_at->format('Y-m-d'));
        $messagesByDay = [];
        $days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $dayName = $days[$date->dayOfWeek];
            $dayMessages = $messagesByDate->get($date->format('Y-m-d')) ?? collect();
            $messagesByDay[] = [
                'day' => $dayName,
                'sent' => $dayMessages->filter($isSystem)->count(),
                'received' => $dayMessages->filter($isClient)->count(),
            ];
        }

        // Tiempo de respuesta por día (el próximo mensaje del sistema, solo si es del mismo día)
        $responseTimeByDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $dayName = $days[$date->dayOfWeek];
            $dayMessages = ($messagesByDate->get($date->format('Y-m-d')) ?? collect())->values();
            $dayNextSystemAfter = $this->nextMessageBySenderType($dayMessages, 'system');

            $dayResponseTimes = [];
            foreach ($dayMessages as $j => $msg) {
                if (! $isClient($msg)) {
                    continue;
                }
                if ($nextSystemMsg = $dayNextSystemAfter[$j] ?? null) {
                    $dayResponseTimes[] = $msg->created_at->diffInMinutes($nextSystemMsg->created_at);
                }
            }

            $responseTimeByDay[] = [
                'day' => $dayName,
                'time' => count($dayResponseTimes) > 0 ? round(array_sum($dayResponseTimes) / count($dayResponseTimes), 1) : 0,
            ];
        }

        // Distribución de tipos de mensajes
        $messageTypes = $lastMonth->groupBy('type')->map->count()->toArray();

        // NUEVOS INDICADORES ÚTILES

        // 1. Mensajes enviados vs recibidos (últimos 30 días)
        $sentMessages = $totalResponses;
        $receivedMessages = $totalInbound;
        $sentReceivedRatio = $receivedMessages > 0 ? round($sentMessages / $receivedMessages, 2) : 0;

        // 2. Última actividad
        $lastMessage = $messages->last();
        $lastActivity = $lastMessage ? $lastMessage->created_at->diffForHumans() : 'Nunca';
        $lastActivityDate = $lastMessage ? $lastMessage->created_at->format('d/m/Y H:i') : 'N/A';

        // 3. Hora de mayor actividad (últimos 30 días)
        $messagesByHour = $lastMonth->groupBy(fn ($m) => $m->created_at->hour)->map->count();
        $peakHour = $messagesByHour->isNotEmpty() ? $messagesByHour->sortDesc()->keys()->first().':00' : 'N/A';

        // 4. Día más activo de la semana (últimos 30 días)
        $weekdayNames = ['', 'Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $messagesByWeekday = $lastMonth->groupBy(fn ($m) => $m->created_at->dayOfWeek + 1)->map->count();
        $mostActiveDay = $messagesByWeekday->isNotEmpty() ? $weekdayNames[$messagesByWeekday->sortDesc()->keys()->first()] : 'N/A';

        // 5. Longitud promedio de mensajes del cliente (últimos 30 días)
        $avgMessageLength = $lastMonth->filter($isClient)->filter(fn ($m) => $m->content !== null)
            ->map(function ($msg) {
                $decoded = json_decode($msg->content, true);
                if (is_array($decoded)) {
                    return isset($decoded['text']) ? strlen($decoded['text']) : strlen($msg->content);
                }

                return strlen($msg->content);
            })
            ->filter(fn ($len) => $len > 0);
        $avgLength = $avgMessageLength->count() > 0
            ? round($avgMessageLength->avg(), 0).' caracteres'
            : '0 caracteres';

        // 6. Número de conversaciones/sesiones (grupos de mensajes con menos de 2 horas entre ellos)
        $conversations = 0;
        $lastMessageTime = null;
        foreach ($messages as $msg) {
            if ($lastMessageTime === null || $msg->created_at->diffInHours($lastMessageTime) >= 2) {
                $conversations++;
            }
            $lastMessageTime = $msg->created_at;
        }

        // 7. Tiempo promedio entre mensajes del cliente (últimos 30 días)
        $clientMessagesList = $lastMonth->filter($isClient)->values();
        $timeBetweenMessages = [];
        for ($i = 1; $i < $clientMessagesList->count(); $i++) {
            $timeBetweenMessages[] = $clientMessagesList[$i - 1]->created_at->diffInMinutes($clientMessagesList[$i]->created_at);
        }
        $avgTimeBetween = count($timeBetweenMessages) > 0
            ? round(array_sum($timeBetweenMessages) / count($timeBetweenMessages), 1)
            : 0;
        $avgTimeBetweenFormatted = $avgTimeBetween > 0
            ? ($avgTimeBetween >= 60 ? round($avgTimeBetween / 60, 1).'h' : $avgTimeBetween.'m')
            : '0m';

        // 8. Tasa de respuesta del cliente (cuánto responde a nuestros mensajes dentro de 24h)
        $systemMessagesList = $lastMonth->filter($isSystem)->values();
        $clientResponses = 0;
        foreach ($systemMessagesList as $sysMsg) {
            $windowEnd = $sysMsg->created_at->copy()->addHours(24);
            $hasReply = $messages->contains(fn ($m) => $isClient($m) && $m->created_at->gt($sysMsg->created_at) && $m->created_at->lte($windowEnd));
            if ($hasReply) {
                $clientResponses++;
            }
        }
        $clientResponseRate = $systemMessagesList->count() > 0
            ? round(($clientResponses / $systemMessagesList->count()) * 100, 1)
            : 0;

        // 9. Frecuencia de mensajes (mensajes por día en últimos 30 días)
        $daysActive = $lastMonth->map(fn ($m) => $m->created_at->format('Y-m-d'))->unique()->count();
        $frequencyPerDay = $daysActive > 0
            ? round($lastMonthMessages / $daysActive, 1)
            : 0;

        // Si es una petición AJAX, devolver JSON
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'contact' => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'phone_number' => $contact->phone_number,
                    'bot_enabled' => $contact->bot_enabled ?? true,
                    'needs_agent' => $contact->needsAgent(),
                ],
                'messages' => $messages->map(function ($msg) {
                    return [
                        'id' => $msg->id,
                        'content' => $msg->content,
                        'type' => $msg->type,
                        'sender_type' => $msg->sender_type,
                        'metadata' => $msg->metadata,
                        'created_at' => $msg->created_at->toDateTimeString(),
                    ];
                }),
                'stats' => [
                    'totalMessages' => $totalMessages,
                    'messageGrowth' => $messageGrowth,
                    'responseRate' => $responseRate.'%',
                    'responseRateGrowth' => $responseRateGrowth,
                    'avgResponseTime' => $avgResponseTime,
                    'buttonMessagesRate' => $buttonMessagesRate.'%',
                    'interactionRate' => $interactionRate.'%',
                    'messagesByDay' => $messagesByDay,
                    'responseTimeByDay' => $responseTimeByDay,
                    'messageTypes' => $messageTypes,
                    // Nuevos indicadores
                    'sentMessages' => $sentMessages,
                    'receivedMessages' => $receivedMessages,
                    'sentReceivedRatio' => $sentReceivedRatio,
                    'lastActivity' => $lastActivity,
                    'lastActivityDate' => $lastActivityDate,
                    'peakHour' => $peakHour,
                    'mostActiveDay' => $mostActiveDay,
                    'avgMessageLength' => $avgLength,
                    'conversations' => $conversations,
                    'avgTimeBetweenMessages' => $avgTimeBetweenFormatted,
                    'clientResponseRate' => $clientResponseRate.'%',
                    'frequencyPerDay' => $frequencyPerDay,
                ],
            ]);
        }

        // Estadísticas para la vista
        $stats = [
            'totalMessages' => $totalMessages,
            'messageGrowth' => $messageGrowth,
            'responseRate' => $responseRate.'%',
            'responseRateGrowth' => $responseRateGrowth,
            'avgResponseTime' => $avgResponseTime,
            'buttonMessagesRate' => $buttonMessagesRate.'%',
            'interactionRate' => $interactionRate.'%',
            'messagesByDay' => $messagesByDay,
            'responseTimeByDay' => $responseTimeByDay,
            'messageTypes' => $messageTypes,
            // Nuevos indicadores
            'sentMessages' => $sentMessages,
            'receivedMessages' => $receivedMessages,
            'sentReceivedRatio' => $sentReceivedRatio,
            'lastActivity' => $lastActivity,
            'lastActivityDate' => $lastActivityDate,
            'peakHour' => $peakHour,
            'mostActiveDay' => $mostActiveDay,
            'avgMessageLength' => $avgLength,
            'conversations' => $conversations,
            'avgTimeBetweenMessages' => $avgTimeBetweenFormatted,
            'clientResponseRate' => $clientResponseRate.'%',
            'frequencyPerDay' => $frequencyPerDay,
        ];

        // Calcular estadísticas globales (todos los chats)
        $globalStats = $this->calculateGlobalStats($now, $thirtyDaysAgo, $sixtyDaysAgo);

        $whatsappService = new WhatsappService;
        $lastInboundWamid = $whatsappService->syncContactLastInbound($contact);
        $typingAvailable = ! empty($lastInboundWamid);

        $chatbotConfig = ($contact->business_profile_id
            ? WhatsappChatbotConfig::where('business_profile_id', $contact->business_profile_id)->first()
            : null) ?? WhatsappChatbotConfig::first();

        return view('admin.chat', compact('contacts', 'contact', 'messages', 'stats', 'globalStats', 'lastInboundWamid', 'typingAvailable', 'chatbotConfig'));
    }

    /**
     * Calcular estadísticas globales de todos los chats
     */
    private function calculateGlobalStats($now, $thirtyDaysAgo, $sixtyDaysAgo)
    {
        // Igual que en chat(): se trae toda la tabla UNA sola vez (es
        // chica, ver nota ahí) y el resto se calcula en memoria -- este
        // método corre en CADA apertura de una conversación (no es un
        // dashboard aparte), así que las mismas consultas por-mensaje que
        // se arreglaron en chat() acá se multiplicaban por todos los
        // contactos de la empresa.
        $allMessages = WhatsappMessage::orderBy('created_at')->get();

        $isClient = fn ($m) => $m->sender_type === 'client';
        $isSystem = fn ($m) => $m->sender_type === 'system';

        $totalMessages = $allMessages->count();
        $lastMonth = $allMessages->filter(fn ($m) => $m->created_at->gte($thirtyDaysAgo))->values();
        $previousMonth = $allMessages->filter(fn ($m) => $m->created_at->gte($sixtyDaysAgo) && $m->created_at->lt($thirtyDaysAgo))->values();
        $lastMonthMessages = $lastMonth->count();
        $previousMonthMessages = $previousMonth->count();
        $messageGrowth = $previousMonthMessages > 0
            ? round((($lastMonthMessages - $previousMonthMessages) / $previousMonthMessages) * 100, 1)
            : 0;

        // Mensajes enviados vs recibidos
        $sentMessages = $lastMonth->filter($isSystem)->count();
        $receivedMessages = $lastMonth->filter($isClient)->count();
        $sentReceivedRatio = $receivedMessages > 0 ? round($sentMessages / $receivedMessages, 2) : 0;

        // Última actividad global
        $lastMessage = $allMessages->last();
        $lastActivity = $lastMessage ? $lastMessage->created_at->diffForHumans() : 'Nunca';
        $lastActivityDate = $lastMessage ? $lastMessage->created_at->format('d/m/Y H:i') : 'N/A';

        // Tiempo promedio de respuesta global: el "próximo mensaje del
        // sistema" tiene que ser del MISMO contacto, así que se agrupa por
        // contacto y se aplica el mismo cálculo que en chat().
        $messagesByContact = $allMessages->groupBy('contact_id');
        $responseTimes = [];
        foreach ($messagesByContact as $contactMessages) {
            $contactMessages = $contactMessages->values();
            $nextSystemAfter = $this->nextMessageBySenderType($contactMessages, 'system');
            foreach ($contactMessages as $i => $msg) {
                if (! $isClient($msg) || $msg->created_at->lt($thirtyDaysAgo)) {
                    continue;
                }
                if ($nextSystemMsg = $nextSystemAfter[$i] ?? null) {
                    $responseTimes[] = $msg->created_at->diffInMinutes($nextSystemMsg->created_at);
                }
            }
        }
        $avgResponseTime = count($responseTimes) > 0
            ? round(array_sum($responseTimes) / count($responseTimes), 1).'m'
            : '0m';

        // Tasa de respuesta del cliente global (dentro de 24h, mismo contacto)
        $systemMessagesList = $lastMonth->filter($isSystem)->values();
        $clientResponses = 0;
        foreach ($systemMessagesList as $sysMsg) {
            $windowEnd = $sysMsg->created_at->copy()->addHours(24);
            $hasReply = ($messagesByContact->get($sysMsg->contact_id) ?? collect())
                ->contains(fn ($m) => $isClient($m) && $m->created_at->gt($sysMsg->created_at) && $m->created_at->lte($windowEnd));
            if ($hasReply) {
                $clientResponses++;
            }
        }
        $clientResponseRate = $systemMessagesList->count() > 0
            ? round(($clientResponses / $systemMessagesList->count()) * 100, 1)
            : 0;

        // Hora pico global
        $messagesByHour = $lastMonth->groupBy(fn ($m) => $m->created_at->hour)->map->count();
        $peakHour = $messagesByHour->isNotEmpty() ? $messagesByHour->sortDesc()->keys()->first().':00' : 'N/A';

        // Día más activo
        $weekdayNames = ['', 'Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $messagesByWeekday = $lastMonth->groupBy(fn ($m) => $m->created_at->dayOfWeek + 1)->map->count();
        $mostActiveDay = $messagesByWeekday->isNotEmpty() ? $weekdayNames[$messagesByWeekday->sortDesc()->keys()->first()] : 'N/A';

        // Total de contactos activos
        $activeContacts = $lastMonth->pluck('contact_id')->unique()->count();

        // Total de conversaciones globales
        $conversations = 0;
        $lastMessageTime = null;
        $lastContactId = null;
        foreach ($allMessages as $msg) {
            if ($lastMessageTime === null || $lastContactId !== $msg->contact_id || $msg->created_at->diffInHours($lastMessageTime) >= 2) {
                $conversations++;
            }
            $lastMessageTime = $msg->created_at;
            $lastContactId = $msg->contact_id;
        }

        // Frecuencia diaria global
        $daysActive = $lastMonth->map(fn ($m) => $m->created_at->format('Y-m-d'))->unique()->count();
        $frequencyPerDay = $daysActive > 0
            ? round($lastMonthMessages / $daysActive, 1)
            : 0;

        // Distribución de tipos de mensajes global
        $messageTypes = $lastMonth->groupBy('type')->map->count()->toArray();

        // Mensajes por día (últimos 7 días) global
        $messagesByDate = $allMessages->groupBy(fn ($m) => $m->created_at->format('Y-m-d'));
        $messagesByDay = [];
        $days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $dayName = $days[$date->dayOfWeek];
            $dayMessages = $messagesByDate->get($date->format('Y-m-d')) ?? collect();
            $messagesByDay[] = [
                'day' => $dayName,
                'sent' => $dayMessages->filter($isSystem)->count(),
                'received' => $dayMessages->filter($isClient)->count(),
            ];
        }

        // Mensajes con botones global
        $isButtonMessage = function ($m) {
            if (in_array($m->type, ['button', 'interactive'], true)) {
                return true;
            }
            $decoded = json_decode((string) $m->content, true);

            return is_array($decoded) && in_array($decoded['type'] ?? null, ['button_reply', 'list_reply'], true);
        };
        $buttonMessages = $lastMonth->filter($isButtonMessage)->count();
        $buttonMessagesRate = $lastMonthMessages > 0 ? round(($buttonMessages / $lastMonthMessages) * 100, 1) : 0;

        // Tasa de interacción global
        $interactions = $receivedMessages;
        $totalOutbound = $sentMessages;
        $interactionRate = $totalOutbound > 0 ? round(($interactions / $totalOutbound) * 100, 1) : 0;

        return [
            'totalMessages' => $totalMessages,
            'messageGrowth' => $messageGrowth,
            'sentMessages' => $sentMessages,
            'receivedMessages' => $receivedMessages,
            'sentReceivedRatio' => $sentReceivedRatio,
            'lastActivity' => $lastActivity,
            'lastActivityDate' => $lastActivityDate,
            'avgResponseTime' => $avgResponseTime,
            'clientResponseRate' => $clientResponseRate.'%',
            'peakHour' => $peakHour,
            'mostActiveDay' => $mostActiveDay,
            'activeContacts' => $activeContacts,
            'conversations' => $conversations,
            'frequencyPerDay' => $frequencyPerDay,
            'messageTypes' => $messageTypes,
            'messagesByDay' => $messagesByDay,
            'buttonMessagesRate' => $buttonMessagesRate.'%',
            'interactionRate' => $interactionRate.'%',
        ];
    }

    /**
     * Para cada mensaje de una colección ya ordenada por created_at
     * ascendente, el próximo mensaje de $senderType que le sigue
     * cronológicamente (o null si no hay ninguno después). Se usa en
     * chat() y calculateGlobalStats() para calcular "tiempo de respuesta"
     * sin hacer una consulta a la base de datos por cada mensaje.
     *
     * @return array<int, WhatsappMessage|null> indexado igual que la colección de entrada (0-based)
     */
    private function nextMessageBySenderType($messages, string $senderType): array
    {
        $messages = $messages->values();
        $map = [];
        $next = null;
        for ($i = $messages->count() - 1; $i >= 0; $i--) {
            $map[$i] = $next;
            if ($messages[$i]->sender_type === $senderType) {
                $next = $messages[$i];
            }
        }

        return $map;
    }

    public function updateOrderStatus(Request $request, $id, OrderLifecycleService $lifecycle)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,payment_pending,paid,preparing,ready,completed,cancelled'],
        ]);

        try {
            $order = WhatsappCart::reportable()->forActiveCompany()->findOrFail($id);
            $note = $validated['status'] === WhatsappCart::STATUS_CANCELLED ? WhatsappCart::CANCEL_REASON_OPERATOR : null;
            $lifecycle->transition($order, $validated['status'], (int) $request->user()->id, $note);

            return response()->json(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Borrado definitivo de un pedido (a diferencia de cancelarlo, que solo
     * cambia el estado). Devuelve el stock reservado si lo había -- sin
     * pasar por la máquina de estados de transition(), porque un pedido
     * 'completed' no tiene ninguna transición permitida hacia 'cancelled' y
     * aun así debe poder devolver stock al borrarse (ver
     * OrderLifecycleService::releaseInventoryIfReserved()). Los ítems y
     * notas del pedido se borran solos por ON DELETE CASCADE; los mensajes
     * de WhatsApp del cliente quedan intactos (son historial de
     * conversación, no del pedido).
     */
    public function destroyOrder(Request $request, $id, OrderLifecycleService $lifecycle)
    {
        $order = WhatsappCart::reportable()->forActiveCompany()->findOrFail($id);
        $orderNumber = $order->getOrderNumber();

        $lifecycle->releaseInventoryIfReserved($order, (int) $request->user()->id);
        $order->delete();

        Log::info('[AdminController] Pedido eliminado definitivamente', [
            'order_number' => $orderNumber,
            'user_id' => $request->user()->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('admin.orders')->with('success', "Pedido {$orderNumber} eliminado correctamente.");
    }

    public function contactDetails($id)
    {
        $contact = WhatsappContact::findOrFail($id);

        return response()->json($contact);
    }

    public function typingIndicator(Request $request)
    {
        $request->validate([
            'contact_id' => 'required|exists:whatsapp_contacts,id',
            'whatsapp_message_id' => 'nullable|string|max:255',
        ]);

        $contact = WhatsappContact::findOrFail($request->contact_id);
        $whatsappService = new WhatsappService;

        try {
            $whatsappService->useBusinessProfile($contact->businessProfile);
        } catch (\Throwable $e) {
            Log::error('[typingIndicator] No se pudo resolver el perfil de WhatsApp del contacto', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'typing_available' => false,
                'message' => 'No se pudo verificar la conexión de WhatsApp de esta empresa.',
            ], 422);
        }

        $wamid = $request->input('whatsapp_message_id');
        if (! $wamid) {
            $wamid = $whatsappService->syncContactLastInbound($contact);
        }

        if (! $wamid) {
            return response()->json([
                'success' => false,
                'typing_available' => false,
                'message' => 'El cliente debe escribir primero por WhatsApp (ventana de 24 h) para mostrar "escribiendo...".',
            ]);
        }

        $sent = $whatsappService->sendTypingIndicator($wamid);

        return response()->json([
            'success' => $sent,
            'typing_available' => true,
            'message' => $sent
                ? 'Indicador de escritura enviado'
                : 'No se pudo enviar el indicador (wamid expirado o inválido)',
        ]);
    }

    public function sendMessage(Request $request)
    {
        // Validar que al menos haya un mensaje, imagen o documento
        $hasMessage = $request->filled('message') && trim($request->input('message')) !== '';
        $hasImage = $request->hasFile('image');
        $hasDocument = $request->hasFile('document');

        if (! $hasMessage && ! $hasImage && ! $hasDocument) {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar un mensaje, imagen o documento.',
            ], 400);
        }

        $request->validate([
            'contact_id' => 'required|exists:whatsapp_contacts,id',
            'message' => 'nullable|string|max:4096',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            'audio' => 'nullable|mimes:mp3,ogg,wav,m4a,aac,webm|max:16384', // 16MB max para audio (incluye webm para grabaciones)
            'document' => 'nullable|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar|max:10240', // 10MB max para documentos
        ]);

        try {
            $contact = WhatsappContact::findOrFail($request->contact_id);
            $whatsappService = new WhatsappService;
            $whatsappService->useBusinessProfile($contact->businessProfile);

            $hasImage = $request->hasFile('image');
            $hasAudio = $request->hasFile('audio');
            $hasDocument = $request->hasFile('document');
            $hasMessage = $request->filled('message') && trim($request->input('message')) !== '';
            $success = false;
            $message = null;

            if ($hasImage) {
                // Guardar la imagen temporalmente
                $imagePath = $request->file('image')->store('temp', 'public');
                $fullPath = storage_path('app/public/'.$imagePath);

                // Enviar imagen con o sin caption (marcar como enviado por humano)
                $success = $whatsappService->sendImageMessage(
                    $contact,
                    $fullPath,
                    $request->input('message'),
                    true // humanSent = true
                );

                // Eliminar archivo temporal
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }

                if ($success) {
                    $message = WhatsappMessage::where('contact_id', $contact->id)
                        ->whereIn('sender_type', ['system', 'humano'])
                        ->where('type', 'image')
                        ->latest()
                        ->first();
                }
            } elseif ($hasAudio) {
                // Guardar el audio temporalmente
                $audioPath = $request->file('audio')->store('temp', 'public');
                $fullPath = storage_path('app/public/'.$audioPath);
                $originalFilename = $request->file('audio')->getClientOriginalName();
                $extension = strtolower($request->file('audio')->getClientOriginalExtension());

                // Verificar formato compatible antes de enviar
                $allowedExtensions = ['mp3', 'ogg', 'wav', 'm4a', 'aac'];
                if (! in_array($extension, $allowedExtensions) && $extension !== 'webm') {
                    // Eliminar archivo temporal
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => 'Formato de audio no compatible. Por favor, usa MP3, OGG, WAV, M4A o AAC.',
                    ], 400);
                }

                // Si es webm, advertir que puede fallar
                if ($extension === 'webm') {
                    Log::warning('Intento de enviar audio WebM', [
                        'contact_id' => $contact->id,
                        'filename' => $originalFilename,
                    ]);
                }

                // Enviar audio (marcar como enviado por humano)
                $success = $whatsappService->sendAudioMessage(
                    $contact,
                    $fullPath,
                    $request->input('message'),
                    true // humanSent = true
                );

                // Eliminar archivo temporal
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }

                if ($success) {
                    $message = WhatsappMessage::where('contact_id', $contact->id)
                        ->whereIn('sender_type', ['system', 'humano'])
                        ->where('type', 'audio')
                        ->latest()
                        ->first();
                } else {
                    // Si falló y es webm, dar mensaje específico
                    if ($extension === 'webm') {
                        return response()->json([
                            'success' => false,
                            'message' => 'WhatsApp no acepta archivos WebM. Por favor, graba en formato OGG o sube un archivo MP3, OGG, WAV, M4A o AAC.',
                        ], 400);
                    }
                }
            } elseif ($hasDocument) {
                // Guardar el documento temporalmente
                $documentPath = $request->file('document')->store('temp', 'public');
                $fullPath = storage_path('app/public/'.$documentPath);
                $originalFilename = $request->file('document')->getClientOriginalName();

                // Enviar documento con o sin caption (marcar como enviado por humano)
                $success = $whatsappService->sendDocumentMessage(
                    $contact,
                    $fullPath,
                    $originalFilename,
                    $request->input('message'),
                    true // humanSent = true
                );

                // Eliminar archivo temporal
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }

                if ($success) {
                    $message = WhatsappMessage::where('contact_id', $contact->id)
                        ->whereIn('sender_type', ['system', 'humano'])
                        ->where('type', 'document')
                        ->latest()
                        ->first();
                }
            } elseif ($hasMessage) {
                // Enviar solo texto
                Log::info('Enviando mensaje de texto', [
                    'contact_id' => $contact->id,
                    'phone' => $contact->phone_number,
                    'message_length' => strlen($request->message),
                ]);

                // Enviar mensaje de texto (marcar como enviado por humano)
                $success = $whatsappService->sendTextMessage($contact, $request->message, true);

                if ($success) {
                    $message = WhatsappMessage::where('contact_id', $contact->id)
                        ->whereIn('sender_type', ['system', 'humano'])
                        ->where('type', 'text')
                        ->latest()
                        ->first();

                    Log::info('Mensaje enviado exitosamente', [
                        'message_id' => $message ? $message->id : null,
                    ]);
                } else {
                    Log::error('Error al enviar mensaje de texto', [
                        'contact_id' => $contact->id,
                    ]);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar un mensaje, imagen, audio o documento.',
                ], 400);
            }

            if ($success && $message) {
                $contact->clearAgentRequest(auth()->id());
                $message->load('adminUser:id,name');

                $responseData = $message->toChatPayload();

                return response()->json([
                    'success' => true,
                    'message' => $responseData,
                    'needs_agent' => false,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el mensaje. Por favor, intenta nuevamente.',
            ], 500);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación: '.implode(', ', $e->errors()),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error sending message from admin chat', [
                'error' => $e->getMessage(),
                'contact_id' => $request->contact_id ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el mensaje: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle el estado del bot para un contacto
     */
    public function toggleBot(Request $request, $contactId)
    {
        try {
            $request->validate([
                'enabled' => 'required|boolean',
            ]);

            $contact = WhatsappContact::findOrFail($contactId);
            $contact->bot_enabled = $request->enabled;
            $contact->save();

            Log::info('Estado del bot actualizado', [
                'contact_id' => $contactId,
                'bot_enabled' => $request->enabled,
            ]);

            return response()->json([
                'success' => true,
                'message' => $request->enabled ? 'Bot activado' : 'Bot desactivado',
                'bot_enabled' => $contact->bot_enabled,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado del bot', [
                'error' => $e->getMessage(),
                'contact_id' => $contactId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado del bot: '.$e->getMessage(),
            ], 500);
        }
    }

    public function dismissAgentRequest($contactId)
    {
        try {
            $contact = WhatsappContact::findOrFail($contactId);
            $contact->clearAgentRequest(auth()->id());

            return response()->json([
                'success' => true,
                'needs_agent' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al descartar solicitud de asesor', [
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo marcar como atendido.',
            ], 500);
        }
    }

    /**
     * "Saca" a un cliente de un pedido a medias/atascado: cancela su carrito
     * activo o pendiente de comprobante y reinicia su posición en el flujo,
     * para que su próximo mensaje empiece limpio. No toca pedidos ya
     * confirmados/pagados -- esos se cancelan desde el módulo de Pedidos.
     */
    public function resetConversation($contactId, AbandonedCartService $abandonedCarts)
    {
        try {
            $contact = WhatsappContact::findOrFail($contactId);

            $cart = WhatsappCart::where('contact_id', $contact->id)
                ->whereIn('status', ['active', WhatsappCart::STATUS_PAYMENT_PENDING])
                ->latest()
                ->first();

            // Bug real: un carrito "payment_pending" con el comprobante ya
            // recibido (falta que caja lo verifique) o con el costo de envío
            // sin confirmar es un pedido real en curso, no uno "atascado" --
            // este botón lo cancelaba igual, aunque el cliente ya hubiera
            // hecho su parte. Mismo criterio que ya usa el timeout automático
            // (ver WhatsappCart::isWaitingOnBusiness): si de verdad hay que
            // cancelarlo, se hace desde el módulo de Pedidos, con intención.
            if ($cart && $cart->isWaitingOnBusiness()) {
                $cart = null;
            }

            $closed = $cart ? $abandonedCarts->close($cart) : false;

            if (! $cart) {
                // No había un pedido a medias que cancelar, pero igual puede
                // estar "atascado" en un nodo del flujo visual (grafo), o con
                // alguna bandera de "esperando una respuesta de texto" viva
                // en otro pedido suyo que no se tocó arriba.
                $contact->forgetFlowPosition();
                $contact->forgetPrivacyNoticeSent();
                $abandonedCarts->clearLingeringInteractionFlags($contact);
            }

            return response()->json([
                'success' => true,
                'cart_closed' => $closed,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al reiniciar conversación', [
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo reiniciar la conversación.',
            ], 500);
        }
    }

    public function getContactsList(Request $request)
    {
        $currentContactId = $request->input('current_contact_id');

        $contacts = $this->getSidebarContacts($currentContactId ? (int) $currentContactId : null);

        return response()->json([
            'success' => true,
            'agent_requests_count' => $contacts->where('needs_agent', true)->count(),
            'contacts' => $contacts->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'name' => $contact->name ?? 'Cliente',
                    'phone_number' => $contact->phone_number,
                    'last_client_message' => $contact->last_message_preview,
                    'last_message_preview' => $contact->last_message_preview,
                    'last_message_date' => $contact->last_message_date ? $contact->last_message_date->toIso8601String() : null,
                    'last_message_timestamp' => $contact->last_message_timestamp ?? null,
                    'last_message_sort' => $contact->last_message_sort ?? 0,
                    'last_message_label' => $contact->last_message_label ?? '',
                    'has_new_message' => $contact->has_new_message ?? false,
                    'needs_agent' => $contact->needs_agent ?? false,
                ];
            }),
        ]);
    }

    /**
     * Polling ligero para alertas de asesor (cualquier pantalla del admin).
     */
    public function pollAgentRequests()
    {
        $requests = WhatsappContact::query()
            ->where('business_profile_id', CompanyContext::current()->businessProfileId())
            ->whereRaw("JSON_EXTRACT(metadata, '$.needs_agent') = true")
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'phone_number', 'metadata']);

        return response()->json([
            'success' => true,
            'count' => $requests->count(),
            'requests' => $requests->map(function (WhatsappContact $contact) {
                $metadata = $contact->metadata ?? [];

                return [
                    'id' => $contact->id,
                    'name' => $contact->name ?? 'Cliente',
                    'phone_number' => $contact->phone_number,
                    'requested_at' => $metadata['agent_requested_at'] ?? null,
                    'alert_token' => $metadata['agent_requested_at'] ?? ('contact-'.$contact->id),
                ];
            })->values(),
        ]);
    }

    /**
     * Contactos del sidebar ordenados por último mensaje (más reciente primero).
     */
    private function getSidebarContacts(?int $currentContactId = null)
    {
        $businessProfileId = CompanyContext::current()->businessProfileId();

        // whereIn('id', SELECT DISTINCT contact_id FROM whatsapp_messages)
        // escaneaba TODA la tabla de mensajes de la plataforma (de cualquier
        // empresa) en cada carga/poll de la lista, sin usar el filtro de
        // empresa -- whereHas('messages') aplica el filtro de empresa
        // primero y solo verifica existencia (EXISTS) por contacto candidato,
        // en vez de deduplicar contact_id sobre toda la tabla.
        $contacts = WhatsappContact::query()
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->whereHas('messages')
            ->with(['latestMessage'])
            ->withMax('messages as last_message_at', 'created_at')
            ->orderByDesc('last_message_at')
            ->get();

        return $this->enrichContactsForSidebar($contacts, $currentContactId);
    }

    /**
     * Datos de sidebar: último mensaje (cualquier emisor), preview y fecha.
     */
    private function enrichContactsForSidebar($contacts, ?int $currentContactId = null)
    {
        if ($contacts->isEmpty()) {
            return $contacts;
        }

        foreach ($contacts as $contact) {
            $lastMsg = $contact->latestMessage;
            $lastAt = $contact->last_message_at ?? $lastMsg?->created_at;

            $contact->last_message_date = $lastAt ? Carbon::parse($lastAt) : null;
            $contact->last_message_timestamp = $contact->last_message_date?->toIso8601String();
            $contact->last_message_sort = $contact->last_message_date?->getTimestamp() ?? 0;
            $contact->last_message_label = WhatsappMessageFormatter::formatSidebarDateTime($contact->last_message_date);
            $contact->last_message_preview = $lastMsg
                ? WhatsappMessageFormatter::displayText($lastMsg->content, $lastMsg->type, $lastMsg->metadata ?? [])
                : null;
            $contact->last_client_message = $contact->last_message_preview;

            $contact->has_new_message = false;
            if ($lastMsg && $lastMsg->sender_type === 'client' && $currentContactId !== (int) $contact->id) {
                $contact->has_new_message = $lastMsg->created_at->isAfter(now()->subHours(24));
            }

            $contact->needs_agent = $contact->needsAgent();
        }

        return $contacts->sort(function ($a, $b) {
            $aAgent = ! empty($a->needs_agent) ? 1 : 0;
            $bAgent = ! empty($b->needs_agent) ? 1 : 0;

            if ($aAgent !== $bAgent) {
                return $bAgent <=> $aAgent;
            }

            return ($b->last_message_sort ?? 0) <=> ($a->last_message_sort ?? 0);
        })->values();
    }

    public function getNewMessages($contactId, Request $request)
    {
        try {
            $contact = WhatsappContact::query()
                ->where('business_profile_id', CompanyContext::current()->businessProfileId())
                ->find($contactId);
            if (! $contact) {
                return response()->json(['success' => false, 'message' => 'Conversación no encontrada'], 404);
            }
            $lastMessageId = $request->input('last_message_id', 0);
            $lastTimestamp = $request->input('last_timestamp');

            $query = WhatsappMessage::where('contact_id', $contact->id);

            // Si hay un timestamp, filtrar por mensajes más recientes
            if ($lastTimestamp) {
                $query->where('created_at', '>', $lastTimestamp)->limit(100);
            } elseif ($lastMessageId > 0) {
                // Si solo hay un ID, obtener mensajes después de ese ID. Sin
                // el límite, una conversación con un backlog grande (o un
                // last_message_id que quedó atascado del lado del navegador)
                // devolvía TODOS los mensajes nuevos de una sola vez -- se
                // vieron respuestas de más de 500 KB repitiéndose en cada
                // poll de 2 segundos. Con el límite, el cliente se pone al
                // día en tandas en vez de todo de golpe.
                $query->where('id', '>', $lastMessageId)->limit(100);
            } else {
                // Si no hay parámetros, obtener los últimos 10 mensajes
                $query->latest()->limit(10);
            }

            $newMessages = $query->with('adminUser:id,name')->orderBy('created_at')->get();

            return response()->json([
                'success' => true,
                'messages' => $newMessages->map(fn ($msg) => $msg->toChatPayload()),
                'count' => $newMessages->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo nuevos mensajes', [
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener nuevos mensajes',
            ], 500);
        }
    }

    public function getImage($messageId)
    {
        try {
            $message = WhatsappMessage::findOrFail($messageId);

            if ($message->type !== 'image') {
                // Retornar placeholder en lugar de error JSON
                $placeholderSvg = '<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#202c33"/><text x="150" y="100" text-anchor="middle" fill="#8696a0" font-family="Arial" font-size="14">No es una imagen</text></svg>';

                return response($placeholderSvg, 404)
                    ->header('Content-Type', 'image/svg+xml')
                    ->header('Cache-Control', 'no-cache');
            }

            $metadata = $message->metadata ?? [];
            $contentData = WhatsappMessageFormatter::parseJsonContent($message->content) ?? [];
            $mediaId = $metadata['media_id']
                ?? $metadata['image_id']
                ?? $contentData['media_id']
                ?? $contentData['image_id']
                ?? $contentData['id']
                ?? null;

            if (! $mediaId) {
                // Retornar placeholder en lugar de error JSON
                $placeholderSvg = '<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#202c33"/><text x="150" y="100" text-anchor="middle" fill="#8696a0" font-family="Arial" font-size="14">Imagen no disponible</text></svg>';

                return response($placeholderSvg, 404)
                    ->header('Content-Type', 'image/svg+xml')
                    ->header('Cache-Control', 'no-cache');
            }

            // Obtener la URL de la imagen desde WhatsApp Media API -- con el
            // token de la empresa dueña de este mensaje, nunca el global.
            $mediaToken = $message->businessProfile?->access_token;
            $response = Http::withToken($mediaToken)
                ->timeout(5) // Timeout corto para no bloquear
                ->get('https://graph.facebook.com/'.config('whatsapp.api_version', 'v22.0')."/{$mediaId}");

            if (! $response->successful()) {
                // Para cualquier error, retornar placeholder silenciosamente
                $placeholderSvg = '<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#202c33"/><text x="150" y="100" text-anchor="middle" fill="#8696a0" font-family="Arial" font-size="14">Imagen no disponible</text></svg>';

                return response($placeholderSvg, 404)
                    ->header('Content-Type', 'image/svg+xml')
                    ->header('Cache-Control', 'no-cache');
            }

            $mediaData = $response->json();
            $imageUrl = $mediaData['url'] ?? null;

            if (! $imageUrl) {
                // Retornar placeholder en lugar de error
                $placeholderSvg = '<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#202c33"/><text x="150" y="100" text-anchor="middle" fill="#8696a0" font-family="Arial" font-size="14">Imagen no disponible</text></svg>';

                return response($placeholderSvg, 404)
                    ->header('Content-Type', 'image/svg+xml')
                    ->header('Cache-Control', 'no-cache');
            }

            // Descargar la imagen desde WhatsApp
            $imageResponse = Http::withToken($mediaToken)
                ->timeout(5) // Timeout corto
                ->get($imageUrl);

            if (! $imageResponse->successful()) {
                // Retornar placeholder en lugar de error
                $placeholderSvg = '<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#202c33"/><text x="150" y="100" text-anchor="middle" fill="#8696a0" font-family="Arial" font-size="14">Imagen no disponible</text></svg>';

                return response($placeholderSvg, 404)
                    ->header('Content-Type', 'image/svg+xml')
                    ->header('Cache-Control', 'no-cache');
            }

            // Obtener el tipo de contenido
            $contentType = $imageResponse->header('Content-Type') ?? 'image/jpeg';

            // Retornar la imagen con los headers correctos
            return response($imageResponse->body(), 200)
                ->header('Content-Type', $contentType)
                ->header('Cache-Control', 'public, max-age=3600');

        } catch (\Exception $e) {
            // No loguear errores de imágenes expiradas para evitar spam en logs
            // Solo retornar placeholder silenciosamente
            $placeholderSvg = '<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#202c33"/><text x="150" y="100" text-anchor="middle" fill="#8696a0" font-family="Arial" font-size="14">Imagen no disponible</text></svg>';

            return response($placeholderSvg, 404)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Cache-Control', 'no-cache');
        }
    }
}
