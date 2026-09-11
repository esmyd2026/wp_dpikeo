@extends('admin.layouts.app')

@section('header', 'Comandas')

@section('content')
@php
    $canUpdateKitchen = auth()->user()?->hasPermission('orders.update') ?? false;
@endphp
<style>
    .kitchen-page { max-width: 1320px; margin: 0 auto; }
    .kitchen-hero { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1rem; padding:.9rem 1rem; border-radius:16px; background:linear-gradient(110deg,#fff7ed,#fff 55%); border:1px solid #fed7aa; }
    .kitchen-brand { display:flex; align-items:center; gap:.8rem; }
    .kitchen-brand img { width:58px; height:46px; object-fit:contain; object-position:left center; }
    .kitchen-hero h2 { margin:0; color:#0f172a; font-weight:850; font-size:1.4rem; }
    .kitchen-hero p { margin:.18rem 0 0; color:#64748b; font-size:.86rem; }
    .kitchen-link { display:inline-flex; align-items:center; gap:.45rem; padding:.62rem .85rem; border-radius:10px; text-decoration:none; background:linear-gradient(110deg,#b73808,#f36a17); color:#fff; font-size:.8rem; font-weight:750; }
    .kitchen-hero-actions { display:flex; align-items:center; gap:.55rem; }
    .kitchen-reset { border:1px solid #fdba74; border-radius:10px; padding:.62rem .85rem; background:#fff; color:#9a3412; cursor:pointer; font:inherit; font-size:.8rem; font-weight:750; }
    .kitchen-reset:hover { background:#fff7ed; }
    .kitchen-flow { display:flex; gap:.45rem; flex-wrap:wrap; margin-bottom:1rem; color:#526173; font-size:.76rem; font-weight:700; }
    .kitchen-flow span { padding:.34rem .55rem; border-radius:999px; background:#fff; border:1px solid #e2e8f0; }
    .kitchen-flow i { color:#94a3b8; align-self:center; }
    .kitchen-board { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1rem; align-items:start; }
    .kitchen-column { border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; background:#f8fafc; min-height:330px; }
    .kitchen-column-head { display:flex; justify-content:space-between; align-items:center; padding:.85rem 1rem; background:#fff; border-bottom:1px solid #e2e8f0; }
    .kitchen-column-head strong { font-size:.88rem; color:#0f172a; }
    .kitchen-count { min-width:24px; height:24px; display:grid; place-items:center; border-radius:999px; background:#e2e8f0; color:#334155; font-size:.73rem; font-weight:800; }
    .kitchen-column.queue .kitchen-column-head { border-top:4px solid #2563eb; }
    .kitchen-column.preparing .kitchen-column-head { border-top:4px solid #f59e0b; }
    .kitchen-column.ready .kitchen-column-head { border-top:4px solid #16a34a; }
    .kitchen-cards { padding:.75rem; display:flex; flex-direction:column; gap:.7rem; }
    .kitchen-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:.85rem; box-shadow:0 1px 3px rgba(15,23,42,.05); }
    .kitchen-card-top { display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; }
    .kitchen-print { width:32px; height:32px; display:grid; place-items:center; flex:0 0 32px; border:1px solid #fed7aa; border-radius:8px; background:#fff7ed; color:#b45309; text-decoration:none; }
    .kitchen-print:hover { background:#ffedd5; color:#9a3412; }
    .kitchen-order-number { color:#0f172a; font-size:1rem; font-weight:850; }
    .kitchen-time { color:#64748b; font-size:.72rem; margin-top:.13rem; }
    .kitchen-time.urgent { color:#dc2626; font-weight:800; }
    .kitchen-items { padding:0; margin:.7rem 0; list-style:none; border-top:1px solid #f1f5f9; }
    .kitchen-items li { padding:.35rem 0; border-bottom:1px solid #f8fafc; color:#334155; font-size:.79rem; }
    .kitchen-items b { color:#0f172a; }
    .kitchen-item-note { display:block; margin:.15rem 0 0 1.2rem; color:#a16207; font-size:.7rem; }
    .kitchen-action { width:100%; border:0; border-radius:9px; padding:.55rem .7rem; cursor:pointer; font:inherit; font-size:.78rem; font-weight:800; color:#fff; }
    .kitchen-action.start { background:#d97706; }.kitchen-action.ready { background:#15803d; }.kitchen-action.deliver { background:#075e54; }
    .kitchen-empty { color:#94a3b8; text-align:center; font-size:.82rem; padding:2.5rem .8rem; }
    .kitchen-toast { position:fixed; right:1rem; bottom:1rem; padding:.7rem .9rem; border-radius:10px; background:#0f172a; color:#fff; font-size:.8rem; opacity:0; transform:translateY(10px); pointer-events:none; transition:.2s; z-index:10; }.kitchen-toast.show{opacity:1;transform:none}.kitchen-toast.error{background:#b91c1c}
    @media(max-width:960px){.kitchen-board{grid-template-columns:1fr}.kitchen-hero{align-items:flex-start;flex-direction:column}.kitchen-hero-actions{width:100%}.kitchen-link,.kitchen-reset{flex:1;justify-content:center;text-align:center}}

    /* Lenguaje visual operativo, alineado con el módulo de pedidos. */
    .kitchen-page { max-width: 1380px; }
    .kitchen-hero {
        padding: 1rem 1.1rem; border-color: #dbe5e2;
        background: #fff; box-shadow: 0 2px 8px rgba(15,23,42,.04);
    }
    .kitchen-brand img { width: 54px; height: 44px; border-radius: 10px; }
    .kitchen-hero h2 { font-size: 1.32rem; }
    .kitchen-hero p { color: #64748b; }
    .kitchen-hero-actions { flex-wrap: wrap; }
    .kitchen-live {
        display: inline-flex; align-items: center; gap: .42rem; padding: .48rem .65rem;
        border-radius: 999px; background: #ecfdf5; color: #047857;
        font-size: .72rem; font-weight: 800; white-space: nowrap;
    }
    .kitchen-live::before {
        content: ''; width: 7px; height: 7px; border-radius: 50%; background: #10b981;
        box-shadow: 0 0 0 4px rgba(16,185,129,.12);
    }
    .kitchen-link { background: #0f766e; box-shadow: 0 4px 12px rgba(15,118,110,.16); }
    .kitchen-link:hover { background: #115e59; color: #fff; }
    .kitchen-reset { border-color: #dbe5e2; color: #64748b; }
    .kitchen-reset:hover { background: #f8fafc; color: #334155; }
    .kitchen-flow {
        align-items: center; margin-bottom: 1rem; padding: .7rem .85rem;
        border: 1px solid #e2e8f0; border-radius: 12px; background: #fff;
    }
    .kitchen-flow span { border: 0; padding: 0; background: transparent; }
    .kitchen-flow b {
        display: inline-grid; width: 23px; height: 23px; margin-right: .35rem; place-items: center;
        border-radius: 50%; background: #e7f7f3; color: #0f766e; font-size: .68rem;
    }
    .kitchen-flow i { color: #cbd5e1; }
    .kitchen-board { gap: .85rem; }
    .kitchen-column { border-color: #dfe7e5; background: #f4f7f6; min-height: 390px; }
    .kitchen-column-head { padding: .85rem .9rem; border-bottom-color: #dfe7e5; }
    .kitchen-column-head-main { display: flex; align-items: center; gap: .55rem; }
    .kitchen-column-icon {
        width: 32px; height: 32px; display: grid; place-items: center;
        border-radius: 9px; background: #e7f7f3; color: #0f766e;
    }
    .kitchen-column-head strong { display: block; font-size: .9rem; }
    .kitchen-column-head small { display: block; margin-top: .05rem; color: #94a3b8; font-size: .67rem; }
    .kitchen-column.queue .kitchen-column-head,
    .kitchen-column.preparing .kitchen-column-head,
    .kitchen-column.ready .kitchen-column-head { border-top: 4px solid #0f766e; }
    .kitchen-count { background: #e7f7f3; color: #0f766e; }
    .kitchen-cards { padding: .7rem; }
    .kitchen-card {
        position: relative; padding: .85rem .85rem .75rem; border-color: #dfe7e5;
        border-left: 4px solid #0f766e; box-shadow: 0 3px 10px rgba(15,23,42,.05);
    }
    .kitchen-card:hover { box-shadow: 0 7px 18px rgba(15,23,42,.08); }
    .kitchen-order-label { color: #64748b; font-size: .64rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .kitchen-order-number { margin-top: .08rem; font-size: 1.28rem; }
    .kitchen-time { display: inline-flex; align-items: center; gap: .28rem; margin-top: .25rem; }
    .kitchen-time.urgent { padding: .18rem .38rem; border-radius: 999px; background: #fef2f2; }
    .kitchen-print { border-color: #dbe5e2; background: #f8fafc; color: #0f766e; }
    .kitchen-print:hover { background: #e7f7f3; color: #115e59; }
    .kitchen-card-meta {
        display: flex; flex-wrap: wrap; gap: .3rem .8rem; margin-top: .55rem;
        color: #64748b; font-size: .7rem;
    }
    .kitchen-card-meta span { display: inline-flex; align-items: center; gap: .3rem; }
    .kitchen-items { margin: .65rem 0 .75rem; }
    .kitchen-items li { padding: .42rem 0; font-size: .81rem; }
    .kitchen-item-note { padding: .25rem .4rem; border-radius: 6px; background: #fffbeb; }
    .kitchen-action.start,
    .kitchen-action.ready,
    .kitchen-action.deliver { background: #0f766e; }
    .kitchen-action:hover { background: #115e59; }
    .kitchen-action:disabled { opacity: .6; cursor: wait; }
    .kitchen-empty {
        margin: .4rem; padding: 2.6rem .8rem; border: 1px dashed #cbd5e1;
        border-radius: 12px; background: rgba(255,255,255,.55);
    }
    .kitchen-empty i { display: block; margin-bottom: .5rem; color: #94a3b8; font-size: 1.15rem; }
    @media(max-width:960px) {
        .kitchen-live { order: 3; width: 100%; justify-content: center; }
        .kitchen-flow { justify-content: center; }
    }
    @media(max-width:560px) {
        .kitchen-hero-actions { display: grid; grid-template-columns: 1fr 1fr; }
        .kitchen-live { grid-column: 1 / -1; }
        .kitchen-flow i { transform: rotate(90deg); }
        .kitchen-flow { flex-direction: column; align-items: flex-start; }
    }

    /* Atajos de teclado tipo "bump bar": la tecla siempre actúa sobre el
       pedido más antiguo de su columna, igual que los botones físicos que
       usan las cadenas de comida rápida en su pantalla de cocina. */
    .kitchen-shortcuts-hint {
        margin: -.35rem 0 1rem; padding: .55rem .85rem; border-radius: 10px;
        background: #f0fdfa; border: 1px solid #ccfbf1; color: #0f766e;
        font-size: .76rem; font-weight: 700; display: flex; flex-wrap: wrap; gap: .3rem .9rem; align-items: center;
    }
    .kitchen-shortcuts-hint kbd {
        display: inline-block; min-width: 1.3em; padding: .12rem .4rem; margin-right: .3rem;
        border-radius: 5px; background: #0f766e; color: #fff; font-size: .72rem; font-weight: 800; text-align: center;
    }
    .kitchen-key {
        display: inline-block; min-width: 1.2em; padding: .05rem .35rem; margin-right: .4rem;
        border-radius: 4px; background: rgba(255,255,255,.28); font-size: .72rem; font-weight: 800; text-align: center; vertical-align: 1px;
    }

    /* Modo pantalla completa: solo el tablero, sin menú ni cabecera del panel. */
    body.kitchen-fullscreen-active .sidebar,
    body.kitchen-fullscreen-active .sidebar-overlay,
    body.kitchen-fullscreen-active .top-navbar,
    body.kitchen-fullscreen-active .mobile-menu-btn { display: none !important; }
    body.kitchen-fullscreen-active .main-wrapper { margin-left: 0 !important; }
    body.kitchen-fullscreen-active .main-content { padding: 1.1rem 1.4rem !important; }
    body.kitchen-fullscreen-active .kitchen-page { max-width: none; }
    body.kitchen-fullscreen-active .kitchen-order-number { font-size: 1.45rem; }
    body.kitchen-fullscreen-active .kitchen-action { padding: .75rem .8rem; font-size: .86rem; }
</style>

<div class="kitchen-page">
    <div class="kitchen-hero">
        <div class="kitchen-brand"><div><h2>Comandas de cocina{{ $activeCompany?->name ? ' — '.$activeCompany->name : '' }}</h2><p>Controla la preparación y entrega de cada turno.</p></div></div>
        <div class="kitchen-hero-actions">
            <span class="kitchen-live" id="kitchenLastUpdate">Actualizando pedidos</span>
            @if($canUpdateKitchen)
                <button type="button" class="kitchen-reset" id="kitchenResetTurns"><i class="fas fa-rotate-left"></i> Reiniciar turnos</button>
            @endif
            <button type="button" class="kitchen-link" id="kitchenFullscreenBtn"><i class="fas fa-expand"></i> Ver en pantalla completa</button>
        </div>
    </div>
    <div class="kitchen-flow"><span><b>1</b>Caja confirma</span><i class="fas fa-arrow-right"></i><span><b>2</b>Cocina prepara</span><i class="fas fa-arrow-right"></i><span><b>3</b>Se entrega el pedido</span></div>
    @if($canUpdateKitchen)
        <div class="kitchen-shortcuts-hint"><i class="fas fa-keyboard"></i> Atajos de teclado (siempre al pedido más antiguo de cada columna):
            <span><kbd>1</kbd>Iniciar preparación</span>
            <span><kbd>2</kbd>Marcar listo</span>
            <span><kbd>3</kbd>Confirmar entrega</span>
        </div>
    @endif
    <div class="kitchen-board">
        <section class="kitchen-column queue"><div class="kitchen-column-head"><div class="kitchen-column-head-main"><span class="kitchen-column-icon"><i class="fas fa-inbox"></i></span><div><strong>Por preparar</strong><small>Pedidos confirmados</small></div></div><span class="kitchen-count" id="queueCount">0</span></div><div class="kitchen-cards" id="queueOrders"></div></section>
        <section class="kitchen-column preparing"><div class="kitchen-column-head"><div class="kitchen-column-head-main"><span class="kitchen-column-icon"><i class="fas fa-fire"></i></span><div><strong>En preparación</strong><small>Trabajando ahora</small></div></div><span class="kitchen-count" id="preparingCount">0</span></div><div class="kitchen-cards" id="preparingOrders"></div></section>
        <section class="kitchen-column ready"><div class="kitchen-column-head"><div class="kitchen-column-head-main"><span class="kitchen-column-icon"><i class="fas fa-bag-shopping"></i></span><div><strong>Listos para entregar</strong><small>Esperando despacho</small></div></div><span class="kitchen-count" id="readyCount">0</span></div><div class="kitchen-cards" id="readyOrders"></div></section>
    </div>
</div>
<div class="kitchen-toast" id="kitchenToast"></div>

<script>
const kitchenDataUrl = @json(route('admin.kitchen.data'));
const kitchenTransitionTemplate = @json(route('admin.kitchen.transition', ['id' => '__ORDER__']));
const kitchenPrintTemplate = @json(route('admin.kitchen.print', ['id' => '__ORDER__']));
const kitchenResetTurnsUrl = @json(route('admin.kitchen.turns.reset'));
const kitchenCanUpdate = @json($canUpdateKitchen);
const kitchenCsrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
const kitchenEsc = value => { const node = document.createElement('div'); node.textContent = value ?? ''; return node.innerHTML; };

function kitchenToast(message, error = false) {
    const node = document.getElementById('kitchenToast'); node.textContent = message; node.className = 'kitchen-toast show' + (error ? ' error' : '');
    clearTimeout(node._timer); node._timer = setTimeout(() => node.className = 'kitchen-toast', 2800);
}
function kitchenAction(order) {
    if (['confirmed', 'paid'].includes(order.status)) return { status:'preparing', label:'Iniciar preparación', className:'start', icon:'fa-fire' };
    if (order.status === 'preparing') return { status:'ready', label:'Pedido listo', className:'ready', icon:'fa-check' };
    return { status:'completed', label:'Confirmar entrega', className:'deliver', icon:'fa-handshake' };
}
const kitchenColumnKeys = { queue: '1', preparing: '2', ready: '3' };
let kitchenNextByColumn = { queue: null, preparing: null, ready: null };

function kitchenCard(order, keyHint) {
    const action = kitchenAction(order);
    const elapsed = Number(order.elapsed_minutes || 0);
    const elapsedLabel = order.elapsed_label || 'Recién ingresado';
    const timing = elapsed >= 20 ? 'urgent' : '';
    const printUrl = kitchenPrintTemplate.replace('__ORDER__', order.id);
    const keyBadge = keyHint ? `<kbd class="kitchen-key">${keyHint}</kbd>` : '';
    const actionButton = kitchenCanUpdate
        ? `<button class="kitchen-action ${action.className}" data-order="${order.id}" data-status="${action.status}">${keyBadge}<i class="fas ${action.icon} me-1"></i>${action.label}</button>`
        : '';
    return `<article class="kitchen-card">
        <div class="kitchen-card-top">
            <div><div class="kitchen-order-label">Comanda</div><div class="kitchen-order-number">Turno ${kitchenEsc(order.turn_number || order.display_number)}</div><div class="kitchen-time ${timing}"><i class="far fa-clock"></i>${kitchenEsc(elapsedLabel)}</div></div>
            <a class="kitchen-print" href="${printUrl}" target="_blank" rel="noopener" title="Imprimir comanda" aria-label="Imprimir comanda turno ${kitchenEsc(order.turn_number || order.display_number)}"><i class="fas fa-print"></i></a>
        </div>
        <div class="kitchen-card-meta"><span><i class="fas fa-store"></i>${kitchenEsc(order.branch || 'Matriz')}</span><span><i class="fas fa-bag-shopping"></i>${order.items.length} ${order.items.length === 1 ? 'producto' : 'productos'}</span></div>
        <ul class="kitchen-items">${order.items.map(item => `<li><b>${item.quantity}×</b> ${kitchenEsc(item.name)}${item.note ? `<span class="kitchen-item-note">${kitchenEsc(item.note)}</span>` : ''}</li>`).join('')}</ul>
        ${actionButton}
    </article>`;
}
function renderKitchen(orders) {
    const groups = { queue: orders.filter(o => ['confirmed','paid'].includes(o.status)), preparing: orders.filter(o => o.status === 'preparing'), ready: orders.filter(o => o.status === 'ready') };
    Object.entries(groups).forEach(([key, items]) => {
        document.getElementById(key + 'Count').textContent = items.length;
        document.getElementById(key + 'Orders').innerHTML = items.length
            ? items.map((order, index) => kitchenCard(order, index === 0 ? kitchenColumnKeys[key] : null)).join('')
            : '<div class="kitchen-empty"><i class="fas fa-circle-check"></i>Sin pedidos en esta etapa</div>';
        kitchenNextByColumn[key] = items[0] || null;
    });
    document.querySelectorAll('[data-order]').forEach(button => button.addEventListener('click', () => updateKitchenStatus(button.dataset.order, button.dataset.status, button)));
}
/** "Bump": la tecla siempre avanza el pedido más antiguo de esa columna -- igual que un botón físico de bump bar. */
function kitchenBump(columnKey) {
    const order = kitchenNextByColumn[columnKey];
    if (!kitchenCanUpdate || !order) return;
    const action = kitchenAction(order);
    const button = document.querySelector(`[data-order="${order.id}"]`);
    if (button) updateKitchenStatus(order.id, action.status, button);
}
async function updateKitchenStatus(id, status, button) {
    button.disabled = true;
    try {
        const response = await fetch(kitchenTransitionTemplate.replace('__ORDER__', id), { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':kitchenCsrf}, body:JSON.stringify({status}) });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo actualizar el pedido.');
        kitchenToast('Comanda actualizada'); fetchKitchen();
    } catch (error) { kitchenToast(error.message, true); button.disabled = false; }
}
async function fetchKitchen() {
    const updateLabel = document.getElementById('kitchenLastUpdate');
    try {
        const response = await fetch(kitchenDataUrl, { headers:{ Accept:'application/json' } });
        if (!response.ok) throw new Error();
        const data = await response.json();
        renderKitchen(data.orders || []);
        if (updateLabel) {
            const time = new Date(data.updated_at || Date.now()).toLocaleTimeString('es-EC', { hour:'2-digit', minute:'2-digit' });
            updateLabel.textContent = 'Actualizado ' + time;
        }
    } catch (_) {
        if (updateLabel) updateLabel.textContent = 'Sin conexión';
        kitchenToast('No se pudo actualizar el tablero.', true);
    }
}
async function resetKitchenTurns() {
    if (!confirm('El contador volverá a 001. Solo es posible si no quedan pedidos operativos. ¿Continuar?')) return;
    try {
        const response = await fetch(kitchenResetTurnsUrl, { method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':kitchenCsrf} });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo reiniciar el contador.');
        kitchenToast(data.message); fetchKitchen();
    } catch (error) { kitchenToast(error.message, true); }
}
document.getElementById('kitchenResetTurns')?.addEventListener('click', resetKitchenTurns);

// Atajos 1/2/3 = bump bar. Se ignoran si el foco está en un campo de texto
// (por si el operador tiene abierto algún formulario en otra parte del panel).
document.addEventListener('keydown', (event) => {
    if (!kitchenCanUpdate) return;
    const tag = (event.target?.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || event.target?.isContentEditable) return;

    if (event.key === '1') { event.preventDefault(); kitchenBump('queue'); }
    else if (event.key === '2') { event.preventDefault(); kitchenBump('preparing'); }
    else if (event.key === '3') { event.preventDefault(); kitchenBump('ready'); }
});

// Pantalla completa real (Fullscreen API): oculta el menú/cabecera del panel
// y deja solo el tablero. Antes este botón solo abría la vista de TV en una
// pestaña aparte, que no tiene botones de acción ni atajos.
const kitchenFullscreenBtn = document.getElementById('kitchenFullscreenBtn');
function syncKitchenFullscreenUi() {
    const active = document.fullscreenElement != null;
    document.body.classList.toggle('kitchen-fullscreen-active', active);
    if (kitchenFullscreenBtn) {
        kitchenFullscreenBtn.innerHTML = active
            ? '<i class="fas fa-compress"></i> Salir de pantalla completa'
            : '<i class="fas fa-expand"></i> Ver en pantalla completa';
    }
}
kitchenFullscreenBtn?.addEventListener('click', () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen?.().catch(() => kitchenToast('El navegador no permitió pantalla completa.', true));
    } else {
        document.exitFullscreen?.();
    }
});
document.addEventListener('fullscreenchange', syncKitchenFullscreenUi);

fetchKitchen(); setInterval(fetchKitchen, 10000);
</script>
@endsection
