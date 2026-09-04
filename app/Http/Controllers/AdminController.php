<?php

namespace App\Http\Controllers;

use App\Models\WhatsappCart;
use App\Models\WhatsappCartNote;
use App\Models\WhatsappMessage;
use App\Models\WhatsappContact;
use App\Models\WhatsappConversation;
use App\Services\OrderAdminService;
use App\Services\OrderConfirmationService;
use App\Services\OrderExportService;
use App\Services\WhatsappMediaService;
use App\Services\WhatsappService;
use App\Helpers\WhatsappMessageFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function dashboard()
    {
        $businessProfileId = \App\Support\CompanyContext::current()->businessProfileId();

        $orders = WhatsappCart::forActiveCompany()->with(['items', 'contact'])->latest()->get();
        $messages = WhatsappMessage::when($businessProfileId, fn ($q) => $q->whereHas(
            'contact',
            fn ($c) => $c->where('business_profile_id', $businessProfileId)
        ))->with(['contact', 'conversation'])->latest()->get();

        return view('admin.dashboard', compact('orders', 'messages'));
    }

    public function orders()
    {
        $orders = WhatsappCart::reportable()->forActiveCompany()
            ->with(['items', 'contact'])
            ->withCount([
                'notes as internal_notes_count' => fn ($q) => $q->where('type', WhatsappCartNote::TYPE_INTERNAL),
                'notes as feedback_count' => fn ($q) => $q->where('type', WhatsappCartNote::TYPE_FEEDBACK),
            ])
            ->latest()
            ->paginate(10);

        $stats = [
            'total' => WhatsappCart::reportable()->forActiveCompany()->count(),
            'pending' => WhatsappCart::reportable()->forActiveCompany()->where('status', WhatsappCart::STATUS_PENDING)->count(),
            'confirmed' => WhatsappCart::reportable()->forActiveCompany()->whereIn('status', [
                WhatsappCart::STATUS_CONFIRMED,
                WhatsappCart::STATUS_PREPARING,
                WhatsappCart::STATUS_READY,
            ])->count(),
            'completed' => WhatsappCart::reportable()->forActiveCompany()->where('status', WhatsappCart::STATUS_COMPLETED)->count(),
            'invoice_pending' => WhatsappCart::reportable()->forActiveCompany()
                ->where('requires_invoice', true)
                ->whereIn('invoice_status', ['requested', 'data_ready'])
                ->count(),
            'revenue' => WhatsappCart::reportable()->forActiveCompany()->whereIn('status', [
                WhatsappCart::STATUS_CONFIRMED,
                WhatsappCart::STATUS_COMPLETED,
                WhatsappCart::STATUS_PAID,
            ])->sum('total'),
        ];

        $latestOrderId = (int) (WhatsappCart::reportable()->forActiveCompany()->max('id') ?? 0);

        return view('admin.orders', compact('orders', 'stats', 'latestOrderId'));
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
        $messages = WhatsappMessage::with(['contact', 'conversation'])
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
        if (!$user?->hasPermission('orders.billing')) {
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
        } elseif (!$user?->hasPermission('orders.followup')) {
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

        if (!$order->hasPaymentProof()) {
            abort(404);
        }

        $media = app(\App\Services\PaymentProofArchiveService::class)->read($order);
        $message = $order->paymentProofMessage();
        if (!$media && (!$message || !in_array($message->type, ['image', 'document'], true))) {
            abort(404);
        }

        // Compatibilidad con comprobantes recibidos antes de activar respaldo.
        $media ??= $mediaService->fetchMedia($message);
        if (!$media) {
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
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
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

        return ($slug ?: 'Comprobante') . '.' . $extension;
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
            $updatesBilling && !(auth()->user()?->hasPermission('orders.billing') ?? false),
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
    public function sendFulfillmentCosts(Request $request, $id, \App\Services\OrderLifecycleService $lifecycle)
    {
        $order = WhatsappCart::reportable()->forActiveCompany()->with('contact')->findOrFail($id);

        $validated = $request->validate([
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'pickup_fee' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $deliveryFee = array_key_exists('delivery_fee', $validated) && $validated['delivery_fee'] !== null
            ? (float) $validated['delivery_fee']
            : null;
        $pickupFee = array_key_exists('pickup_fee', $validated) && $validated['pickup_fee'] !== null
            ? (float) $validated['pickup_fee']
            : null;

        try {
            $result = $lifecycle->sendFulfillmentCostsMessage($order, $deliveryFee, $pickupFee, (int) $request->user()->id);

            $message = match ($result['reason']) {
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

        $botWhatsApp = \App\Models\WhatsappBusinessProfile::publicWhatsAppLink();
        $chatbotConfig = \App\Models\WhatsappChatbotConfig::first();
        if ($botWhatsApp && $chatbotConfig?->bot_name) {
            $botWhatsApp['label'] = $chatbotConfig->bot_name;
        }

        return view('admin.chats', compact('contacts', 'botWhatsApp'));
    }

    public function chat($contactId)
    {
        $contacts = $this->getSidebarContacts((int) $contactId);
        $contact = \App\Models\WhatsappContact::findOrFail($contactId);

        // El id del contacto llega en la URL: sin este chequeo, un admin
        // podría abrir la conversación de otra empresa con solo cambiar el
        // número, aunque el sidebar no se la muestre.
        $activeBusinessProfileId = \App\Support\CompanyContext::current()->businessProfileId();
        abort_unless(
            !$activeBusinessProfileId || (int) $contact->business_profile_id === (int) $activeBusinessProfileId,
            404
        );
        $messages = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->with('adminUser:id,name')
            ->orderBy('created_at')
            ->get();

        // Calcular estadísticas del contacto actual
        $now = \Carbon\Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sixtyDaysAgo = $now->copy()->subDays(60);

        // Total de mensajes del contacto
        $totalMessages = \App\Models\WhatsappMessage::where('contact_id', $contactId)->count();
        $lastMonthMessages = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('created_at', '>=', $thirtyDaysAgo)->count();
        $previousMonthMessages = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])->count();
        $messageGrowth = $previousMonthMessages > 0
            ? round((($lastMonthMessages - $previousMonthMessages) / $previousMonthMessages) * 100, 1)
            : 0;

        // Tasa de respuesta (mensajes del sistema / mensajes del cliente)
        $totalResponses = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'system')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        $totalInbound = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'client')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        $responseRate = $totalInbound > 0 ? round(($totalResponses / $totalInbound) * 100, 1) : 0;

        $previousResponses = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'system')
            ->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->count();
        $previousInbound = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'client')
            ->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->count();
        $previousResponseRate = $previousInbound > 0 ? ($previousResponses / $previousInbound) * 100 : 0;
        $responseRateGrowth = $previousResponseRate > 0
            ? round((($responseRate - $previousResponseRate) / $previousResponseRate) * 100, 1)
            : 0;

        // Tiempo promedio de respuesta (calcular tiempo entre mensaje del cliente y respuesta del sistema)
        $responseTimes = [];
        $clientMessages = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'client')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->orderBy('created_at')
            ->get();

        foreach ($clientMessages as $clientMsg) {
            $nextSystemMsg = \App\Models\WhatsappMessage::where('contact_id', $contactId)
                ->where('sender_type', 'system')
                ->where('created_at', '>', $clientMsg->created_at)
                ->orderBy('created_at')
                ->first();

            if ($nextSystemMsg) {
                $responseTimes[] = $clientMsg->created_at->diffInMinutes($nextSystemMsg->created_at);
            }
        }

        $avgResponseTime = count($responseTimes) > 0
            ? round(array_sum($responseTimes) / count($responseTimes), 1) . 'm'
            : '0m';

        // Mensajes con botones/interactivos
        $buttonMessages = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where(function($q) {
                $q->where('type', 'button')
                  ->orWhere('type', 'interactive')
                  ->orWhere(function($subQ) {
                      $subQ->whereRaw("JSON_VALID(content) = 1")
                           ->where(function($jsonQ) {
                               $jsonQ->whereRaw("JSON_EXTRACT(content, '$.type') = 'button_reply'")
                                     ->orWhereRaw("JSON_EXTRACT(content, '$.type') = 'list_reply'");
                           });
                  });
            })
            ->count();
        $buttonMessagesRate = $lastMonthMessages > 0 ? round(($buttonMessages / $lastMonthMessages) * 100, 1) : 0;

        // Tasa de interacción
        $interactions = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'client')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        $totalOutbound = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'system')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        $interactionRate = $totalOutbound > 0 ? round(($interactions / $totalOutbound) * 100, 1) : 0;

        // Mensajes por día de la semana (últimos 7 días)
        $messagesByDay = [];
        $days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $dayName = $days[$date->dayOfWeek];
            $sent = \App\Models\WhatsappMessage::where('contact_id', $contactId)
                ->where('sender_type', 'system')
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->count();
            $received = \App\Models\WhatsappMessage::where('contact_id', $contactId)
                ->where('sender_type', 'client')
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->count();
            $messagesByDay[] = [
                'day' => $dayName,
                'sent' => $sent,
                'received' => $received
            ];
        }

        // Tiempo de respuesta por día
        $responseTimeByDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $dayName = $days[$date->dayOfWeek];
            $dayResponseTimes = [];

            $dayClientMessages = \App\Models\WhatsappMessage::where('contact_id', $contactId)
                ->where('sender_type', 'client')
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->orderBy('created_at')
                ->get();

            foreach ($dayClientMessages as $clientMsg) {
                $nextSystemMsg = \App\Models\WhatsappMessage::where('contact_id', $contactId)
                    ->where('sender_type', 'system')
                    ->where('created_at', '>', $clientMsg->created_at)
                    ->whereDate('created_at', $date->format('Y-m-d'))
                    ->orderBy('created_at')
                    ->first();

                if ($nextSystemMsg) {
                    $dayResponseTimes[] = $clientMsg->created_at->diffInMinutes($nextSystemMsg->created_at);
                }
            }

            $avgTime = count($dayResponseTimes) > 0
                ? round(array_sum($dayResponseTimes) / count($dayResponseTimes), 1)
                : 0;

            $responseTimeByDay[] = [
                'day' => $dayName,
                'time' => $avgTime
            ];
        }

        // Distribución de tipos de mensajes
        $messageTypes = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type')
            ->toArray();

        // NUEVOS INDICADORES ÚTILES

        // 1. Mensajes enviados vs recibidos (últimos 30 días)
        $sentMessages = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'system')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        $receivedMessages = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'client')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        $sentReceivedRatio = $receivedMessages > 0 ? round($sentMessages / $receivedMessages, 2) : 0;

        // 2. Última actividad
        $lastMessage = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->first();
        $lastActivity = $lastMessage ? $lastMessage->created_at->diffForHumans() : 'Nunca';
        $lastActivityDate = $lastMessage ? $lastMessage->created_at->format('d/m/Y H:i') : 'N/A';

        // 3. Hora de mayor actividad (últimos 30 días)
        $messagesByHour = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();
        $peakHour = $messagesByHour ? $messagesByHour->hour . ':00' : 'N/A';

        // 4. Día más activo de la semana (últimos 30 días)
        $messagesByWeekday = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DAYOFWEEK(created_at) as weekday, COUNT(*) as count')
            ->groupBy('weekday')
            ->orderByDesc('count')
            ->first();
        $weekdayNames = ['', 'Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $mostActiveDay = $messagesByWeekday ? $weekdayNames[$messagesByWeekday->weekday] : 'N/A';

        // 5. Longitud promedio de mensajes del cliente (últimos 30 días)
        $avgMessageLength = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'client')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->whereNotNull('content')
            ->get()
            ->map(function($msg) {
                try {
                    $decoded = json_decode($msg->content, true);
                    if (is_array($decoded)) {
                        return isset($decoded['text']) ? strlen($decoded['text']) : strlen($msg->content);
                    }
                } catch (\Exception $e) {
                    // No es JSON, usar contenido directo
                }
                return strlen($msg->content);
            })
            ->filter(function($len) {
                return $len > 0;
            });
        $avgLength = $avgMessageLength->count() > 0
            ? round($avgMessageLength->avg(), 0) . ' caracteres'
            : '0 caracteres';

        // 6. Número de conversaciones/sesiones (grupos de mensajes con menos de 2 horas entre ellos)
        $allMessages = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->orderBy('created_at')
            ->get();
        $conversations = 0;
        $lastMessageTime = null;
        foreach ($allMessages as $msg) {
            if ($lastMessageTime === null || $msg->created_at->diffInHours($lastMessageTime) >= 2) {
                $conversations++;
            }
            $lastMessageTime = $msg->created_at;
        }

        // 7. Tiempo promedio entre mensajes del cliente (últimos 30 días)
        $clientMessages = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'client')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->orderBy('created_at')
            ->get();
        $timeBetweenMessages = [];
        for ($i = 1; $i < $clientMessages->count(); $i++) {
            $timeBetweenMessages[] = $clientMessages[$i-1]->created_at->diffInMinutes($clientMessages[$i]->created_at);
        }
        $avgTimeBetween = count($timeBetweenMessages) > 0
            ? round(array_sum($timeBetweenMessages) / count($timeBetweenMessages), 1)
            : 0;
        $avgTimeBetweenFormatted = $avgTimeBetween > 0
            ? ($avgTimeBetween >= 60 ? round($avgTimeBetween / 60, 1) . 'h' : $avgTimeBetween . 'm')
            : '0m';

        // 8. Tasa de respuesta del cliente (cuánto responde a nuestros mensajes)
        $systemMessages = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'system')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->get();
        $clientResponses = 0;
        foreach ($systemMessages as $sysMsg) {
            $clientReply = \App\Models\WhatsappMessage::where('contact_id', $contactId)
                ->where('sender_type', 'client')
                ->where('created_at', '>', $sysMsg->created_at)
                ->where('created_at', '<=', $sysMsg->created_at->copy()->addHours(24))
                ->first();
            if ($clientReply) {
                $clientResponses++;
            }
        }
        $clientResponseRate = $systemMessages->count() > 0
            ? round(($clientResponses / $systemMessages->count()) * 100, 1)
            : 0;

        // 9. Frecuencia de mensajes (mensajes por día en últimos 30 días)
        $daysActive = \App\Models\WhatsappMessage::where('contact_id', $contactId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->count();
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
                'messages' => $messages->map(function($msg) {
                    return [
                        'id' => $msg->id,
                        'content' => $msg->content,
                        'type' => $msg->type,
                        'sender_type' => $msg->sender_type,
                        'metadata' => $msg->metadata,
                        'created_at' => $msg->created_at->toDateTimeString()
                    ];
                }),
                'stats' => [
                    'totalMessages' => $totalMessages,
                    'messageGrowth' => $messageGrowth,
                    'responseRate' => $responseRate . '%',
                    'responseRateGrowth' => $responseRateGrowth,
                    'avgResponseTime' => $avgResponseTime,
                    'buttonMessagesRate' => $buttonMessagesRate . '%',
                    'interactionRate' => $interactionRate . '%',
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
                    'clientResponseRate' => $clientResponseRate . '%',
                    'frequencyPerDay' => $frequencyPerDay
                ]
            ]);
        }

        // Estadísticas para la vista
        $stats = [
            'totalMessages' => $totalMessages,
            'messageGrowth' => $messageGrowth,
            'responseRate' => $responseRate . '%',
            'responseRateGrowth' => $responseRateGrowth,
            'avgResponseTime' => $avgResponseTime,
            'buttonMessagesRate' => $buttonMessagesRate . '%',
            'interactionRate' => $interactionRate . '%',
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
            'clientResponseRate' => $clientResponseRate . '%',
            'frequencyPerDay' => $frequencyPerDay
        ];

        // Calcular estadísticas globales (todos los chats)
        $globalStats = $this->calculateGlobalStats($now, $thirtyDaysAgo, $sixtyDaysAgo);

        $whatsappService = new \App\Services\WhatsappService();
        $lastInboundWamid = $whatsappService->syncContactLastInbound($contact);
        $typingAvailable = !empty($lastInboundWamid);

        $chatbotConfig = ($contact->business_profile_id
            ? \App\Models\WhatsappChatbotConfig::where('business_profile_id', $contact->business_profile_id)->first()
            : null) ?? \App\Models\WhatsappChatbotConfig::first();

        return view('admin.chat', compact('contacts', 'contact', 'messages', 'stats', 'globalStats', 'lastInboundWamid', 'typingAvailable', 'chatbotConfig'));
    }

    /**
     * Calcular estadísticas globales de todos los chats
     */
    private function calculateGlobalStats($now, $thirtyDaysAgo, $sixtyDaysAgo)
    {
        // Total de mensajes globales
        $totalMessages = \App\Models\WhatsappMessage::count();
        $lastMonthMessages = \App\Models\WhatsappMessage::where('created_at', '>=', $thirtyDaysAgo)->count();
        $previousMonthMessages = \App\Models\WhatsappMessage::whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])->count();
        $messageGrowth = $previousMonthMessages > 0
            ? round((($lastMonthMessages - $previousMonthMessages) / $previousMonthMessages) * 100, 1)
            : 0;

        // Mensajes enviados vs recibidos
        $sentMessages = \App\Models\WhatsappMessage::where('sender_type', 'system')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        $receivedMessages = \App\Models\WhatsappMessage::where('sender_type', 'client')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        $sentReceivedRatio = $receivedMessages > 0 ? round($sentMessages / $receivedMessages, 2) : 0;

        // Última actividad global
        $lastMessage = \App\Models\WhatsappMessage::orderByDesc('created_at')->first();
        $lastActivity = $lastMessage ? $lastMessage->created_at->diffForHumans() : 'Nunca';
        $lastActivityDate = $lastMessage ? $lastMessage->created_at->format('d/m/Y H:i') : 'N/A';

        // Tiempo promedio de respuesta global
        $responseTimes = [];
        $clientMessages = \App\Models\WhatsappMessage::where('sender_type', 'client')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->orderBy('created_at')
            ->get();

        foreach ($clientMessages as $clientMsg) {
            $nextSystemMsg = \App\Models\WhatsappMessage::where('contact_id', $clientMsg->contact_id)
                ->where('sender_type', 'system')
                ->where('created_at', '>', $clientMsg->created_at)
                ->orderBy('created_at')
                ->first();

            if ($nextSystemMsg) {
                $responseTimes[] = $clientMsg->created_at->diffInMinutes($nextSystemMsg->created_at);
            }
        }

        $avgResponseTime = count($responseTimes) > 0
            ? round(array_sum($responseTimes) / count($responseTimes), 1) . 'm'
            : '0m';

        // Tasa de respuesta del cliente global
        $systemMessages = \App\Models\WhatsappMessage::where('sender_type', 'system')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->get();
        $clientResponses = 0;
        foreach ($systemMessages as $sysMsg) {
            $clientReply = \App\Models\WhatsappMessage::where('contact_id', $sysMsg->contact_id)
                ->where('sender_type', 'client')
                ->where('created_at', '>', $sysMsg->created_at)
                ->where('created_at', '<=', $sysMsg->created_at->copy()->addHours(24))
                ->first();
            if ($clientReply) {
                $clientResponses++;
            }
        }
        $clientResponseRate = $systemMessages->count() > 0
            ? round(($clientResponses / $systemMessages->count()) * 100, 1)
            : 0;

        // Hora pico global
        $messagesByHour = \App\Models\WhatsappMessage::where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();
        $peakHour = $messagesByHour ? $messagesByHour->hour . ':00' : 'N/A';

        // Día más activo
        $messagesByWeekday = \App\Models\WhatsappMessage::where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DAYOFWEEK(created_at) as weekday, COUNT(*) as count')
            ->groupBy('weekday')
            ->orderByDesc('count')
            ->first();
        $weekdayNames = ['', 'Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $mostActiveDay = $messagesByWeekday ? $weekdayNames[$messagesByWeekday->weekday] : 'N/A';

        // Total de contactos activos
        $activeContacts = \App\Models\WhatsappMessage::where('created_at', '>=', $thirtyDaysAgo)
            ->distinct('contact_id')
            ->count('contact_id');

        // Total de conversaciones globales
        $allMessages = \App\Models\WhatsappMessage::orderBy('created_at')->get();
        $conversations = 0;
        $lastMessageTime = null;
        $lastContactId = null;
        foreach ($allMessages as $msg) {
            if ($lastMessageTime === null || $lastContactId !== $msg->contact_id || $msg->created_at->diffInHours($lastMessageTime) >= 2) {
                if ($lastContactId !== $msg->contact_id) {
                    $conversations++;
                } else {
                    $conversations++;
                }
            }
            $lastMessageTime = $msg->created_at;
            $lastContactId = $msg->contact_id;
        }

        // Frecuencia diaria global
        $daysActive = \App\Models\WhatsappMessage::where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->count();
        $frequencyPerDay = $daysActive > 0
            ? round($lastMonthMessages / $daysActive, 1)
            : 0;

        // Distribución de tipos de mensajes global
        $messageTypes = \App\Models\WhatsappMessage::where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type')
            ->toArray();

        // Mensajes por día (últimos 7 días) global
        $messagesByDay = [];
        $days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $dayName = $days[$date->dayOfWeek];
            $sent = \App\Models\WhatsappMessage::where('sender_type', 'system')
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->count();
            $received = \App\Models\WhatsappMessage::where('sender_type', 'client')
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->count();
            $messagesByDay[] = [
                'day' => $dayName,
                'sent' => $sent,
                'received' => $received
            ];
        }

        // Mensajes con botones global
        $buttonMessages = \App\Models\WhatsappMessage::where('created_at', '>=', $thirtyDaysAgo)
            ->where(function($q) {
                $q->where('type', 'button')
                  ->orWhere('type', 'interactive')
                  ->orWhere(function($subQ) {
                      $subQ->whereRaw("JSON_VALID(content) = 1")
                           ->where(function($jsonQ) {
                               $jsonQ->whereRaw("JSON_EXTRACT(content, '$.type') = 'button_reply'")
                                     ->orWhereRaw("JSON_EXTRACT(content, '$.type') = 'list_reply'");
                           });
                  });
            })
            ->count();
        $buttonMessagesRate = $lastMonthMessages > 0 ? round(($buttonMessages / $lastMonthMessages) * 100, 1) : 0;

        // Tasa de interacción global
        $interactions = \App\Models\WhatsappMessage::where('sender_type', 'client')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        $totalOutbound = \App\Models\WhatsappMessage::where('sender_type', 'system')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
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
            'clientResponseRate' => $clientResponseRate . '%',
            'peakHour' => $peakHour,
            'mostActiveDay' => $mostActiveDay,
            'activeContacts' => $activeContacts,
            'conversations' => $conversations,
            'frequencyPerDay' => $frequencyPerDay,
            'messageTypes' => $messageTypes,
            'messagesByDay' => $messagesByDay,
            'buttonMessagesRate' => $buttonMessagesRate . '%',
            'interactionRate' => $interactionRate . '%'
        ];
    }

    public function updateOrderStatus(Request $request, $id, \App\Services\OrderLifecycleService $lifecycle)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,payment_pending,paid,preparing,ready,completed,cancelled'],
        ]);

        try {
            $order = WhatsappCart::reportable()->forActiveCompany()->findOrFail($id);
            $lifecycle->transition($order, $validated['status'], (int) $request->user()->id);

            return response()->json(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function contactDetails($id)
    {
        $contact = \App\Models\WhatsappContact::findOrFail($id);
        return response()->json($contact);
    }

    public function typingIndicator(Request $request)
    {
        $request->validate([
            'contact_id' => 'required|exists:whatsapp_contacts,id',
            'whatsapp_message_id' => 'nullable|string|max:255',
        ]);

        $contact = WhatsappContact::findOrFail($request->contact_id);
        $whatsappService = new WhatsappService();

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
        if (!$wamid) {
            $wamid = $whatsappService->syncContactLastInbound($contact);
        }

        if (!$wamid) {
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

        if (!$hasMessage && !$hasImage && !$hasDocument) {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar un mensaje, imagen o documento.'
            ], 400);
        }

        $request->validate([
            'contact_id' => 'required|exists:whatsapp_contacts,id',
            'message' => 'nullable|string|max:4096',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            'audio' => 'nullable|mimes:mp3,ogg,wav,m4a,aac,webm|max:16384', // 16MB max para audio (incluye webm para grabaciones)
            'document' => 'nullable|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar|max:10240' // 10MB max para documentos
        ]);

        try {
            $contact = WhatsappContact::findOrFail($request->contact_id);
            $whatsappService = new WhatsappService();
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
                $fullPath = storage_path('app/public/' . $imagePath);

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
                $fullPath = storage_path('app/public/' . $audioPath);
                $originalFilename = $request->file('audio')->getClientOriginalName();
                $extension = strtolower($request->file('audio')->getClientOriginalExtension());

                // Verificar formato compatible antes de enviar
                $allowedExtensions = ['mp3', 'ogg', 'wav', 'm4a', 'aac'];
                if (!in_array($extension, $allowedExtensions) && $extension !== 'webm') {
                    // Eliminar archivo temporal
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }
                    return response()->json([
                        'success' => false,
                        'message' => 'Formato de audio no compatible. Por favor, usa MP3, OGG, WAV, M4A o AAC.'
                    ], 400);
                }

                // Si es webm, advertir que puede fallar
                if ($extension === 'webm') {
                    Log::warning('Intento de enviar audio WebM', [
                        'contact_id' => $contact->id,
                        'filename' => $originalFilename
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
                            'message' => 'WhatsApp no acepta archivos WebM. Por favor, graba en formato OGG o sube un archivo MP3, OGG, WAV, M4A o AAC.'
                        ], 400);
                    }
                }
            } elseif ($hasDocument) {
                // Guardar el documento temporalmente
                $documentPath = $request->file('document')->store('temp', 'public');
                $fullPath = storage_path('app/public/' . $documentPath);
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
                    'message_length' => strlen($request->message)
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
                        'message_id' => $message ? $message->id : null
                    ]);
                } else {
                    Log::error('Error al enviar mensaje de texto', [
                        'contact_id' => $contact->id
                    ]);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar un mensaje, imagen, audio o documento.'
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
                'message' => 'Error al enviar el mensaje. Por favor, intenta nuevamente.'
            ], 500);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación: ' . implode(', ', $e->errors())
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error sending message from admin chat', [
                'error' => $e->getMessage(),
                'contact_id' => $request->contact_id ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el mensaje: ' . $e->getMessage()
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
                'enabled' => 'required|boolean'
            ]);

            $contact = WhatsappContact::findOrFail($contactId);
            $contact->bot_enabled = $request->enabled;
            $contact->save();

            Log::info('Estado del bot actualizado', [
                'contact_id' => $contactId,
                'bot_enabled' => $request->enabled
            ]);

            return response()->json([
                'success' => true,
                'message' => $request->enabled ? 'Bot activado' : 'Bot desactivado',
                'bot_enabled' => $contact->bot_enabled
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado del bot', [
                'error' => $e->getMessage(),
                'contact_id' => $contactId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado del bot: ' . $e->getMessage()
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
    public function resetConversation($contactId, \App\Services\AbandonedCartService $abandonedCarts)
    {
        try {
            $contact = WhatsappContact::findOrFail($contactId);

            $cart = \App\Models\WhatsappCart::where('contact_id', $contact->id)
                ->whereIn('status', ['active', \App\Models\WhatsappCart::STATUS_PAYMENT_PENDING])
                ->latest()
                ->first();

            $closed = $cart ? $abandonedCarts->close($cart) : false;

            if (!$cart) {
                // No había un pedido a medias, pero igual puede estar
                // "atascado" en un nodo del flujo visual (grafo).
                $contact->forgetFlowPosition();
                $contact->forgetPrivacyNoticeSent();
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
            'contacts' => $contacts->map(function($contact) {
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
            })
        ]);
    }

    /**
     * Polling ligero para alertas de asesor (cualquier pantalla del admin).
     */
    public function pollAgentRequests()
    {
        $requests = WhatsappContact::query()
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
                    'alert_token' => $metadata['agent_requested_at'] ?? ('contact-' . $contact->id),
                ];
            })->values(),
        ]);
    }

    /**
     * Contactos del sidebar ordenados por último mensaje (más reciente primero).
     */
    private function getSidebarContacts(?int $currentContactId = null)
    {
        $businessProfileId = \App\Support\CompanyContext::current()->businessProfileId();

        $contacts = WhatsappContact::query()
            ->whereIn('id', function ($query) {
                $query->select('contact_id')->from('whatsapp_messages')->distinct();
            })
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
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

            $contact->last_message_date = $lastAt ? \Carbon\Carbon::parse($lastAt) : null;
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
            $aAgent = !empty($a->needs_agent) ? 1 : 0;
            $bAgent = !empty($b->needs_agent) ? 1 : 0;

            if ($aAgent !== $bAgent) {
                return $bAgent <=> $aAgent;
            }

            return ($b->last_message_sort ?? 0) <=> ($a->last_message_sort ?? 0);
        })->values();
    }

    public function getNewMessages($contactId, Request $request)
    {
        try {
            $lastMessageId = $request->input('last_message_id', 0);
            $lastTimestamp = $request->input('last_timestamp');

            $query = WhatsappMessage::where('contact_id', $contactId);

            // Si hay un timestamp, filtrar por mensajes más recientes
            if ($lastTimestamp) {
                $query->where('created_at', '>', $lastTimestamp);
            } elseif ($lastMessageId > 0) {
                // Si solo hay un ID, obtener mensajes después de ese ID
                $query->where('id', '>', $lastMessageId);
            } else {
                // Si no hay parámetros, obtener los últimos 10 mensajes
                $query->latest()->limit(10);
            }

            $newMessages = $query->with('adminUser:id,name')->orderBy('created_at')->get();

            return response()->json([
                'success' => true,
                'messages' => $newMessages->map(fn ($msg) => $msg->toChatPayload()),
                'count' => $newMessages->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo nuevos mensajes', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener nuevos mensajes'
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
                return response($placeholderSvg, 200)
                    ->header('Content-Type', 'image/svg+xml')
                    ->header('Cache-Control', 'no-cache');
            }

            $metadata = $message->metadata ?? [];
            $mediaId = $metadata['media_id'] ?? null;

            if (!$mediaId) {
                // Retornar placeholder en lugar de error JSON
                $placeholderSvg = '<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#202c33"/><text x="150" y="100" text-anchor="middle" fill="#8696a0" font-family="Arial" font-size="14">Imagen no disponible</text></svg>';
                return response($placeholderSvg, 200)
                    ->header('Content-Type', 'image/svg+xml')
                    ->header('Cache-Control', 'no-cache');
            }

            // Obtener la URL de la imagen desde WhatsApp Media API -- con el
            // token de la empresa dueña de este mensaje, nunca el global.
            $mediaToken = $message->businessProfile?->access_token;
            $response = \Illuminate\Support\Facades\Http::withToken($mediaToken)
                ->timeout(5) // Timeout corto para no bloquear
                ->get("https://graph.facebook.com/" . config('whatsapp.api_version', 'v22.0') . "/{$mediaId}");

            if (!$response->successful()) {
                // Para cualquier error, retornar placeholder silenciosamente
                $placeholderSvg = '<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#202c33"/><text x="150" y="100" text-anchor="middle" fill="#8696a0" font-family="Arial" font-size="14">Imagen no disponible</text></svg>';
                return response($placeholderSvg, 200)
                    ->header('Content-Type', 'image/svg+xml')
                    ->header('Cache-Control', 'no-cache');
            }

            $mediaData = $response->json();
            $imageUrl = $mediaData['url'] ?? null;

            if (!$imageUrl) {
                // Retornar placeholder en lugar de error
                $placeholderSvg = '<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#202c33"/><text x="150" y="100" text-anchor="middle" fill="#8696a0" font-family="Arial" font-size="14">Imagen no disponible</text></svg>';
                return response($placeholderSvg, 200)
                    ->header('Content-Type', 'image/svg+xml')
                    ->header('Cache-Control', 'no-cache');
            }

            // Descargar la imagen desde WhatsApp
            $imageResponse = \Illuminate\Support\Facades\Http::withToken($mediaToken)
                ->timeout(5) // Timeout corto
                ->get($imageUrl);

            if (!$imageResponse->successful()) {
                // Retornar placeholder en lugar de error
                $placeholderSvg = '<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="200" fill="#202c33"/><text x="150" y="100" text-anchor="middle" fill="#8696a0" font-family="Arial" font-size="14">Imagen no disponible</text></svg>';
                return response($placeholderSvg, 200)
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
            return response($placeholderSvg, 200)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Cache-Control', 'no-cache');
        }
    }
}
