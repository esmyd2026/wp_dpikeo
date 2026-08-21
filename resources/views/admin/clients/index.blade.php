@extends('admin.layouts.app')

@section('header', 'Clientes')

@section('content')
@php
    use App\Services\ClientInsightsService;

    $fmt = fn ($dt) => $dt ? \Carbon\Carbon::parse($dt)->format('d/m/Y H:i') : '—';
    $fmtShort = fn ($dt) => $dt ? \Carbon\Carbon::parse($dt)->format('d/m H:i') : '—';
    $bestContactTimeHint = ClientInsightsService::BEST_CONTACT_TIME_HINT;
    $hasFilters = collect($filters ?? [])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    $attentionTotal = ($summary['pending_reply'] ?? 0) + ($summary['needs_agent'] ?? 0);
    $segmentHints = ClientInsightsService::SEGMENT_HINTS;
    $currentSegment = $filters['segment'] ?? '';
    $currentSegmentHint = $segmentHints[$currentSegment] ?? $segmentHints[''];
@endphp

<style>
    .clients-page { max-width: 1140px; margin: 0 auto; }

    .clients-top {
        display: flex; flex-wrap: wrap; align-items: flex-start;
        justify-content: space-between; gap: 1rem; margin-bottom: .85rem;
    }
    .clients-top h2 {
        margin: 0 0 .3rem; font-size: 1.35rem; font-weight: 800;
        color: #0f172a; letter-spacing: -.02em;
    }
    .clients-top .lead { margin: 0; font-size: .875rem; color: #64748b; max-width: 520px; }

    .clients-priority {
        display: grid; grid-template-columns: repeat(4, 1fr);
        gap: .65rem; margin-bottom: .85rem;
    }
    @media (max-width: 900px) { .clients-priority { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 480px) { .clients-priority { grid-template-columns: 1fr; } }

    .prio-card {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        padding: .85rem 1rem; min-height: 78px;
        box-shadow: 0 1px 3px rgba(15,23,42,.04);
    }
    .prio-card .lbl {
        font-size: .68rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .04em; color: #64748b; margin-bottom: .2rem;
    }
    .prio-card .val { font-size: 1.25rem; font-weight: 800; color: #0f172a; line-height: 1.2; }
    .prio-card .sub { font-size: .72rem; color: #94a3b8; margin-top: .15rem; }
    .prio-card.urgent { border-color: #fecaca; background: linear-gradient(180deg, #fef2f2, #fff); }
    .prio-card.urgent .val { color: #dc2626; }
    .prio-card.accent { border-color: #99f6e4; background: linear-gradient(180deg, #f0fdfa, #fff); }
    .prio-card.accent .val { color: #047857; }

    .clients-toolbar {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
        padding: .85rem 1rem; margin-bottom: .85rem;
        box-shadow: 0 1px 3px rgba(15,23,42,.04);
    }
    .clients-toolbar-main {
        display: flex; flex-wrap: wrap; align-items: flex-end; gap: .65rem;
    }
    .clients-toolbar-main .field { flex: 1; min-width: 160px; }
    .clients-toolbar-main .field.search { flex: 2; min-width: 220px; }
    .clients-toolbar-main label {
        display: block; font-size: .72rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: .25rem;
    }
    .segment-label-row {
        display: flex; align-items: center; gap: .35rem; margin-bottom: .25rem;
    }
    .segment-label-row .lbl-text {
        font-size: .72rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .04em; color: #64748b;
    }
    .segment-info-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 18px; height: 18px; padding: 0; border: none; background: transparent;
        color: #94a3b8; cursor: help; border-radius: 50%; font-size: .78rem;
        line-height: 1; flex-shrink: 0;
    }
    .segment-info-btn:hover { color: #128c7e; }
    .th-label-row {
        display: inline-flex; align-items: center; gap: .3rem; white-space: nowrap;
    }
    .metric-info-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 16px; height: 16px; padding: 0; border: none; background: transparent;
        color: #94a3b8; cursor: help; border-radius: 50%; font-size: .72rem;
        line-height: 1; vertical-align: middle;
    }
    .metric-info-btn:hover { color: #128c7e; }
    .segment-hint {
        font-size: .72rem; color: #64748b; line-height: 1.35;
        margin: .35rem 0 0; min-height: 2.5em;
    }
    .segment-field { max-width: 220px; }
    .clients-toolbar-actions { display: flex; gap: .4rem; flex-shrink: 0; }

    .clients-collapse {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        margin-bottom: .85rem; overflow: hidden;
    }
    .clients-collapse summary {
        padding: .65rem 1rem; cursor: pointer; font-weight: 600;
        font-size: .84rem; color: #475569; list-style: none;
        display: flex; align-items: center; gap: .45rem; background: #f8fafc;
    }
    .clients-collapse summary::-webkit-details-marker { display: none; }
    .clients-collapse summary::after {
        content: '▾'; margin-left: auto; color: #94a3b8; font-size: .75rem;
    }
    .clients-collapse[open] summary::after { transform: rotate(180deg); }
    .clients-collapse .inner {
        padding: .85rem 1rem 1rem; border-top: 1px solid #f1f5f9;
    }
    .clients-collapse .filter-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: .65rem; align-items: end;
    }

    .clients-table-wrap {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        overflow-x: auto; box-shadow: 0 1px 3px rgba(15,23,42,.04);
    }
    .clients-table {
        width: 100%; border-collapse: collapse; font-size: .82rem;
    }
    .clients-table th {
        padding: .55rem .65rem; text-align: left; font-size: .68rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .03em; color: #64748b;
        background: #f8fafc; border-bottom: 1px solid #e5e7eb; white-space: nowrap;
    }
    .clients-table td {
        padding: .5rem .65rem; border-bottom: 1px solid #f1f5f9;
        vertical-align: middle; color: #334155;
    }
    .clients-table tbody tr:hover { background: #fafbfc; }
    .clients-table tbody tr.needs-attention { background: #fffafa; }
    .clients-table tbody tr.needs-attention:hover { background: #fef2f2; }
    .clients-table tbody tr:last-child td { border-bottom: none; }

    .client-cell-name { font-weight: 700; color: #0f172a; white-space: nowrap; }
    .client-cell-muted { font-size: .78rem; color: #64748b; white-space: nowrap; }
    .client-cell-money { font-weight: 700; color: #047857; white-space: nowrap; }

    .client-status {
        display: inline-flex; align-items: center; gap: .25rem;
        font-size: .68rem; font-weight: 700; padding: .18rem .45rem; border-radius: 999px;
        white-space: nowrap;
    }
    .client-status.pending { background: #ffedd5; color: #c2410c; }
    .client-status.agent { background: #fee2e2; color: #991b1b; }
    .client-status.ok { background: #ecfdf5; color: #047857; }

    .client-badge {
        display: inline-flex; align-items: center; gap: .2rem;
        font-size: .62rem; font-weight: 600; padding: .15rem .4rem; border-radius: 999px;
        white-space: nowrap;
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

    .client-row-actions { display: flex; gap: .3rem; align-items: center; justify-content: flex-end; white-space: nowrap; }
    .c-btn {
        display: inline-flex; align-items: center; gap: .25rem;
        padding: .35rem .55rem; border-radius: 8px; font-size: .75rem;
        font-weight: 600; text-decoration: none; border: 1px solid #e2e8f0;
        background: #fff; color: #475569;
    }
    .c-btn:hover { background: #f8fafc; color: #0f172a; }
    .c-btn.primary {
        background: linear-gradient(135deg, #128c7e, #075e54);
        border-color: transparent; color: #fff !important;
    }
    .c-btn.primary:hover { color: #fff; }

    .clients-empty {
        text-align: center; padding: 3rem 1.5rem;
        background: #fff; border: 1px dashed #e2e8f0; border-radius: 14px; color: #64748b;
    }
    .clients-pagination {
        margin-top: 1rem; padding: .75rem 1rem;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    }
</style>

<div class="clients-page">
    {{-- 1. Contexto breve --}}
    <div class="clients-top">
        <div>
            <h2><i class="fas fa-users me-1 text-success"></i> Clientes</h2>
            <p class="lead">Quién necesita atención, quién compra y cuándo habló por última vez.</p>
        </div>
    </div>

    {{-- 2. KPIs accionables --}}
    <div class="clients-priority">
        <div class="prio-card {{ $attentionTotal > 0 ? 'urgent' : '' }}">
            <div class="lbl">Atención pendiente</div>
            <div class="val">{{ number_format($attentionTotal) }}</div>
            <div class="sub">{{ $summary['pending_reply'] ?? 0 }} sin responder · {{ $summary['needs_agent'] ?? 0 }} piden agente</div>
        </div>
        <div class="prio-card accent">
            <div class="lbl">Activos (7 días)</div>
            <div class="val">{{ number_format($summary['active_7d']) }}</div>
            <div class="sub">de {{ number_format($summary['total']) }} clientes</div>
        </div>
        <div class="prio-card">
            <div class="lbl">Compradores frecuentes</div>
            <div class="val">{{ number_format($summary['frequent_buyers']) }}</div>
            <div class="sub">3+ pedidos cerrados</div>
        </div>
        <div class="prio-card">
            <div class="lbl">Total en lista</div>
            <div class="val">{{ number_format($summary['total']) }}</div>
            <div class="sub">Con conversación o pedidos</div>
        </div>
    </div>

    {{-- 3. Búsqueda rápida --}}
    <form class="clients-toolbar" method="get" action="{{ route('admin.clients.index') }}">
        <div class="clients-toolbar-main">
            <div class="field search">
                <label for="q">Buscar</label>
                <input type="text" id="q" name="q" class="form-control form-control-sm"
                    placeholder="Nombre, teléfono, cédula o dirección" value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="field segment-field">
                <div class="segment-label-row">
                    <span class="lbl-text" id="segment-label">Segmento</span>
                    <button type="button" class="segment-info-btn" id="segment-info-btn"
                        title="{{ $currentSegmentHint }}" aria-label="Qué significa este segmento">
                        <i class="fas fa-info-circle"></i>
                    </button>
                </div>
                <select id="segment" name="segment" class="form-select form-select-sm"
                    title="{{ $currentSegmentHint }}" aria-describedby="segment-hint">
                    @foreach($segments as $value => $label)
                        <option value="{{ $value }}"
                            title="{{ $segmentHints[$value] ?? '' }}"
                            @selected($currentSegment === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="segment-hint mb-0" id="segment-hint">{{ $currentSegmentHint }}</p>
            </div>
            <div class="clients-toolbar-actions">
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-search me-1"></i>Buscar</button>
                @if($hasFilters)
                    <a href="{{ route('admin.clients.index') }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                @endif
            </div>
        </div>

        @if($filters['sort'] ?? null)
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        @endif
        @if($filters['activity_from'] ?? null)
            <input type="hidden" name="activity_from" value="{{ $filters['activity_from'] }}">
        @endif
        @if($filters['activity_to'] ?? null)
            <input type="hidden" name="activity_to" value="{{ $filters['activity_to'] }}">
        @endif
        @if($filters['min_orders'] ?? null)
            <input type="hidden" name="min_orders" value="{{ $filters['min_orders'] }}">
        @endif
    </form>

    {{-- 4. Filtros avanzados (colapsado) --}}
    <details class="clients-collapse" @if(($filters['sort'] ?? 'recent') !== 'recent' || ($filters['activity_from'] ?? '') || ($filters['activity_to'] ?? '') || ($filters['min_orders'] ?? '')) open @endif>
        <summary><i class="fas fa-sliders-h text-muted"></i> Orden y filtros avanzados</summary>
        <div class="inner">
            <form method="get" action="{{ route('admin.clients.index') }}">
                <div class="filter-grid">
                    <div>
                        <label class="form-label small mb-1">Ordenar</label>
                        <select name="sort" class="form-select form-select-sm">
                            @foreach($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'recent') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1">Actividad desde</label>
                        <input type="date" name="activity_from" class="form-control form-control-sm" value="{{ $filters['activity_from'] ?? '' }}">
                    </div>
                    <div>
                        <label class="form-label small mb-1">Actividad hasta</label>
                        <input type="date" name="activity_to" class="form-control form-control-sm" value="{{ $filters['activity_to'] ?? '' }}">
                    </div>
                    <div>
                        <label class="form-label small mb-1">Mín. pedidos</label>
                        <input type="number" name="min_orders" min="0" class="form-control form-control-sm" value="{{ $filters['min_orders'] ?? '' }}">
                    </div>
                    <div class="d-flex align-items-end gap-2">
                        <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                        <input type="hidden" name="segment" value="{{ $filters['segment'] ?? '' }}">
                        <button type="submit" class="btn btn-outline-success btn-sm w-100">Aplicar</button>
                    </div>
                </div>
            </form>
        </div>
    </details>

    {{-- 5. Lista de clientes (tabla) --}}
    <div class="clients-table-wrap">
        @if($clients->isEmpty())
            <div class="clients-empty">
                <i class="fas fa-user-slash fa-2x mb-2 opacity-50"></i>
                <p class="mb-0">No hay clientes que coincidan con los filtros.</p>
            </div>
        @else
            <table class="clients-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Teléfono</th>
                        <th>Segmento</th>
                        <th>Estado</th>
                        <th>Pedidos</th>
                        <th>Comprado</th>
                        <th>
                            <span class="th-label-row">
                                Mejor hora
                                <button type="button" class="metric-info-btn" title="{{ $bestContactTimeHint }}" aria-label="Cómo se calcula la mejor hora de contacto">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </span>
                        </th>
                        <th>Último msg.</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clients as $client)
                        @php
                            $badges = $insights->indicators($client);
                            $pendingReply = collect($badges)->contains(fn ($b) => ($b['key'] ?? '') === 'pending');
                            $needsAgent = !empty($client->needs_agent_flag);
                            $needsAttention = $pendingReply || $needsAgent;
                            $segmentBadge = collect($badges)->first(fn ($b) => !in_array($b['key'] ?? '', ['pending', 'agent'], true));
                        @endphp
                        <tr class="{{ $needsAttention ? 'needs-attention' : '' }}">
                            <td>
                                <span class="client-cell-name">{{ $client->name ?: 'Sin nombre' }}</span>
                                @if($client->national_id)
                                    <div class="client-cell-muted"><i class="fas fa-id-card me-1"></i>{{ $client->national_id }}</div>
                                @endif
                            </td>
                            <td class="client-cell-muted"><i class="fab fa-whatsapp text-success me-1"></i>{{ $client->phone_number }}</td>
                            <td>
                                @if($segmentBadge)
                                    <span class="client-badge {{ $segmentBadge['tone'] }}">
                                        <i class="fas {{ $segmentBadge['icon'] }}"></i>{{ $segmentBadge['label'] }}
                                    </span>
                                @else
                                    <span class="client-cell-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($pendingReply)
                                    <span class="client-status pending"><i class="fas fa-clock"></i> Sin responder</span>
                                @elseif($needsAgent)
                                    <span class="client-status agent"><i class="fas fa-headset"></i> Pide agente</span>
                                @else
                                    <span class="client-status ok"><i class="fas fa-check-circle"></i> Al día</span>
                                @endif
                            </td>
                            <td>{{ $client->orders_count ?? 0 }}</td>
                            <td class="client-cell-money">
                                @if(($client->orders_count ?? 0) > 0)
                                    ${{ number_format((float) ($client->total_spent ?? 0), 0) }}
                                @else
                                    <span class="client-cell-muted">—</span>
                                @endif
                            </td>
                            @php $bestTime = $bestContactTimes[$client->id] ?? null; @endphp
                            <td class="client-cell-muted" title="{{ $bestTime ? 'Basado en '.$bestTime['total_messages'].' mensajes del último año' : 'Sin mensajes del cliente' }}">
                                @if($bestTime)
                                    <i class="fas fa-clock text-success me-1"></i>{{ $bestTime['window'] }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="client-cell-muted">{{ $fmtShort($client->last_client_message_at) }}</td>
                            <td>
                                <div class="client-row-actions">
                                    @perm('chats.open')
                                        <a href="{{ route('admin.chat', $client->id) }}" class="c-btn primary" title="Abrir chat">
                                            <i class="fas fa-comments"></i>
                                        </a>
                                    @endperm
                                    <a href="{{ route('admin.clients.show', $client) }}" class="c-btn" title="Ver detalle">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($clients->hasPages())
        <div class="clients-pagination">{{ $clients->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const segmentHints = @json($segmentHints);
    const select = document.getElementById('segment');
    const hintEl = document.getElementById('segment-hint');
    const infoBtn = document.getElementById('segment-info-btn');

    if (!select || !hintEl) return;

    function updateSegmentHint() {
        const hint = segmentHints[select.value] ?? segmentHints[''] ?? '';
        hintEl.textContent = hint;
        select.title = hint;
        if (infoBtn) infoBtn.title = hint;
    }

    select.addEventListener('change', updateSegmentHint);
    select.addEventListener('mouseenter', updateSegmentHint);
})();
</script>
@endpush
