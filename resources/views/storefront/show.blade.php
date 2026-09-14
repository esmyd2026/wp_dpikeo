<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $settings->primary_color }}">
    <title>Pide en línea — {{ $profile->business_name ?: $company->name }}</title>
    <style>
        :root{--brand:{{ $settings->primary_color }};--brand-dark:{{ $settings->secondary_color }};--accent:{{ $settings->accent_color }}}
        *{box-sizing:border-box}body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#242424;background:#fff}
        .storefront-gateway{min-height:100dvh;padding-bottom:48px;background:#fff}.storefront-hero{background:#fff;color:#222;box-shadow:0 12px 26px rgba(0,0,0,.05)}
        .storefront-gateway.is-browsing{min-height:0;padding:0}.storefront-gateway.is-browsing>.storefront-hero,.storefront-gateway.is-browsing>.storefront-preview,.storefront-gateway.is-browsing>.storefront-home-bottom{display:none}
        .storefront-nav{display:flex;min-height:124px;align-items:center;padding:20px 34px}.storefront-brand{display:flex;align-items:center;gap:20px}.storefront-menu-toggle{width:36px;height:36px;padding:3px;border:0;background:transparent;color:#2d2d2d;cursor:pointer}.storefront-menu-toggle span{display:block;height:3px;margin:6px 0;border-radius:4px;background:currentColor}.storefront-logo{width:120px;height:100px;object-fit:contain}.storefront-tabs{display:flex;height:62px;padding:0 82px;align-items:flex-end;gap:44px}.storefront-tab{position:relative;padding:0 0 17px;font-size:1rem}.storefront-tab:after{content:'';position:absolute;left:0;right:0;bottom:0;height:4px;border-radius:4px;background:var(--accent)}
        .storefront-mode-row{display:flex;gap:12px}.storefront-mode{flex:1;border:1px solid #ddd;border-radius:10px;padding:15px;background:#fff;color:#222;font:inherit;font-weight:800;cursor:pointer}.storefront-mode.is-active{border:2px solid var(--accent);background:color-mix(in srgb,var(--accent) 10%,white)}
        .storefront-location{display:none;position:fixed;z-index:130;inset:0;width:auto;margin:0;padding:20px;align-items:center;justify-content:center;background:rgba(0,0,0,.45)}.storefront-location.is-open{display:flex}.storefront-location-card{width:min(800px,100%);min-height:70dvh;max-height:92dvh;padding:42px 56px;border-radius:8px;background:#fff;box-shadow:0 24px 70px rgba(0,0,0,.3);overflow:auto}.storefront-location-head{display:flex;align-items:center;justify-content:space-between}.storefront-location-head h2{margin:0 0 18px;font-size:1.55rem}.storefront-location-close{border:0;background:transparent;font-size:2rem;cursor:pointer}.storefront-field{display:grid;gap:6px;margin:14px 0}.storefront-field span{font-size:.78rem;font-weight:800;color:#555}.storefront-field input,.storefront-field select{width:100%;border:1px solid #d7d7d7;border-radius:9px;padding:13px;font:inherit}.storefront-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px}.storefront-button{border:0;border-radius:8px;padding:14px 22px;background:var(--accent);font:inherit;font-weight:800;cursor:pointer}.storefront-geolocate{background:#f3f3f3}.storefront-preview{width:min(1260px,100%);margin:auto;padding:70px 40px 120px}.storefront-category-grid{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:76px 64px}.storefront-category{min-height:120px;border:0;background:#fff;text-align:center;font:inherit;font-size:1.3rem;cursor:pointer}.storefront-category img,.storefront-category-icon{display:flex;width:120px;height:82px;margin:0 auto 16px;align-items:center;justify-content:center;object-fit:contain;font-size:3rem}.storefront-category:hover{transform:translateY(-3px)}.storefront-error{display:none;color:#b42318;font-size:.82rem;margin-top:7px}
        .storefront-drawer{position:fixed;z-index:140;inset:0 auto 0 0;width:min(470px,88vw);padding:26px 32px;background:#fff;color:#222;box-shadow:20px 0 60px rgba(0,0,0,.3);transform:translateX(-105%);transition:transform .22s}.storefront-drawer.is-open{transform:none}.storefront-drawer-close{float:right;border:0;background:none;font-size:2rem;cursor:pointer}.storefront-drawer-nav{display:grid;gap:5px;clear:both;padding-top:22px}.storefront-drawer-link{display:flex;width:100%;align-items:center;gap:14px;padding:15px 4px;border:0;border-bottom:1px solid #f0f0f0;background:#fff;color:#222;text-align:left;text-decoration:none;font:inherit;font-weight:700;cursor:pointer}.storefront-drawer-link:hover{color:var(--brand-dark);background:#fffaf2}.storefront-drawer-link-icon{display:grid;width:30px;height:30px;place-items:center;color:var(--brand-dark)}.storefront-drawer-link-icon svg{display:block;width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.storefront-drawer-logout{margin-top:18px;color:#b42318}.storefront-home-bottom{display:none}
        .storefront-branch-list{display:grid;gap:2px;margin:8px 0 12px}.storefront-branch-option{display:grid;padding:13px 8px;border:0;border-bottom:1px solid #e5e7eb;background:#fff;text-align:left;font:inherit;cursor:pointer}.storefront-branch-option strong{font-size:.86rem}.storefront-branch-option small{color:#555}.storefront-branch-option.is-active{border-left:4px solid var(--accent);background:#fffaf0}.storefront-location-copy{margin:-8px 0 18px;color:#666;font-size:.82rem}.storefront-pickup-fields{margin-top:18px}
        .storefront-nav-overlay{display:none;position:fixed;z-index:1450;inset:0;padding:20px;align-items:center;justify-content:center;background:rgba(0,0,0,.48)}.storefront-nav-overlay.is-open{display:flex}.storefront-nav-card{width:min(620px,100%);max-height:90dvh;padding:28px;border-radius:22px;background:#fff;box-shadow:0 24px 70px rgba(0,0,0,.3);overflow:auto}.storefront-nav-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}.storefront-nav-head h2{margin:0}.storefront-nav-head button{border:0;background:none;font-size:2rem;cursor:pointer}.storefront-account-form{display:grid;gap:14px}.storefront-account-form label{display:grid;gap:6px;font-size:.78rem;font-weight:800}.storefront-account-form input{width:100%;padding:13px;border:1px solid #d7d7d7;border-radius:9px;font:inherit}.storefront-account-save{padding:14px;border:0;border-radius:8px;background:var(--accent);font:inherit;font-weight:800;cursor:pointer}.storefront-account-note{color:#666;font-size:.8rem;line-height:1.45;margin-top:14px}
        .storefront-account-tabs{display:flex;gap:6px;margin-bottom:18px;border-bottom:1px solid #eee}.storefront-account-tab{flex:1;padding:12px 4px;border:0;border-bottom:3px solid transparent;background:none;font:inherit;font-weight:800;color:#888;cursor:pointer}.storefront-account-tab.is-active{color:var(--brand-dark);border-bottom-color:var(--accent)}
        .storefront-account-error{margin:0 0 14px;padding:11px 13px;border-radius:9px;background:#fdecea;color:#b42318;font-size:.82rem;font-weight:700}
        .storefront-account-welcome{margin:0 0 20px;font-size:1.02rem;line-height:1.5}.storefront-account-welcome span{color:#777;font-size:.85rem;font-weight:600}
        .storefront-account-subtitle{margin:0 0 12px;font-size:.92rem}
        .storefront-orders-list{display:flex;flex-direction:column;gap:10px;margin-bottom:20px}
        .storefront-order-card{width:100%;border:1px solid #e5e7eb;border-radius:12px;padding:13px 15px;background:#fff;color:#242424;text-align:left;font:inherit;cursor:pointer}.storefront-order-card:hover{border-color:var(--accent);box-shadow:0 5px 16px rgba(0,0,0,.06)}.storefront-order-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.storefront-order-card strong{font-size:.9rem}.storefront-order-card time{display:block;margin-top:2px;color:#888;font-size:.76rem}.storefront-order-card-foot{display:flex;align-items:center;justify-content:space-between;margin-top:8px;font-size:.85rem}.storefront-order-card-foot b{font-variant-numeric:tabular-nums}.storefront-order-card-hint{display:block;margin-top:7px;color:var(--brand-dark);font-size:.72rem;font-weight:800}
        .storefront-order-status{padding:3px 10px;border-radius:999px;font-size:.72rem;font-weight:800;white-space:nowrap;background:#eef2f7;color:#425466}.storefront-order-status[data-status-kind="success"]{background:#e6f6ea;color:#1a7f37}.storefront-order-status[data-status-kind="warning"]{background:#fff6e0;color:#8a6100}.storefront-order-status[data-status-kind="danger"]{background:#fdecea;color:#b42318}
        .storefront-account-logout{width:100%;padding:13px;border:1px solid #ddd;border-radius:8px;background:#fff;font:inherit;font-weight:800;color:#333;cursor:pointer}
        .storefront-order-detail{display:none;position:fixed;z-index:1580;inset:0;padding:20px;align-items:center;justify-content:center;background:rgba(0,0,0,.52)}.storefront-order-detail.is-open{display:flex}.storefront-order-detail-card{width:min(820px,100%);max-height:92dvh;border-radius:22px;background:#f7f9fb;box-shadow:0 28px 80px rgba(0,0,0,.35);overflow:auto}.storefront-order-detail-head{position:sticky;z-index:2;top:0;display:flex;align-items:flex-start;justify-content:space-between;gap:15px;padding:22px 24px;border-bottom:1px solid #e5e7eb;background:#fff}.storefront-order-detail-head h2{margin:0;font-size:1.15rem}.storefront-order-detail-head p{margin:5px 0 0;color:#718096;font-size:.78rem}.storefront-order-detail-close{width:38px;height:38px;border:0;border-radius:50%;background:#edf1f5;font-size:1.5rem;cursor:pointer}.storefront-order-detail-body{display:grid;gap:14px;padding:18px 22px 26px}.storefront-order-progress{display:flex;padding:18px 12px;border:1px solid #dce3e9;border-radius:14px;background:#fff}.storefront-order-progress-step{position:relative;display:grid;flex:1;justify-items:center;gap:7px;color:#9aa6b2;font-size:.7rem;text-align:center}.storefront-order-progress-step:not(:last-child):after{content:'';position:absolute;z-index:0;left:62%;right:-38%;top:15px;height:2px;background:#dce3e9}.storefront-order-progress-dot{position:relative;z-index:1;display:grid;width:31px;height:31px;place-items:center;border:2px solid #dce3e9;border-radius:50%;background:#fff;font-weight:900}.storefront-order-progress-step.is-done,.storefront-order-progress-step.is-current{color:#147d73}.storefront-order-progress-step.is-done:not(:last-child):after{background:#168c82}.storefront-order-progress-step.is-done .storefront-order-progress-dot{border-color:#168c82;background:#168c82;color:#fff}.storefront-order-progress-step.is-current .storefront-order-progress-dot{border-color:#168c82;box-shadow:0 0 0 4px #e4f7f3}.storefront-order-next{padding:16px;border:1px solid #a8ead3;border-radius:14px;background:#ecfbf4}.storefront-order-next small{display:block;color:#16745d;font-size:.68rem;font-weight:900;text-transform:uppercase}.storefront-order-next strong{display:block;margin-top:4px}.storefront-order-next span{display:block;margin-top:3px;color:#396b5c;font-size:.82rem}.storefront-order-snapshot{display:grid;grid-template-columns:repeat(4,1fr);border:1px solid #dce3e9;border-radius:14px;background:#fff;overflow:hidden}.storefront-order-snapshot div{padding:13px;border-right:1px solid #e5e7eb}.storefront-order-snapshot div:last-child{border:0}.storefront-order-snapshot small{display:block;color:#718096;font-size:.62rem;font-weight:900;text-transform:uppercase}.storefront-order-snapshot strong{display:block;margin-top:5px;font-size:.8rem}.storefront-order-section{border:1px solid #dce3e9;border-radius:14px;background:#fff;overflow:hidden}.storefront-order-section h3{margin:0;padding:14px 16px;border-bottom:1px solid #edf0f2;font-size:.88rem}.storefront-order-line{display:flex;justify-content:space-between;gap:15px;padding:13px 16px;border-bottom:1px solid #edf0f2}.storefront-order-line:last-child{border:0}.storefront-order-line strong{font-size:.84rem}.storefront-order-line span{display:block;margin-top:3px;color:#718096;font-size:.75rem}.storefront-order-line-price{flex:0 0 auto;font-weight:850;font-size:.85rem}.storefront-order-total{display:flex;justify-content:space-between;padding:14px 16px;background:#ecfbf4;color:#086b57;font-weight:900}.storefront-order-history{display:grid;gap:0;padding:8px 16px 14px}.storefront-order-history-event{position:relative;padding:10px 0 10px 25px;border-left:2px solid #dce3e9}.storefront-order-history-event:before{content:'';position:absolute;left:-6px;top:15px;width:10px;height:10px;border-radius:50%;background:#168c82}.storefront-order-history-event:last-child{border-left-color:transparent}.storefront-order-history-event strong{display:block;font-size:.8rem}.storefront-order-history-event time{color:#718096;font-size:.72rem}
        .storefront-order-self-service{display:grid;gap:10px;padding:15px 16px}.storefront-order-self-service p{margin:0;color:#5f6b76;font-size:.78rem;line-height:1.45}.storefront-order-bank{padding:12px;border-left:3px solid var(--brand);background:#fff8e7;color:#3c3322!important}.storefront-order-self-service label{display:grid;gap:5px;color:#364152;font-size:.72rem;font-weight:800}.storefront-order-self-service input,.storefront-order-self-service select{width:100%;padding:10px 11px;border:1px solid #ccd5dd;border-radius:8px;background:#fff;font:inherit}.storefront-order-self-service button{min-height:44px;border:0;border-radius:8px;background:var(--brand);color:#fff;font:inherit;font-weight:850;cursor:pointer}.storefront-order-invoice-fields{display:none;grid-template-columns:1fr 1fr;gap:10px}.storefront-order-invoice-fields.is-open{display:grid}.storefront-order-form-status{font-weight:800!important;color:#087f5b!important}
        .storefront-branch-accordion{display:flex;flex-direction:column;gap:10px}.storefront-branch-item{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.storefront-branch-item summary{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 16px;cursor:pointer;list-style:none;font-weight:800}.storefront-branch-item summary::-webkit-details-marker{display:none}.storefront-branch-item summary:after{content:'▾';color:#999;transition:transform .15s}.storefront-branch-item[open] summary:after{transform:rotate(180deg)}.storefront-branch-item-badge{font-size:.7rem;font-weight:800;color:var(--brand-dark);background:color-mix(in srgb,var(--accent) 22%,white);border-radius:999px;padding:3px 10px;margin-right:auto;margin-left:10px}.storefront-branch-item-body{padding:0 16px 16px;border-top:1px solid #f0f0f0}.storefront-branch-item-row{margin:12px 0 0;font-size:.88rem;color:#333}.storefront-branch-item-row a{color:inherit}.storefront-branch-hours{margin-top:12px;border-top:1px dashed #e5e7eb;padding-top:10px}.storefront-branch-hours-row{display:flex;justify-content:space-between;padding:3px 0;font-size:.82rem;color:#555}.storefront-branch-item-map{display:inline-block;margin-top:14px;font-size:.85rem;font-weight:800;color:var(--brand-dark);text-decoration:underline}
        #storefrontApp{display:none}.storefront-back{display:none}
        @media(max-width:640px){.storefront-gateway{padding-bottom:88px}.storefront-hero{box-shadow:none}.storefront-nav{min-height:82px;padding:12px 20px}.storefront-brand{gap:14px}.storefront-menu-toggle{width:34px;height:34px}.storefront-logo{width:100px;height:100px}.storefront-tabs{display:none}.storefront-preview{padding:34px 24px 48px}.storefront-category-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:48px 24px}.storefront-category{min-height:120px;font-size:1.08rem}.storefront-category img,.storefront-category-icon{width:96px;height:76px;margin-bottom:12px}.storefront-location{padding:0;background:#fff}.storefront-location-card{width:100%;height:100dvh;max-height:none;padding:18px 16px 22px;border-radius:0;box-shadow:none}.storefront-location-copy{display:none}.storefront-location-head{margin-bottom:20px}.storefront-location-head h2{font-size:1.4rem}.storefront-actions{position:sticky;bottom:0;padding-top:18px;background:#fff}.storefront-actions .storefront-button{width:100%;min-height:52px}.storefront-home-bottom{display:flex;position:fixed;z-index:80;left:0;right:0;bottom:0;height:76px;align-items:center;justify-content:space-around;border-top:1px solid #eee;border-radius:10px 10px 0 0;background:#fff;box-shadow:0 -8px 25px rgba(0,0,0,.09)}.storefront-home-bottom button{border:0;background:none;color:#777;font:inherit;font-size:.68rem}.storefront-home-bottom span{display:flex;justify-content:center;margin-bottom:3px}.storefront-home-bottom span svg{display:block}.storefront-home-bottom button:first-child{color:var(--brand);font-weight:800}.storefront-order-detail{padding:0;background:#fff}.storefront-order-detail-card{width:100%;height:100dvh;max-height:none;border-radius:0}.storefront-order-detail-head{padding:16px}.storefront-order-detail-body{padding:14px 12px 28px}.storefront-order-progress{padding:16px 4px}.storefront-order-progress-step{font-size:.62rem}.storefront-order-snapshot{grid-template-columns:repeat(2,1fr)}.storefront-order-snapshot div:nth-child(2){border-right:0}.storefront-order-snapshot div:nth-child(-n+2){border-bottom:1px solid #e5e7eb}}
        /* El selector de entrega debe mostrarse sobre el detalle del producto. */
        .storefront-location{z-index:1600}
    </style>
</head>
<body>
<main class="storefront-gateway" id="storefrontGateway">
    <section class="storefront-hero">
        <nav class="storefront-nav">
            <div class="storefront-brand"><button type="button" class="storefront-menu-toggle" id="storefrontMenuToggle" aria-label="Abrir menú"><span></span><span></span><span></span></button>@if($settings->logoUrl())<img src="{{ $settings->logoUrl() }}" class="storefront-logo" alt="{{ $profile->business_name ?: $company->name }}">@else<strong>{{ $profile->business_name ?: $company->name }}</strong>@endif</div>
        </nav>
        <div class="storefront-tabs"><span class="storefront-tab">Productos</span></div>
    </section>
    <section class="storefront-location" id="storefrontLocation">
        <div class="storefront-location-card">
            <div class="storefront-location-head"><h2 id="storefrontLocationTitle">¿Cómo quieres recibir tu pedido?</h2><button type="button" class="storefront-location-close" id="storefrontLocationClose" aria-label="Cerrar">×</button></div>
            <p class="storefront-location-copy">Elige si deseas retirar tu pedido en un local o recibirlo en una dirección.</p>
            <div class="storefront-mode-row"><button class="storefront-mode" data-storefront-mode="pickup">📍 Pide y retira</button><button class="storefront-mode" data-storefront-mode="delivery">🛵 Delivery</button></div>
            <select id="storefrontBranch" hidden><option value="">Selecciona una sucursal</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branch->is_default)>{{ $branch->name }}{{ $branch->address ? ' · '.$branch->address : '' }}</option>@endforeach</select>
            <div class="storefront-pickup-fields" id="pickupFields"><label class="storefront-field"><span>Busca un local</span><input type="search" id="storefrontBranchSearch" placeholder="Busca una dirección o sucursal" autocomplete="off"></label><div class="storefront-branch-list" id="storefrontBranchList">@foreach($branches as $branch)<button type="button" class="storefront-branch-option @if($branch->is_default) is-active @endif" data-branch-option="{{ $branch->id }}" data-branch-search="{{ Str::lower($branch->name.' '.$branch->address) }}"><strong>{{ $branch->name }}</strong><small>{{ $branch->address ?: 'Sucursal disponible' }}</small></button>@endforeach</div></div>
            <div id="deliveryFields" style="display:none">
                <label class="storefront-field"><span>Dirección de entrega</span><input id="storefrontAddress" placeholder="Busca una dirección" autocomplete="street-address"></label>
                <label class="storefront-field"><span>Referencia para el repartidor</span><input id="storefrontReference" placeholder="Ej.: casa de dos pisos, junto al mercado"></label>
                <button type="button" class="storefront-button storefront-geolocate" id="storefrontGeolocate">⌖ Usar mi ubicación actual</button>
            </div>
            <p class="storefront-error" id="storefrontError"></p>
            <div class="storefront-actions"><button class="storefront-button" id="storefrontContinue">Comenzar</button></div>
        </div>
    </section>
    <section class="storefront-preview"><div class="storefront-category-grid" id="storefrontCategoryPreview" aria-label="Categorías">@foreach($categories as $category)<button class="storefront-category" type="button" data-preview-category="{{ $category['id'] }}">@if(filled($category['image'] ?? null))<img src="{{ $category['image'] }}" alt="">@else<span class="storefront-category-icon">{{ $category['icon'] ?? '🍔' }}</span>@endif<span>{{ $category['title'] }}</span></button>@endforeach</div></section>
    <nav class="storefront-home-bottom" aria-label="Navegación principal">
        <button type="button" data-storefront-nav="home"><span><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9a1 1 0 0 0 1 1H10v-6h4v6h3.5a1 1 0 0 0 1-1v-9"/></svg></span>Inicio</button>
        <button type="button" data-storefront-nav="branches"><span><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.2a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.8" r="2.6"/></svg></span>Sucursales</button>
        <button type="button" data-storefront-nav="account"><span><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4.5 20c0-4 3.5-6.2 7.5-6.2s7.5 2.2 7.5 6.2"/></svg></span>Mi cuenta</button>
    </nav>
</main>
<aside class="storefront-drawer" id="storefrontDrawer" aria-hidden="true">
    <button type="button" class="storefront-drawer-close" id="storefrontDrawerClose" aria-label="Cerrar">×</button>
    <nav class="storefront-drawer-nav" aria-label="Menú principal">
        <a href="#storefrontGateway" class="storefront-drawer-link" data-drawer-nav="products"><span class="storefront-drawer-link-icon" data-icon="products" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 11h16l-1.2 8H5.2L4 11Z"/><path d="M7 11a5 5 0 0 1 10 0"/><path d="M3 20h18"/></svg></span><span>Productos</span></a>
        <button type="button" class="storefront-drawer-link" data-drawer-nav="branches"><span class="storefront-drawer-link-icon" data-icon="branches" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11.2a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.8" r="2.6"/></svg></span><span>Sucursales</span></button>
        <button type="button" class="storefront-drawer-link" data-drawer-nav="account"><span class="storefront-drawer-link-icon" data-icon="account" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 20c0-4 3.5-6.2 7.5-6.2s7.5 2.2 7.5 6.2"/></svg></span><span>Mi cuenta</span></button>
        <button type="button" class="storefront-drawer-link storefront-drawer-logout" id="storefrontDrawerLogout" style="display:none"><span class="storefront-drawer-link-icon" data-icon="logout" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M10 5H5v14h5"/><path d="M13 8l4 4-4 4"/><path d="M8 12h9"/></svg></span><span>Cerrar sesión</span></button>
    </nav>
</aside>
<section class="storefront-nav-overlay" id="storefrontBranches" aria-hidden="true">
    <div class="storefront-nav-card">
        <div class="storefront-nav-head"><h2>Sucursales</h2><button type="button" data-close-nav="branches" aria-label="Cerrar">×</button></div>
        <div class="storefront-branch-accordion">
            @forelse($infoBranches as $branch)
                @php
                    $hours = $branch->hoursByDay();
                    $mapUrl = $branch->latitude && $branch->longitude
                        ? 'https://maps.google.com/?q='.$branch->latitude.','.$branch->longitude
                        : ($branch->address ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($branch->address) : null);
                @endphp
                <details class="storefront-branch-item">
                    <summary>
                        <span class="storefront-branch-item-name">{{ $branch->name }}</span>
                        @if($branch->is_default)<span class="storefront-branch-item-badge">Principal</span>@endif
                    </summary>
                    <div class="storefront-branch-item-body">
                        @if($branch->address)<p class="storefront-branch-item-row">📍 {{ $branch->address }}</p>@endif
                        @if($branch->phone)<p class="storefront-branch-item-row">📞 <a href="tel:{{ $branch->phone }}">{{ $branch->phone }}</a></p>@endif
                        @if($branch->reservations_info)<p class="storefront-branch-item-row">🗓️ {{ $branch->reservations_info }}</p>@endif
                        <div class="storefront-branch-hours">
                            @foreach($hours as $hour)
                                <div class="storefront-branch-hours-row">
                                    <span>{{ $hour->dayLabel() }}</span>
                                    <span>{{ $hour->is_closed ? 'Cerrado' : (($hour->opens_at ? \Illuminate\Support\Carbon::parse($hour->opens_at)->format('H:i') : '--').' - '.($hour->closes_at ? \Illuminate\Support\Carbon::parse($hour->closes_at)->format('H:i') : '--')) }}</span>
                                </div>
                            @endforeach
                        </div>
                        @if($mapUrl)<a class="storefront-branch-item-map" href="{{ $mapUrl }}" target="_blank" rel="noopener">Ver ubicación en el mapa</a>@endif
                    </div>
                </details>
            @empty
                <p>No hay sucursales disponibles en este momento.</p>
            @endforelse
        </div>
    </div>
</section>
<section class="storefront-nav-overlay" id="storefrontAccount" aria-hidden="true"><div class="storefront-nav-card">
    <div class="storefront-nav-head"><h2>Mi cuenta</h2><button type="button" data-close-nav="account" aria-label="Cerrar">×</button></div>
    <div id="storefrontAccountGuest">
        <div class="storefront-account-tabs">
            <button type="button" class="storefront-account-tab is-active" data-account-tab="login">Iniciar sesión</button>
            <button type="button" class="storefront-account-tab" data-account-tab="register">Crear cuenta</button>
        </div>
        <p class="storefront-account-error" id="storefrontAccountError" style="display:none"></p>
        <form class="storefront-account-form" id="storefrontLoginForm" data-account-panel="login">
            <label>Teléfono<input type="tel" id="loginPhone" required maxlength="30" autocomplete="tel"></label>
            <label>Contraseña<input type="password" id="loginPassword" required autocomplete="current-password"></label>
            <button type="submit" class="storefront-account-save">Iniciar sesión</button>
        </form>
        <form class="storefront-account-form" id="storefrontRegisterForm" data-account-panel="register" style="display:none">
            <label>Nombre completo<input type="text" id="registerName" required maxlength="120" autocomplete="name"></label>
            <label>Teléfono<input type="tel" id="registerPhone" required maxlength="30" autocomplete="tel"></label>
            <label>Contraseña<input type="password" id="registerPassword" required minlength="6" autocomplete="new-password"></label>
            <label>Confirmar contraseña<input type="password" id="registerPasswordConfirm" required minlength="6" autocomplete="new-password"></label>
            <button type="submit" class="storefront-account-save">Crear cuenta</button>
        </form>
        <p class="storefront-account-note">Usa el mismo número de WhatsApp con el que haces tus pedidos.</p>
    </div>
    <div id="storefrontAccountAuthed" style="display:none">
        <p class="storefront-account-welcome"><strong id="storefrontAccountName"></strong><br><span id="storefrontAccountPhoneLabel"></span></p>
        <h3 class="storefront-account-subtitle">Mis pedidos</h3>
        <div id="storefrontOrdersList" class="storefront-orders-list"><p class="storefront-account-note">Cargando…</p></div>
        <button type="button" class="storefront-account-logout" id="storefrontLogoutBtn">Cerrar sesión</button>
    </div>
</div></section>
<section class="storefront-order-detail" id="storefrontOrderDetail" aria-hidden="true">
    <div class="storefront-order-detail-card" role="dialog" aria-modal="true" aria-labelledby="storefrontOrderDetailTitle">
        <header class="storefront-order-detail-head">
            <div><h2 id="storefrontOrderDetailTitle">Detalle del pedido</h2><p id="storefrontOrderDetailSubtitle"></p></div>
            <button type="button" class="storefront-order-detail-close" id="storefrontOrderDetailClose" aria-label="Cerrar">×</button>
        </header>
        <div class="storefront-order-detail-body" id="storefrontOrderDetailBody"></div>
    </div>
</section>

<div id="storefrontApp">
    <button class="storefront-back" type="button" id="storefrontBack">← Cambiar entrega</button>
    @include('bulk-order.partials.form-app', [
        'mode' => 'storefront',
        'catalogUrl' => route('storefront.catalog', $company),
        'submitUrl' => route('storefront.submit', $company),
        'branches' => $branches,
        'headerTitle' => $profile->business_name ?: $company->name,
        'headerSubtitle' => 'Elige tus favoritos y arma tu pedido.',
        'storefrontSettings' => $settings,
        'bankTransferInstructions' => $bankTransferInstructions,
        'cardPaymentUrl' => $cardPaymentUrl,
        'persistenceKey' => 'storefront_cart_'.$company->id,
    ])
</div>

<script>
const storefrontOrderStorageKey = @json('storefront_order_'.$company->id);
const storefrontOrderDefaults = {confirmed:false,service_type:null,branch_id:null,address:'',reference:'',latitude:null,longitude:null};
try {
    const savedStorefrontOrder = JSON.parse(localStorage.getItem(storefrontOrderStorageKey) || 'null');
    window.storefrontOrder = savedStorefrontOrder && typeof savedStorefrontOrder === 'object'
        ? {...storefrontOrderDefaults, ...savedStorefrontOrder}
        : {...storefrontOrderDefaults};
} catch (_) {
    localStorage.removeItem(storefrontOrderStorageKey);
    window.storefrontOrder = {...storefrontOrderDefaults};
}
(function(){
 const gateway=document.getElementById('storefrontGateway'),locationBox=document.getElementById('storefrontLocation'),delivery=document.getElementById('deliveryFields'),error=document.getElementById('storefrontError');
 const persistOrder=()=>{try{localStorage.setItem(storefrontOrderStorageKey,JSON.stringify(window.storefrontOrder));}catch(_){/* El pedido sigue funcionando aunque el navegador bloquee el almacenamiento. */}};
 window.clearStorefrontOrderState=()=>{localStorage.removeItem(storefrontOrderStorageKey);window.storefrontOrder={...storefrontOrderDefaults};};
 let pendingCategory='';
 const openCategory=(categoryId,attempt=0)=>{const categorySelect=document.getElementById('bulkCategory'),option=categorySelect?.querySelector(`option[value="${categoryId}"]`);if(!option&&attempt<12){setTimeout(()=>openCategory(categoryId,attempt+1),80);return;}if(categorySelect){categorySelect.value=categoryId;categorySelect.dispatchEvent(new Event('change'));}gateway.classList.add('is-browsing');document.getElementById('storefrontApp').style.display='block';window.scrollTo({top:0});};
 const drawer=document.getElementById('storefrontDrawer'),setDrawer=open=>{drawer.classList.toggle('is-open',open);drawer.setAttribute('aria-hidden',open?'false':'true')};document.getElementById('storefrontMenuToggle').addEventListener('click',()=>setDrawer(true));document.getElementById('storefrontCatalogMenu')?.addEventListener('click',()=>setDrawer(true));document.getElementById('storefrontDrawerClose').addEventListener('click',()=>setDrawer(false));document.querySelectorAll('[data-close-drawer]').forEach(link=>link.addEventListener('click',()=>setDrawer(false)));
 const branchesOverlay=document.getElementById('storefrontBranches'),account=document.getElementById('storefrontAccount');
 const toggleNavOverlay=(target,open)=>{target.classList.toggle('is-open',open);target.setAttribute('aria-hidden',open?'false':'true');document.body.style.overflow=open?'hidden':'';};
 const goHome=()=>{document.getElementById('bulkCustomizer')?.classList.remove('is-open');document.getElementById('bulkCartPanel')?.classList.remove('is-storefront-open','is-checkout-step');document.getElementById('storefrontApp').style.display='none';gateway.classList.remove('is-browsing');gateway.style.display='block';document.body.style.overflow='';window.scrollTo({top:0,behavior:'smooth'});};window.goHome=goHome;
 document.querySelectorAll('[data-drawer-nav]').forEach(item=>item.addEventListener('click',event=>{
     event.preventDefault();
     const action=item.dataset.drawerNav;
     setDrawer(false);
     if(action==='products')goHome();
     if(action==='branches')toggleNavOverlay(branchesOverlay,true);
     if(action==='account'){toggleNavOverlay(account,true);window.loadStorefrontOrders?.();}
 }));
 document.querySelectorAll('[data-storefront-nav]').forEach(button=>button.addEventListener('click',()=>{const action=button.dataset.storefrontNav;if(action==='home')goHome();if(action==='branches')toggleNavOverlay(branchesOverlay,true);if(action==='account'){toggleNavOverlay(account,true);window.loadStorefrontOrders?.();}}));
 document.querySelectorAll('[data-close-nav]').forEach(button=>button.addEventListener('click',()=>toggleNavOverlay(button.dataset.closeNav==='branches'?branchesOverlay:account,false)));[branchesOverlay,account].forEach(overlay=>overlay.addEventListener('click',event=>{if(event.target===overlay)toggleNavOverlay(overlay,false);}));
 // "Mi cuenta": login/registro real con teléfono+contraseña (WhatsappContact
 // como identidad), reemplaza el viejo formulario que solo guardaba datos en
 // localStorage. La sesión vive en el guard "storefront_customer".
 (function(){
     const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
     const accountBase=@json('/tienda/'.$company->slug);
     const accountUrl=path=>accountBase+path;
     const guestBox=document.getElementById('storefrontAccountGuest'),authedBox=document.getElementById('storefrontAccountAuthed');
     const drawerLogout=document.getElementById('storefrontDrawerLogout');
     const errorBox=document.getElementById('storefrontAccountError');
     const loginForm=document.getElementById('storefrontLoginForm'),registerForm=document.getElementById('storefrontRegisterForm');
     const showError=message=>{errorBox.textContent=message;errorBox.style.display='block';};
     const clearError=()=>{errorBox.style.display='none';};
     document.querySelectorAll('[data-account-tab]').forEach(tab=>tab.addEventListener('click',()=>{
         document.querySelectorAll('[data-account-tab]').forEach(t=>t.classList.toggle('is-active',t===tab));
         const target=tab.dataset.accountTab;
         loginForm.style.display=target==='login'?'grid':'none';
         registerForm.style.display=target==='register'?'grid':'none';
         clearError();
     }));
     const fillCustomerProfile=customer=>{
         if(!customer)return;
         if(document.getElementById('storefrontCustomerName'))document.getElementById('storefrontCustomerName').value=customer.name||'';
         if(document.getElementById('storefrontCustomerPhone'))document.getElementById('storefrontCustomerPhone').value=customer.phone||'';
         if(document.getElementById('storefrontCustomerEmail'))document.getElementById('storefrontCustomerEmail').value=customer.email||'';
     };
     const statusKind=status=>({paid:'success',completed:'success',ready:'success',confirmed:'success',cancelled:'danger',payment_pending:'warning',pending:'warning'}[status]||'neutral');
    let customerOrders=[];
    let currentCustomerOrderId=null;
    const escAccount=value=>{const node=document.createElement('div');node.textContent=value??'';return node.innerHTML;};
    const money=value=>'$'+Number(value||0).toFixed(2);
    const dateTime=value=>value?new Date(value).toLocaleString('es-EC',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}):'';
    const renderOrderProgress=order=>{
        if(order.status==='cancelled')return '<div class="storefront-order-next"><small>Proceso detenido</small><strong>Pedido cancelado</strong><span>Este pedido ya no continuará.</span></div>';
        // En efectivo no hay comprobante ni verificación de pago antes de
        // cocina -- el pago se recibe recién al entregar el pedido, así que
        // no existe un paso "Pago" independiente como en transferencia/
        // tarjeta (ver el mismo criterio en OrderLifecycleService).
        const isCash=order.payment?.method==='efectivo';
        const stages=isCash?['Pedido','Preparación','Entrega y pago']:['Pedido','Pago','Preparación','Entrega'];
        const currentByStatus=isCash
            ?{pending:0,confirmed:1,preparing:1,ready:2,completed:3}
            :{pending:0,payment_pending:1,paid:2,confirmed:2,preparing:2,ready:3,completed:4};
        const current=currentByStatus[order.status]??0;
        return `<div class="storefront-order-progress" aria-label="Avance del pedido">${stages.map((label,index)=>{
            const state=current>=stages.length||index<current?'is-done':(index===current?'is-current':'');
            return `<div class="storefront-order-progress-step ${state}"><span class="storefront-order-progress-dot">${state==='is-done'?'✓':index+1}</span><strong>${label}</strong></div>`;
        }).join('')}</div>`;
    };
    const renderOrderDetail=order=>{
        currentCustomerOrderId=order.id;
        const detail=document.getElementById('storefrontOrderDetail');
        const fulfillment=order.fulfillment||{};
        const items=(order.items||[]).map(item=>`<div class="storefront-order-line"><div><strong>${escAccount(item.name)}</strong><span>${item.quantity} × ${money(item.price)}</span>${item.selection?`<span>${escAccount(item.selection)}</span>`:''}</div><b class="storefront-order-line-price">${money(item.subtotal)}</b></div>`).join('');
        const history=(order.timeline||[]).map(event=>`<div class="storefront-order-history-event"><strong>${escAccount(event.label)}</strong><time>${dateTime(event.at)}</time></div>`).join('');
        const payment=order.payment||{},invoice=order.invoice||{},invoiceData=invoice.data||{};
        const paymentSection=payment.method==='transferencia'?`<section class="storefront-order-section"><h3>Transferencia y comprobante</h3><div class="storefront-order-self-service">${payment.bank_instructions?`<p class="storefront-order-bank">${escAccount(payment.bank_instructions).replace(/\n/g,'<br>')}</p>`:'<p>Solicita los datos bancarios a la empresa antes de transferir.</p>'}${payment.proof_submitted?'<p class="storefront-order-form-status">✓ Tu comprobante ya fue recibido y está en revisión.</p>':payment.can_upload_proof?`<form id="storefrontOrderProofForm"><label>Sube una imagen o PDF<input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" required></label><button type="submit">Cargar comprobante</button><p class="storefront-order-form-status" id="storefrontOrderProofStatus"></p></form>`:'<p>Este pedido no permite cargar un comprobante en su estado actual.</p>'}</div></section>`:'';
        const wantsInvoice=!!invoice.requires_invoice;
        const invoiceSection=`<section class="storefront-order-section"><h3>Facturación</h3><form class="storefront-order-self-service" id="storefrontOrderInvoiceForm"><label>Comprobante de venta<select name="requires_invoice"><option value="0" ${!wantsInvoice?'selected':''}>Consumidor final</option><option value="1" ${wantsInvoice?'selected':''}>Factura con datos</option></select></label><div class="storefront-order-invoice-fields ${wantsInvoice?'is-open':''}" id="storefrontOrderInvoiceFields"><label>Tipo<select name="billing_type"><option value="cedula" ${invoiceData.billing_type==='cedula'?'selected':''}>Cédula</option><option value="ruc" ${invoiceData.billing_type==='ruc'?'selected':''}>RUC</option><option value="pasaporte" ${invoiceData.billing_type==='pasaporte'?'selected':''}>Pasaporte</option></select></label><label>Identificación<input name="billing_id" maxlength="20" value="${escAccount(invoiceData.billing_id||'')}"></label><label>Nombre o razón social<input name="billing_legal_name" maxlength="255" value="${escAccount(invoiceData.billing_legal_name||'')}"></label><label>Dirección<input name="billing_address" maxlength="500" value="${escAccount(invoiceData.address||'')}"></label><label>Correo<input name="billing_email" type="email" maxlength="255" value="${escAccount(invoiceData.email||'')}"></label></div><button type="submit" id="storefrontOrderInvoiceSubmit" ${invoice.decided?'hidden':''}>Guardar datos de facturación</button><p class="storefront-order-form-status" id="storefrontOrderInvoiceStatus">${invoice.decided?'✓ Ya guardaste esta preferencia. Cambia una opción arriba si quieres corregirla.':''}</p></form></section>`;
        document.getElementById('storefrontOrderDetailTitle').textContent='Pedido '+order.order_number;
        document.getElementById('storefrontOrderDetailSubtitle').textContent=`${order.created_at_label||''} · ${order.status_label}`;
        document.getElementById('storefrontOrderDetailBody').innerHTML=`
            ${renderOrderProgress(order)}
            <div class="storefront-order-next"><small>Qué está pasando ahora</small><strong>${escAccount(order.next_action?.title||order.status_label)}</strong><span>${escAccount(order.next_action?.description||'')}</span></div>
            <div class="storefront-order-snapshot"><div><small>Sucursal</small><strong>${escAccount(order.branch||'Por confirmar')}</strong></div><div><small>Entrega</small><strong>${escAccount(fulfillment.label||'Por confirmar')}</strong></div><div><small>Pago</small><strong>${escAccount(order.payment_method||'Por confirmar')}</strong></div><div><small>Total</small><strong>${money(order.total)}</strong></div></div>
            ${paymentSection}${invoiceSection}
            ${fulfillment.address?`<section class="storefront-order-section"><h3>Entrega y sucursal</h3><div class="storefront-order-line"><div><strong>${escAccount(fulfillment.address)}</strong>${fulfillment.reference?`<span>Referencia: ${escAccount(fulfillment.reference)}</span>`:''}${order.branch_address?`<span>${escAccount(order.branch_address)}</span>`:''}</div></div></section>`:''}
            <section class="storefront-order-section"><h3>Productos del pedido · ${(order.items||[]).length}</h3>${items||'<div class="storefront-order-line"><span>Sin productos registrados.</span></div>'}<div class="storefront-order-total"><span>Total</span><strong>${money(order.total)}</strong></div></section>
            <section class="storefront-order-section"><h3>Historial del pedido</h3><div class="storefront-order-history">${history}</div></section>`;
        detail.classList.add('is-open');detail.setAttribute('aria-hidden','false');
        const invoiceForm=document.getElementById('storefrontOrderInvoiceForm');
        const invoiceSubmit=document.getElementById('storefrontOrderInvoiceSubmit');
        const rearmInvoiceSubmit=()=>{if(invoiceSubmit)invoiceSubmit.hidden=false;};
        invoiceForm?.elements.requires_invoice.addEventListener('change',event=>{document.getElementById('storefrontOrderInvoiceFields')?.classList.toggle('is-open',event.target.value==='1');rearmInvoiceSubmit();});
        invoiceForm?.querySelectorAll('input').forEach(input=>input.addEventListener('input',rearmInvoiceSubmit));
        invoiceForm?.addEventListener('submit',async event=>{event.preventDefault();const status=document.getElementById('storefrontOrderInvoiceStatus');status.textContent='Guardando…';const form=new FormData(invoiceForm),requiresInvoice=form.get('requires_invoice')==='1';const payload={requires_invoice:requiresInvoice,billing_type:form.get('billing_type'),billing_id:form.get('billing_id'),billing_legal_name:form.get('billing_legal_name'),billing_address:form.get('billing_address'),billing_email:form.get('billing_email')};try{const response=await fetch(accountUrl(`/cuenta/pedidos/${order.id}/facturacion`),{method:'PUT',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.message||'No se pudieron guardar los datos.');status.textContent='✓ '+result.message;if(invoiceSubmit)invoiceSubmit.hidden=true;await loadOrders();}catch(error){status.textContent=error.message||'No se pudieron guardar los datos.';}});
        document.getElementById('storefrontOrderProofForm')?.addEventListener('submit',async event=>{event.preventDefault();const form=event.currentTarget,status=document.getElementById('storefrontOrderProofStatus'),file=form.elements.proof.files[0];if(!file)return;status.textContent='Cargando…';const body=new FormData();body.append('proof',file);try{const response=await fetch(accountUrl(`/cuenta/pedidos/${order.id}/comprobante`),{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf},body});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.message||'No se pudo cargar el comprobante.');status.textContent='✓ '+result.message;await loadOrders();}catch(error){status.textContent=error.message||'No se pudo cargar el comprobante.';}});
    };
    const closeOrderDetail=()=>{const detail=document.getElementById('storefrontOrderDetail');detail.classList.remove('is-open');detail.setAttribute('aria-hidden','true');currentCustomerOrderId=null;};
    document.getElementById('storefrontOrderDetailClose').addEventListener('click',closeOrderDetail);
    document.getElementById('storefrontOrderDetail').addEventListener('click',event=>{if(event.target.id==='storefrontOrderDetail')closeOrderDetail();});
    const renderOrders=orders=>{
        customerOrders=orders;
        const list=document.getElementById('storefrontOrdersList');
        if(!orders.length){list.innerHTML='<p class="storefront-account-note">Todavía no tienes pedidos con esta cuenta.</p>';return;}
        list.innerHTML=orders.map(order=>`<button type="button" class="storefront-order-card" data-customer-order="${order.id}"><div class="storefront-order-card-head"><strong>${escAccount(order.order_number)}</strong><span class="storefront-order-status" data-status-kind="${statusKind(order.status)}">${escAccount(order.status_label)}</span></div><time>${escAccount(order.created_at_label||'')}${order.branch?' · '+escAccount(order.branch):''}</time><div class="storefront-order-card-foot"><span>Total</span><b>${money(order.total)}</b></div><span class="storefront-order-card-hint">Ver detalle y seguimiento →</span></button>`).join('');
        list.querySelectorAll('[data-customer-order]').forEach(button=>button.addEventListener('click',()=>{const order=customerOrders.find(item=>String(item.id)===button.dataset.customerOrder);if(order)renderOrderDetail(order);}));
        if(currentCustomerOrderId){const updated=customerOrders.find(item=>String(item.id)===String(currentCustomerOrderId));if(updated)renderOrderDetail(updated);}
    };
    const loadOrders=()=>fetch(accountUrl('/cuenta/pedidos'),{headers:{'Accept':'application/json'},cache:'no-store'}).then(r=>r.json()).then(data=>{if(data.ok)renderOrders(data.orders);});
    window.loadStorefrontOrders=loadOrders;
    window.setInterval(()=>{if(!document.hidden&&(account.classList.contains('is-open')||document.getElementById('storefrontOrderDetail').classList.contains('is-open')))loadOrders();},15000);
     const showAuthed=customer=>{
         guestBox.style.display='none';authedBox.style.display='block';
         if(drawerLogout)drawerLogout.style.display='flex';
         document.getElementById('storefrontAccountName').textContent=customer.name||'';
         document.getElementById('storefrontAccountPhoneLabel').textContent=customer.phone||'';
         fillCustomerProfile(customer);
         loadOrders();
     };
     const showGuest=()=>{guestBox.style.display='block';authedBox.style.display='none';if(drawerLogout)drawerLogout.style.display='none';};
     fetch(accountUrl('/cuenta/yo'),{headers:{'Accept':'application/json'}}).then(r=>r.json()).then(data=>{if(data.ok&&data.customer)showAuthed(data.customer);});
     loginForm.addEventListener('submit',event=>{
         event.preventDefault();clearError();
         fetch(accountUrl('/cuenta/entrar'),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({phone:document.getElementById('loginPhone').value.trim(),password:document.getElementById('loginPassword').value})})
             .then(r=>r.json()).then(data=>{if(data.ok){showAuthed(data.customer);}else{showError(data.message||'No pudimos iniciar sesión.');}});
     });
     registerForm.addEventListener('submit',event=>{
         event.preventDefault();clearError();
         const password=document.getElementById('registerPassword').value,confirm=document.getElementById('registerPasswordConfirm').value;
         if(password!==confirm){showError('Las contraseñas no coinciden.');return;}
         fetch(accountUrl('/cuenta/registro'),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({name:document.getElementById('registerName').value.trim(),phone:document.getElementById('registerPhone').value.trim(),password,password_confirmation:confirm})})
             .then(r=>r.json()).then(data=>{if(data.ok){showAuthed(data.customer);}else{showError(data.message||'No pudimos crear tu cuenta.');}});
     });
     const logout=()=>{
         fetch(accountUrl('/cuenta/salir'),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}})
             .then(()=>{loginForm.reset();registerForm.reset();showGuest();toggleNavOverlay(account,false);});
     };
     document.getElementById('storefrontLogoutBtn').addEventListener('click',logout);
     drawerLogout?.addEventListener('click',()=>{setDrawer(false);logout();});
 })();
 // El bot manda este link con ?cuenta=pedidos cuando "modo ecommerce" está
 // activo (ver WhatsappService::sendStorefrontRedirectLink) para que el
 // cliente llegue directo a ver el estado de su pedido, no a la portada.
 if(new URLSearchParams(location.search).get('cuenta')==='pedidos'){toggleNavOverlay(account,true);window.loadStorefrontOrders?.();}
 document.querySelectorAll('[data-preview-category]').forEach(button=>button.addEventListener('click',()=>{pendingCategory=button.dataset.previewCategory;openCategory(pendingCategory);}));
 document.getElementById('storefrontLocationClose').addEventListener('click',()=>locationBox.classList.remove('is-open'));
 const selectMode=(mode,save=true)=>{document.querySelectorAll('[data-storefront-mode]').forEach(x=>x.classList.toggle('is-active',x.dataset.storefrontMode===mode));window.storefrontOrder.service_type=mode;delivery.style.display=mode==='delivery'?'block':'none';document.getElementById('pickupFields').style.display=mode==='pickup'?'block':'none';if(save){window.storefrontOrder.confirmed=false;persistOrder();}};document.querySelectorAll('[data-storefront-mode]').forEach(btn=>btn.addEventListener('click',()=>selectMode(btn.dataset.storefrontMode)));
 document.querySelectorAll('[data-branch-option]').forEach(button=>button.addEventListener('click',()=>{const branchId=button.dataset.branchOption;document.getElementById('storefrontBranch').value=branchId;document.getElementById('bulkBranch').value=branchId;window.storefrontOrder.confirmed=false;window.storefrontOrder.branch_id=Number(branchId)||null;persistOrder();document.querySelectorAll('[data-branch-option]').forEach(item=>item.classList.toggle('is-active',item===button));}));document.getElementById('storefrontBranchSearch').addEventListener('input',event=>{const query=event.target.value.trim().toLowerCase();document.querySelectorAll('[data-branch-option]').forEach(item=>item.style.display=item.dataset.branchSearch.includes(query)?'grid':'none');});
 document.getElementById('storefrontAddress').addEventListener('input',event=>{window.storefrontOrder.confirmed=false;window.storefrontOrder.address=event.target.value;persistOrder();});
 document.getElementById('storefrontReference').addEventListener('input',event=>{window.storefrontOrder.confirmed=false;window.storefrontOrder.reference=event.target.value;persistOrder();});
 window.openStorefrontOrderMode=()=>{selectMode(window.storefrontOrder.service_type||'pickup');if(locationBox.parentElement!==document.body)document.body.appendChild(locationBox);locationBox.classList.add('is-open');};
 window.addEventListener('storefront:start-order',window.openStorefrontOrderMode);
 document.getElementById('storefrontContinue').addEventListener('click',()=>{const branch=document.getElementById('storefrontBranch').value,address=document.getElementById('storefrontAddress').value.trim();if(!window.storefrontOrder.service_type||!branch||(window.storefrontOrder.service_type==='delivery'&&!address)){error.textContent='Completa la modalidad, sucursal y dirección de entrega.';error.style.display='block';return;}error.style.display='none';window.storefrontOrder.confirmed=true;window.storefrontOrder.branch_id=Number(branch)||null;window.storefrontOrder.address=address;window.storefrontOrder.reference=document.getElementById('storefrontReference').value.trim();persistOrder();document.getElementById('bulkBranch').value=branch;const branchLabel=document.getElementById('storefrontBranch').selectedOptions[0]?.textContent||'',title=window.storefrontOrder.service_type==='delivery'?'Enviar a':'Retirar en',detail=window.storefrontOrder.service_type==='delivery'?address:branchLabel;document.getElementById('storefrontFulfillmentTitle').textContent=title;document.getElementById('storefrontFulfillmentAddress').textContent=detail;document.getElementById('storefrontCartDeliveryTitle').textContent=title;document.getElementById('storefrontCartDeliveryAddress').textContent=detail;document.getElementById('bulkOrderApp').classList.add('is-order-started');window.syncStorefrontCustomizerActions?.();locationBox.classList.remove('is-open');});
 document.getElementById('storefrontBack').addEventListener('click',()=>{document.getElementById('storefrontApp').style.display='none';gateway.classList.remove('is-browsing');gateway.style.display='block';});
 document.getElementById('storefrontGeolocate').addEventListener('click',()=>{if(!navigator.geolocation){error.textContent='Tu navegador no permite obtener la ubicación.';error.style.display='block';return;}navigator.geolocation.getCurrentPosition(pos=>{window.storefrontOrder.latitude=pos.coords.latitude;window.storefrontOrder.longitude=pos.coords.longitude;window.storefrontOrder.address='Ubicación actual confirmada';document.getElementById('storefrontAddress').value=window.storefrontOrder.address;persistOrder();error.style.display='none';if(window.google?.maps){new google.maps.Geocoder().geocode({location:{lat:pos.coords.latitude,lng:pos.coords.longitude}},(results,status)=>{if(status==='OK'&&results[0]){window.storefrontOrder.address=results[0].formatted_address;document.getElementById('storefrontAddress').value=window.storefrontOrder.address;persistOrder();}});}},()=>{error.textContent='No pudimos obtener tu ubicación. Escribe la dirección.';error.style.display='block';},{enableHighAccuracy:true,timeout:10000});});
 const restoredBranch=String(window.storefrontOrder.branch_id||'');
 if(restoredBranch&&document.querySelector(`#storefrontBranch option[value="${restoredBranch}"]`)){
     document.getElementById('storefrontBranch').value=restoredBranch;
     document.getElementById('bulkBranch').value=restoredBranch;
     document.querySelectorAll('[data-branch-option]').forEach(item=>item.classList.toggle('is-active',item.dataset.branchOption===restoredBranch));
 }
 document.getElementById('storefrontAddress').value=window.storefrontOrder.address||'';
 document.getElementById('storefrontReference').value=window.storefrontOrder.reference||'';
 if(window.storefrontOrder.confirmed&&window.storefrontOrder.service_type){
     selectMode(window.storefrontOrder.service_type,false);
     const branchLabel=document.getElementById('storefrontBranch').selectedOptions[0]?.textContent||'';
     const title=window.storefrontOrder.service_type==='delivery'?'Enviar a':'Retirar en';
     const detail=window.storefrontOrder.service_type==='delivery'?window.storefrontOrder.address:branchLabel;
     document.getElementById('storefrontFulfillmentTitle').textContent=title;
     document.getElementById('storefrontFulfillmentAddress').textContent=detail;
     document.getElementById('storefrontCartDeliveryTitle').textContent=title;
     document.getElementById('storefrontCartDeliveryAddress').textContent=detail;
     document.getElementById('bulkOrderApp').classList.add('is-order-started');
 }
})();
</script>
@if(filled($settings->google_maps_api_key))
<script>window.initStorefrontMaps=function(){const input=document.getElementById('storefrontAddress');if(window.google?.maps?.places&&input){const autocomplete=new google.maps.places.Autocomplete(input,{fields:['formatted_address','geometry']});autocomplete.addListener('place_changed',()=>{const p=autocomplete.getPlace();if(p.formatted_address)input.value=p.formatted_address;if(p.geometry?.location){window.storefrontOrder.latitude=p.geometry.location.lat();window.storefrontOrder.longitude=p.geometry.location.lng();}});}};</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($settings->google_maps_api_key) }}&libraries=places&loading=async&callback=initStorefrontMaps"></script>
@endif
</body>
</html>
