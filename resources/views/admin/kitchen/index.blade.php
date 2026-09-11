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
    .kitchen-card-tools { display: flex; align-items: center; gap: .35rem; }
    .kitchen-detail-btn { width: auto; padding: 0 .55rem; gap: .3rem; border: 0; cursor: pointer; font: inherit; font-size: .68rem; font-weight: 800; }
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

    /* Tablero industrial: alto contraste, lectura a distancia y un color
       operativo estable por etapa. El color indica proceso; nunca sustituye
       el texto ni el icono para que siga siendo comprensible para todos. */
    .kitchen-page { max-width: 1680px; }
    .kitchen-hero {
        margin-bottom: .65rem; padding: .72rem .9rem; border: 0;
        border-radius: 12px; background: #172033; color: #fff;
        box-shadow: 0 7px 20px rgba(15,23,42,.14);
    }
    .kitchen-hero h2 { color: #fff; font-size: 1.35rem; letter-spacing: -.02em; }
    .kitchen-hero p { color: #cbd5e1; }
    .kitchen-live { border: 1px solid rgba(52,211,153,.3); background: rgba(6,95,70,.45); color: #d1fae5; }
    .kitchen-link { background: #fff; color: #172033; box-shadow: none; }
    .kitchen-link:hover { background: #f1f5f9; color: #0f172a; }
    .kitchen-reset { border-color: #475569; background: transparent; color: #e2e8f0; }
    .kitchen-reset:hover { background: #293548; color: #fff; }
    .kitchen-flow {
        margin-bottom: .55rem; padding: .42rem .65rem; border: 0;
        background: transparent; color: #64748b; font-size: .72rem;
    }
    .kitchen-flow span { display: inline-flex; align-items: center; }
    .kitchen-flow span:nth-of-type(1) b { background: #dbeafe; color: #1d4ed8; }
    .kitchen-flow span:nth-of-type(2) b { background: #fef3c7; color: #b45309; }
    .kitchen-flow span:nth-of-type(3) b { background: #dcfce7; color: #15803d; }
    .kitchen-shortcuts-hint {
        margin: 0 0 .65rem; padding: .45rem .7rem; border-color: #d8e0e8;
        background: #fff; color: #475569; font-size: .72rem;
    }
    .kitchen-shortcuts-hint strong { color: #0f172a; }
    .kitchen-shortcuts-hint kbd { background: #172033; }
    .kitchen-confirm-rule { margin-left: auto; color: #64748b; font-weight: 600; }
    .kitchen-confirm-rule i { margin-right: .25rem; color: #f59e0b; }
    .kitchen-armed-bar {
        display: flex; align-items: center; justify-content: space-between; gap: .7rem;
        margin-bottom: .65rem; padding: .6rem .8rem; border: 2px solid #fbbf24;
        border-radius: 10px; background: #fffbeb; color: #78350f;
        font-size: .82rem; font-weight: 800;
    }
    .kitchen-armed-bar[hidden] { display: none; }
    .kitchen-armed-bar small { color: #92400e; font-size: .72rem; font-weight: 650; }
    .kitchen-board { gap: .7rem; }
    .kitchen-column { min-height: 440px; border: 0; border-radius: 12px; background: #e9eef3; box-shadow: 0 2px 10px rgba(15,23,42,.08); }
    .kitchen-column-head { padding: .72rem .82rem; border: 0; color: #fff; }
    .kitchen-column-head strong { color: #fff; font-size: 1rem; letter-spacing: .01em; }
    .kitchen-column-head small { color: rgba(255,255,255,.78); font-size: .7rem; }
    .kitchen-column-icon { background: rgba(255,255,255,.18); color: #fff; }
    .kitchen-column.queue .kitchen-column-head { border: 0; background: #2563eb; }
    .kitchen-column.preparing .kitchen-column-head { border: 0; background: #d97706; }
    .kitchen-column.ready .kitchen-column-head { border: 0; background: #15803d; }
    .kitchen-count { min-width: 30px; height: 30px; border: 1px solid rgba(255,255,255,.34); background: rgba(0,0,0,.16); color: #fff; font-size: .82rem; }
    .kitchen-cards { padding: .62rem; gap: .58rem; }
    .kitchen-card {
        padding: .78rem; border: 2px solid #cbd5e1; border-left-width: 7px;
        border-radius: 10px; box-shadow: 0 3px 8px rgba(15,23,42,.09);
        transition: border-color .16s, box-shadow .16s, transform .16s;
    }
    .queue .kitchen-card { border-left-color: #2563eb; }
    .preparing .kitchen-card { border-left-color: #d97706; }
    .ready .kitchen-card { border-left-color: #15803d; }
    .kitchen-card.is-armed {
        position: relative; z-index: 2; border-color: #f59e0b;
        background: #fffbeb; box-shadow: 0 0 0 4px rgba(245,158,11,.24), 0 8px 20px rgba(15,23,42,.16);
        transform: translateY(-1px);
    }
    .kitchen-order-label { font-size: .68rem; }
    .kitchen-order-number { color: #07101f; font-size: 1.58rem; line-height: 1.05; }
    .kitchen-time { font-size: .74rem; }
    .kitchen-card-meta { font-size: .76rem; }
    .kitchen-items { margin: .68rem 0 .72rem; border-top-color: #dbe2ea; }
    .kitchen-items li { padding: .48rem 0; color: #172033; font-size: .94rem; line-height: 1.25; }
    .kitchen-items b { font-size: 1rem; }
    .kitchen-item-note { margin: .25rem 0 0; padding: .32rem .42rem; color: #854d0e; font-size: .78rem; font-weight: 650; }
    .kitchen-action { min-height: 46px; border-radius: 8px; font-size: .86rem; }
    .queue .kitchen-action { background: #2563eb; }
    .preparing .kitchen-action { background: #d97706; }
    .ready .kitchen-action { background: #15803d; }
    .kitchen-action:hover { filter: brightness(.92); }
    .kitchen-action.is-confirming { background: #172033; color: #fff; }
    .kitchen-armed-note {
        margin: -.1rem 0 .55rem; padding: .42rem .5rem; border-radius: 6px;
        background: #fef3c7; color: #78350f; font-size: .76rem; font-weight: 800; text-align: center;
    }
    .kitchen-waiting { margin-top: .15rem; }
    .kitchen-waiting-label {
        display: flex; align-items: center; justify-content: space-between; gap: .5rem;
        margin-bottom: .4rem; padding: .45rem .15rem .2rem; color: #475569;
        font-size: .69rem; font-weight: 900; letter-spacing: .06em; text-transform: uppercase;
    }
    .kitchen-overflow-list { display: flex; flex-direction: column; gap: .4rem; }
    .kitchen-card.compact { padding: .55rem .6rem; border-left-width: 5px; box-shadow: none; }
    .kitchen-card.compact .kitchen-order-number { font-size: 1rem; }
    .kitchen-card.compact .kitchen-card-meta { margin-top: .2rem; }
    .kitchen-card.compact .kitchen-action { min-height: 34px; margin-top: .42rem; padding: .35rem .5rem; font-size: .72rem; }
    .kitchen-priority-tag { display: inline-block; margin-bottom: .25rem; color: #475569; font-size: .62rem; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
    .kitchen-empty { color: #64748b; background: #fff; font-size: .9rem; font-weight: 700; }
    .kitchen-detail-modal {
        position: fixed; inset: 0; z-index: 1200; display: grid; place-items: center;
        padding: 1rem; background: rgba(15,23,42,.72); backdrop-filter: blur(3px);
    }
    .kitchen-detail-modal[hidden] { display: none; }
    .kitchen-detail-dialog {
        width: min(620px, 100%); max-height: min(82vh, 760px); overflow: auto;
        border-radius: 14px; background: #fff; box-shadow: 0 22px 60px rgba(0,0,0,.32);
    }
    .kitchen-detail-head {
        position: sticky; top: 0; z-index: 1; display: flex; align-items: center;
        justify-content: space-between; gap: 1rem; padding: .9rem 1rem;
        border-bottom: 1px solid #dbe2ea; background: #172033; color: #fff;
    }
    .kitchen-detail-head strong { display: block; font-size: 1.35rem; }
    .kitchen-detail-head small { color: #cbd5e1; }
    .kitchen-detail-close {
        width: 42px; height: 42px; border: 1px solid #475569; border-radius: 9px;
        background: #293548; color: #fff; cursor: pointer; font-size: 1rem;
    }
    .kitchen-detail-body { padding: 1rem; }
    .kitchen-detail-meta { display: flex; flex-wrap: wrap; gap: .45rem .9rem; margin-bottom: .8rem; color: #475569; font-size: .8rem; }
    .kitchen-detail-list { padding: 0; margin: 0; list-style: none; }
    .kitchen-detail-list li { padding: .72rem 0; border-bottom: 1px solid #e2e8f0; color: #172033; font-size: 1rem; }
    .kitchen-detail-list b { display: inline-block; min-width: 36px; font-size: 1.08rem; }
    .kitchen-detail-note { margin-top: .35rem; padding: .4rem .5rem; border-radius: 6px; background: #fef3c7; color: #854d0e; font-size: .82rem; font-weight: 650; }
    .kitchen-order-note { margin-top: .85rem; padding: .75rem; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc; color: #334155; }
    body.kitchen-fullscreen-active { background: #cfd7df; }
    body.kitchen-fullscreen-active .main-content { padding: .65rem !important; }
    body.kitchen-fullscreen-active .kitchen-hero { margin-bottom: .5rem; }
    body.kitchen-fullscreen-active .kitchen-order-number { font-size: 1.8rem; }
    body.kitchen-fullscreen-active .kitchen-items li { font-size: 1.02rem; }
    @media(max-width:960px) {
        .kitchen-column { min-height: 0; }
        .kitchen-confirm-rule { width: 100%; margin-left: 0; }
    }
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
    @if($canUpdateKitchen)
        <div class="kitchen-shortcuts-hint"><i class="fas fa-keyboard"></i> <strong>Control rápido del primer pedido:</strong>
            <span><kbd>1</kbd>Iniciar preparación</span>
            <span><kbd>2</kbd>Marcar listo</span>
            <span><kbd>3</kbd>Confirmar entrega</span>
            <span class="kitchen-confirm-rule"><i class="fas fa-shield-halved"></i>Presiona una vez para seleccionar y otra vez para confirmar</span>
        </div>
    @endif
    <div class="kitchen-armed-bar" id="kitchenArmedBar" hidden></div>
    <div class="kitchen-board">
        <section class="kitchen-column queue"><div class="kitchen-column-head"><div class="kitchen-column-head-main"><span class="kitchen-column-icon"><i class="fas fa-inbox"></i></span><div><strong>Por preparar</strong><small>Pedidos confirmados</small></div></div><span class="kitchen-count" id="queueCount">0</span></div><div class="kitchen-cards" id="queueOrders"></div></section>
        <section class="kitchen-column preparing"><div class="kitchen-column-head"><div class="kitchen-column-head-main"><span class="kitchen-column-icon"><i class="fas fa-fire"></i></span><div><strong>En preparación</strong><small>Trabajando ahora</small></div></div><span class="kitchen-count" id="preparingCount">0</span></div><div class="kitchen-cards" id="preparingOrders"></div></section>
        <section class="kitchen-column ready"><div class="kitchen-column-head"><div class="kitchen-column-head-main"><span class="kitchen-column-icon"><i class="fas fa-bag-shopping"></i></span><div><strong>Listos para entregar</strong><small>Esperando despacho</small></div></div><span class="kitchen-count" id="readyCount">0</span></div><div class="kitchen-cards" id="readyOrders"></div></section>
    </div>
</div>
<div class="kitchen-toast" id="kitchenToast"></div>
<div class="kitchen-detail-modal" id="kitchenDetailModal" role="dialog" aria-modal="true" aria-labelledby="kitchenDetailTitle" hidden>
    <div class="kitchen-detail-dialog">
        <div class="kitchen-detail-head">
            <div><small>Detalle de la comanda</small><strong id="kitchenDetailTitle">Turno</strong></div>
            <button type="button" class="kitchen-detail-close" id="kitchenDetailClose" aria-label="Cerrar detalle"><i class="fas fa-times"></i></button>
        </div>
        <div class="kitchen-detail-body" id="kitchenDetailBody"></div>
    </div>
</div>

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
const kitchenVisibleLimits = { queue: 1, preparing: 3, ready: 3 };
let kitchenNextByColumn = { queue: null, preparing: null, ready: null };
let kitchenOrders = [];
let kitchenArmedAction = null;
let kitchenArmTimer = null;

function kitchenCard(order, keyHint, compact = false) {
    const action = kitchenAction(order);
    const elapsed = Number(order.elapsed_minutes || 0);
    const elapsedLabel = order.elapsed_label || 'Recién ingresado';
    const timing = elapsed >= 20 ? 'urgent' : '';
    const printUrl = kitchenPrintTemplate.replace('__ORDER__', order.id);
    const keyBadge = keyHint ? `<kbd class="kitchen-key">${keyHint}</kbd>` : '';
    const isArmed = kitchenArmedAction?.orderId === String(order.id)
        && kitchenArmedAction?.status === action.status;
    const actionLabel = isArmed
        ? `<i class="fas fa-shield-halved me-1"></i>Confirmar: ${action.label}`
        : `${keyBadge}<i class="fas ${action.icon} me-1"></i>${action.label}`;
    const actionButton = kitchenCanUpdate
        ? `<button class="kitchen-action ${action.className}${isArmed ? ' is-confirming' : ''}" data-order="${order.id}" data-status="${action.status}">${actionLabel}</button>`
        : '';
    if (compact) {
        return `<article class="kitchen-card compact${isArmed ? ' is-armed' : ''}">
            <div class="kitchen-card-top">
                <div><div class="kitchen-order-number">Turno ${kitchenEsc(order.turn_number || order.display_number)}</div><div class="kitchen-card-meta"><span><i class="fas fa-bag-shopping"></i>${order.items.length} ${order.items.length === 1 ? 'producto' : 'productos'}</span></div></div>
                <div class="kitchen-card-tools"><button type="button" class="kitchen-print kitchen-detail-btn" data-detail-order="${order.id}" title="Ver detalle" aria-label="Ver detalle del turno ${kitchenEsc(order.turn_number || order.display_number)}"><i class="fas fa-eye"></i><span>Detalle</span></button><a class="kitchen-print" href="${printUrl}" target="_blank" rel="noopener" title="Imprimir comanda" aria-label="Imprimir comanda turno ${kitchenEsc(order.turn_number || order.display_number)}"><i class="fas fa-print"></i></a></div>
            </div>
            ${isArmed ? '<div class="kitchen-armed-note">Seleccionado · vuelve a tocar para confirmar</div>' : ''}
            ${actionButton}
        </article>`;
    }
    return `<article class="kitchen-card${isArmed ? ' is-armed' : ''}">
        <div class="kitchen-card-top">
            <div><div class="kitchen-priority-tag">${keyHint ? 'Siguiente en avanzar' : 'Pedido activo'}</div><div class="kitchen-order-number">Turno ${kitchenEsc(order.turn_number || order.display_number)}</div><div class="kitchen-time ${timing}"><i class="far fa-clock"></i>${kitchenEsc(elapsedLabel)}</div></div>
            <div class="kitchen-card-tools"><button type="button" class="kitchen-print kitchen-detail-btn" data-detail-order="${order.id}" title="Ver detalle" aria-label="Ver detalle del turno ${kitchenEsc(order.turn_number || order.display_number)}"><i class="fas fa-eye"></i><span>Detalle</span></button><a class="kitchen-print" href="${printUrl}" target="_blank" rel="noopener" title="Imprimir comanda" aria-label="Imprimir comanda turno ${kitchenEsc(order.turn_number || order.display_number)}"><i class="fas fa-print"></i></a></div>
        </div>
        <div class="kitchen-card-meta"><span><i class="fas fa-store"></i>${kitchenEsc(order.branch || 'Matriz')}</span><span><i class="fas fa-bag-shopping"></i>${order.items.length} ${order.items.length === 1 ? 'producto' : 'productos'}</span></div>
        <ul class="kitchen-items">${order.items.map(item => `<li><b>${item.quantity}×</b> ${kitchenEsc(item.name)}${item.note ? `<span class="kitchen-item-note">${kitchenEsc(item.note)}</span>` : ''}</li>`).join('')}</ul>
        ${isArmed ? '<div class="kitchen-armed-note">Pedido seleccionado · repite la acción para confirmar el cambio</div>' : ''}
        ${actionButton}
    </article>`;
}
function renderKitchen(orders) {
    kitchenOrders = orders;
    if (kitchenArmedAction && !orders.some(order => String(order.id) === kitchenArmedAction.orderId)) {
        clearTimeout(kitchenArmTimer);
        kitchenArmedAction = null;
    }
    const groups = { queue: orders.filter(o => ['confirmed','paid'].includes(o.status)), preparing: orders.filter(o => o.status === 'preparing'), ready: orders.filter(o => o.status === 'ready') };
    Object.entries(groups).forEach(([key, items]) => {
        const visibleLimit = kitchenVisibleLimits[key];
        const visible = items.slice(0, visibleLimit);
        const waiting = items.slice(visibleLimit);
        const overflow = waiting.length
            ? `<div class="kitchen-waiting"><div class="kitchen-waiting-label"><span><i class="fas fa-layer-group me-1"></i>${waiting.length} ${waiting.length === 1 ? 'pedido resumido' : 'pedidos resumidos'}</span><small>Cola visible</small></div><div class="kitchen-overflow-list">${waiting.map(order => kitchenCard(order, null, true)).join('')}</div></div>`
            : '';
        document.getElementById(key + 'Count').textContent = items.length;
        document.getElementById(key + 'Orders').innerHTML = items.length
            ? visible.map((order, index) => kitchenCard(order, index === 0 ? kitchenColumnKeys[key] : null)).join('') + overflow
            : '<div class="kitchen-empty"><i class="fas fa-circle-check"></i>Sin pedidos en esta etapa</div>';
        kitchenNextByColumn[key] = items[0] || null;
    });
    syncKitchenArmedBar();
    document.querySelectorAll('[data-order]').forEach(button => button.addEventListener('click', () => {
        const order = kitchenOrders.find(item => String(item.id) === button.dataset.order);
        if (order) requestKitchenAction(order);
    }));
    document.querySelectorAll('[data-detail-order]').forEach(button => button.addEventListener('click', () => openKitchenDetail(button.dataset.detailOrder)));
}
function openKitchenDetail(orderId) {
    const order = kitchenOrders.find(item => String(item.id) === String(orderId));
    const modal = document.getElementById('kitchenDetailModal');
    if (!order || !modal) return;
    document.getElementById('kitchenDetailTitle').textContent = `Turno ${order.turn_number || order.display_number}`;
    document.getElementById('kitchenDetailBody').innerHTML = `
        <div class="kitchen-detail-meta">
            <span><i class="fas fa-store me-1"></i>${kitchenEsc(order.branch || 'Matriz')}</span>
            <span><i class="fas fa-user me-1"></i>${kitchenEsc(order.customer?.name || 'Cliente')}</span>
            <span><i class="fas fa-bag-shopping me-1"></i>${order.items.length} ${order.items.length === 1 ? 'producto' : 'productos'}</span>
        </div>
        <ul class="kitchen-detail-list">${order.items.map(item => `<li><b>${item.quantity}×</b>${kitchenEsc(item.name)}${item.note ? `<div class="kitchen-detail-note">${kitchenEsc(item.note)}</div>` : ''}</li>`).join('')}</ul>
        ${order.order_note ? `<div class="kitchen-order-note"><strong>Nota general</strong><br>${kitchenEsc(order.order_note)}</div>` : ''}`;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    document.getElementById('kitchenDetailClose')?.focus();
}
function closeKitchenDetail() {
    const modal = document.getElementById('kitchenDetailModal');
    if (!modal || modal.hidden) return;
    modal.hidden = true;
    document.body.style.overflow = '';
}
function syncKitchenArmedBar() {
    const bar = document.getElementById('kitchenArmedBar');
    if (!bar) return;
    const order = kitchenOrders.find(item => String(item.id) === kitchenArmedAction?.orderId);
    if (!order) {
        bar.hidden = true;
        bar.innerHTML = '';
        return;
    }
    const action = kitchenAction(order);
    bar.hidden = false;
    bar.innerHTML = `<span><i class="fas fa-triangle-exclamation me-1"></i>Turno ${kitchenEsc(order.turn_number || order.display_number)} seleccionado para: ${kitchenEsc(action.label)}</span><small>Repite la tecla o toca nuevamente para confirmar</small>`;
}
function clearKitchenArm(rerender = true) {
    clearTimeout(kitchenArmTimer);
    kitchenArmTimer = null;
    kitchenArmedAction = null;
    if (rerender) renderKitchen(kitchenOrders);
}
/** Primer toque selecciona y resalta; repetir la misma acción confirma. */
function requestKitchenAction(order) {
    if (!kitchenCanUpdate || !order) return;
    const action = kitchenAction(order);
    const isConfirmation = kitchenArmedAction?.orderId === String(order.id)
        && kitchenArmedAction?.status === action.status;

    if (isConfirmation) {
        const button = document.querySelector(`[data-order="${order.id}"]`);
        clearKitchenArm(false);
        if (button) updateKitchenStatus(order.id, action.status, button);
        return;
    }

    clearTimeout(kitchenArmTimer);
    kitchenArmedAction = { orderId: String(order.id), status: action.status };
    renderKitchen(kitchenOrders);
    kitchenArmTimer = setTimeout(() => clearKitchenArm(), 6000);
}
/** "Bump": la tecla apunta al pedido más antiguo de esa columna, pero necesita dos pulsaciones para ejecutarse. */
function kitchenBump(columnKey) {
    const order = kitchenNextByColumn[columnKey];
    if (!kitchenCanUpdate || !order) return;
    requestKitchenAction(order);
}
async function updateKitchenStatus(id, status, button) {
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Actualizando pedido…';
    try {
        const response = await fetch(kitchenTransitionTemplate.replace('__ORDER__', id), { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':kitchenCsrf}, body:JSON.stringify({status}) });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo actualizar el pedido.');
        kitchenToast('Comanda actualizada'); fetchKitchen();
    } catch (error) { kitchenToast(error.message, true); clearKitchenArm(); }
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
document.getElementById('kitchenDetailClose')?.addEventListener('click', closeKitchenDetail);
document.getElementById('kitchenDetailModal')?.addEventListener('click', event => {
    if (event.target.id === 'kitchenDetailModal') closeKitchenDetail();
});

// Atajos 1/2/3 = bump bar con confirmación. Se ignoran si el foco está en un campo de texto
// (por si el operador tiene abierto algún formulario en otra parte del panel).
document.addEventListener('keydown', (event) => {
    const detailModal = document.getElementById('kitchenDetailModal');
    if (detailModal && !detailModal.hidden) {
        if (event.key === 'Escape') closeKitchenDetail();
        return;
    }
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
