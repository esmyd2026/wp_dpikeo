@extends('admin.layouts.app')

@section('header', 'Comandas')

@section('content')
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
</style>

<div class="kitchen-page">
    <div class="kitchen-hero">
        <div class="kitchen-brand"><img src="{{ asset('storage/img/dpikeologo.jpg') }}" alt="DPIKEOS"><div><h2>Centro de comandas</h2><p>La caja confirma, cocina prepara y despacho entrega.</p></div></div>
        <div class="kitchen-hero-actions"><button type="button" class="kitchen-reset" id="kitchenResetTurns"><i class="fas fa-rotate-left"></i> Reiniciar turnos</button><a class="kitchen-link" href="{{ route('admin.kitchen.display') }}" target="_blank" rel="noopener"><i class="fas fa-tv"></i> Abrir pantalla TV</a></div>
    </div>
    <div class="kitchen-flow"><span>1. Caja confirma</span><i class="fas fa-arrow-right"></i><span>2. Cocina prepara</span><i class="fas fa-arrow-right"></i><span>3. Despacho entrega</span></div>
    <div class="kitchen-board">
        <section class="kitchen-column queue"><div class="kitchen-column-head"><strong>En cola</strong><span class="kitchen-count" id="queueCount">0</span></div><div class="kitchen-cards" id="queueOrders"></div></section>
        <section class="kitchen-column preparing"><div class="kitchen-column-head"><strong>En preparación</strong><span class="kitchen-count" id="preparingCount">0</span></div><div class="kitchen-cards" id="preparingOrders"></div></section>
        <section class="kitchen-column ready"><div class="kitchen-column-head"><strong>Listos para entregar</strong><span class="kitchen-count" id="readyCount">0</span></div><div class="kitchen-cards" id="readyOrders"></div></section>
    </div>
</div>
<div class="kitchen-toast" id="kitchenToast"></div>

<script>
const kitchenDataUrl = @json(route('admin.kitchen.data'));
const kitchenTransitionTemplate = @json(route('admin.kitchen.transition', ['id' => '__ORDER__']));
const kitchenPrintTemplate = @json(route('admin.kitchen.print', ['id' => '__ORDER__']));
const kitchenResetTurnsUrl = @json(route('admin.kitchen.turns.reset'));
const kitchenCsrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
const kitchenEsc = value => { const node = document.createElement('div'); node.textContent = value ?? ''; return node.innerHTML; };

function kitchenToast(message, error = false) {
    const node = document.getElementById('kitchenToast'); node.textContent = message; node.className = 'kitchen-toast show' + (error ? ' error' : '');
    clearTimeout(node._timer); node._timer = setTimeout(() => node.className = 'kitchen-toast', 2800);
}
function kitchenAction(order) {
    if (['confirmed', 'paid'].includes(order.status)) return { status:'preparing', label:'Iniciar preparación', className:'start', icon:'fa-fire-burner' };
    if (order.status === 'preparing') return { status:'ready', label:'Marcar listo', className:'ready', icon:'fa-check' };
    return { status:'completed', label:'Entregado', className:'deliver', icon:'fa-handshake' };
}
function kitchenCard(order) {
    const action = kitchenAction(order);
    const elapsed = Number(order.elapsed_minutes || 0);
    const elapsedLabel = order.elapsed_label || 'Recién ingresado';
    const timing = elapsed >= 20 ? 'urgent' : '';
    const printUrl = kitchenPrintTemplate.replace('__ORDER__', order.id);
    return `<article class="kitchen-card"><div class="kitchen-card-top"><div><div class="kitchen-order-number">Turno ${kitchenEsc(order.turn_number || order.display_number)}</div><div class="kitchen-time ${timing}">${kitchenEsc(elapsedLabel)} desde ingreso</div></div><a class="kitchen-print" href="${printUrl}" target="_blank" rel="noopener" title="Imprimir comanda" aria-label="Imprimir comanda turno ${kitchenEsc(order.turn_number || order.display_number)}"><i class="fas fa-print"></i></a></div><ul class="kitchen-items">${order.items.map(item => `<li><b>${item.quantity}×</b> ${kitchenEsc(item.name)}${item.note ? `<span class="kitchen-item-note">${kitchenEsc(item.note)}</span>` : ''}</li>`).join('')}</ul><button class="kitchen-action ${action.className}" data-order="${order.id}" data-status="${action.status}"><i class="fas ${action.icon} me-1"></i>${action.label}</button></article>`;
}
function renderKitchen(orders) {
    const groups = { queue: orders.filter(o => ['confirmed','paid'].includes(o.status)), preparing: orders.filter(o => o.status === 'preparing'), ready: orders.filter(o => o.status === 'ready') };
    Object.entries(groups).forEach(([key, items]) => {
        document.getElementById(key + 'Count').textContent = items.length;
        document.getElementById(key + 'Orders').innerHTML = items.length ? items.map(kitchenCard).join('') : '<div class="kitchen-empty">Sin pedidos en esta área.</div>';
    });
    document.querySelectorAll('[data-order]').forEach(button => button.addEventListener('click', () => updateKitchenStatus(button.dataset.order, button.dataset.status, button)));
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
async function fetchKitchen() { try { const response = await fetch(kitchenDataUrl, {headers:{Accept:'application/json'}}); if (!response.ok) throw new Error(); renderKitchen((await response.json()).orders || []); } catch (_) { kitchenToast('No se pudo actualizar el tablero.', true); } }
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
fetchKitchen(); setInterval(fetchKitchen, 10000);
</script>
@endsection
