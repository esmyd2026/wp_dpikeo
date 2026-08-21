@extends('admin.layouts.app')

@section('header', 'Inicio')

@section('content')
<style>
    body.dashboard-page {
        background: linear-gradient(180deg, #eef2f7 0%, #f8fafc 40%, #f1f5f9 100%) !important;
    }
    .home-wrap { max-width: 960px; margin: 0 auto; }
    .home-top { border-radius:18px; padding:24px; color:#fff; background:radial-gradient(circle at 85% 10%,rgba(255,209,102,.34),transparent 28%),linear-gradient(120deg,#842000,#e85d04 55%,#ff8a19); }
    .home-top h1 { font-size: 1.6rem; font-weight: 900; color: #fff; margin: 0 0 .25rem; }
    .home-top p { margin: 0; color: rgba(255,255,255,.92); font-size: .9rem; }
    .home-quick {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: .75rem;
        margin: 1.25rem 0;
    }
    @media (max-width: 768px) { .home-quick { grid-template-columns: 1fr; } }
    .home-quick-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: .85rem 1rem;
    }
    .home-quick-card .lbl { font-size: .68rem; font-weight: 700; text-transform: uppercase; color: #64748b; }
    .home-quick-card .val { font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-top: .15rem; }
    .home-reports {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1.25rem;
    }
    @media (max-width: 992px) { .home-reports { grid-template-columns: 1fr; } }
    .home-report-card {
        display: block;
        text-decoration: none;
        background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.06);
        border-radius: 16px;
        padding: 1.25rem 1.35rem;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
        transition: transform .15s ease, box-shadow .15s ease;
        position: relative;
        overflow: hidden;
    }
    .home-report-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.09);
        text-decoration: none;
    }
    .home-report-card::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 4px;
        background: var(--accent, #128c7e);
    }
    .home-report-card.orders { --accent: #0f766e; }
    .home-report-card.products { --accent: #7c3aed; }
    .home-report-card.whatsapp { --accent: #128c7e; }
    .home-report-card h2 {
        margin: 0 0 .35rem;
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
    }
    .home-report-card p {
        margin: 0 0 .85rem;
        font-size: .84rem;
        color: #64748b;
        line-height: 1.45;
    }
    .home-report-card .link {
        font-size: .82rem;
        font-weight: 700;
        color: #128c7e;
    }
    .home-report-card .icon {
        width: 42px; height: 42px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: .75rem;
        font-size: 1.1rem;
    }
    .home-report-card.orders .icon { background: #e0f2fe; color: #0369a1; }
    .home-report-card.products .icon { background: #f3e8ff; color: #7c3aed; }
    .home-report-card.whatsapp .icon { background: #ecfdf5; color: #128c7e; }
    .demo-reset-panel {
        border: 1px solid #fde68a;
        background: #fffbeb;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
    }
    .demo-reset-panel h3 { font-size: .95rem; font-weight: 700; color: #92400e; margin: 0 0 .35rem; }
    .demo-reset-panel p { margin: 0; font-size: .84rem; color: #a16207; line-height: 1.45; max-width: 640px; }
    .demo-reset-panel .demo-reset-actions { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
    .demo-reset-btn {
        background: #d97706; color: #fff; border: none; border-radius: 8px;
        padding: .5rem 1rem; font-size: .85rem; font-weight: 600; cursor: pointer;
    }
    .demo-reset-check { display: flex; align-items: center; gap: .4rem; font-size: .8rem; color: #92400e; }
</style>
<script>document.body.classList.add('dashboard-page');</script>

<div class="home-wrap">
    <div class="home-top">
        <h1>DPIKEOS · Centro de operación</h1>
        <p>Gestiona pedidos, cocina, catálogo y atención de Club Dpikeolovers.</p>
    </div>

    <div class="home-quick">
        <div class="home-quick-card">
            <div class="lbl">Pedidos hoy</div>
            <div class="val">{{ number_format($quickStats['orders_today']) }}</div>
        </div>
        <div class="home-quick-card">
            <div class="lbl">Mensajes hoy</div>
            <div class="val">{{ number_format($quickStats['messages_today']) }}</div>
        </div>
        <div class="home-quick-card">
            <div class="lbl">Pedidos pendientes</div>
            <div class="val">{{ number_format($quickStats['pending_orders']) }}</div>
        </div>
    </div>

    <div class="home-reports">
        @perm('orders.view')
        @if(!($platformFeatureAccess['orders_blocked'] ?? false))
        <a href="{{ route('admin.reports.orders') }}" class="home-report-card orders">
            <div class="icon"><i class="fas fa-shopping-bag"></i></div>
            <h2>Reportes de pedidos</h2>
            <p>Ingresos, ticket promedio, estados y exportación Excel de ventas.</p>
            <span class="link">Ver reportes de pedidos →</span>
        </a>
        @endif
        @endperm

        @perm('products.view')
        <a href="{{ route('admin.products.index') }}" class="home-report-card products">
            <div class="icon"><i class="fas fa-box-open"></i></div>
            <h2>Productos</h2>
            <p>Catálogo, precios, stock e imágenes de tu tienda.</p>
            <span class="link">Ir a productos →</span>
        </a>
        @endperm

        @perm('dashboard.view')
        <a href="{{ route('admin.reports.whatsapp') }}" class="home-report-card whatsapp">
            <div class="icon"><i class="fab fa-whatsapp"></i></div>
            <h2>WhatsApp</h2>
            <p>Consumo Meta, mensajes, tiempos de respuesta y actividad del canal.</p>
            <span class="link">Ver reportes de WhatsApp →</span>
        </a>
        @endperm
    </div>

    @perm('demo.reset')
    <section class="demo-reset-panel" aria-label="Reiniciar demo">
        <div class="demo-reset-actions">
            <div style="flex:1; min-width:220px;">
                <h3><i class="fas fa-rotate-left me-1"></i> Reiniciar demo</h3>
                <p>Elimina chats y pedidos para volver a mostrar la demo. No borra productos ni configuración del bot.</p>
            </div>
            <form method="post" action="{{ route('admin.demo.reset') }}"
                onsubmit="return confirm('¿Reiniciar la demo? Se borrarán todos los mensajes y pedidos.');">
                @csrf
                <label class="demo-reset-check mb-2">
                    <input type="checkbox" name="confirm" value="1" required>
                    Entiendo que se borrarán los datos
                </label>
                <button type="submit" class="demo-reset-btn">
                    <i class="fas fa-trash-restore me-1"></i> Reiniciar demo
                </button>
            </form>
        </div>
    </section>
    @endperm
</div>
@endsection
