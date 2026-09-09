@extends('admin.layouts.app')

@section('header', 'Delivery')

@section('content')
<style>
    .delivery-page { max-width: 1100px; margin: 0 auto; }
    .delivery-hero { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1rem; padding:.9rem 1rem; border-radius:16px; background:linear-gradient(110deg,#fdf4ff,#fff 55%); border:1px solid #f0abfc; }
    .delivery-hero h2 { margin:0; color:#0f172a; font-weight:850; font-size:1.4rem; }
    .delivery-hero p { margin:.18rem 0 0; color:#64748b; font-size:.86rem; }
    .delivery-tabs { display:flex; gap:.4rem; margin-bottom:1rem; }
    .delivery-tab { border:1px solid #e2e8f0; background:#fff; border-radius:999px; padding:.45rem .9rem; font-size:.8rem; font-weight:700; color:#64748b; cursor:pointer; }
    .delivery-tab.active { background:#a21caf; border-color:#a21caf; color:#fff; }
    .delivery-list { display:flex; flex-direction:column; gap:.75rem; }
    .delivery-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1rem; box-shadow:0 1px 3px rgba(15,23,42,.05); border-left:4px solid #a21caf; }
    .delivery-card.status-completed { border-left-color:#16a34a; opacity:.85; }
    .delivery-card.status-cancelled { border-left-color:#dc2626; opacity:.7; }
    .delivery-card-top { display:flex; justify-content:space-between; align-items:flex-start; gap:.75rem; flex-wrap:wrap; }
    .delivery-order-number { font-size:1rem; font-weight:850; color:#0f172a; }
    .delivery-time { color:#64748b; font-size:.72rem; margin-top:.1rem; }
    .delivery-badge { font-size:.66rem; font-weight:800; padding:.3rem .6rem; border-radius:999px; white-space:nowrap; text-transform:uppercase; letter-spacing:.03em; }
    .delivery-badge.pending, .delivery-badge.confirmed, .delivery-badge.paid, .delivery-badge.payment_pending { background:#fef3c7; color:#92400e; }
    .delivery-badge.preparing { background:#fff7ed; color:#c2410c; }
    .delivery-badge.ready { background:#dcfce7; color:#15803d; }
    .delivery-badge.completed { background:#dbeafe; color:#1d4ed8; }
    .delivery-badge.cancelled { background:#fee2e2; color:#991b1b; }
    .delivery-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem .9rem; margin:.75rem 0; font-size:.82rem; }
    .delivery-grid-full { grid-column:1/-1; }
    .delivery-lbl { display:block; font-size:.66rem; text-transform:uppercase; letter-spacing:.03em; color:#64748b; font-weight:700; }
    .delivery-val { display:block; color:#0f172a; font-weight:600; margin-top:.1rem; }
    .delivery-val a { color:#a21caf; text-decoration:none; }
    .delivery-val a:hover { text-decoration:underline; }
    .delivery-fee-pending { color:#c2410c; font-weight:600; font-size:.72rem; margin-top:.15rem; }
    .delivery-actions { display:flex; gap:.5rem; margin-top:.6rem; flex-wrap:wrap; }
    .delivery-btn { display:inline-flex; align-items:center; gap:.4rem; padding:.5rem .85rem; border-radius:9px; font-size:.8rem; font-weight:700; border:1px solid #e2e8f0; background:#fff; color:#475569; cursor:pointer; text-decoration:none; }
    .delivery-btn.primary { background:linear-gradient(135deg,#a21caf,#701a75); border-color:transparent; color:#fff; }
    .delivery-btn:disabled { opacity:.5; cursor:not-allowed; }
    .delivery-proof { margin-top:.6rem; padding:.6rem .75rem; border-radius:10px; background:#f0fdf4; border:1px solid #bbf7d0; display:flex; gap:.7rem; align-items:center; }
    .delivery-proof img { width:56px; height:56px; object-fit:cover; border-radius:8px; cursor:zoom-in; }
    .delivery-proof-meta { font-size:.76rem; color:#166534; }
    .delivery-empty { text-align:center; padding:3rem; background:#fff; border:1px dashed #e2e8f0; border-radius:14px; color:#64748b; }
    .delivery-card.is-highlighted { box-shadow:0 0 0 3px #a21caf; }

    .delivery-modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); display:flex; align-items:center; justify-content:center; padding:1rem; z-index:1050; opacity:0; visibility:hidden; transition:.2s; }
    .delivery-modal-overlay.is-open { opacity:1; visibility:visible; }
    .delivery-modal { background:#fff; border-radius:16px; width:100%; max-width:420px; padding:1.25rem; }
    .delivery-modal h4 { margin:0 0 .8rem; font-size:1rem; color:#0f172a; }
    .delivery-modal label { display:block; font-size:.78rem; font-weight:700; color:#475569; margin-bottom:.3rem; }
    .delivery-modal input[type="file"], .delivery-modal textarea { width:100%; border:1px solid #e2e8f0; border-radius:9px; padding:.5rem .65rem; font-size:.82rem; margin-bottom:.75rem; }
    .delivery-modal-actions { display:flex; justify-content:flex-end; gap:.5rem; }

    .delivery-toast { position:fixed; right:1rem; bottom:1rem; padding:.7rem .9rem; border-radius:10px; background:#0f172a; color:#fff; font-size:.8rem; opacity:0; transform:translateY(10px); pointer-events:none; transition:.2s; z-index:1100; }
    .delivery-toast.show { opacity:1; transform:none; }
    .delivery-toast.error { background:#b91c1c; }
    @media(max-width:640px){ .delivery-grid { grid-template-columns:1fr; } }
</style>

<div class="delivery-page">
    <div class="delivery-hero">
        <div>
            <h2><i class="fas fa-motorcycle me-2"></i>Delivery</h2>
            <p>Pedidos con envío a domicilio: dirección, receptor y confirmación de entrega con foto.</p>
        </div>
    </div>

    <div class="delivery-tabs">
        <button type="button" class="delivery-tab active" data-filter="active">Por entregar</button>
        <button type="button" class="delivery-tab" data-filter="completed">Entregados</button>
        <button type="button" class="delivery-tab" data-filter="all">Todos</button>
    </div>

    <div class="delivery-list" id="deliveryList">
        <div class="delivery-empty">Cargando pedidos de delivery…</div>
    </div>
</div>

<div class="delivery-modal-overlay" id="deliveryModal">
    <div class="delivery-modal">
        <h4><i class="fas fa-camera me-1"></i>Confirmar entrega</h4>
        <form id="deliveryConfirmForm">
            <label for="deliveryPhoto">Foto de la entrega (comprobante)</label>
            <input type="file" id="deliveryPhoto" name="photo" accept="image/*" capture="environment" required>
            <label for="deliveryNote">Nota (opcional)</label>
            <textarea id="deliveryNote" name="note" rows="2" placeholder="Ej: Entregado en portería, recibió el guardia."></textarea>
            <div class="delivery-modal-actions">
                <button type="button" class="delivery-btn" id="deliveryModalCancel">Cancelar</button>
                <button type="submit" class="delivery-btn primary"><i class="fas fa-check me-1"></i>Confirmar entrega</button>
            </div>
        </form>
    </div>
</div>

<div class="delivery-toast" id="deliveryToast"></div>

<script>
const deliveryDataUrl = @json(route('admin.delivery.data'));
const deliveryConfirmTemplate = @json(route('admin.delivery.confirm', ['id' => '__ORDER__']));
const deliveryCsrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
const deliveryEsc = value => { const node = document.createElement('div'); node.textContent = value ?? ''; return node.innerHTML; };

let deliveryOrders = [];
let deliveryFilter = 'active';
let deliveryConfirmingId = null;

function deliveryToast(message, error = false) {
    const node = document.getElementById('deliveryToast');
    node.textContent = message;
    node.className = 'delivery-toast show' + (error ? ' error' : '');
    clearTimeout(node._timer);
    node._timer = setTimeout(() => node.className = 'delivery-toast', 2800);
}

function deliveryFormatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString('es-EC', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

/**
 * dispatchResult (opcional) viene de un despacho recién hecho -- trae la
 * sucursal de retirada que el operador acaba de confirmar y la ruta
 * (origen sucursal -> destino cliente) ya recalculada con ella. Si no viene
 * (ej. al reenviar), se usan los últimos datos conocidos del pedido.
 */
function deliveryShareText(order, dispatchResult) {
    const branchName = dispatchResult?.branch_name ?? order.branch;
    const routeUrl = dispatchResult?.maps_url ?? order.maps_url;
    const lines = [
        '🛵 *Datos para el delivery*',
        '',
        `Pedido: *${order.order_number}*`,
        branchName ? `Retirar en: ${branchName}` : null,
        `Entregar a: ${order.recipient_name || order.customer?.name || 'Cliente'}`,
        `Dirección: ${order.address || 'Sin dirección registrada'}`,
        `Pago: ${order.payment_dispatch_label || 'No especificado'}`,
    ].filter(line => line !== null);
    if (routeUrl) {
        lines.push('', 'Ruta (retiro → entrega):', routeUrl);
    }
    if (order.confirmation_url) {
        lines.push('', 'Cuando entregues el pedido, confirma aquí (con una foto):', order.confirmation_url);
    }
    return lines.join('\n');
}

function openDeliveryDispatchModal(orderId) {
    const order = deliveryOrders.find(o => o.id === orderId);
    if (!order) return;
    openDriverDispatchModal(orderId, (driver, dispatchResult) => deliveryShareText(order, dispatchResult), () => {
        deliveryToast('Datos listos para el repartidor.');
        fetchDeliveryOrders();
    });
}

/** Reabre WhatsApp con el último repartidor despachado, para reenviarle los datos. */
function reopenDeliveryDispatch(orderId) {
    const order = deliveryOrders.find(o => o.id === orderId);
    if (!order || !order.last_dispatch_driver) return;
    window.reopenDriverWhatsapp(order.last_dispatch_driver.phone_number, deliveryShareText(order));
}

function deliveryCard(order) {
    const badgeClass = order.status || 'pending';
    let grid = `<div class="delivery-grid">`;
    if (order.branch) grid += `<div><span class="delivery-lbl">Sucursal</span><span class="delivery-val">${deliveryEsc(order.branch)}</span></div>`;
    grid += `<div><span class="delivery-lbl">Cliente</span><span class="delivery-val">${deliveryEsc(order.customer?.name)}${order.customer?.phone ? ` · <a href="https://wa.me/${deliveryEsc(order.customer.phone)}" target="_blank" rel="noopener">${deliveryEsc(order.customer.phone)}</a>` : ''}</span></div>`;
    if (order.recipient_name) grid += `<div><span class="delivery-lbl">Recibe</span><span class="delivery-val">${deliveryEsc(order.recipient_name)}</span></div>`;
    if (order.distance_km != null) grid += `<div><span class="delivery-lbl">Distancia a sucursal</span><span class="delivery-val">~${order.distance_km} km</span></div>`;
    if (order.address) {
        grid += `<div class="delivery-grid-full"><span class="delivery-lbl">Dirección / ruta</span><span class="delivery-val">${deliveryEsc(order.address)}${order.maps_url ? ` · <a href="${order.maps_url}" target="_blank" rel="noopener"><i class="fas fa-map-location-dot me-1"></i>Ver ruta</a>` : ''}</span></div>`;
    } else if (order.maps_url) {
        grid += `<div class="delivery-grid-full"><span class="delivery-lbl">Ruta</span><span class="delivery-val"><a href="${order.maps_url}" target="_blank" rel="noopener"><i class="fas fa-map-location-dot me-1"></i>Ver ubicación</a></span></div>`;
    }
    if (order.delivery_fee != null) {
        grid += `<div><span class="delivery-lbl">Costo de envío</span><span class="delivery-val">$${parseFloat(order.delivery_fee).toFixed(2)}${order.delivery_fee_pending_review ? '<div class="delivery-fee-pending"><i class="fas fa-triangle-exclamation me-1"></i>Referencial, sin confirmar</div>' : ''}</span></div>`;
    }
    grid += `<div><span class="delivery-lbl">Total del pedido</span><span class="delivery-val">$${parseFloat(order.total).toFixed(2)}</span></div>`;
    grid += `</div>`;

    let proofHtml = '';
    if (order.proof) {
        proofHtml = `<div class="delivery-proof">
            ${order.proof.photo_url ? `<img src="${order.proof.photo_url}" alt="Comprobante de entrega" onclick="window.open('${order.proof.photo_url}','_blank')">` : ''}
            <div class="delivery-proof-meta"><strong>Entregado</strong>${order.proof.confirmed_at ? ' · ' + deliveryFormatDate(order.proof.confirmed_at) : ''}${order.proof.note ? `<br>${deliveryEsc(order.proof.note)}` : ''}</div>
        </div>`;
    }

    const shareBtn = `<button type="button" class="delivery-btn" onclick="openDeliveryDispatchModal(${order.id})"><i class="fab fa-whatsapp me-1"></i>Enviar a repartidor</button>`;
    const resendBtn = order.last_dispatch_driver
        ? `<button type="button" class="delivery-btn" onclick="reopenDeliveryDispatch(${order.id})" title="Vuelve a abrir WhatsApp con ${deliveryEsc(order.last_dispatch_driver.name)} para reenviarle los datos"><i class="fas fa-rotate-right me-1"></i>Reenviar a ${deliveryEsc(order.last_dispatch_driver.name)}</button>`
        : '';

    let actions = '<div class="delivery-actions">';
    if (order.can_confirm) {
        actions += `<button type="button" class="delivery-btn primary" onclick="openDeliveryModal(${order.id})"><i class="fas fa-camera me-1"></i>Confirmar entrega</button>`;
    } else if (order.status !== 'completed' && order.status !== 'cancelled') {
        actions += `<button type="button" class="delivery-btn" disabled title="El pedido debe estar 'Listo para despachar' para confirmar la entrega"><i class="fas fa-camera me-1"></i>Confirmar entrega</button>`;
    }
    if (order.status !== 'cancelled') {
        actions += shareBtn + resendBtn;
    }
    actions += '</div>';

    return `<article class="delivery-card status-${order.status}" id="delivery-order-${order.id}">
        <div class="delivery-card-top">
            <div>
                <div class="delivery-order-number">${deliveryEsc(order.order_number)}</div>
                <div class="delivery-time">${deliveryFormatDate(order.created_at)}</div>
            </div>
            <span class="delivery-badge ${badgeClass}">${deliveryEsc(order.status_label)}</span>
        </div>
        ${grid}
        ${proofHtml}
        ${actions}
    </article>`;
}

function renderDeliveryList() {
    const list = document.getElementById('deliveryList');
    let filtered = deliveryOrders;
    if (deliveryFilter === 'active') {
        filtered = deliveryOrders.filter(o => !['completed', 'cancelled'].includes(o.status));
    } else if (deliveryFilter === 'completed') {
        filtered = deliveryOrders.filter(o => o.status === 'completed');
    }

    if (!filtered.length) {
        list.innerHTML = '<div class="delivery-empty"><i class="fas fa-motorcycle fa-2x mb-2 opacity-50 d-block"></i>No hay pedidos de delivery en esta vista.</div>';
        return;
    }

    list.innerHTML = filtered.map(deliveryCard).join('');
    maybeHighlightDeliveryOrder();
}

/**
 * Atajo desde Pedidos ("Enviar al repartidor" en el modal de cambiar
 * etapa): llega como /admin/delivery?order=ID -- resalta esa tarjeta y
 * abre de una vez el modal de despacho, para no obligar al operador a
 * buscarla en la lista.
 */
const deliveryHighlightOrderId = parseInt(new URLSearchParams(window.location.search).get('order'), 10) || null;
let deliveryHighlightHandled = false;
function maybeHighlightDeliveryOrder() {
    if (!deliveryHighlightOrderId || deliveryHighlightHandled) return;
    if (!deliveryOrders.some(o => o.id === deliveryHighlightOrderId)) return;

    deliveryHighlightHandled = true;
    const card = document.getElementById('delivery-order-' + deliveryHighlightOrderId);
    if (card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.classList.add('is-highlighted');
    }
    openDeliveryDispatchModal(deliveryHighlightOrderId);
}

async function fetchDeliveryOrders() {
    try {
        const response = await fetch(deliveryDataUrl, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error();
        const data = await response.json();
        deliveryOrders = data.orders || [];
        renderDeliveryList();
    } catch (_) {
        deliveryToast('No se pudo actualizar la lista de delivery.', true);
    }
}

function openDeliveryModal(orderId) {
    deliveryConfirmingId = orderId;
    document.getElementById('deliveryConfirmForm').reset();
    document.getElementById('deliveryModal').classList.add('is-open');
}
function closeDeliveryModal() {
    document.getElementById('deliveryModal').classList.remove('is-open');
    deliveryConfirmingId = null;
}

document.getElementById('deliveryModalCancel').addEventListener('click', closeDeliveryModal);
document.getElementById('deliveryModal').addEventListener('click', e => {
    if (e.target.id === 'deliveryModal') closeDeliveryModal();
});

document.getElementById('deliveryConfirmForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!deliveryConfirmingId) return;

    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    const formData = new FormData(e.target);

    try {
        const response = await fetch(deliveryConfirmTemplate.replace('__ORDER__', deliveryConfirmingId), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': deliveryCsrf },
            body: formData,
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo confirmar la entrega.');

        deliveryToast(data.message || 'Entrega confirmada');
        closeDeliveryModal();
        fetchDeliveryOrders();
    } catch (error) {
        deliveryToast(error.message, true);
    } finally {
        submitBtn.disabled = false;
    }
});

document.querySelectorAll('.delivery-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.delivery-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        deliveryFilter = tab.dataset.filter;
        renderDeliveryList();
    });
});

fetchDeliveryOrders();
setInterval(fetchDeliveryOrders, 15000);
</script>

@include('admin.partials.driver-dispatch-modal')
@endsection
