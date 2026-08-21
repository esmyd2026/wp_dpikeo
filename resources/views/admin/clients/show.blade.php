@extends('admin.layouts.app')

@section('header', 'Detalle del cliente')

@section('content')
@php
    use App\Helpers\WhatsappMessageFormatter;
    use App\Services\ClientInsightsService;

    $bestContactTimeHint = ClientInsightsService::BEST_CONTACT_TIME_HINT;

    $statusLabels = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'preparing' => 'En preparación',
        'ready' => 'Listo para entregar',
        'completed' => 'Entregado',
        'cancelled' => 'Cancelado',
        'payment_pending' => 'Pago pend.',
        'paid' => 'Pagado',
    ];
    $fmt = fn ($dt) => $dt ? \Carbon\Carbon::parse($dt)->format('d/m/Y H:i') : '—';
    $initials = collect(explode(' ', $contact->name ?: '?'))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('');
    $needsAttention = $response_metrics['pending_reply'] || !empty($contact->needs_agent_flag);
    $memberTenure = $contact->created_at
        ? $contact->created_at->locale('es')->diffForHumans(now(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'join' => ' y '])
        : null;
@endphp

<style>
    body.client-detail-page {
        background: linear-gradient(180deg, #e8edf3 0%, #f1f5f9 45%, #eef2f7 100%) !important;
    }
    .client-detail { max-width: 1140px; margin: 0 auto; }

    /* —— 1. Identidad —— */
    .client-hero {
        background: linear-gradient(135deg, #ffffff 0%, #f0fdfa 100%);
        border: 1px solid #99f6e4;
        border-left: 4px solid #128c7e;
        border-radius: 16px;
        padding: 1.15rem 1.35rem;
        margin-bottom: .85rem;
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 1rem 1.25rem;
        align-items: center;
        box-shadow: 0 6px 22px rgba(15, 118, 110, 0.1);
    }
    @media (max-width: 768px) {
        .client-hero { grid-template-columns: auto 1fr; }
        .client-hero-actions { grid-column: 1 / -1; }
    }
    .client-avatar {
        width: 52px; height: 52px; border-radius: 14px;
        background: linear-gradient(135deg, #128c7e, #25d366);
        color: #fff; font-weight: 800; font-size: 1.1rem;
        display: flex; align-items: center; justify-content: center;
    }
    .client-hero h1 { margin: 0; font-size: 1.3rem; font-weight: 800; color: #0f172a; letter-spacing: -.02em; }
    .client-chips { display: flex; flex-wrap: wrap; gap: .35rem .5rem; margin-top: .45rem; }
    .client-chip {
        font-size: .78rem; color: #475569; background: #f8fafc;
        border: 1px solid #e2e8f0; border-radius: 8px; padding: .2rem .55rem;
        display: inline-flex; align-items: center; gap: .35rem;
    }
    .client-badges { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .5rem; }
    .client-badge {
        display: inline-flex; align-items: center; gap: .25rem;
        font-size: .68rem; font-weight: 600; padding: .18rem .5rem; border-radius: 999px;
    }
    .client-badge.green { background: #dcfce7; color: #166534; }
    .client-badge.purple { background: #f3e8ff; color: #6b21a8; }
    .client-badge.blue { background: #dbeafe; color: #1e40af; }
    .client-badge.red { background: #fee2e2; color: #991b1b; }
    .client-badge.amber { background: #fef3c7; color: #92400e; }
    .client-badge.gray { background: #f3f4f6; color: #4b5563; }
    .client-badge.orange { background: #ffedd5; color: #c2410c; }
    .client-badge.slate { background: #e2e8f0; color: #475569; }
    .client-badge.teal { background: #ccfbf1; color: #115e59; }

    /* —— 2. Alerta urgente —— */
    .client-alert {
        border-radius: 12px; padding: .75rem 1rem; margin-bottom: .85rem;
        display: flex; align-items: center; gap: .65rem; font-size: .875rem; font-weight: 600;
    }
    .client-alert.danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .client-alert.warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }

    /* —— 3. Lo esencial (4 métricas) —— */
    .client-priority {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: .65rem;
        margin-bottom: .85rem;
    }
    @media (max-width: 900px) { .client-priority { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 480px) { .client-priority { grid-template-columns: 1fr; } }
    .client-priority.five-cols { grid-template-columns: repeat(5, 1fr); }
    @media (max-width: 1100px) { .client-priority.five-cols { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 700px) { .client-priority.five-cols { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 480px) { .client-priority.five-cols { grid-template-columns: 1fr; } }

    .prio-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: .85rem 1rem .85rem 1.1rem;
        min-height: 88px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
        position: relative;
        overflow: hidden;
    }
    .prio-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
    }
    .prio-card .lbl {
        font-size: .68rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .04em; color: #475569; margin-bottom: .25rem;
    }
    .prio-card .val { font-size: 1.15rem; font-weight: 800; color: #0f172a; line-height: 1.2; }
    .prio-card .sub { font-size: .72rem; color: #64748b; margin-top: .2rem; }
    .metric-label-row {
        display: inline-flex; align-items: center; gap: .3rem;
    }
    .metric-info-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 16px; height: 16px; padding: 0; border: none; background: transparent;
        color: #94a3b8; cursor: help; border-radius: 50%; font-size: .72rem;
        line-height: 1; vertical-align: middle;
    }
    .metric-info-btn:hover { color: #128c7e; }

    .prio-card.tone-attention::before { background: #10b981; }
    .prio-card.tone-attention {
        background: linear-gradient(145deg, #ecfdf5 0%, #fff 72%);
        border-color: #6ee7b7;
    }
    .prio-card.tone-attention.urgent {
        background: linear-gradient(145deg, #fef2f2 0%, #fff 72%);
        border-color: #fca5a5;
    }
    .prio-card.tone-attention.urgent::before { background: #ef4444; }

    .prio-card.tone-commerce::before { background: #2563eb; }
    .prio-card.tone-commerce {
        background: linear-gradient(145deg, #eff6ff 0%, #fff 72%);
        border-color: #93c5fd;
    }

    .prio-card.tone-activity::before { background: #64748b; }
    .prio-card.tone-activity {
        background: linear-gradient(145deg, #f1f5f9 0%, #fff 72%);
        border-color: #cbd5e1;
    }

    .prio-card.tone-contact::before { background: #d97706; }
    .prio-card.tone-contact {
        background: linear-gradient(145deg, #fffbeb 0%, #fff 72%);
        border-color: #fcd34d;
    }

    .prio-card.tone-response::before { background: #0f766e; }
    .prio-card.tone-response {
        background: linear-gradient(145deg, #ccfbf1 0%, #fff 72%);
        border-color: #5eead4;
    }

    .prio-card.accent { border-color: #6ee7b7; }
    .prio-card.urgent { border-color: #fca5a5; }
    .prio-card.urgent .val { color: #dc2626; }
    .prio-card .sub.ok { color: #047857; font-weight: 600; }
    .prio-card .sub.agent { color: #b45309; font-weight: 600; }

    /* —— Paneles —— */
    .client-section {
        background: #fff;
        border: 1px solid #dbe3ee;
        border-radius: 14px;
        margin-bottom: .85rem;
        overflow: hidden;
        box-shadow: 0 3px 14px rgba(15, 23, 42, 0.06);
    }
    .client-section-head {
        padding: .85rem 1.15rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        background: #f8fafc;
    }
    .client-section.notes-section .client-section-head {
        background: linear-gradient(90deg, #fffbeb, #fff);
        border-bottom-color: #fde68a;
    }
    .client-section.messages-section .client-section-head {
        background: linear-gradient(90deg, #ecfdf5, #fff);
        border-bottom-color: #a7f3d0;
    }
    .client-section.orders-section .client-section-head {
        background: linear-gradient(90deg, #eff6ff, #fff);
        border-bottom-color: #bfdbfe;
    }
    .client-section-head h2 {
        margin: 0; font-size: .92rem; font-weight: 700; color: #0f172a;
    }
    .client-section-head .hint { font-size: .72rem; color: #94a3b8; font-weight: 400; }
    .client-section-body { padding: 1rem 1.15rem; }

    .client-grid-2 {
        display: grid; grid-template-columns: 1fr 1fr; gap: .85rem;
    }
    @media (max-width: 992px) { .client-grid-2 { grid-template-columns: 1fr; } }

    /* Observaciones */
    .notes-list { max-height: 280px; overflow-y: auto; }
    .note-item { padding: .65rem 0; border-bottom: 1px solid #f1f5f9; }
    .note-item:first-child { padding-top: 0; }
    .note-item:last-child { border-bottom: none; }
    .note-head { display: flex; flex-wrap: wrap; gap: .35rem .65rem; font-size: .75rem; margin-bottom: .25rem; }
    .note-author { font-weight: 700; color: #0f172a; }
    .note-date { color: #94a3b8; }
    .note-body { font-size: .84rem; color: #334155; white-space: pre-wrap; line-height: 1.45; }
    .note-form textarea { border-radius: 10px; font-size: .84rem; min-height: 72px; }

    /* Timeline */
    .timeline { max-height: 320px; overflow-y: auto; }
    .timeline-item {
        display: flex; gap: .65rem; padding: .55rem 0;
        border-bottom: 1px solid #f8fafc; font-size: .82rem;
    }
    .timeline-item:last-child { border-bottom: none; }
    .timeline-icon {
        width: 26px; height: 26px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: .65rem; flex-shrink: 0;
    }
    .timeline-icon.client { background: #dbeafe; color: #1d4ed8; }
    .timeline-icon.system { background: #dcfce7; color: #15803d; }
    .timeline-icon.humano { background: #fef3c7; color: #b45309; }
    .timeline-meta { font-size: .68rem; color: #94a3b8; margin-top: .1rem; }

    .orders-mini-table { font-size: .82rem; margin: 0; }
    .orders-mini-table th { font-size: .68rem; text-transform: uppercase; color: #64748b; }

    /* Colapsables (menos importante) */
    .client-collapse {
        background: #fff;
        border: 1px solid #dbe3ee;
        border-radius: 12px;
        margin-bottom: .65rem;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
    }
    .client-collapse summary {
        padding: .75rem 1.1rem; cursor: pointer; font-weight: 600;
        font-size: .88rem; color: #334155; list-style: none;
        display: flex; align-items: center; gap: .5rem;
        background: #eef2f7;
    }
    .client-collapse summary::-webkit-details-marker { display: none; }
    .client-collapse summary::after {
        content: '▾'; margin-left: auto; color: #94a3b8; font-size: .75rem;
    }
    .client-collapse[open] summary::after { transform: rotate(180deg); }
    .client-collapse .inner { padding: 1rem 1.15rem; border-top: 1px solid #f1f5f9; }

    .profile-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: .75rem;
    }
    .profile-grid .form-label { font-size: .78rem; font-weight: 600; color: #475569; }
    .profile-phone {
        font-size: .82rem; color: #64748b; padding: .5rem .65rem;
        background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;
    }
</style>
<script>document.body.classList.add('client-detail-page');</script>

<div class="client-detail">
    <div class="mb-3">
        <a href="{{ route('admin.clients.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Clientes
        </a>
    </div>

    {{-- 1. IDENTIDAD + ACCIÓN PRINCIPAL --}}
    <div class="client-hero">
        <div class="client-avatar" aria-hidden="true">{{ strtoupper($initials) }}</div>
        <div>
            <h1>{{ $contact->name ?: 'Sin nombre' }}</h1>
            <div class="client-chips">
                <span class="client-chip"><i class="fab fa-whatsapp text-success"></i>{{ $contact->phone_number }}</span>
                @if($memberTenure)
                    <span class="client-chip" title="Cliente desde {{ $contact->created_at->format('d/m/Y') }}">
                        <i class="fas fa-clock"></i>{{ $memberTenure }} como cliente
                    </span>
                @endif
                @if($contact->national_id)
                    <span class="client-chip"><i class="fas fa-id-card"></i>{{ $contact->national_id }}</span>
                @endif
                @if($contact->address)
                    <span class="client-chip"><i class="fas fa-map-marker-alt"></i>{{ \Illuminate\Support\Str::limit($contact->address, 40) }}</span>
                @endif
                @if($contact->birth_date)
                    <span class="client-chip"><i class="fas fa-birthday-cake"></i>{{ $contact->birth_date->format('d/m/Y') }}</span>
                @endif
                @if(!empty($best_contact_time['window']))
                    <span class="client-chip">
                        <i class="fas fa-bell"></i> Mejor contacto: {{ $best_contact_time['window'] }}
                        <button type="button" class="metric-info-btn ms-1" title="{{ $bestContactTimeHint }}" aria-label="Cómo se calcula la mejor hora de contacto">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </span>
                @endif
            </div>
            @if($indicators)
                <div class="client-badges">
                    @foreach($indicators as $badge)
                        <span class="client-badge {{ $badge['tone'] }}">
                            <i class="fas {{ $badge['icon'] }}"></i>{{ $badge['label'] }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="client-hero-actions">
            @perm('bulk_orders.create')
                <a href="{{ route('admin.orders.bulk.create', ['contact' => $contact->id]) }}" class="btn btn-outline-success btn-lg">
                    <i class="fas fa-clipboard-list me-1"></i> Nuevo pedido
                </a>
            @endperm
            @perm('chats.open')
                <a href="{{ route('admin.chat', $contact->id) }}" class="btn btn-success btn-lg">
                    <i class="fas fa-comments me-1"></i> Abrir chat
                </a>
            @endperm
        </div>
    </div>

    {{-- 2. ALERTAS (solo si aplica) --}}
    @if($response_metrics['pending_reply'])
        <div class="client-alert danger">
            <i class="fas fa-clock"></i>
            Esperando respuesta desde {{ $fmt($contact->last_client_message_at) }}
        </div>
    @elseif(!empty($contact->needs_agent_flag))
        <div class="client-alert warn">
            <i class="fas fa-headset"></i>
            Solicita hablar con un asesor humano
        </div>
    @endif

    {{-- 3. LO ESENCIAL EN 5 TARJETAS --}}
    <div class="client-priority five-cols">
        <div class="prio-card tone-attention {{ $needsAttention ? 'urgent' : '' }}">
            <div class="lbl">Atención</div>
            @if($response_metrics['pending_reply'])
                <div class="val">Pendiente</div>
                <div class="sub">Responder en chat</div>
            @else
                <div class="val" style="font-size:1rem;color:#059669;">Al día</div>
                <div class="sub ok">Sin mensajes sin contestar</div>
            @endif
        </div>
        <div class="prio-card tone-commerce">
            <div class="lbl">Comercial</div>
            <div class="val">${{ number_format((float) ($contact->total_spent ?? 0), 0) }}</div>
            <div class="sub">{{ $contact->orders_count ?? 0 }} pedido(s) · {{ $contact->recent_orders_count ?? 0 }} recientes</div>
        </div>
        <div class="prio-card tone-activity">
            <div class="lbl">Último mensaje</div>
            <div class="val" style="font-size:.95rem;">{{ $fmt($contact->last_client_message_at) }}</div>
            <div class="sub">{{ $contact->client_messages_count ?? 0 }} mensajes del cliente</div>
        </div>
        <div class="prio-card tone-contact">
            <div class="lbl">
                <span class="metric-label-row">
                    Mejor hora de contacto
                    <button type="button" class="metric-info-btn" title="{{ $bestContactTimeHint }}" aria-label="Cómo se calcula la mejor hora de contacto">
                        <i class="fas fa-info-circle"></i>
                    </button>
                </span>
            </div>
            @if(!empty($best_contact_time['window']))
                <div class="val" style="font-size:.95rem;">{{ $best_contact_time['window'] }}</div>
                <div class="sub">
                    {{ $best_contact_time['message_count'] }} de {{ $best_contact_time['total_messages'] }} msgs
                    ({{ number_format($best_contact_time['share_percent'], 0) }}%)
                    · confianza {{ $best_contact_time['confidence'] }}
                </div>
            @else
                <div class="val" style="font-size:1rem;">—</div>
                <div class="sub">Sin mensajes del cliente aún</div>
            @endif
        </div>
        <div class="prio-card tone-response">
            <div class="lbl">Tiempo de respuesta</div>
            @if($response_metrics['last_seconds'] !== null)
                <div class="val">{{ $response_metrics['last_formatted'] }}</div>
                <div class="sub {{ $response_metrics['last_responder_kind'] === 'agent' ? 'agent' : 'ok' }}">
                    {{ $response_metrics['last_responder_label'] }}
                    @if($response_metrics['sample_count'] > 0)
                        · prom. {{ $response_metrics['avg_formatted'] }}
                    @endif
                </div>
            @else
                <div class="val" style="font-size:1rem;">—</div>
                <div class="sub">Sin respuestas aún</div>
            @endif
        </div>
    </div>

    {{-- 4. OPERATIVO: observaciones + mensajes recientes --}}
    <div class="client-grid-2">
        <div class="client-section notes-section">
            <div class="client-section-head">
                <h2><i class="fas fa-sticky-note text-warning me-1"></i> Observaciones</h2>
                <span class="hint">{{ $contact_notes->count() }} nota(s)</span>
            </div>
            <div class="client-section-body">
                @perm('clients.notes')
                    <form method="POST" action="{{ route('admin.clients.notes.store', $contact) }}" class="note-form mb-3">
                        @csrf
                        <textarea name="body" class="form-control mb-2" placeholder="Nota para el equipo..." required>{{ old('body') }}</textarea>
                        <button type="submit" class="btn btn-outline-success btn-sm"><i class="fas fa-plus me-1"></i>Agregar</button>
                    </form>
                @endperm
                <div class="notes-list">
                    @forelse($contact_notes as $note)
                        <article class="note-item">
                            <div class="note-head">
                                <span class="note-author">{{ $note->user?->name ?? 'Agente' }}</span>
                                <span class="note-date">{{ $note->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            <div class="note-body">{{ $note->body }}</div>
                        </article>
                    @empty
                        <p class="text-muted mb-0 small">Sin observaciones. Agrega contexto para quien atienda después.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="client-section messages-section">
            <div class="client-section-head">
                <h2><i class="fas fa-comment-dots text-success me-1"></i> Últimos mensajes</h2>
                @perm('chats.open')
                    <a href="{{ route('admin.chat', $contact->id) }}" class="hint text-decoration-none">Ver chat →</a>
                @endperm
            </div>
            <div class="client-section-body">
                <div class="timeline">
                    @forelse($recent_messages->take(8) as $message)
                        @php
                            $iconClass = match($message->senderKind()) {
                                'client' => 'client', 'agent' => 'humano', default => 'system',
                            };
                            $senderLabel = $message->senderBadgeLabel($contact->name ?? null);
                            $badgeIcon = match($message->senderKind()) {
                                'client' => 'user', 'agent' => 'headset', default => 'robot',
                            };
                            $preview = WhatsappMessageFormatter::displayText($message->content, $message->type, $message->metadata ?? []);
                        @endphp
                        <div class="timeline-item">
                            <div class="timeline-icon {{ $iconClass }}"><i class="fas fa-{{ $badgeIcon }}"></i></div>
                            <div style="flex:1;min-width:0;">
                                <strong>{{ $senderLabel }}</strong>
                                <div>{{ \Illuminate\Support\Str::limit($preview, 90) }}</div>
                                <div class="timeline-meta">{{ $message->created_at->format('d/m/Y H:i') }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0 small">Sin mensajes.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- 5. PEDIDOS --}}
    <div class="client-section orders-section">
        <div class="client-section-head">
            <h2><i class="fas fa-shopping-cart text-success me-1"></i> Pedidos recientes</h2>
        </div>
        <div class="client-section-body pt-2 pb-2">
            @if($orders->isEmpty())
                <p class="text-muted mb-0 small px-1">Sin pedidos registrados.</p>
            @else
                <div class="table-responsive">
                    <table class="table orders-mini-table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders->take(8) as $order)
                                <tr>
                                    <td>{{ $order->id }}</td>
                                    <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $statusLabels[$order->status] ?? $order->status }}</td>
                                    <td class="text-end">${{ number_format((float) $order->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- 6. MENOS URGENTE: datos editables --}}
    @perm('clients.update')
    <details class="client-collapse">
        <summary><i class="fas fa-user-edit me-1 text-muted"></i> Editar datos del cliente</summary>
        <div class="inner">
            <p class="text-muted small mb-3">Campos opcionales. El teléfono WhatsApp no se modifica.</p>
            <form method="POST" action="{{ route('admin.clients.update', $contact) }}">
                @csrf
                @method('PUT')
                <div class="profile-grid">
                    <div>
                        <label class="form-label">Nombre completo</label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $contact->name) }}">
                    </div>
                    <div>
                        <label class="form-label">Cédula / documento</label>
                        <input type="text" name="national_id" class="form-control form-control-sm" value="{{ old('national_id', $contact->national_id) }}">
                    </div>
                    <div>
                        <label class="form-label">Fecha de nacimiento</label>
                        <input type="date" name="birth_date" class="form-control form-control-sm" value="{{ old('birth_date', $contact->birth_date?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="form-label">Teléfono WhatsApp</label>
                        <div class="profile-phone"><i class="fab fa-whatsapp me-1 text-success"></i>{{ $contact->phone_number }}</div>
                    </div>
                    <div style="grid-column:1/-1;">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="address" class="form-control form-control-sm" value="{{ old('address', $contact->address) }}">
                    </div>
                    <div style="grid-column:1/-1;" class="mt-1 pt-2 border-top">
                        <p class="text-muted small mb-2"><i class="fas fa-file-invoice me-1"></i> Datos para factura (se guardan en el perfil)</p>
                    </div>
                    <div>
                        <label class="form-label">Tipo de factura</label>
                        <select name="billing_type" class="form-select form-select-sm">
                            <option value="">— Sin definir —</option>
                            <option value="cedula" @selected(old('billing_type', $contact->billing_type) === 'cedula')>Con cédula</option>
                            <option value="ruc" @selected(old('billing_type', $contact->billing_type) === 'ruc')>Con RUC</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Cédula o RUC</label>
                        <input type="text" name="billing_id" class="form-control form-control-sm" value="{{ old('billing_id', $contact->billing_id ?? $contact->national_id) }}">
                    </div>
                    <div style="grid-column:1/-1;">
                        <label class="form-label">Nombre / Razón social (factura)</label>
                        <input type="text" name="billing_legal_name" class="form-control form-control-sm" value="{{ old('billing_legal_name', $contact->billing_legal_name ?? $contact->name) }}">
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-sm mt-3"><i class="fas fa-save me-1"></i>Guardar</button>
            </form>
        </div>
    </details>
    @endperm

    {{-- 7. ANALÍTICA HISTÓRICA --}}
    <details class="client-collapse">
        <summary><i class="fas fa-chart-bar me-1 text-muted"></i> Actividad mensual (6 meses)</summary>
        <div class="inner">
            @if($messages_by_month->isEmpty())
                <p class="text-muted mb-0 small">Sin actividad registrada.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="font-size:.84rem;">
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th class="text-center">Cliente</th>
                                <th class="text-center">Respuestas</th>
                                <th class="text-center">Ratio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($messages_by_month as $row)
                                <tr>
                                    <td>{{ $row->month }}</td>
                                    <td class="text-center">{{ $row->inbound }}</td>
                                    <td class="text-center">{{ $row->outbound }}</td>
                                    <td class="text-center">{{ number_format($insights->responseRatioPercent((int) $row->inbound, (int) $row->outbound), 0) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <p class="text-muted small mt-2 mb-0">
                Cliente desde {{ $contact->created_at?->format('d/m/Y') ?? '—' }}
                · Bot {{ $contact->bot_enabled ? 'activo' : 'pausado' }}
                · {{ $contact->replied_messages_count ?? 0 }} respuestas totales (ratio {{ number_format($response_rate, 0) }}%)
            </p>
        </div>
    </details>
</div>
@endsection
