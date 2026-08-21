@extends('admin.layouts.app')

@section('header')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endsection

@section('content')
<style>
    .orders-page { max-width: 1140px; margin: 0 auto; }
    .orders-top { margin-bottom: .85rem; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; align-items: flex-end; }
    .orders-top h2 { margin: 0 0 .3rem; font-size: 1.35rem; font-weight: 800; color: #0f172a; }
    .orders-top .lead { margin: 0; font-size: .875rem; color: #64748b; }
    .dash-filter {
        display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
        background: #fff; border: 1px solid #e3e7ee; border-radius: 10px; padding: .5rem .75rem;
    }
    .dash-filter input {
        border: 1px solid #dee2e6; border-radius: 8px; padding: .4rem .6rem; font-size: .85rem;
    }
    .dash-filter button {
        background: #128c7e; color: #fff; border: none; border-radius: 8px;
        padding: .45rem 1rem; font-size: .85rem; font-weight: 600; cursor: pointer;
    }
    .orders-priority {
        display: grid; grid-template-columns: repeat(4, 1fr);
        gap: .65rem; margin-bottom: .85rem;
    }
    @media (max-width: 900px) { .orders-priority { grid-template-columns: repeat(2, 1fr); } }
    .prio-card {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        padding: .85rem 1rem; box-shadow: 0 1px 3px rgba(15,23,42,.04);
    }
    .prio-card .lbl { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
    .prio-card .val { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin-top: .15rem; }
    .prio-card .sub { font-size: .72rem; color: #64748b; margin-top: .2rem; }
 border-color: #fde68a; background: linear-gradient(180deg, #fffbeb, #fff); }
    .prio-card.pending .val { color: #b45309; }
    .prio-card.confirmed { border-color: #bfdbfe; background: linear-gradient(180deg, #eff6ff, #fff); }
    .prio-card.confirmed .val { color: #1d4ed8; }
    .prio-card.paid { border-color: #bbf7d0; background: linear-gradient(180deg, #ecfdf5, #fff); }
    .prio-card.paid .val { color: #047857; }
    .prio-card.cancelled { border-color: #fecaca; background: linear-gradient(180deg, #fef2f2, #fff); }
    .prio-card.cancelled .val { color: #dc2626; }
    .orders-toolbar {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
        padding: .85rem 1rem; margin-bottom: .85rem;
        display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end;
    }
    .orders-export-form {
        display: flex; flex-wrap: wrap; gap: .5rem; align-items: flex-end; margin-left: auto;
    }
    .orders-export-form .field label {
        display: block; font-size: .65rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .03em; color: #64748b; margin-bottom: .2rem;
    }
    .orders-export-form .field input,
    .orders-export-form .field select {
        border: 1px solid #e2e8f0; border-radius: 8px; padding: .4rem .55rem; font-size: .8rem;
    }
    .o-btn {
        display: inline-flex; align-items: center; gap: .3rem; padding: .35rem .55rem;
        border-radius: 8px; font-size: .75rem; font-weight: 600; border: 1px solid #e2e8f0;
        background: #fff; color: #475569; cursor: pointer; text-decoration: none;
    }
    .o-btn.export { background: #0f766e; border-color: #0f766e; color: #fff !important; }
    .orders-table-wrap {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        overflow-x: auto; box-shadow: 0 1px 3px rgba(15,23,42,.04); margin-bottom: .85rem;
    }
    .orders-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .orders-table th {
        padding: .55rem .65rem; text-align: left; font-size: .68rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .03em; color: #64748b;
        background: #f8fafc; border-bottom: 1px solid #e5e7eb; white-space: nowrap;
    }
    .orders-table td {
        padding: .5rem .65rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #334155;
    }
    .orders-table tbody tr:hover { background: #fafbfc; }
    .order-cell-name { font-weight: 700; color: #0f172a; white-space: nowrap; }
    .order-cell-money { font-weight: 700; color: #0f172a; white-space: nowrap; }
    .badge-status {
        font-size: .7rem; font-weight: 600; padding: .2rem .5rem; border-radius: 6px;
    }
    .badge-status.confirmed, .badge-status.completed, .badge-status.paid { background: #dcfce7; color: #166534; }
    .badge-status.pending, .badge-status.payment_pending { background: #fef3c7; color: #92400e; }
    .badge-status.cancelled { background: #fee2e2; color: #991b1b; }
    .panel-chart { height: 240px; position: relative; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1rem; margin-bottom: .85rem; }
    .panel-chart h3 { font-size: .95rem; font-weight: 600; margin: 0 0 1rem; color: #0f172a; }
    .report-footer-link { font-size: .82rem; color: #128c7e; text-decoration: none; font-weight: 600; }
</style>

<div class="orders-page">
    <div class="orders-top">
        <div>
            <h2><i class="fas fa-chart-bar me-1 text-success"></i> Reportes de pedidos</h2>
            <p class="lead">Ventas e ingresos · {{ $from->format('d/m/Y') }} — {{ $to->format('d/m/Y') }}</p>
        </div>
        @include('admin.partials.report-period-filter', ['action' => route('admin.reports.orders')])
    </div>

    <div class="orders-priority">
        @foreach(['pending', 'confirmed', 'paid', 'cancelled'] as $cardKey)
            @php $card = $statusCards[$cardKey]; @endphp
            <div class="prio-card {{ $cardKey }}">
                <div class="lbl">{{ $card['label'] }}</div>
                <div class="val">{{ number_format($card['count']) }}</div>
                <div class="sub">${{ number_format($card['amount'], 0) }} en total</div>
            </div>
        @endforeach
    </div>

    <div class="orders-toolbar">
        <span class="text-muted small">Exportar pedidos del listado operativo</span>
        <form class="orders-export-form" method="get" action="{{ route('admin.orders.export') }}">
            <div class="field">
                <label for="export-status">Estado</label>
                <select name="status" id="export-status">
                    <option value="">Todos</option>
                    @foreach($statusLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="export-from">Desde</label>
                <input type="date" name="date_from" id="export-from" value="{{ $from->format('Y-m-d') }}">
            </div>
            <div class="field">
                <label for="export-to">Hasta</label>
                <input type="date" name="date_to" id="export-to" value="{{ $to->format('Y-m-d') }}">
            </div>
            <button type="submit" class="o-btn export">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </button>
        </form>
    </div>

    <div class="panel-chart">
        <h3>Tendencia de ingresos diarios</h3>
        <div style="height:180px;position:relative;">
            <canvas id="ordersRevenueChart"></canvas>
        </div>
    </div>

    @if($statusBreakdown !== [])
    <div class="orders-table-wrap">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Estado</th>
                    <th>Pedidos</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($statusBreakdown as $row)
                    <tr>
                        <td>
                            <span class="badge-status {{ $row['status'] }}">
                                {{ $statusLabels[$row['status']] ?? $row['status'] }}
                            </span>
                        </td>
                        <td>{{ number_format($row['count']) }}</td>
                        <td class="order-cell-money">${{ number_format($row['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="orders-table-wrap">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentOrders as $order)
                    <tr>
                        <td>
                            <div class="order-cell-name">{{ $order->contact->name ?? 'Cliente' }}</div>
                        </td>
                        <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td class="order-cell-money">${{ number_format($order->total, 2) }}</td>
                        <td>
                            <span class="badge-status {{ $order->status }}">
                                {{ $statusLabels[$order->status] ?? $order->status }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No hay pedidos en este período.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <a href="{{ route('admin.orders') }}" class="report-footer-link">Ir al listado operativo de pedidos →</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const trend = {!! json_encode($dailyTrend) !!};
    const labels = trend.length ? trend.map(d => d.label) : ['Sin datos'];
    const revenue = trend.length ? trend.map(d => d.revenue) : [0];

    new Chart(document.getElementById('ordersRevenueChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Ingresos (USD)',
                data: revenue,
                borderColor: '#0f766e',
                backgroundColor: 'rgba(15, 118, 110, 0.12)',
                fill: true,
                tension: 0.3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '$' + v } }
            }
        }
    });
});
</script>
@endsection
