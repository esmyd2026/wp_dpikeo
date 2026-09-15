@extends('admin.layouts.app')

@section('content')
<style>
    .orders-page { max-width: 1240px; margin: 0 auto; }
    .orders-top { margin-bottom: .85rem; }
    .orders-top h2 { margin: 0 0 .3rem; font-size: 1.35rem; font-weight: 800; color: #0f172a; }
    .orders-top .lead { margin: 0; font-size: .875rem; color: #64748b; }
    .orders-table-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow-x: auto; box-shadow: 0 1px 3px rgba(15,23,42,.04); margin-bottom: .85rem; }
    .orders-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .orders-table th { padding: .55rem .65rem; text-align: left; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #64748b; background: #f8fafc; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
    .orders-table td { padding: .5rem .65rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #334155; }
    .orders-table tbody tr:hover { background: #fafbfc; }
    .order-cell-name { font-weight: 700; color: #0f172a; white-space: nowrap; }
    .order-cell-money { font-weight: 700; color: #0f172a; white-space: nowrap; }
    .order-link { color: #128c7e; text-decoration: none; font-weight: 700; }
    .order-link:hover { text-decoration: underline; }
    h3.section-title { display:flex; align-items:center; gap:.5rem; font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 1.4rem 0 .6rem; }
    .section-count { font-size:.72rem; font-weight:700; padding:.1rem .5rem; border-radius:999px; background:#eef2f7; color:#425466; }
    .mark-issued-btn { background:#0f766e; border:1px solid #0f766e; color:#fff; border-radius:8px; padding:.35rem .6rem; font-size:.75rem; font-weight:700; cursor:pointer; white-space:nowrap; }
    .mark-issued-btn:disabled { opacity:.6; cursor:wait; }
    .issued-tag { font-size:.72rem; font-weight:700; padding:.15rem .45rem; border-radius:6px; background:#dcfce7; color:#166534; white-space:nowrap; }
</style>

<div class="orders-page">
    <div class="orders-top">
        <h2><i class="fas fa-file-invoice me-1 text-success"></i> Solicitudes de facturación</h2>
        <p class="lead">Datos fiscales de los pedidos que pidieron factura, para el equipo de facturación electrónica.</p>
    </div>

    <h3 class="section-title">Pendientes de facturar <span class="section-count" id="pendingCount">{{ $pending->count() }}</span></h3>
    <div class="orders-table-wrap">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Envío</th>
                    <th>Documento</th>
                    <th>Nombre / razón social</th>
                    <th>Dirección</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="pendingBody">
                @forelse($pending as $row)
                    <tr id="invoice-row-{{ $row['id'] }}">
                        <td><a class="order-link" href="{{ route('admin.orders', ['open_order' => $row['id']]) }}">{{ $row['order_number'] }}</a></td>
                        <td>{{ $row['created_at']->format('d/m/Y H:i') }}</td>
                        <td class="order-cell-money">${{ number_format($row['total'], 2) }}</td>
                        <td>{{ $row['delivery_fee'] === null ? '—' : '$'.number_format($row['delivery_fee'], 2) }}</td>
                        <td>{{ strtoupper($row['billing_type']) }} {{ $row['billing_id'] }}</td>
                        <td class="order-cell-name">{{ $row['billing_legal_name'] }}</td>
                        <td>{{ $row['address'] ?: '—' }}</td>
                        <td>{{ $row['email'] ?: '—' }}</td>
                        <td>{{ $row['phone'] ?: '—' }}</td>
                        <td>
                            <button type="button" class="mark-issued-btn" data-mark-issued="{{ $row['id'] }}">Marcar como facturado</button>
                        </td>
                    </tr>
                @empty
                    <tr id="pendingEmptyRow"><td colspan="10" class="text-center text-muted py-4">No hay solicitudes pendientes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h3 class="section-title">Ya facturadas <span class="section-count" id="issuedCount">{{ $issued->count() }}</span></h3>
    <div class="orders-table-wrap">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Envío</th>
                    <th>Documento</th>
                    <th>Nombre / razón social</th>
                    <th>Dirección</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="issuedBody">
                @forelse($issued as $row)
                    <tr>
                        <td><a class="order-link" href="{{ route('admin.orders', ['open_order' => $row['id']]) }}">{{ $row['order_number'] }}</a></td>
                        <td>{{ $row['created_at']->format('d/m/Y H:i') }}</td>
                        <td class="order-cell-money">${{ number_format($row['total'], 2) }}</td>
                        <td>{{ $row['delivery_fee'] === null ? '—' : '$'.number_format($row['delivery_fee'], 2) }}</td>
                        <td>{{ strtoupper($row['billing_type']) }} {{ $row['billing_id'] }}</td>
                        <td class="order-cell-name">{{ $row['billing_legal_name'] }}</td>
                        <td>{{ $row['address'] ?: '—' }}</td>
                        <td>{{ $row['email'] ?: '—' }}</td>
                        <td>{{ $row['phone'] ?: '—' }}</td>
                        <td><span class="issued-tag">Facturado</span></td>
                    </tr>
                @empty
                    <tr id="issuedEmptyRow"><td colspan="10" class="text-center text-muted py-4">Todavía no se ha marcado ninguna como facturada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const pendingBody = document.getElementById('pendingBody');
    const issuedBody = document.getElementById('issuedBody');
    const pendingCount = document.getElementById('pendingCount');
    const issuedCount = document.getElementById('issuedCount');

    function esc(value) {
        const node = document.createElement('div');
        node.textContent = value ?? '';
        return node.innerHTML;
    }

    function money(value) {
        return '$' + Number(value || 0).toFixed(2);
    }

    function bumpCount(el, delta) {
        el.textContent = String(Math.max(0, parseInt(el.textContent, 10) + delta));
    }

    function buildIssuedRow(order) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><a class="order-link" href="/admin/orders?open_order=${order.id}">${esc(order.order_number)}</a></td>
            <td>${esc(order.created_at)}</td>
            <td class="order-cell-money">${money(order.total)}</td>
            <td>${order.delivery_fee === null ? '—' : money(order.delivery_fee)}</td>
            <td>${esc((order.billing_type || '').toUpperCase())} ${esc(order.billing_id)}</td>
            <td class="order-cell-name">${esc(order.billing_legal_name)}</td>
            <td>${esc(order.address) || '—'}</td>
            <td>${esc(order.email) || '—'}</td>
            <td>${esc(order.phone) || '—'}</td>
            <td><span class="issued-tag">Facturado</span></td>
        `;
        return tr;
    }

    pendingBody.addEventListener('click', async function (event) {
        const btn = event.target.closest('[data-mark-issued]');
        if (!btn) return;
        const orderId = btn.dataset.markIssued;
        const row = document.getElementById(`invoice-row-${orderId}`);
        btn.disabled = true;
        btn.textContent = 'Guardando…';

        try {
            const res = await fetch(`/admin/orders/${orderId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ invoice_status: 'issued' }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'No se pudo marcar como facturado.');

            const order = data.order;
            const emptyRow = document.getElementById('issuedEmptyRow');
            if (emptyRow) emptyRow.remove();
            issuedBody.prepend(buildIssuedRow({
                id: order.id,
                order_number: order.order_number,
                created_at: row.children[1].textContent,
                total: order.total,
                delivery_fee: order.fulfillment?.delivery_fee ?? null,
                billing_type: order.billing?.billing_type,
                billing_id: order.billing?.billing_id,
                billing_legal_name: order.billing?.billing_legal_name,
                address: order.billing?.address,
                email: order.billing?.email,
                phone: order.contact?.phone_number,
            }));
            bumpCount(issuedCount, 1);
            bumpCount(pendingCount, -1);
            row.remove();
            if (!pendingBody.children.length) {
                pendingBody.innerHTML = '<tr id="pendingEmptyRow"><td colspan="10" class="text-center text-muted py-4">No hay solicitudes pendientes.</td></tr>';
            }
        } catch (error) {
            btn.disabled = false;
            btn.textContent = 'Marcar como facturado';
            alert(error.message || 'No se pudo marcar como facturado.');
        }
    });
});
</script>
@endsection
