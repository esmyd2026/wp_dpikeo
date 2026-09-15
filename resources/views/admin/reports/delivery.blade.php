@extends('admin.layouts.app')

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
    .dash-filter input { border: 1px solid #dee2e6; border-radius: 8px; padding: .4rem .6rem; font-size: .85rem; }
    .dash-filter button { background: #128c7e; color: #fff; border: none; border-radius: 8px; padding: .45rem 1rem; font-size: .85rem; font-weight: 600; cursor: pointer; }
    .acct-delivery-cards { display:grid; grid-template-columns:repeat(3,1fr); gap:.65rem; margin-bottom:.85rem; }
    @media (max-width:900px) { .acct-delivery-cards { grid-template-columns:1fr; } }
    .acct-delivery-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:.85rem 1rem; box-shadow:0 1px 3px rgba(15,23,42,.04); }
    .acct-delivery-card .lbl { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
    .acct-delivery-card .val { font-size:1.25rem; font-weight:800; color:#0f172a; margin-top:.15rem; }
    .orders-table-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow-x: auto; box-shadow: 0 1px 3px rgba(15,23,42,.04); margin-bottom: .85rem; }
    .orders-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .orders-table th { padding: .55rem .65rem; text-align: left; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #64748b; background: #f8fafc; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
    .orders-table td { padding: .5rem .65rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #334155; }
    .orders-table tbody tr:hover { background: #fafbfc; }
    .order-cell-name { font-weight: 700; color: #0f172a; white-space: nowrap; }
    .order-cell-money { font-weight: 700; color: #0f172a; white-space: nowrap; }
    .badge-pending { font-size: .68rem; font-weight: 700; padding: .15rem .45rem; border-radius: 6px; background: #fef3c7; color: #92400e; }
    .order-link { color: #128c7e; text-decoration: none; font-weight: 700; }
    .order-link:hover { text-decoration: underline; }
    .report-footer-link { font-size: .82rem; color: #128c7e; text-decoration: none; font-weight: 600; }
    h3.section-title { font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 1.4rem 0 .6rem; }
</style>

<div class="orders-page">
    <div class="orders-top">
        <div>
            <h2><i class="fas fa-motorcycle me-1 text-success"></i> Reporte de repartidores</h2>
            <p class="lead">Entregas y envíos cobrados por repartidor · {{ $from->format('d/m/Y') }} — {{ $to->format('d/m/Y') }}</p>
        </div>
        @include('admin.partials.report-period-filter', ['action' => route('admin.reports.delivery')])
    </div>

    <div class="acct-delivery-cards">
        <div class="acct-delivery-card">
            <div class="lbl">Entregas completadas</div>
            <div class="val">{{ number_format($summary['deliveries']) }}</div>
        </div>
        <div class="acct-delivery-card">
            <div class="lbl">Total cobrado por envíos</div>
            <div class="val">${{ number_format($summary['fee_total'], 2) }}</div>
        </div>
        <div class="acct-delivery-card">
            <div class="lbl">Repartidores activos</div>
            <div class="val">{{ number_format($summary['active_drivers']) }}</div>
        </div>
    </div>

    <div class="orders-table-wrap">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Repartidor</th>
                    <th>Teléfono</th>
                    <th>Entregas</th>
                    <th>Total cobrado por envío</th>
                    <th>Valor de esos pedidos</th>
                    <th>Envío sin confirmar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stats as $row)
                    <tr>
                        <td class="order-cell-name">{{ $row['driver_name'] }}</td>
                        <td>{{ $row['driver_phone'] ?? '—' }}</td>
                        <td>{{ number_format($row['deliveries']) }}</td>
                        <td class="order-cell-money">${{ number_format($row['delivery_fee_total'], 2) }}</td>
                        <td class="order-cell-money">${{ number_format($row['orders_total'], 2) }}</td>
                        <td>
                            @if($row['pending_review_count'] > 0)
                                <span class="badge-pending">{{ $row['pending_review_count'] }} pendiente{{ $row['pending_review_count'] === 1 ? '' : 's' }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay entregas completadas en este período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h3 class="section-title"><i class="fas fa-list-check me-1 text-success"></i> Detalle de pedidos entregados</h3>
    <div class="orders-table-wrap">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Fecha</th>
                    <th>Repartidor</th>
                    <th>Envío</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ordersDetail as $order)
                    <tr>
                        <td><a class="order-link" href="{{ route('admin.orders', ['open_order' => $order['id']]) }}">{{ $order['order_number'] }}</a></td>
                        <td>{{ $order['created_at']->format('d/m/Y H:i') }}</td>
                        <td>{{ $order['driver_name'] }}</td>
                        <td>${{ number_format($order['delivery_fee'], 2) }}{{ $order['delivery_fee_pending_review'] ? ' (sin confirmar)' : '' }}</td>
                        <td class="order-cell-money">${{ number_format($order['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay pedidos en este período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <a href="{{ route('admin.delivery.index') }}" class="report-footer-link">Ir al panel de delivery →</a>
</div>
@endsection
