<?php

namespace App\Services;

use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientInsightsService
{
    public const SEGMENTS = [
        '' => 'Todos',
        'frequent_buyer' => 'Comprador frecuente',
        'vip' => 'Cliente VIP',
        'new' => 'Cliente nuevo',
        'inactive' => 'Inactivo (+30 días)',
        'needs_agent' => 'Requiere agente',
        'bot_off' => 'Bot desactivado',
        'has_orders' => 'Con pedidos',
        'no_orders' => 'Sin pedidos',
        'pending_reply' => 'Esperando respuesta',
    ];

    /** Texto de ayuda por segmento (tooltips en listado de clientes). */
    public const SEGMENT_HINTS = [
        '' => 'Todos los clientes con conversación o al menos un pedido cerrado.',
        'frequent_buyer' => '3 o más pedidos cerrados. No cuenta carritos activos ni abandonados.',
        'vip' => '5 o más pedidos cerrados, o más de $500 gastados en total.',
        'new' => 'Contacto registrado en los últimos 7 días.',
        'inactive' => 'Sin mensajes de actividad en más de 30 días.',
        'needs_agent' => 'Pidió hablar con un asesor humano; el bot lo marcó en el chat.',
        'bot_off' => 'El bot automático está pausado para ese contacto.',
        'has_orders' => 'Al menos un pedido cerrado (confirmado, pagado, completado, etc.).',
        'no_orders' => 'Sin pedidos cerrados; solo conversó o tiene carrito abierto.',
        'pending_reply' => 'El último mensaje del cliente aún no tiene respuesta del bot ni de un agente.',
    ];

    public const SORT_OPTIONS = [
        'recent' => 'Actividad más reciente',
        'orders_desc' => 'Más pedidos',
        'messages_desc' => 'Más mensajes',
        'spent_desc' => 'Mayor gasto',
        'name_asc' => 'Nombre A–Z',
    ];

    public const BEST_CONTACT_TIME_HINT = 'Se calcula con los mensajes que envió el cliente en los últimos 12 meses. Se agrupa por hora del día (hora local) y se muestra la franja de 1 hora con más mensajes. Confianza: baja (<5 msgs), media (5–14), alta (15 o más).';

    public function paginate(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->baseQuery($request);
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request->input('sort', 'recent'));

        return $query->paginate($perPage)->withQueryString();
    }

    public function summaryStats(Request $request): array
    {
        $base = $this->baseQuery($request);
        $this->applyFilters($base, $request, skipSegment: true);

        $activeSince = now()->subDays(7);

        return [
            'total' => (clone $base)->count(),
            'active_7d' => (clone $base)
                ->where($this->lastActivitySubquery(), '>=', $activeSince)
                ->count(),
            'frequent_buyers' => (clone $base)
                ->has('carts', '>=', 3, 'and', fn (Builder $q) => $q->reportable())
                ->count(),
            'needs_agent' => (clone $base)
                ->whereRaw("JSON_EXTRACT(metadata, '$.needs_agent') = true")
                ->count(),
            'pending_reply' => (clone $base)
                ->whereRaw($this->pendingReplySql())
                ->count(),
        ];
    }

    public function contactDetail(WhatsappContact $contact): array
    {
        $contact = $this->loadContactMetrics($contact);

        $orders = WhatsappCart::reportable()
            ->with('items')
            ->where('contact_id', $contact->id)
            ->latest()
            ->limit(15)
            ->get();

        $recentMessages = WhatsappMessage::where('contact_id', $contact->id)
            ->with('adminUser:id,name')
            ->latest('created_at')
            ->limit(30)
            ->get();

        $notes = $contact->notes()
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(50)
            ->get();

        $messagesByMonth = WhatsappMessage::where('contact_id', $contact->id)
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month')
            ->selectRaw('SUM(CASE WHEN sender_type = "client" THEN 1 ELSE 0 END) as inbound')
            ->selectRaw('SUM(CASE WHEN sender_type IN ("system", "humano") THEN 1 ELSE 0 END) as outbound')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'contact' => $contact,
            'indicators' => $this->indicators($contact),
            'best_contact_time' => $this->bestContactTimeForContact($contact->id),
            'orders' => $orders,
            'recent_messages' => $recentMessages,
            'contact_notes' => $notes,
            'messages_by_month' => $messagesByMonth,
            'response_rate' => $this->responseRateFromMetrics($contact),
            'response_metrics' => $this->responseMetricsForContact($contact->id),
        ];
    }

    /**
     * @param  list<int>  $contactIds
     * @return array<int, array<string, mixed>|null>
     */
    public function bestContactTimesForContacts(array $contactIds, ?int $lookbackDays = 365): array
    {
        if ($contactIds === []) {
            return [];
        }

        $hourCountsByContact = [];
        $totalsByContact = array_fill_keys($contactIds, 0);
        $timezone = config('app.timezone');

        $query = WhatsappMessage::query()
            ->whereIn('contact_id', $contactIds)
            ->where('sender_type', 'client')
            ->select(['contact_id', 'created_at']);

        if ($lookbackDays !== null) {
            $query->where('created_at', '>=', now()->subDays($lookbackDays));
        }

        $query->orderBy('id')->chunk(2000, function ($messages) use (&$hourCountsByContact, &$totalsByContact, $timezone) {
            foreach ($messages as $message) {
                $contactId = (int) $message->contact_id;
                if (!isset($hourCountsByContact[$contactId])) {
                    $hourCountsByContact[$contactId] = array_fill(0, 24, 0);
                }

                $hour = $message->created_at->timezone($timezone)->hour;
                $hourCountsByContact[$contactId][$hour]++;
                $totalsByContact[$contactId]++;
            }
        });

        $results = [];
        foreach ($contactIds as $contactId) {
            $total = $totalsByContact[$contactId] ?? 0;
            if ($total === 0) {
                $results[$contactId] = null;

                continue;
            }

            $results[$contactId] = $this->buildBestContactTimeResult(
                $hourCountsByContact[$contactId],
                $total
            );
        }

        return $results;
    }

    /**
     * @return array{
     *     hour: int,
     *     window: string,
     *     label: string,
     *     message_count: int,
     *     total_messages: int,
     *     share_percent: float,
     *     confidence: string
     * }|null
     */
    public function bestContactTimeForContact(int $contactId, ?int $lookbackDays = 365): ?array
    {
        return $this->bestContactTimesForContacts([$contactId], $lookbackDays)[$contactId] ?? null;
    }

    /**
     * @param  array<int, int>  $hourCounts
     * @return array{
     *     hour: int,
     *     window: string,
     *     label: string,
     *     message_count: int,
     *     total_messages: int,
     *     share_percent: float,
     *     confidence: string
     * }
     */
    private function buildBestContactTimeResult(array $hourCounts, int $total): array
    {
        $maxCount = max($hourCounts);
        $bestHour = 0;

        foreach ($hourCounts as $hour => $count) {
            if ($count === $maxCount) {
                $bestHour = $hour;
                break;
            }
        }

        $nextHour = ($bestHour + 1) % 24;
        $window = sprintf('%02d:00 – %02d:00', $bestHour, $nextHour);

        return [
            'hour' => $bestHour,
            'window' => $window,
            'label' => $window,
            'message_count' => $maxCount,
            'total_messages' => $total,
            'share_percent' => round(($maxCount / $total) * 100, 1),
            'confidence' => match (true) {
                $total < 5 => 'baja',
                $total < 15 => 'media',
                default => 'alta',
            },
        ];
    }

    /**
     * @return array{
     *     last_seconds: ?int,
     *     last_formatted: string,
     *     last_responder_kind: ?string,
     *     last_responder_label: ?string,
     *     last_reply_at: ?Carbon,
     *     pending_reply: bool,
     *     avg_seconds: ?int,
     *     avg_formatted: string,
     *     avg_bot_seconds: ?int,
     *     avg_bot_formatted: string,
     *     avg_agent_seconds: ?int,
     *     avg_agent_formatted: string,
     *     sample_count: int
     * }
     */
    public function responseMetricsForContact(int $contactId, int $days = 90): array
    {
        $empty = [
            'last_seconds' => null,
            'last_formatted' => '—',
            'last_responder_kind' => null,
            'last_responder_label' => null,
            'last_reply_at' => null,
            'pending_reply' => false,
            'avg_seconds' => null,
            'avg_formatted' => '—',
            'avg_bot_seconds' => null,
            'avg_bot_formatted' => '—',
            'avg_agent_seconds' => null,
            'avg_agent_formatted' => '—',
            'sample_count' => 0,
        ];

        $lastClientMsg = WhatsappMessage::query()
            ->where('contact_id', $contactId)
            ->where('sender_type', 'client')
            ->latest('created_at')
            ->first(['id', 'created_at']);

        $lastReply = null;
        $lastSeconds = null;
        $pendingReply = false;

        if ($lastClientMsg) {
            $lastReply = WhatsappMessage::query()
                ->where('contact_id', $contactId)
                ->where(function ($q) {
                    $q->whereIn('sender_type', ['system', 'humano'])
                        ->orWhereNotNull('admin_user_id');
                })
                ->where('created_at', '>', $lastClientMsg->created_at)
                ->with('adminUser:id,name')
                ->orderBy('created_at')
                ->first();

            if ($lastReply) {
                $lastSeconds = $lastClientMsg->created_at->diffInSeconds($lastReply->created_at);
            } else {
                $pendingReply = true;
            }
        }

        $from = now()->subDays($days);
        $outbound = WhatsappMessage::query()
            ->where('contact_id', $contactId)
            ->where('created_at', '>=', $from)
            ->where(function ($q) {
                $q->whereIn('sender_type', ['system', 'humano'])
                    ->orWhereNotNull('admin_user_id');
            })
            ->orderBy('created_at')
            ->get(['id', 'sender_type', 'admin_user_id', 'created_at']);

        $allDiffs = [];
        $botDiffs = [];
        $agentDiffs = [];

        foreach ($outbound as $reply) {
            $prevClientAt = WhatsappMessage::query()
                ->where('contact_id', $contactId)
                ->where('sender_type', 'client')
                ->where('created_at', '<', $reply->created_at)
                ->orderByDesc('created_at')
                ->value('created_at');

            if (!$prevClientAt) {
                continue;
            }

            $seconds = Carbon::parse($prevClientAt)->diffInSeconds($reply->created_at);
            if ($seconds < 0 || $seconds > 604800) {
                continue;
            }

            $allDiffs[] = $seconds;
            if ($reply->sender_type === 'humano' || $reply->admin_user_id) {
                $agentDiffs[] = $seconds;
            } else {
                $botDiffs[] = $seconds;
            }
        }

        $responderKind = null;
        $responderLabel = null;
        if ($lastReply) {
            if ($lastReply->sender_type === 'humano' || $lastReply->admin_user_id) {
                $responderKind = 'agent';
                $responderLabel = $lastReply->senderBadgeLabel();
            } else {
                $responderKind = 'bot';
                $responderLabel = 'Bot';
            }
        }

        return array_merge($empty, [
            'last_seconds' => $lastSeconds,
            'last_formatted' => $this->formatDurationSeconds($lastSeconds),
            'last_responder_kind' => $responderKind,
            'last_responder_label' => $responderLabel,
            'last_reply_at' => $lastReply?->created_at,
            'pending_reply' => $pendingReply,
            'avg_seconds' => $this->averageSeconds($allDiffs),
            'avg_formatted' => $this->formatDurationSeconds($this->averageSeconds($allDiffs)),
            'avg_bot_seconds' => $this->averageSeconds($botDiffs),
            'avg_bot_formatted' => $this->formatDurationSeconds($this->averageSeconds($botDiffs)),
            'avg_agent_seconds' => $this->averageSeconds($agentDiffs),
            'avg_agent_formatted' => $this->formatDurationSeconds($this->averageSeconds($agentDiffs)),
            'sample_count' => count($allDiffs),
        ]);
    }

    private function averageSeconds(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        return (int) round(array_sum($values) / count($values));
    }

    private function formatDurationSeconds(?int $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds . ' seg';
        }

        if ($seconds < 3600) {
            $mins = (int) floor($seconds / 60);
            $secs = $seconds % 60;

            return $secs > 0 ? "{$mins} min {$secs} seg" : "{$mins} min";
        }

        $hours = (int) floor($seconds / 3600);
        $mins = (int) floor(($seconds % 3600) / 60);

        return $mins > 0 ? "{$hours} h {$mins} min" : "{$hours} h";
    }

    /** @return array<int, array{key: string, label: string, tone: string, icon: string}> */
    public function indicators(object $contact): array
    {
        $badges = [];
        $orders = (int) ($contact->orders_count ?? 0);
        $spent = (float) ($contact->total_spent ?? 0);
        $recentOrders = (int) ($contact->recent_orders_count ?? 0);
        $lastActivity = $this->resolveLastActivity($contact);
        $createdAt = $contact->created_at ? Carbon::parse($contact->created_at) : null;

        if ($orders >= 5 || $spent >= 500) {
            $badges[] = ['key' => 'vip', 'label' => 'Cliente VIP', 'tone' => 'purple', 'icon' => 'fa-crown'];
        } elseif ($orders >= 3 || $recentOrders >= 2) {
            $badges[] = ['key' => 'frequent', 'label' => 'Comprador frecuente', 'tone' => 'green', 'icon' => 'fa-repeat'];
        }

        if ($createdAt && $createdAt->gte(now()->subDays(7)) && $orders <= 1) {
            $badges[] = ['key' => 'new', 'label' => 'Cliente nuevo', 'tone' => 'blue', 'icon' => 'fa-star'];
        }

        if (!empty($contact->needs_agent_flag)) {
            $badges[] = ['key' => 'agent', 'label' => 'Requiere agente', 'tone' => 'red', 'icon' => 'fa-headset'];
        }

        if ($contact->bot_enabled === false) {
            $badges[] = ['key' => 'bot_off', 'label' => 'Bot pausado', 'tone' => 'amber', 'icon' => 'fa-robot'];
        }

        if ($lastActivity && $lastActivity->lt(now()->subDays(30))) {
            $badges[] = ['key' => 'inactive', 'label' => 'Inactivo', 'tone' => 'gray', 'icon' => 'fa-moon'];
        }

        if ($this->isPendingReply($contact)) {
            $badges[] = ['key' => 'pending', 'label' => 'Esperando respuesta', 'tone' => 'orange', 'icon' => 'fa-clock'];
        }

        if ($orders === 0 && ((int) ($contact->client_messages_count ?? 0)) >= 5) {
            $badges[] = ['key' => 'no_purchase', 'label' => 'Sin compras', 'tone' => 'slate', 'icon' => 'fa-comment-dollar'];
        }

        if ($spent >= 100 && $orders >= 1) {
            $badges[] = ['key' => 'buyer', 'label' => 'Ha comprado', 'tone' => 'teal', 'icon' => 'fa-bag-shopping'];
        }

        return $badges;
    }

    private function baseQuery(Request $request): Builder
    {
        $ninetyDaysAgo = now()->subDays(90);

        return WhatsappContact::query()
            ->where(function (Builder $q) {
                $q->whereHas('messages')
                    ->orWhereHas('carts', fn (Builder $c) => $c->reportable());
            })
            ->where(function (Builder $q) {
                $q->whereNull('metadata')
                    ->orWhereRaw("JSON_EXTRACT(metadata, '$.role') IS NULL");
            })
            ->withCount([
                'messages as client_messages_count' => fn (Builder $q) => $q->where('sender_type', 'client'),
                'messages as replied_messages_count' => fn (Builder $q) => $q->whereIn('sender_type', ['system', 'humano']),
                'carts as orders_count' => fn (Builder $q) => $q->reportable(),
                'carts as recent_orders_count' => fn (Builder $q) => $q->reportable()->where('created_at', '>=', $ninetyDaysAgo),
            ])
            ->withSum([
                'carts as total_spent' => fn (Builder $q) => $q->reportable()
                    ->where('status', '!=', WhatsappCart::STATUS_CANCELLED),
            ], 'total')
            ->withMax([
                'messages as last_client_message_at' => fn (Builder $q) => $q->where('sender_type', 'client'),
            ], 'created_at')
            ->withMax([
                'messages as last_reply_message_at' => fn (Builder $q) => $q->whereIn('sender_type', ['system', 'humano']),
            ], 'created_at')
            ->withMax('messages as last_activity_at', 'created_at')
            ->addSelect(DB::raw(
                "CASE WHEN JSON_EXTRACT(whatsapp_contacts.metadata, '$.needs_agent') = true THEN 1 ELSE 0 END as needs_agent_flag"
            ));
    }

    private function applyFilters(Builder $query, Request $request, bool $skipSegment = false): void
    {
        if ($search = trim((string) $request->input('q', ''))) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('activity_from')) {
            $from = Carbon::parse($request->input('activity_from'))->startOfDay();
            $query->where($this->lastActivitySubquery(), '>=', $from);
        }

        if ($request->filled('activity_to')) {
            $to = Carbon::parse($request->input('activity_to'))->endOfDay();
            $query->where($this->lastActivitySubquery(), '<=', $to);
        }

        if ($request->filled('min_orders')) {
            $min = (int) $request->input('min_orders');
            $query->has('carts', '>=', $min, 'and', fn (Builder $q) => $q->reportable());
        }

        if (!$skipSegment && ($segment = $request->input('segment', ''))) {
            match ($segment) {
                'frequent_buyer' => $query->has('carts', '>=', 3, 'and', fn (Builder $q) => $q->reportable()),
                'vip' => $query->where(function (Builder $q) {
                    $q->has('carts', '>=', 5, 'and', fn (Builder $c) => $c->reportable())
                        ->orWhereRaw('(
                            SELECT COALESCE(SUM(total), 0) FROM whatsapp_carts
                            WHERE whatsapp_carts.contact_id = whatsapp_contacts.id
                            AND status NOT IN ("active", "abandoned", "cancelled")
                        ) >= 500');
                }),
                'new' => $query->where('whatsapp_contacts.created_at', '>=', now()->subDays(7)),
                'inactive' => $query->where(function (Builder $q) {
                    $q->where($this->lastActivitySubquery(), '<', now()->subDays(30))
                        ->orWhere(function (Builder $inner) {
                            $inner->whereDoesntHave('messages')
                                ->whereDoesntHave('carts', fn (Builder $c) => $c->reportable())
                                ->where('whatsapp_contacts.created_at', '<', now()->subDays(30));
                        });
                }),
                'needs_agent' => $query->whereRaw("JSON_EXTRACT(metadata, '$.needs_agent') = true"),
                'bot_off' => $query->where('bot_enabled', false),
                'has_orders' => $query->has('carts', '>=', 1, 'and', fn (Builder $q) => $q->reportable()),
                'no_orders' => $query->doesntHave('carts', 'and', fn (Builder $q) => $q->reportable()),
                'pending_reply' => $query->whereRaw($this->pendingReplySql()),
                default => null,
            };
        }
    }

    private function applySorting(Builder $query, string $sort): void
    {
        // Ordenar con subconsultas: los alias de withMax/withCount no existen en el COUNT de paginación.
        match ($sort) {
            'orders_desc' => $query
                ->orderBy($this->ordersCountSubquery(), 'desc')
                ->orderBy($this->lastActivitySubquery(), 'desc'),
            'messages_desc' => $query
                ->orderBy($this->clientMessagesCountSubquery(), 'desc')
                ->orderBy($this->lastActivitySubquery(), 'desc'),
            'spent_desc' => $query
                ->orderBy($this->totalSpentSubquery(), 'desc')
                ->orderBy($this->lastActivitySubquery(), 'desc'),
            'name_asc' => $query->orderByRaw('COALESCE(whatsapp_contacts.name, whatsapp_contacts.phone_number) ASC'),
            default => $query->orderBy($this->lastActivitySubquery(), 'desc'),
        };
    }

    private function enrichContact(WhatsappContact $contact): WhatsappContact
    {
        $enriched = $this->baseQuery(new Request())
            ->where('whatsapp_contacts.id', $contact->id)
            ->first();

        if (!$enriched) {
            return $this->loadContactMetrics($contact);
        }

        $enriched->pending_reply = $this->isPendingReply($enriched);

        return $enriched;
    }

    /** Métricas directas — fuente de verdad para el detalle del cliente. */
    private function loadContactMetrics(WhatsappContact $contact): WhatsappContact
    {
        $contactId = $contact->id;
        $ninetyDaysAgo = now()->subDays(90);

        $contact->client_messages_count = WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'client')
            ->count();
        $contact->replied_messages_count = WhatsappMessage::where('contact_id', $contactId)
            ->whereIn('sender_type', ['system', 'humano'])
            ->count();
        $contact->orders_count = WhatsappCart::reportable()
            ->where('contact_id', $contactId)
            ->count();
        $contact->recent_orders_count = WhatsappCart::reportable()
            ->where('contact_id', $contactId)
            ->where('created_at', '>=', $ninetyDaysAgo)
            ->count();
        $contact->total_spent = (float) WhatsappCart::reportable()
            ->where('contact_id', $contactId)
            ->where('status', '!=', WhatsappCart::STATUS_CANCELLED)
            ->sum('total');
        $contact->last_client_message_at = WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'client')
            ->max('created_at');
        $contact->last_reply_message_at = WhatsappMessage::where('contact_id', $contactId)
            ->whereIn('sender_type', ['system', 'humano'])
            ->max('created_at');
        $contact->last_activity_at = WhatsappMessage::where('contact_id', $contactId)
            ->max('created_at');
        $contact->needs_agent_flag = $contact->needsAgent() ? 1 : 0;
        $contact->pending_reply = $this->isPendingReply($contact);

        return $contact;
    }

    /** Subconsulta segura para filtrar/ordenar por última actividad (no es columna física). */
    private function lastActivitySubquery(): Builder
    {
        return WhatsappMessage::query()
            ->selectRaw('MAX(created_at)')
            ->whereColumn('whatsapp_messages.contact_id', 'whatsapp_contacts.id');
    }

    private function ordersCountSubquery(): Builder
    {
        return WhatsappCart::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('whatsapp_carts.contact_id', 'whatsapp_contacts.id')
            ->whereNotIn('status', ['active', 'abandoned']);
    }

    private function clientMessagesCountSubquery(): Builder
    {
        return WhatsappMessage::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('whatsapp_messages.contact_id', 'whatsapp_contacts.id')
            ->where('sender_type', 'client');
    }

    private function totalSpentSubquery(): Builder
    {
        return WhatsappCart::query()
            ->selectRaw('COALESCE(SUM(total), 0)')
            ->whereColumn('whatsapp_carts.contact_id', 'whatsapp_contacts.id')
            ->whereNotIn('status', ['active', 'abandoned'])
            ->where('status', '!=', WhatsappCart::STATUS_CANCELLED);
    }

    private function pendingReplySql(): string
    {
        return '(
            SELECT MAX(created_at) FROM whatsapp_messages wm1
            WHERE wm1.contact_id = whatsapp_contacts.id AND wm1.sender_type = "client"
        ) IS NOT NULL AND (
            SELECT MAX(created_at) FROM whatsapp_messages wm2
            WHERE wm2.contact_id = whatsapp_contacts.id AND wm2.sender_type IN ("system", "humano")
        ) IS NULL OR (
            SELECT MAX(created_at) FROM whatsapp_messages wm3
            WHERE wm3.contact_id = whatsapp_contacts.id AND wm3.sender_type = "client"
        ) > (
            SELECT MAX(created_at) FROM whatsapp_messages wm4
            WHERE wm4.contact_id = whatsapp_contacts.id AND wm4.sender_type IN ("system", "humano")
        )';
    }

    private function resolveLastActivity(object $contact): ?Carbon
    {
        if (!$contact->last_activity_at) {
            return null;
        }

        return Carbon::parse($contact->last_activity_at);
    }

    private function isPendingReply(object $contact): bool
    {
        if (!$contact->last_client_message_at) {
            return false;
        }

        $clientAt = Carbon::parse($contact->last_client_message_at);

        if (!$contact->last_reply_message_at) {
            return $clientAt->gte(now()->subHours(48));
        }

        return $clientAt->gt(Carbon::parse($contact->last_reply_message_at));
    }

    public function responseRatioPercent(int $inbound, int $outbound): float
    {
        if ($inbound === 0) {
            return 0;
        }

        return min(round(($outbound / $inbound) * 100, 1), 100);
    }

    private function responseRateFromMetrics(object $contact): float
    {
        return $this->responseRatioPercent(
            (int) ($contact->client_messages_count ?? 0),
            (int) ($contact->replied_messages_count ?? 0)
        );
    }

    private function responseRate(int $contactId): float
    {
        $inbound = WhatsappMessage::where('contact_id', $contactId)
            ->where('sender_type', 'client')
            ->count();

        $outbound = WhatsappMessage::where('contact_id', $contactId)
            ->whereIn('sender_type', ['system', 'humano'])
            ->count();

        return $this->responseRatioPercent($inbound, $outbound);
    }
}
