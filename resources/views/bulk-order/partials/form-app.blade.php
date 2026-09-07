@php
    $isAgent = ($mode ?? 'public') === 'agent';
    $isKiosk = ($mode ?? 'public') === 'kiosk';
    $contactName = $contactName ?? 'Cliente';
    $headerTitle = $headerTitle ?? 'Pedido en línea';
    $headerSubtitle = $headerSubtitle ?? (
        $isAgent
            ? 'Selecciona un cliente, agrega productos y registra el pedido desde el panel.'
            : ($isKiosk
                ? 'Elige tus productos. Al final te damos tu número de pedido para pagar en caja.'
                : "Hola, {$contactName}. Elige tus favoritos y confirma en menos de un minuto.")
    );
    $successWhatsappHint = $successWhatsappHint ?? 'Revisa WhatsApp: recibirás el PDF y los botones para confirmar, modificar o cancelar.';
@endphp

<style>
    .bulk-order-app {
        --wa: #e85d04;
        --wa-dark: #9d2e00;
        --lime: #ffd166;
        --bg: {{ $isAgent ? '#f8fafc' : '#fff8f2' }};
        --card: #fff;
        --muted: #667781;
        --border: #e9edef;
    }
    .bulk-order-app * { box-sizing: border-box; }
    /* State select used by JavaScript; customers navigate with visual chips. */
    .bulk-order-app .visually-hidden { display: none !important; }
    .bulk-order-app {
        font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        color: #111;
    }
    @if(!$isAgent)
    .bulk-order-app { min-height: 100vh; padding-bottom: 120px; }
    @endif
    .bulk-order-header {
        background: radial-gradient(circle at 88% 8%, rgba(255,209,102,.38), transparent 25%), linear-gradient(135deg, #6d1b00, var(--wa-dark) 52%, var(--wa));
        color: #fff;
        padding: 16px 18px 20px;
        border-radius: {{ $isAgent ? '12px' : '0' }};
        margin-bottom: {{ $isAgent ? '14px' : '0' }};
    }
    .bulk-order-header h1 { margin: 0 0 4px; font-size: 1.38rem; font-weight: 900; letter-spacing: -.04em; }
    .bulk-order-header p { margin: 0; font-size: .85rem; opacity: .9; }
    .bulk-order-wrap { max-width: 760px; margin: 0 auto; padding: {{ $isAgent ? '0' : '14px' }}; }
    .bulk-order-panel {
        background: var(--card);
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
        border: {{ $isAgent ? '1px solid #e5e7eb' : 'none' }};
    }
    .bulk-order-panel h2 {
        margin: 0 0 12px;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--muted);
    }
    .bulk-order-filters { display: grid; gap: 10px; }
    .bulk-order-filters input, .bulk-order-filters select, .bulk-order-app textarea {
        width: 100%;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 10px 12px;
        font: inherit;
    }
    .bulk-order-product-list { display: grid; gap: 8px; max-height: 420px; overflow: auto; }
    .bulk-order-product-list { grid-template-columns: repeat(2, minmax(0, 1fr)); max-height: none; overflow: visible; }
    .bulk-order-product-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: 9px;
        align-items: stretch;
        padding: 0;
        border: 1px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
        background:#fff;
        box-shadow:0 6px 18px rgba(94,30,0,.06);
        cursor:pointer;
    }
    .bulk-order-product-row:focus-visible { outline:3px solid rgba(255,101,11,.35); outline-offset:3px; }
    .bulk-order-product-row strong { display: block; font-size: .92rem; margin-bottom: 4px; }
    .bulk-order-product-row .bulk-order-product-desc,
    .bulk-order-product-row .bulk-order-product-meta { display:none; }
    .bulk-order-product-content > strong {
        display:-webkit-box;
        overflow:hidden;
        line-height:1.28;
        -webkit-box-orient:vertical;
        -webkit-line-clamp:3;
    }
    .bulk-order-product-row small { color: var(--muted); display: block; line-height: 1.4; }
    .bulk-order-product-desc {
        font-size: .82rem;
        color: #374151;
        margin: 4px 0 6px;
        line-height: 1.45;
    }
    .bulk-order-product-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 6px;
    }
    .bulk-order-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: .72rem;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 999px;
        background: #ecfdf5;
        color: #047857;
    }
    .bulk-order-tag.price-tag {
        background: #f0f2f5;
        color: #111;
    }
    .bulk-order-product-media { height:132px; background:linear-gradient(145deg,#ffe4c2,#ffd166); overflow:hidden; }
    button.bulk-order-product-media { width:100%; border:0; padding:0; cursor:pointer; position:relative; text-align:inherit; }
    button.bulk-order-product-media::after { content:'Ver detalles'; position:absolute; right:9px; bottom:9px; padding:5px 8px; border-radius:999px; background:rgba(0,0,0,.68); color:#fff; font-size:.68rem; font-weight:800; opacity:0; transform:translateY(4px); transition:.18s ease; }
    button.bulk-order-product-media:hover::after, button.bulk-order-product-media:focus-visible::after { opacity:1; transform:translateY(0); }
    .bulk-order-product-media img { width:100%;height:100%;object-fit:cover;display:block;transition:transform .25s ease; }
    .bulk-order-product-row:hover .bulk-order-product-media img { transform:scale(1.04); }
    .bulk-order-product-media .fallback { height:100%;display:grid;place-items:center;font-size:2.8rem; }
    .bulk-order-product-content { padding:0 12px 12px; }
    .bulk-order-product-actions { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:10px; }
    .bulk-order-product-price {
        display:block;
        margin-top:auto;
        padding-top:9px;
        color:#858585;
        font-size:.86rem;
        font-weight:650;
        line-height:1.2;
    }
    .bulk-order-product-price.is-promo {
        color:#b54708;
        font-size:.94rem;
        font-weight:900;
    }
    .bulk-order-btn {
        border: none;
        border-radius: 8px;
        padding: 8px 12px;
        font: inherit;
        font-weight: 600;
        cursor: pointer;
    }
    .bulk-order-btn-primary { background: var(--wa); color: #fff; box-shadow:0 5px 12px rgba(232,93,4,.25); }
    .bulk-order-btn-primary:disabled { opacity: .5; cursor: not-allowed; }
    .bulk-order-btn-ghost { background: #f0f2f5; color: #111; }
    .bulk-order-btn-danger { background: #fee2e2; color: #991b1b; }
    .bulk-order-cart-item {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 11px;
        margin-bottom: 8px;
        background:#fff;
    }
    .bulk-order-cart-main { display:grid; grid-template-columns:58px minmax(0,1fr) auto; gap:10px; align-items:center; }
    .bulk-order-cart-thumb { width:58px; height:58px; overflow:hidden; border-radius:10px; background:#fff0e6; display:grid; place-items:center; }
    .bulk-order-cart-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
    .bulk-order-cart-thumb .fallback { font-size:1.55rem; }
    .bulk-order-cart-info { min-width:0; }
    .bulk-order-cart-info strong { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .bulk-order-cart-info .bulk-order-line-total { display:block; margin-top:5px; }
    .bulk-order-cart-item-head { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 0; align-items: flex-start; }
    .bulk-order-cart-item-head strong { font-size: .95rem; line-height: 1.3; }
    .bulk-order-cart-meta {
        font-size: .82rem;
        color: #374151;
        line-height: 1.45;
        margin-bottom: 10px;
    }
    .bulk-order-cart-meta .measurements {
        display: inline-block;
        margin-top: 4px;
        font-size: .78rem;
        font-weight: 600;
        color: #047857;
        background: #ecfdf5;
        padding: 3px 8px;
        border-radius: 999px;
    }
    .bulk-order-cart-foot {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .bulk-order-cart-summary { margin-top:4px; color:#52605a; font-size:.76rem; line-height:1.35; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .bulk-order-cart-summary b { color:#187a46; font-weight:750; }
    .bulk-order-cart-summary .bulk-order-extra-count { color:#8a3b00; }
    .bulk-order-cart-note-toggle { border:0; padding:0; background:transparent; color:var(--wa-dark); font:inherit; font-size:.76rem; font-weight:700; cursor:pointer; }
    .bulk-order-cart-edit { border:0; padding:0; background:transparent; color:#187a46; font:inherit; font-size:.76rem; font-weight:800; cursor:pointer; }
    .bulk-order-cart-note-editor { display:none; margin-top:9px; }
    .bulk-order-cart-note-editor.is-open { display:block; }
    .bulk-order-cart-note-editor textarea { min-height:48px; padding:8px 10px; font-size:.82rem; }
    .bulk-order-line-total {
        font-weight: 700;
        color: var(--wa-dark);
        font-size: .95rem;
        white-space: nowrap;
    }
    @media (max-width: 520px) {
        .bulk-order-cart-item { padding:10px; }
        .bulk-order-cart-main { grid-template-columns:58px minmax(0, 1fr); align-items:start; }
        .bulk-order-cart-thumb { grid-row:span 2; }
        .bulk-order-cart-info { padding-top:2px; }
        .bulk-order-cart-info strong { font-size:.91rem; }
        .bulk-order-cart-main .bulk-order-qty-stepper,
        .bulk-order-cart-main > .bulk-order-qty-remove { grid-column:2; justify-self:end; }
        .bulk-order-cart-main > .bulk-order-qty-remove { margin-top:2px; }
        .bulk-order-cart-item > div[style] { margin-left:68px !important; margin-top:6px !important; }
        .bulk-order-cart-note-editor { margin-left:68px; }
    }
    .bulk-order-qty-stepper {
        display: inline-flex;
        align-items: center;
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
    }
    .bulk-order-qty-stepper button {
        width: 38px;
        height: 38px;
        border: none;
        background: #f0f2f5;
        color: #111;
        font-size: 1.15rem;
        font-weight: 700;
        cursor: pointer;
        line-height: 1;
    }
    .bulk-order-qty-stepper button:hover:not(:disabled) { background: #e2e8f0; }
    .bulk-order-qty-stepper button:disabled { opacity: .4; cursor: not-allowed; }
    .bulk-order-qty-stepper .bulk-order-qty-remove { color:#c0392b; background:#fff3f1; }
    .bulk-order-qty-remove { width:38px; height:38px; border:0; border-radius:10px; display:grid; place-items:center; color:#c0392b; background:#fff3f1; cursor:pointer; }
    .bulk-order-qty-remove svg { width:16px; height:16px; fill:currentColor; pointer-events:none; }
    .bulk-order-qty-stepper input {
        width: 46px;
        height: 38px;
        border: none;
        border-left: 1px solid var(--border);
        border-right: 1px solid var(--border);
        text-align: center;
        font: inherit;
        font-weight: 700;
        padding: 0;
        -moz-appearance: textfield;
    }
    .bulk-order-qty-stepper input::-webkit-outer-spin-button,
    .bulk-order-qty-stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .bulk-order-qty-fixed {
        display: inline-flex;
        align-items: center;
        height: 38px;
        padding: 0 12px;
        border-radius: 10px;
        background: #f0f2f5;
        font-size: .85rem;
        font-weight: 600;
        color: var(--muted);
    }
    .bulk-order-qty-row { display: flex; gap: 8px; align-items: center; }
    .bulk-order-qty-row input[type=number] { width: 72px; }
    .bulk-order-cart-empty { color: var(--muted); font-size: .9rem; text-align: center; padding: 20px 0; }
    .bulk-order-footer {
        position: {{ $isAgent ? 'sticky' : 'fixed' }};
        left: 0; right: 0; bottom: 0;
        background: #fff;
        border-top: 1px solid var(--border);
        padding: 12px 14px calc(12px + env(safe-area-inset-bottom));
        box-shadow: 0 -4px 20px rgba(0,0,0,.08);
        border-radius: {{ $isAgent ? '12px' : '0' }};
        margin-top: {{ $isAgent ? '12px' : '0' }};
        z-index: 5;
    }
    .bulk-order-footer-inner { max-width: 720px; margin: 0 auto; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .bulk-order-total { flex: 1; font-size: .95rem; min-width: 140px; }
    .bulk-order-total strong { display: block; font-size: 1.2rem; color: var(--wa-dark); }
    .bulk-order-toast {
        position: fixed; top: 16px; left: 50%; transform: translateX(-50%);
        background: #111; color: #fff; padding: 10px 16px; border-radius: 8px;
        font-size: .85rem; z-index: 20; display: none;
    }
    .bulk-order-menu-layout { display:block; margin-top:12px; }
    .bulk-order-category-chips { display:flex; gap:8px; overflow:auto; padding:2px 0 4px; scrollbar-width:none; }
    .bulk-order-category-chips button { border:1px solid #fed7aa; background:#fff7ed; color:#9a3412; padding:8px 11px; border-radius:999px; white-space:nowrap; font:inherit; font-size:.78rem; font-weight:700; cursor:pointer; }
    .bulk-order-category-chips button.is-active { background:var(--wa); color:#fff; border-color:var(--wa); }
    .bulk-order-modal { position:fixed;inset:0;z-index:40;background:rgba(42,15,0,.52);display:none;align-items:end;justify-content:center;backdrop-filter:blur(3px); }
    .bulk-order-modal.is-open { display:flex; }
    .bulk-order-modal-card {
        display:flex;
        flex-direction:column;
        width:min(100%,560px);
        max-height:90vh;
        overflow:hidden;
        background:#fff;
        border-radius:24px 24px 0 0;
        animation:bulk-order-sheet .24s ease-out;
    }
    @keyframes bulk-order-sheet { from { transform:translateY(100%); } to { transform:translateY(0); } }
    .bulk-order-modal-head { display:flex;justify-content:space-between;gap:12px;align-items:flex-start;padding:18px 18px 14px; }
    .bulk-order-modal-head h3 { margin:0;font-size:1.22rem; }
    .bulk-order-modal-options {
        overflow:auto;
        padding:0 18px 16px;
        overscroll-behavior:contain;
    }
    .bulk-order-modal-hero {
        margin:0 -18px 16px;
        display:grid;
        place-items:center;
        background:#f7f2ed;
        border-bottom:1px solid #eee5dc;
    }
    .bulk-order-modal-hero img {
        display:block;
        width:100%;
        height:auto;
        max-height:320px;
        object-fit:contain;
    }
    .bulk-order-modal-hero .fallback { height:150px; display:grid; place-items:center; font-size:4rem; background:linear-gradient(145deg,#ffe4c2,#ffd166); }
    .bulk-order-modal-description { margin:8px 0 0; color:#555; line-height:1.48; font-size:.9rem; }
    .bulk-order-close { border:0;background:#f3f4f6;border-radius:50%;width:34px;height:34px;font-size:1.2rem;cursor:pointer; }
    .bulk-order-choice { display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 0;border-bottom:1px solid #f1f5f9;font-size:.9rem; }
    .bulk-order-choice label { flex:1;cursor:pointer; }
    .bulk-order-choice small { color:var(--muted); }
    .bulk-order-modal-footer {
        flex:0 0 auto;
        display:flex;
        gap:12px;
        align-items:center;
        padding:12px 18px calc(14px + env(safe-area-inset-bottom));
        border-top:1px solid #ececec;
        background:#fff;
        box-shadow:0 -5px 18px rgba(0,0,0,.06);
    }
    .bulk-order-modal-footer::before {
        content:'Listo para tu pedido';
        flex:1;
        color:var(--muted);
        font-size:.8rem;
        font-weight:700;
    }
    .bulk-order-modal-add {
        flex:1.35;
        width:auto;
        margin:0;
        padding:14px;
        border-radius:13px;
        font-size:.9rem;
    }
    @media (min-width:640px) { .bulk-order-product-list { grid-template-columns:repeat(3,minmax(0,1fr)); } .bulk-order-product-media { height:145px; } .bulk-order-modal { align-items:center; } .bulk-order-modal-card { border-radius:24px; } }
    @media (max-width:380px) { .bulk-order-product-list { gap:7px; } .bulk-order-product-content { padding:0 9px 10px; } .bulk-order-product-row strong { font-size:.84rem; } }
    .bulk-order-success {
        display: none;
        text-align: center;
        padding: 40px 20px;
    }
    .bulk-order-success .icon { font-size: 3rem; margin-bottom: 12px; }
    .bulk-order-contact-picker { position: relative; }
    .bulk-order-contact-intro { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:-2px 0 10px; }
    .bulk-order-contact-intro p { margin:0; color:#64748b; font-size:.82rem; line-height:1.35; }
    .bulk-order-contact-add { flex:0 0 auto; display:inline-flex; align-items:center; gap:6px; border:1px solid #fdba74; border-radius:9px; padding:8px 10px; background:#fff7ed; color:#9a3412; cursor:pointer; font:inherit; font-size:.78rem; font-weight:800; }
    .bulk-order-contact-add:hover { background:#ffedd5; }
    .bulk-order-contact-search { position:relative; }
    .bulk-order-contact-search i { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; }
    .bulk-order-contact-search input { padding-left:38px; border-color:#cbd5e1; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.04); }
    .bulk-order-contact-search input:focus { outline:none; border-color:var(--wa); box-shadow:0 0 0 3px rgba(232,93,4,.12); }
    .bulk-order-contact-selected {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: 10px 12px; border: 1px solid #bbf7d0; background: #f0fdf4; border-radius: 10px;
    }
    .bulk-order-contact-selected strong { display: block; color: #14532d; }
    .bulk-order-contact-selected small { color: #166534; }
    .bulk-order-contact-results {
        position: absolute; left: 0; right: 0; top: calc(100% + 4px);
        background: #fff; border: 1px solid var(--border); border-radius: 10px;
        max-height: 220px; overflow: auto; z-index: 10; display: none;
        box-shadow: 0 8px 24px rgba(0,0,0,.12);
    }
    .bulk-order-contact-results button {
        display: block; width: 100%; text-align: left; border: none; background: #fff;
        padding: 10px 12px; cursor: pointer; font: inherit;
    }
    .bulk-order-contact-results button:hover { background: #f0f2f5; }
    .bulk-order-contact-empty { padding:13px 12px; color:#64748b; font-size:.83rem; line-height:1.4; }
    .bulk-order-contact-empty button { display:inline; width:auto; margin-top:8px; padding:6px 8px; border-radius:7px; color:#9a3412; background:#fff7ed; font-size:.75rem; font-weight:800; }
    .bulk-order-contact-modal { z-index:60; align-items:center; padding:16px; }
    .bulk-order-contact-modal-card { width:min(100%,420px); padding:0; border-radius:18px; overflow:hidden; background:#fff; box-shadow:0 22px 70px rgba(41,17,5,.36); }
    .bulk-order-contact-modal-head { padding:18px 18px 14px; color:#fff; background:linear-gradient(120deg,#942d08,#ef6a18); }
    .bulk-order-contact-modal-head h3 { margin:0; font-size:1.08rem; }
    .bulk-order-contact-modal-head p { margin:5px 0 0; font-size:.81rem; opacity:.92; }
    .bulk-order-contact-form { display:grid; gap:12px; padding:18px; }
    .bulk-order-contact-form label { display:grid; gap:5px; color:#334155; font-size:.78rem; font-weight:800; }
    .bulk-order-contact-form input, .bulk-order-contact-form textarea, .bulk-order-contact-form select { width:100%; border:1px solid #cbd5e1; border-radius:9px; padding:10px 11px; background:#fff; font:inherit; font-size:.9rem; }
    .bulk-order-contact-form input:focus, .bulk-order-contact-form textarea:focus, .bulk-order-contact-form select:focus { outline:none; border-color:var(--wa); box-shadow:0 0 0 3px rgba(232,93,4,.12); }
    .bulk-order-contact-form-actions { display:flex; justify-content:flex-end; gap:8px; padding:0 18px 18px; }
    .bulk-order-invoice-toggle { display:flex; align-items:center; gap:10px; padding:11px 12px; border:1px solid #fed7aa; border-radius:10px; background:#fff7ed; color:#7c2d12; cursor:pointer; font-size:.83rem; font-weight:800; }
    .bulk-order-invoice-toggle input { width:17px; height:17px; accent-color:var(--wa); }
    .bulk-order-invoice-fields { display:none; gap:12px; padding:13px; border:1px solid #fde68a; border-radius:10px; background:#fffbeb; }
    .bulk-order-invoice-fields.is-open { display:grid; }
    .bulk-order-invoice-fields > strong { color:#92400e; font-size:.8rem; }
    .bulk-order-form-disabled { position:relative; opacity:1; pointer-events:none; }
    .bulk-order-form-disabled::before {
        content:'Selecciona un cliente para habilitar el menú';
        position:absolute;
        top:72px;
        left:50%;
        z-index:12;
        padding:10px 16px;
        border:1px solid #fed7aa;
        border-radius:999px;
        background:rgba(255,247,237,.96);
        color:#9a3412;
        box-shadow:0 9px 24px rgba(124,45,18,.14);
        font-size:.78rem;
        font-weight:850;
        transform:translateX(-50%);
        white-space:nowrap;
    }
    .bulk-order-form-disabled > * { opacity:.58; filter:saturate(.6); }
    .bulk-order-notify-row {
        display: flex; align-items: center; gap: 8px; font-size: .85rem; color: var(--muted);
        width: 100%;
    }
    .bulk-order-back-link {
        display: inline-flex; align-items: center; gap: 6px;
        color: #fff; opacity: .9; text-decoration: none; font-size: .82rem; margin-bottom: 8px;
    }
    .bulk-order-back-link:hover { opacity: 1; color: #fff; }
    .bulk-order-header-nav { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px; }
    .bulk-order-header-nav .bulk-order-back-link { margin:0; }
    .bulk-order-touch-link {
        display:inline-flex;
        align-items:center;
        gap:7px;
        padding:8px 11px;
        border:1px solid rgba(255,255,255,.34);
        border-radius:9px;
        background:rgba(255,255,255,.12);
        color:#fff;
        text-decoration:none;
        font-size:.76rem;
        font-weight:800;
        backdrop-filter:blur(5px);
    }
    .bulk-order-touch-link:hover { background:rgba(255,255,255,.2); color:#fff; }
    .bulk-order-btn.is-loading {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-width: 148px;
    }
    .bulk-order-btn-spinner,
    .bulk-order-submit-spinner {
        width: 18px;
        height: 18px;
        border: 2px solid rgba(255,255,255,.35);
        border-top-color: #fff;
        border-radius: 50%;
        animation: bulk-order-spin .7s linear infinite;
        flex-shrink: 0;
    }
    .bulk-order-submit-spinner {
        width: 36px;
        height: 36px;
        border-width: 3px;
        border-color: rgba(18, 140, 126, .2);
        border-top-color: var(--wa);
        margin: 0 auto 14px;
    }
    @keyframes bulk-order-spin {
        to { transform: rotate(360deg); }
    }
    .bulk-order-submit-overlay {
        position: fixed;
        inset: 0;
        z-index: 30;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(17, 24, 39, .45);
        backdrop-filter: blur(2px);
    }
    .bulk-order-submit-overlay.is-visible {
        display: flex;
    }
    .bulk-order-submit-overlay-card {
        width: min(100%, 340px);
        background: #fff;
        border-radius: 14px;
        padding: 24px 20px;
        text-align: center;
        box-shadow: 0 12px 40px rgba(0,0,0,.18);
    }
    .bulk-order-submit-overlay-card strong {
        display: block;
        font-size: 1rem;
        margin-bottom: 8px;
        color: var(--wa-dark);
    }
    .bulk-order-submit-overlay-card p {
        margin: 0;
        font-size: .88rem;
        line-height: 1.45;
        color: var(--muted);
    }

    /* Public storefront: fast-food self-service kiosk experience. */
    .bulk-order-app[data-mode="public"] {
        --wa: #ff650b;
        --wa-dark: #9e3100;
        --bg: #fff7ef;
        background: var(--bg);
    }
    .bulk-order-app[data-mode="public"] .bulk-order-header {
        min-height: 102px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: linear-gradient(112deg, #4b1d0d 0%, #7f2b08 52%, #e95308 52%, #ff650b 100%);
        border-bottom: 5px solid #ffd071;
        padding: 18px 20px;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-header .bulk-order-brand {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-header .bulk-order-brand img {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        object-fit: cover;
        background: #ff650b;
        box-shadow: 0 4px 14px rgba(0,0,0,.2);
        flex: 0 0 auto;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-header h1 {
        font-size: 1.72rem;
        letter-spacing: -.07em;
        text-transform: uppercase;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-header p { font-size: .9rem; max-width: 630px; }
    .bulk-order-app[data-mode="public"] .bulk-order-wrap { max-width: 1320px; padding: 18px; }
    .bulk-order-app[data-mode="public"] .bulk-order-panel {
        border-radius: 18px;
        box-shadow: none;
        border: 1px solid #e3e3e0;
        padding: 18px;
        background: #fff;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-panel h2 {
        color: #1d1d1f;
        font-weight: 900;
        letter-spacing: .08em;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-filters input {
        height: 48px;
        border-radius: 12px;
        padding-left: 44px;
        background: #fafafa;
        border-color: #dededb;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-filters { position: relative; }
    .bulk-order-app[data-mode="public"] .bulk-order-filters::before {
        content: '⌕'; position: absolute; left: 16px; top: 6px; z-index: 1;
        color: #737373; font-size: 1.8rem; line-height: 1;
    }
    .bulk-order-filter-label {
        display:flex;
        align-items:center;
        justify-content:space-between;
        margin-top:4px;
        color:#6d625b;
        font-size:.75rem;
        font-weight:800;
        letter-spacing:.04em;
        text-transform:uppercase;
    }
    .bulk-order-filter-label::after { content:'Desliza para ver más'; color:#a89b92; font-size:.66rem; font-weight:600; letter-spacing:0; text-transform:none; }
    .bulk-order-app[data-mode="public"] .bulk-order-category-chips {
        gap: 10px;
        margin: 0 -18px;
        padding: 10px 18px 12px;
        scroll-snap-type: x mandatory;
        background:linear-gradient(180deg,#fffaf5, #fff);
        border-top:1px solid #f1e5da;
        border-bottom:1px solid #f1e5da;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-category-chips button {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 50px;
        min-width: max-content;
        padding: 8px 14px 8px 9px;
        border: 1px solid #ebe4df;
        background: #fff;
        color: #242424;
        border-radius: 14px;
        box-shadow: 0 2px 6px rgba(83,42,12,.06);
        transition: transform .16s ease, background .16s ease, color .16s ease;
        scroll-snap-align: start;
    }
    .bulk-order-category-icon {
        display:grid;
        place-items:center;
        width: 22px;
        height: 22px;
        padding:4px;
        box-sizing:content-box;
        border-radius:9px;
        background:#fff0e6;
        color: #ff650b;
        flex: 0 0 auto;
    }
    .bulk-order-category-icon svg { display:block; width:100%; height:100%; fill:none; stroke:currentColor; stroke-width:1.9; stroke-linecap:round; stroke-linejoin:round; }
    .bulk-order-app[data-mode="public"] .bulk-order-category-chips button:hover { transform: translateY(-2px); }
    .bulk-order-app[data-mode="public"] .bulk-order-category-chips button.is-active {
        color: #fff; background: #171717; border-color: #171717;
        box-shadow: 0 5px 0 #ffd071;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-category-chips button.is-active .bulk-order-category-icon { color: #9e3100; background:#ffd071; }
    .bulk-order-app[data-mode="public"] .bulk-order-category-chips button span:last-child { white-space:nowrap; }
    .bulk-order-app[data-mode="public"] .bulk-order-product-list { gap: 14px; }
    .bulk-order-app[data-mode="public"] .bulk-order-product-row {
        border: 1px solid #e5e5e2;
        border-radius: 16px;
        box-shadow: 0 3px 10px rgba(0,0,0,.07);
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-product-row:hover {
        transform: translateY(-3px);
        box-shadow: 0 11px 26px rgba(0,0,0,.13);
    }
    .bulk-order-app[data-mode="public"] .bulk-order-product-media {
        height: 160px;
        background: linear-gradient(145deg, #ffe3c7, #ffb266);
    }
    .bulk-order-app[data-mode="public"] .bulk-order-product-content { padding: 13px; }
    .bulk-order-app[data-mode="public"] .bulk-order-product-row strong { font-size: 1rem; }
    .bulk-order-app[data-mode="public"] .bulk-order-product-desc {
        min-height: 36px;
        color: #666;
        font-size: .78rem;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-tag.price-tag { display: none; }
    .bulk-order-app[data-mode="public"] .bulk-order-product-actions {
        border-top: 1px solid #ededeb;
        padding-top: 10px;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-product-actions > span {
        font-size: 1.12rem;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-btn-primary {
        border-radius: 10px;
        background: var(--wa);
        box-shadow: 0 3px 0 #970018;
        text-transform: uppercase;
        font-size: .76rem;
        letter-spacing: .04em;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-btn-primary:active { transform: translateY(2px); box-shadow: 0 1px 0 #970018; }
    .bulk-order-app[data-mode="public"] .bulk-order-footer {
        background: #4b1d0d;
        border: 0;
        box-shadow: 0 -8px 24px rgba(0,0,0,.18);
        padding: 13px 18px calc(13px + env(safe-area-inset-bottom));
    }
    .bulk-order-app[data-mode="public"] .bulk-order-total { color: #fff; }
    .bulk-order-app[data-mode="public"] .bulk-order-total strong { color: #ffd071; }
    .bulk-order-app[data-mode="public"] .bulk-order-footer .bulk-order-btn-primary {
        min-height: 46px;
        min-width: 160px;
        background: #ff650b;
        color: #fff;
        box-shadow: 0 3px 0 #9e3100;
        font-size: .84rem;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-modal { background: rgba(0,0,0,.6); }
    .bulk-order-app[data-mode="public"] .bulk-order-modal-card { border-top: 5px solid #ff650b; }
    .bulk-order-app[data-mode="public"] .bulk-order-modal-head h3 { font-size: 1.35rem; }
    .bulk-order-app[data-mode="public"] .bulk-order-choice { padding: 13px 0; }
    .bulk-order-app[data-mode="public"] .bulk-order-choice input { accent-color: var(--wa); }
    @media (min-width: 960px) {
        .bulk-order-app[data-mode="public"] .bulk-order-header { padding-left: max(28px, calc((100% - 1260px) / 2)); }
        .bulk-order-app[data-mode="public"] #bulkOrderFormBody {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 18px;
            align-items: start;
        }
        .bulk-order-app[data-mode="public"] #bulkOrderFormBody > .bulk-order-panel { margin: 0; }
        .bulk-order-app[data-mode="public"] #bulkOrderFormBody > .bulk-order-panel:nth-child(2) {
            position: sticky; top: 18px;
            max-height: calc(100vh - 165px); overflow: auto;
        }
        .bulk-order-app[data-mode="public"] .bulk-order-menu-layout {
            display: flex;
            align-items: flex-start;
            gap: 18px;
        }
        .bulk-order-app[data-mode="public"] .bulk-order-category-chips {
            flex: 0 0 200px;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            margin: 0;
            padding: 2px 4px 2px 0;
            background: none;
            border: 0;
            overflow-x: hidden;
            overflow-y: auto;
            scroll-snap-type: none;
            position: sticky;
            top: 18px;
            max-height: calc(100vh - 230px);
        }
        .bulk-order-app[data-mode="public"] .bulk-order-category-chips button {
            width: 100%;
            justify-content: flex-start;
            scroll-snap-align: none;
        }
        .bulk-order-app[data-mode="public"] .bulk-order-menu-layout .bulk-order-product-list {
            flex: 1 1 0;
            min-width: 0;
            grid-template-columns: repeat(auto-fit, minmax(190px, 220px));
        }
        .bulk-order-app[data-mode="public"] .bulk-order-product-media { height: 180px; }
        .bulk-order-filter-label::after { content:''; }
    }

    /* Familiar WhatsApp Business cues: calm surfaces, conversational status and a quick cart action. */
    .bulk-order-app[data-mode="public"] .bulk-order-header p::before {
        content: '✓✓ '; color: #b7f7d0; font-weight: 900;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-panel h2::before {
        content: '●'; color: #25d366; margin-right: 7px; font-size: .65rem; vertical-align: 1px;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-product-meta .bulk-order-tag:not(.price-tag) {
        background: #e8f8ef; color: #187a46;
    }
    .bulk-order-cart-fab {
        position: fixed;
        right: 18px;
        bottom: 88px;
        z-index: 18;
        display: none;
        align-items: center;
        gap: 10px;
        min-height: 54px;
        padding: 8px 15px 8px 10px;
        border: 0;
        border-radius: 999px;
        background: #25d366;
        color: #083b20;
        box-shadow: 0 8px 24px rgba(18, 140, 126, .36);
        cursor: pointer;
        font: inherit;
        font-weight: 800;
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .bulk-order-cart-fab.is-visible { display: inline-flex; }
    .bulk-order-cart-fab:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(18, 140, 126, .42); }
    .bulk-order-cart-fab.is-feedback { animation: bulk-order-cart-pop .48s cubic-bezier(.2,.9,.25,1.35); }
    @keyframes bulk-order-cart-pop { 35% { transform: scale(1.13) rotate(-3deg); } 70% { transform: scale(.96) rotate(2deg); } }
    .bulk-order-cart-fab-icon {
        display: grid; place-items: center;
        width: 36px; height: 36px;
        border-radius: 50%; background: #fff; color: #128c7e;
        font-size: 1.15rem;
    }
    .bulk-order-cart-fab-count {
        display: grid; place-items: center;
        min-width: 21px; height: 21px; padding: 0 5px;
        border-radius: 999px; background: #e4002b; color: #fff;
        font-size: .72rem;
    }
    @media (min-width: 960px) {
        .bulk-order-cart-fab { bottom: 26px; }
    }

    /* Storefront touch-first: una misma experiencia para micrositio y POS. */
    .bulk-order-app[data-mode="public"] {
        min-height: 100dvh;
        background: #f6f4f1;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-wrap { max-width: 1540px; }
    .bulk-order-section-head {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        margin-bottom:12px;
    }
    .bulk-order-section-head h2 { margin:0 !important; }
    .bulk-order-result-count,
    .bulk-order-cart-count {
        display:inline-flex;
        align-items:center;
        min-height:28px;
        padding:5px 9px;
        border-radius:999px;
        background:#f3f4f6;
        color:#667085;
        font-size:.7rem;
        font-weight:800;
        white-space:nowrap;
    }
    .bulk-order-category-title {
        display:none;
        color:#75685f;
        font-size:.68rem;
        font-weight:900;
        letter-spacing:.08em;
        text-transform:uppercase;
    }
    .bulk-order-products-stage { position:relative; min-width:0; }
    .bulk-order-scroll-controls {
        display:none;
        position:absolute;
        right:12px;
        bottom:12px;
        z-index:4;
        flex-direction:column;
        gap:8px;
    }
    .bulk-order-scroll-controls.is-hidden { opacity:0; pointer-events:none; }
    .bulk-order-scroll-controls button {
        width:42px;
        height:42px;
        display:grid;
        place-items:center;
        border:1px solid #ddd6cf;
        border-radius:50%;
        background:rgba(255,255,255,.94);
        color:#2d2926;
        box-shadow:0 7px 18px rgba(52,38,27,.16);
        cursor:pointer;
        font-size:1rem;
        transition:transform .16s ease, opacity .16s ease, background .16s ease;
        backdrop-filter:blur(7px);
    }
    .bulk-order-scroll-controls button:hover:not(:disabled) { transform:translateY(-2px); background:#fff7ed; }
    .bulk-order-scroll-controls button:active:not(:disabled) { transform:scale(.94); }
    .bulk-order-scroll-controls button:disabled { opacity:.32; cursor:default; }
    .bulk-order-scroll-controls svg { width:18px; height:18px; fill:none; stroke:currentColor; stroke-width:2.2; stroke-linecap:round; stroke-linejoin:round; }
    .bulk-order-product-row { position:relative; }
    .bulk-order-promo-ribbon {
        position:absolute;
        top:10px;
        left:10px;
        z-index:2;
        padding:5px 8px;
        border-radius:7px;
        background:#ffd43b;
        color:#3d2b00;
        box-shadow:0 4px 10px rgba(122,83,0,.18);
        font-size:.64rem;
        font-weight:950;
        letter-spacing:.02em;
        text-transform:uppercase;
    }
    .bulk-order-app[data-mode="public"] .bulk-order-product-media img { object-fit:contain; }
    .bulk-order-app[data-mode="public"] .bulk-order-product-media {
        padding:12px;
        background:linear-gradient(180deg,#fff,#f8f5f1);
    }
    .bulk-order-app[data-mode="public"] .bulk-order-product-row:active { transform:scale(.985); }
    .bulk-order-app[data-mode="public"] .bulk-order-btn-primary { min-height:42px; }

    @media (min-width:720px) {
        .bulk-order-app[data-mode="public"] .bulk-order-menu-layout {
            display:grid;
            grid-template-columns:190px minmax(0,1fr);
            gap:18px;
            align-items:start;
        }
        .bulk-order-category-rail {
            position:sticky;
            top:12px;
            min-width:0;
        }
        .bulk-order-category-title { display:block; margin:0 0 9px 4px; }
        .bulk-order-app[data-mode="public"] .bulk-order-category-chips {
            max-height:calc(100dvh - 275px);
            margin:0;
            padding:4px 6px 12px 2px;
            display:flex;
            flex-direction:column;
            align-items:stretch;
            gap:7px;
            overflow-y:auto;
            overflow-x:hidden;
            border:0;
            background:transparent;
            scroll-snap-type:none;
        }
        .bulk-order-app[data-mode="public"] .bulk-order-category-chips button {
            width:100%;
            min-width:0;
            min-height:52px;
            justify-content:flex-start;
            padding:8px 10px;
            border-color:transparent;
            border-radius:12px;
            background:transparent;
            box-shadow:none;
            text-align:left;
        }
        .bulk-order-app[data-mode="public"] .bulk-order-category-chips button:hover { background:#fff7ed; transform:none; }
        .bulk-order-app[data-mode="public"] .bulk-order-category-chips button.is-active {
            position:relative;
            color:#1f1b18;
            background:#fff;
            border-color:#eadfd5;
            box-shadow:0 4px 12px rgba(76,48,25,.08);
        }
        .bulk-order-app[data-mode="public"] .bulk-order-category-chips button.is-active::after {
            content:'';
            position:absolute;
            left:44px;
            right:12px;
            bottom:5px;
            height:3px;
            border-radius:999px;
            background:#ffb000;
        }
        .bulk-order-app[data-mode="public"] .bulk-order-category-chips button.is-active .bulk-order-category-icon {
            color:#fff;
            background:#ff650b;
        }
        .bulk-order-app[data-mode="public"] .bulk-order-product-list {
            grid-template-columns:repeat(auto-fill,minmax(175px,1fr));
            gap:12px;
            max-height:calc(100dvh - 285px);
            padding:3px 58px 24px 3px;
            overflow-y:auto;
            overflow-x:hidden;
            scroll-behavior:smooth;
            overscroll-behavior:contain;
            scrollbar-gutter:stable;
        }
        .bulk-order-app[data-mode="public"] .bulk-order-product-list::-webkit-scrollbar { width:7px; }
        .bulk-order-app[data-mode="public"] .bulk-order-product-list::-webkit-scrollbar-thumb { border-radius:999px; background:#d8cec5; }
        .bulk-order-app[data-mode="public"] .bulk-order-product-row { min-height:258px; }
        .bulk-order-app[data-mode="public"] .bulk-order-product-media { height:150px; }
        .bulk-order-app[data-mode="public"] .bulk-order-product-content { display:flex; flex:1; flex-direction:column; }
    .bulk-order-app[data-mode="public"] .bulk-order-product-price { margin-top:auto; }
        .bulk-order-scroll-controls { display:flex; }
    }
    @media (min-width:1180px) {
        .bulk-order-app[data-mode="public"] #bulkOrderFormBody {
            grid-template-columns:minmax(0,1fr) 360px;
            gap:20px;
        }
        .bulk-order-app[data-mode="public"] #bulkCartPanel { max-height:calc(100dvh - 180px); }
        .bulk-order-app[data-mode="public"] .bulk-order-product-list { grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); }
    }
    @media (min-width:1500px) {
        .bulk-order-app[data-mode="public"] .bulk-order-menu-layout { grid-template-columns:220px minmax(0,1fr); gap:22px; }
        .bulk-order-app[data-mode="public"] .bulk-order-category-chips button { min-height:58px; font-size:.86rem; }
        .bulk-order-app[data-mode="public"] .bulk-order-product-list { grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:16px; }
        .bulk-order-app[data-mode="public"] .bulk-order-product-row { min-height:285px; }
        .bulk-order-app[data-mode="public"] .bulk-order-product-media { height:175px; }
    }
    @media (max-width:719.98px) {
        .bulk-order-section-head { margin-bottom:10px; }
        .bulk-order-result-count { display:none; }
        .bulk-order-app[data-mode="public"] .bulk-order-product-media { height:125px; padding:7px; }
        .bulk-order-app[data-mode="public"] .bulk-order-product-desc { display:none; }
        .bulk-order-app[data-mode="public"] .bulk-order-product-row strong { font-size:.88rem; line-height:1.25; }
        .bulk-order-app[data-mode="public"] .bulk-order-product-price { font-size:.78rem; }
        .bulk-order-app[data-mode="public"] .bulk-order-product-price.is-promo { font-size:.86rem; }
        .bulk-order-app[data-mode="public"] .bulk-order-btn-primary { padding:8px 9px; font-size:.68rem; }
    }

    /* El pedido desde administración usa la misma estructura tipo kiosco. */
    .bulk-order-app[data-mode="agent"] { background:#f5f6f7; }
    .bulk-order-app[data-mode="agent"] .bulk-order-header {
        min-height:118px;
        padding:17px 20px 20px;
        border-radius:18px;
        background:linear-gradient(112deg,#3b1608 0%,#7f2b08 48%,#e95308 48%,#ff650b 100%);
        border-bottom:5px solid #ffd166;
        box-shadow:0 12px 30px rgba(86,34,8,.14);
    }
    .bulk-order-app[data-mode="agent"] .bulk-order-header h1 { font-size:1.7rem; }
    .bulk-order-app[data-mode="agent"] .bulk-order-wrap { max-width:none; }
    .bulk-order-app[data-mode="agent"] .bulk-order-panel {
        padding:18px;
        border-color:#e4e5e7;
        border-radius:16px;
        box-shadow:0 5px 18px rgba(15,23,42,.055);
    }
    .bulk-order-app[data-mode="agent"] .bulk-order-client-panel {
        position:relative;
        border-left:4px solid #ff650b;
    }
    .bulk-order-app[data-mode="agent"] .bulk-order-product-media {
        height:155px;
        padding:10px;
        background:linear-gradient(180deg,#fff,#f8f5f1);
    }
    .bulk-order-app[data-mode="agent"] .bulk-order-product-media img { object-fit:contain; }
    .bulk-order-app[data-mode="agent"] .bulk-order-product-row {
        min-height:264px;
        border-color:#e2ded9;
        box-shadow:0 4px 13px rgba(44,30,20,.07);
        transition:transform .16s ease, box-shadow .16s ease;
    }
    .bulk-order-app[data-mode="agent"] .bulk-order-product-row:hover {
        transform:translateY(-3px);
        box-shadow:0 12px 25px rgba(44,30,20,.12);
    }
    .bulk-order-app[data-mode="agent"] .bulk-order-product-content { display:flex; flex:1; flex-direction:column; padding:13px; }
    .bulk-order-app[data-mode="agent"] .bulk-order-product-price { margin-top:auto; }
    .bulk-order-app[data-mode="agent"] .bulk-order-footer {
        border-radius:14px;
        border:1px solid #e2e4e7;
        box-shadow:0 -8px 28px rgba(15,23,42,.1);
    }
    .bulk-order-app[data-mode="agent"] .bulk-order-footer-inner { max-width:none; }

    @media (min-width:900px) {
        .bulk-order-app[data-mode="agent"] .bulk-order-client-panel {
            display:grid;
            grid-template-columns:130px minmax(240px,320px) minmax(340px,1fr);
            align-items:end;
            gap:18px;
        }
        .bulk-order-app[data-mode="agent"] .bulk-order-client-panel > h2 { align-self:center; margin:0; font-size:.88rem; }
        .bulk-order-app[data-mode="agent"] .bulk-order-client-panel > div { margin:0 !important; }
        .bulk-order-app[data-mode="agent"] #bulkOrderFormBody {
            display:grid;
            grid-template-columns:minmax(0,1fr) 355px;
            gap:18px;
            align-items:start;
        }
        .bulk-order-app[data-mode="agent"] #bulkOrderFormBody > .bulk-order-panel { margin:0; }
        .bulk-order-app[data-mode="agent"] #bulkCartPanel {
            position:sticky;
            top:16px;
            max-height:calc(100dvh - 145px);
            overflow:auto;
        }
        .bulk-order-app[data-mode="agent"] .bulk-order-menu-layout {
            display:grid;
            grid-template-columns:190px minmax(0,1fr);
            gap:18px;
            align-items:start;
        }
        .bulk-order-app[data-mode="agent"] .bulk-order-category-title { display:block; margin:0 0 9px 4px; }
        .bulk-order-app[data-mode="agent"] .bulk-order-category-chips {
            max-height:calc(100dvh - 310px);
            margin:0;
            padding:4px 5px 12px 2px;
            display:flex;
            flex-direction:column;
            align-items:stretch;
            gap:7px;
            overflow-y:auto;
            overflow-x:hidden;
        }
        .bulk-order-app[data-mode="agent"] .bulk-order-category-chips button {
            width:100%;
            min-height:50px;
            display:flex;
            align-items:center;
            justify-content:flex-start;
            gap:7px;
            padding:8px 10px;
            border-color:transparent;
            border-radius:11px;
            background:transparent;
            color:#332b26;
            text-align:left;
        }
        .bulk-order-app[data-mode="agent"] .bulk-order-category-chips button.is-active {
            position:relative;
            background:#fff7ed;
            border-color:#fed7aa;
            color:#9a3412;
            box-shadow:0 4px 10px rgba(124,45,18,.07);
        }
        .bulk-order-app[data-mode="agent"] .bulk-order-category-chips button.is-active::after {
            content:'';
            position:absolute;
            left:44px;
            right:12px;
            bottom:4px;
            height:3px;
            border-radius:999px;
            background:#ffb000;
        }
        .bulk-order-app[data-mode="agent"] .bulk-order-product-list {
            grid-template-columns:repeat(auto-fill,minmax(185px,1fr));
            gap:13px;
            max-height:calc(100dvh - 315px);
            padding:3px 58px 24px 3px;
            overflow-y:auto;
            overflow-x:hidden;
            scroll-behavior:smooth;
            overscroll-behavior:contain;
            scrollbar-gutter:stable;
        }
        .bulk-order-app[data-mode="agent"] .bulk-order-scroll-controls { display:flex; }
    }
    @media (min-width:1450px) {
        .bulk-order-app[data-mode="agent"] .bulk-order-menu-layout { grid-template-columns:210px minmax(0,1fr); }
        .bulk-order-app[data-mode="agent"] .bulk-order-product-list { grid-template-columns:repeat(auto-fill,minmax(205px,1fr)); gap:16px; }
        .bulk-order-app[data-mode="agent"] .bulk-order-product-media { height:175px; }
    }
    @media (max-width:899.98px) {
        .bulk-order-app[data-mode="agent"] .bulk-order-header { border-radius:12px; }
        .bulk-order-app[data-mode="agent"] .bulk-order-client-panel { padding:14px; }
        .bulk-order-app[data-mode="agent"] .bulk-order-product-media { height:130px; }
        .bulk-order-touch-link span { display:none; }
        .bulk-order-form-disabled::before { top:56px; max-width:90%; white-space:normal; text-align:center; }
    }
</style>

<div class="bulk-order-app" id="bulkOrderApp" data-mode="{{ $isAgent ? 'agent' : 'public' }}" data-channel="{{ $isAgent ? 'agent' : ($isKiosk ? 'kiosk' : 'microsite') }}">
    <header class="bulk-order-header">
        @if($isAgent && !empty($ordersUrl))
            <div class="bulk-order-header-nav">
                <a href="{{ $ordersUrl }}" class="bulk-order-back-link"><i class="fas fa-arrow-left"></i> Volver a pedidos</a>
                <a href="{{ route('pos.create') }}" class="bulk-order-touch-link"><i class="fas fa-desktop"></i><span>Modo pantalla táctil</span></a>
            </div>
        @endif
        <div class="bulk-order-brand">
            @if(!$isAgent && !empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="Logo">
            @endif
            <h1>{{ $headerTitle }}</h1>
        </div>
        <p>{{ $headerSubtitle }}</p>
    </header>

    @if(!$isAgent && !$isKiosk && !empty($existingCartItems))
        <div class="bulk-order-wrap" style="padding-top:14px;padding-bottom:0">
            <div style="background:#fff4e6;border:1px solid #ffd8a8;border-radius:10px;padding:12px 14px;font-size:.85rem;color:#7c4a03">
                <strong>📦 Ya tienes esto en tu carrito de WhatsApp:</strong>
                <ul style="margin:6px 0 0;padding-left:18px">
                    @foreach($existingCartItems as $item)
                        <li>{{ $item['quantity'] }}x {{ $item['name'] }} — ${{ number_format($item['price'] * $item['quantity'], 2) }}</li>
                    @endforeach
                </ul>
                <div style="margin-top:6px">Se incluirá automáticamente en tu pedido junto con lo que agregues aquí.</div>
            </div>
        </div>
    @endif

    <div class="bulk-order-wrap" id="bulkOrderMain">
        <div id="bulkFormScreen">
            @if($isKiosk)
            <section class="bulk-order-panel" id="kioskNamePanel">
                <h2>¿Cuál es tu nombre?</h2>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <input type="text" id="kioskCustomerName" placeholder="Tu nombre" maxlength="120" autocomplete="name" style="flex:1 1 220px;border:1px solid var(--border);border-radius:8px;padding:10px 12px;font:inherit">
                    <button type="button" class="bulk-order-btn bulk-order-btn-primary" id="kioskStartBtn">Continuar</button>
                </div>
                <div id="kioskNameError" style="display:none;color:#b91c1c;font-size:.82rem;margin-top:8px"></div>
            </section>
            <input type="hidden" id="bulkBranch" value="{{ $defaultBranchId ?? (!empty($branches) ? $branches->first()?->id : '') }}">
            @endif
            @if($isAgent)
            <section class="bulk-order-panel bulk-order-client-panel">
                <h2>Cliente</h2>
                @if(!empty($branches) && count($branches) > 1)
                    <div style="margin:0 0 14px">
                        <label for="bulkBranch" style="display:block;margin-bottom:6px;font-size:.78rem;font-weight:800;color:#475569">Sucursal que atiende el pedido</label>
                        <select id="bulkBranch" style="width:100%;border:1px solid #cbd5e1;border-radius:9px;padding:10px 11px;background:#fff;font:inherit">
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected($branch->is_default)>{{ $branch->name }}{{ $branch->code ? ' · '.$branch->code : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" id="bulkBranch" value="{{ !empty($branches) ? $branches->first()?->id : '' }}">
                @endif
                <div class="bulk-order-contact-picker" id="contactPicker">
                    <div id="contactSelectedBox" style="display:none">
                        <div class="bulk-order-contact-selected">
                            <div>
                                <strong id="selectedContactName"></strong>
                                <small id="selectedContactPhone"></small>
                            </div>
                            <button type="button" class="bulk-order-btn bulk-order-btn-ghost" id="changeContactBtn">Cambiar</button>
                        </div>
                    </div>
                    <div id="contactSearchBox">
                        <div class="bulk-order-contact-intro"><p>Busca al cliente para asignar este pedido.</p><button type="button" class="bulk-order-contact-add" id="openContactCreate"><i class="fas fa-user-plus"></i> Nuevo</button></div>
                        <div class="bulk-order-contact-search"><i class="fas fa-search"></i><input type="search" id="contactSearch" placeholder="Nombre, WhatsApp o cédula" autocomplete="off"></div>
                        <div class="bulk-order-contact-results" id="contactResults"></div>
                    </div>
                </div>
            </section>
            @endif

            <div id="bulkOrderFormBody" @if($isAgent || $isKiosk) class="bulk-order-form-disabled" @endif>
                <section class="bulk-order-panel bulk-order-menu-panel">
                    <div class="bulk-order-section-head">
                        <h2>Menú</h2>
                        <span class="bulk-order-result-count" id="bulkProductCount">Cargando productos…</span>
                    </div>
                    <div class="bulk-order-filters">
                        <input type="search" id="bulkSearch" placeholder="¿Qué se te antoja hoy?" autocomplete="off">
                        <div class="bulk-order-filter-label">Explora el menú</div>
                        <select id="bulkCategory" class="visually-hidden" aria-hidden="true" tabindex="-1">
                            <option value="">Todas las categorías</option>
                        </select>
                    </div>
                    <div class="bulk-order-menu-layout">
                        <aside class="bulk-order-category-rail">
                            <div class="bulk-order-category-title">Categorías</div>
                            <div class="bulk-order-category-chips" id="bulkCategoryChips" aria-label="Categorías"></div>
                        </aside>
                        <div class="bulk-order-products-stage">
                            <div class="bulk-order-product-list" id="bulkProductList"></div>
                            <div class="bulk-order-scroll-controls" aria-label="Desplazar productos">
                                <button type="button" id="bulkProductsUp" aria-label="Subir en el menú" title="Subir"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg></button>
                                <button type="button" id="bulkProductsDown" aria-label="Bajar en el menú" title="Bajar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bulk-order-panel" id="bulkCartPanel">
                    <div class="bulk-order-section-head">
                        <h2>{{ $isAgent ? 'Lista del pedido' : 'Tu pedido' }}</h2>
                        <span class="bulk-order-cart-count" id="bulkCartCount">Vacío</span>
                    </div>
                    <div id="bulkCartItems"></div>
                    <p class="bulk-order-cart-empty" id="bulkCartEmpty">Aún no agregaste productos.</p>
                    <label for="bulkOrderNote" style="display:block;margin-top:12px;font-size:.85rem;color:var(--muted)">Nota general del pedido (opcional)</label>
                    <textarea id="bulkOrderNote" rows="2" placeholder="Instrucciones de entrega, facturación…"></textarea>

                    @if($isKiosk)
                    <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border)">
                        <label style="display:block;margin-bottom:8px;font-size:.85rem;color:var(--muted)">¿Para llevar o para servir?</label>
                        <div style="display:flex;gap:8px;margin-bottom:12px">
                            <button type="button" class="bulk-order-btn bulk-order-btn-ghost" id="kioskServiceLlevar" style="flex:1">🥡 Para llevar</button>
                            <button type="button" class="bulk-order-btn bulk-order-btn-ghost" id="kioskServiceServir" style="flex:1">🍽️ Para servir</button>
                        </div>
                        <div id="kioskTableWrap" style="display:none;margin-bottom:12px">
                            <label for="kioskTableReference" style="display:block;margin-bottom:6px;font-size:.85rem;color:var(--muted)">Mesa / referencia (opcional)</label>
                            <input type="text" id="kioskTableReference" placeholder="Ej.: Mesa 4" maxlength="120">
                        </div>
                        <label for="kioskPaymentMethod" style="display:block;margin-bottom:6px;font-size:.85rem;color:var(--muted)">Forma de pago</label>
                        <select id="kioskPaymentMethod">
                            <option value="efectivo">💵 Efectivo</option>
                            <option value="transferencia">🏦 Transferencia</option>
                            <option value="tarjeta">💳 Tarjeta</option>
                        </select>
                    </div>
                    @endif
                </section>
            </div>
        </div>

        <div class="bulk-order-success" id="bulkSuccessScreen">
            <div class="icon">✅</div>
            <h2>Pedido enviado</h2>
            <p id="bulkSuccessOrderNumber" style="font-size:1.1rem;font-weight:700;color:var(--wa-dark);margin:12px 0;"></p>
            <p id="bulkSuccessHint">{{ $successWhatsappHint }}</p>
            <p id="bulkSuccessPdfWrap" style="display:none;margin-top:16px">
                <a href="#" id="bulkSuccessPdfLink" class="bulk-order-btn bulk-order-btn-primary" style="display:inline-block;text-decoration:none" target="_blank" rel="noopener">
                    Descargar PDF de la orden
                </a>
            </p>
            @if($isAgent && !empty($ordersUrl))
                <p style="margin-top:16px">
                    <a href="{{ $ordersUrl }}" class="bulk-order-btn bulk-order-btn-primary" style="display:inline-block;text-decoration:none">Ver listado de pedidos</a>
                </p>
            @endif
            @if($isKiosk)
                <p style="margin-top:16px">
                    <button type="button" class="bulk-order-btn bulk-order-btn-primary" id="kioskNewOrderBtn">Nuevo pedido</button>
                </p>
            @endif
        </div>
    </div>

    <div class="bulk-order-footer" id="bulkFooterBar">
        <div class="bulk-order-footer-inner">
            @if($isAgent)
            <label class="bulk-order-notify-row">
                <input type="checkbox" id="bulkNotifyWhatsapp">
                Enviar confirmación por WhatsApp (opcional)
            </label>
            @endif
            <div class="bulk-order-total">
                <span id="bulkItemsCount">0 productos</span>
                <strong id="bulkGrandTotal">$0.00</strong>
            </div>
            <button type="button" class="bulk-order-btn bulk-order-btn-primary" id="bulkSubmitBtn" disabled>{{ $isKiosk ? 'Revisar y ordenar' : ($isAgent ? 'Registrar pedido' : 'Confirmar pedido') }}</button>
        </div>
    </div>

    <div class="bulk-order-toast" id="bulkToast"></div>

    @if(!$isAgent)
        <button type="button" class="bulk-order-cart-fab" id="bulkCartFab" aria-label="Ver mi pedido">
            <span class="bulk-order-cart-fab-icon">🛒</span>
     
            <span class="bulk-order-cart-fab-count" id="bulkCartFabCount">0</span>
        </button>
    @endif

    <div class="bulk-order-modal" id="bulkCustomizer" aria-hidden="true">
        <div class="bulk-order-modal-card" role="dialog" aria-modal="true" aria-labelledby="bulkCustomizerTitle">
            <div class="bulk-order-modal-head">
                <div>
                    <h3 id="bulkCustomizerTitle">Personaliza tu pedido</h3>
                    <div id="bulkCustomizerPrice" style="color:var(--wa-dark);font-weight:800;margin-top:4px"></div>
                </div>
                <button type="button" class="bulk-order-close" id="bulkCustomizerClose" aria-label="Cerrar">×</button>
            </div>
            <div id="bulkCustomizerOptions" class="bulk-order-modal-options"></div>
            <div class="bulk-order-modal-footer">
                <button type="button" class="bulk-order-btn bulk-order-btn-primary bulk-order-modal-add" id="bulkCustomizerAdd">Agregar al carrito</button>
            </div>
        </div>
    </div>

    @if($isAgent)
    <div class="bulk-order-modal bulk-order-contact-modal" id="contactCreateModal" aria-hidden="true">
        <div class="bulk-order-contact-modal-card" role="dialog" aria-modal="true" aria-labelledby="contactCreateTitle">
            <div class="bulk-order-contact-modal-head"><h3 id="contactCreateTitle">Nuevo cliente</h3><p>Solo necesitamos los datos esenciales para registrar el pedido.</p></div>
            <form id="contactCreateForm">
                <div class="bulk-order-contact-form">
                    <label>Nombre completo<input id="contactCreateName" name="name" required minlength="2" maxlength="120" autocomplete="name" placeholder="Ej.: María Pérez"></label>
                    <label>WhatsApp <span style="font-weight:500;color:#94a3b8">(opcional)</span><input id="contactCreatePhone" name="phone" inputmode="tel" maxlength="30" autocomplete="tel" placeholder="Ej.: 593 99 123 4567"><small style="font-weight:500;color:#94a3b8">Si lo ingresas, usaremos entre 8 y 15 dígitos.</small></label>
                    <label>Dirección o referencia <span id="contactAddressOptional" style="font-weight:500;color:#94a3b8">(opcional)</span><textarea id="contactCreateAddress" name="address" rows="2" maxlength="500" placeholder="Ej.: Av. principal y calle 2"></textarea></label>
                    <label class="bulk-order-invoice-toggle"><input type="checkbox" id="contactRequiresInvoice" name="requires_invoice"> Solicita factura</label>
                    <div class="bulk-order-invoice-fields" id="contactInvoiceFields">
                        <strong>Datos para facturación</strong>
                        <label>Tipo de documento<select id="contactBillingType" name="billing_type"><option value="cedula">Cédula</option><option value="ruc">RUC</option><option value="pasaporte">Pasaporte</option></select></label>
                        <label>Identificación<input id="contactBillingId" name="billing_id" maxlength="20" inputmode="numeric" placeholder="Número de documento"></label>
                        <label>Correo de facturación<input id="contactBillingEmail" name="billing_email" type="email" maxlength="255" autocomplete="email" placeholder="correo@ejemplo.com"></label>
                    </div>
                </div>
                <div class="bulk-order-contact-form-actions"><button type="button" class="bulk-order-btn bulk-order-btn-ghost" id="closeContactCreate">Cancelar</button><button type="submit" class="bulk-order-btn bulk-order-btn-primary" id="submitContactCreate">Guardar y seleccionar</button></div>
            </form>
        </div>
    </div>
    @endif

    <div class="bulk-order-submit-overlay" id="bulkSubmitOverlay" aria-hidden="true" aria-live="polite" aria-busy="false">
        <div class="bulk-order-submit-overlay-card">
            <div class="bulk-order-submit-spinner" aria-hidden="true"></div>
            <strong id="bulkSubmitOverlayTitle">Enviando pedido…</strong>
            <p id="bulkSubmitOverlayText">Estamos registrando tu pedido. Esto puede tardar unos segundos.</p>
        </div>
    </div>
</div>

<script>
(function () {
    const isAgent = @json($isAgent);
    const isKiosk = @json($isKiosk);
    const catalogUrl = @json($catalogUrl);
    const submitUrl = @json($submitUrl);
    const contactsSearchUrl = @json($contactsSearchUrl ?? null);
    const contactsCreateUrl = @json($contactsCreateUrl ?? null);
    const initialContact = @json($initialContact ?? null);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let products = [];
    let cart = [];
    let editingCartIndex = null;
    let selectedContact = initialContact ? { ...initialContact } : null;
    let isSubmitting = false;
    let kioskServiceType = null;

    const root = document.getElementById('bulkOrderApp');
    const el = (id) => document.getElementById(id);
    const fmt = (n) => '$' + Number(n).toFixed(2);
    const submitBtnDefaultLabel = el('bulkSubmitBtn')?.textContent.trim() || 'Confirmar pedido';

    function setSubmitting(submitting) {
        isSubmitting = submitting;
        const btn = el('bulkSubmitBtn');
        const overlay = el('bulkSubmitOverlay');
        const overlayTitle = el('bulkSubmitOverlayTitle');
        const overlayText = el('bulkSubmitOverlayText');

        if (overlay) {
            overlay.classList.toggle('is-visible', submitting);
            overlay.setAttribute('aria-hidden', submitting ? 'false' : 'true');
            overlay.setAttribute('aria-busy', submitting ? 'true' : 'false');
        }

        if (overlayTitle) {
            overlayTitle.textContent = isAgent ? 'Registrando pedido…' : 'Enviando pedido…';
        }
        if (overlayText) {
            overlayText.textContent = isAgent
                ? 'Guardando el pedido y, si corresponde, enviando WhatsApp al cliente. Un momento.'
                : 'Estamos registrando tu pedido y preparando el mensaje en WhatsApp. Un momento.';
        }

        if (submitting) {
            btn.disabled = true;
            btn.classList.add('is-loading');
            btn.innerHTML = '<span class="bulk-order-btn-spinner" aria-hidden="true"></span> Enviando…';
            document.body.style.overflow = 'hidden';
        } else {
            btn.classList.remove('is-loading');
            btn.textContent = submitBtnDefaultLabel;
            document.body.style.overflow = '';
            updateFormEnabled();
        }
    }

    function toast(msg) {
        const t = el('bulkToast');
        t.textContent = msg;
        t.style.display = 'block';
        setTimeout(() => { t.style.display = 'none'; }, 2800);
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function debounce(fn, ms) {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), ms);
        };
    }

    function productMetaHtml(p, { truncateDesc = 120 } = {}) {
        let html = '';
        if (p.description) {
            const desc = truncateDesc && p.description.length > truncateDesc
                ? p.description.slice(0, truncateDesc - 1) + '…'
                : p.description;
            html += `<p class="bulk-order-product-desc">${escapeHtml(desc)}</p>`;
        }
        let meta = '';
        if (p.measurements) {
            meta += `<span class="bulk-order-tag">📏 ${escapeHtml(p.measurements)}</span>`;
        }
        if (meta) html += `<div class="bulk-order-product-meta">${meta}</div>`;
        return html;
    }

    function categoryIcon(title) {
        const value = String(title || '').toLowerCase();
        const icons = {
            all: '<svg viewBox="0 0 24 24"><path d="M5 7h14M5 12h14M5 17h14"/><path d="M3 7h.01M3 12h.01M3 17h.01"/></svg>',
            box: '<svg viewBox="0 0 24 24"><path d="m3 8 9-5 9 5-9 5-9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/></svg>',
            familiar: '<svg viewBox="0 0 24 24"><path d="M4 11h16l-1 8H5l-1-8Z"/><path d="M7 11V8a5 5 0 0 1 10 0v3M9 15h.01M15 15h.01"/></svg>',
            premium: '<svg viewBox="0 0 24 24"><path d="m4 9 3 10h10l3-10-4 4-4-7-4 7-4-4Z"/><path d="M6 21h12"/></svg>',
            arroz: '<svg viewBox="0 0 24 24"><path d="M4 13h16"/><path d="M5 13c.5 5 3 7 7 7s6.5-2 7-7"/><path d="M8 9c0-1 1-2 2-2M12 9c0-1 1-2 2-2M16 9c0-1 1-2 2-2"/></svg>',
            hamburguesa: '<svg viewBox="0 0 24 24"><path d="M4 10c0-3 3-5 8-5s8 2 8 5H4Z"/><path d="M3 13h18M4 16h16M6 19h12"/></svg>',
            salchi: '<svg viewBox="0 0 24 24"><path d="M6 5h12l-1 15H7L6 5Z"/><path d="M8 9h8M9 13h6M10 17h4"/></svg>',
            salsa: '<svg viewBox="0 0 24 24"><path d="M9 3h6M10 3v4l-4 4v8h12v-8l-4-4V3"/><path d="M8 14h8"/></svg>',
        };
        const key = value.includes('box') ? 'box'
            : value.includes('familiar') ? 'familiar'
            : value.includes('premium') ? 'premium'
            : value.includes('arroz') ? 'arroz'
            : value.includes('hamburg') ? 'hamburguesa'
            : value.includes('salchi') || value.includes('especial') ? 'salchi'
            : value.includes('salsa') ? 'salsa'
            : 'all';
        return `<span class="bulk-order-category-icon" aria-hidden="true">${icons[key]}</span>`;
    }

    function addFeedback() {
        if (navigator.vibrate) navigator.vibrate(35);
        const cartFab = el('bulkCartFab');
        if (cartFab) {
            cartFab.classList.remove('is-feedback');
            void cartFab.offsetWidth;
            cartFab.classList.add('is-feedback');
        }
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const audio = new AudioContext();
            const now = audio.currentTime;
            const noise = audio.createBuffer(1, Math.floor(audio.sampleRate * .22), audio.sampleRate);
            const channel = noise.getChannelData(0);
            for (let i = 0; i < channel.length; i++) {
                // Short, irregular bursts resemble a light fryer sizzle.
                const envelope = 1 - (i / channel.length);
                channel[i] = (Math.random() * 2 - 1) * envelope * (Math.random() > .52 ? 1 : .24);
            }
            const source = audio.createBufferSource();
            const filter = audio.createBiquadFilter();
            const gain = audio.createGain();
            filter.type = 'bandpass';
            filter.frequency.setValueAtTime(3100, now);
            filter.Q.value = .7;
            gain.gain.setValueAtTime(.001, now);
            gain.gain.exponentialRampToValueAtTime(.055, now + .018);
            gain.gain.exponentialRampToValueAtTime(.001, now + .22);
            source.buffer = noise;
            source.connect(filter).connect(gain).connect(audio.destination);
            source.start(now);

            // A tiny warm confirmation tone, kept deliberately subtle.
            const tone = audio.createOscillator();
            const toneGain = audio.createGain();
            tone.type = 'triangle';
            tone.frequency.setValueAtTime(420, now + .05);
            tone.frequency.exponentialRampToValueAtTime(560, now + .15);
            toneGain.gain.setValueAtTime(.001, now);
            toneGain.gain.exponentialRampToValueAtTime(.018, now + .06);
            toneGain.gain.exponentialRampToValueAtTime(.001, now + .19);
            tone.connect(toneGain).connect(audio.destination);
            tone.start(now);
            tone.stop(now + .21);
            source.addEventListener('ended', () => audio.close());
        } catch (_) {
            // Browsers can block sound; the visual feedback still works.
        }
    }

    function quantityFeedback(direction) {
        if (navigator.vibrate) navigator.vibrate(direction > 0 ? 18 : 10);
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const audio = new AudioContext();
            const now = audio.currentTime;
            const oscillator = audio.createOscillator();
            const gain = audio.createGain();
            oscillator.type = 'triangle';
            const start = direction > 0 ? 680 : 540;
            const end = direction > 0 ? 960 : 380;
            oscillator.frequency.setValueAtTime(start, now);
            oscillator.frequency.exponentialRampToValueAtTime(end, now + .095);
            gain.gain.setValueAtTime(.001, now);
            gain.gain.exponentialRampToValueAtTime(.028, now + .012);
            gain.gain.exponentialRampToValueAtTime(.001, now + .11);
            oscillator.connect(gain).connect(audio.destination);
            oscillator.start(now);
            oscillator.stop(now + .12);
            oscillator.addEventListener('ended', () => audio.close());
        } catch (_) {
            // Haptic and visual controls remain usable if audio is unavailable.
        }
    }

    function removeFeedback() {
        if (navigator.vibrate) navigator.vibrate([12, 24, 12]);
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const audio = new AudioContext();
            const now = audio.currentTime;
            const oscillator = audio.createOscillator();
            const gain = audio.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(390, now);
            oscillator.frequency.exponentialRampToValueAtTime(190, now + .14);
            gain.gain.setValueAtTime(.028, now);
            gain.gain.exponentialRampToValueAtTime(.001, now + .16);
            oscillator.connect(gain).connect(audio.destination);
            oscillator.start(now);
            oscillator.stop(now + .17);
            oscillator.addEventListener('ended', () => audio.close());
        } catch (_) {
            // The cart still updates when a browser blocks audio.
        }
    }

    function cartMetaHtml(line) {
        let html = '';
        if (line.description) {
            html += `<div>${escapeHtml(line.description)}</div>`;
        }
        if (line.measurements) {
            html += `<span class="measurements">📏 ${escapeHtml(line.measurements)}</span>`;
        }
        return html ? `<div class="bulk-order-cart-meta">${html}</div>` : '';
    }

    function changeQty(idx, delta) {
        const line = cart[idx];
        if (!line || !line.allow_quantity) return;
        const quantity = Math.min(line.max_qty, Math.max(line.min_qty, line.quantity + delta));
        if (quantity === line.quantity) return;
        line.quantity = quantity;
        renderCart();
        quantityFeedback(delta);
    }

    function setQty(idx, value) {
        const line = cart[idx];
        if (!line || !line.allow_quantity) return;
        const quantity = Math.min(line.max_qty, Math.max(line.min_qty, Number(value) || line.min_qty));
        const direction = quantity > line.quantity ? 1 : -1;
        if (quantity === line.quantity) return;
        line.quantity = quantity;
        renderCart();
        quantityFeedback(direction);
    }

    function updateFormEnabled() {
        const btn = el('bulkSubmitBtn');
        if (isSubmitting) {
            btn.disabled = true;
            return;
        }
        if (isAgent || isKiosk) {
            const body = el('bulkOrderFormBody');
            const enabled = !!selectedContact;
            body.classList.toggle('bulk-order-form-disabled', !enabled);
            btn.disabled = cart.length === 0 || !enabled || (isKiosk && !kioskServiceType);
            return;
        }
        btn.disabled = cart.length === 0;
    }

    function renderSelectedContact() {
        if (!isAgent) return;
        const has = !!selectedContact;
        el('contactSelectedBox').style.display = has ? 'block' : 'none';
        el('contactSearchBox').style.display = has ? 'none' : 'block';
        if (has) {
            el('selectedContactName').textContent = selectedContact.name;
            el('selectedContactPhone').textContent = selectedContact.phone || '';
        } else {
            el('contactSearch').value = '';
            el('contactResults').style.display = 'none';
        }
        updateFormEnabled();
    }

    async function searchContacts(q) {
        if (!contactsSearchUrl || q.trim().length < 2) {
            el('contactResults').style.display = 'none';
            return;
        }
        const res = await fetch(contactsSearchUrl + '?q=' + encodeURIComponent(q.trim()));
        const data = await res.json();
        const box = el('contactResults');
        const list = data.contacts || [];
        if (!list.length) {
            box.innerHTML = '<div class="bulk-order-contact-empty">No encontramos un cliente con esos datos.<br><button type="button" data-create-from-search>Agregar cliente nuevo</button></div>';
            box.style.display = 'block';
            box.querySelector('[data-create-from-search]')?.addEventListener('click', () => openContactCreate(q));
            return;
        }
        box.innerHTML = list.map(c => `
            <button type="button" data-contact-id="${c.id}" data-contact-name="${escapeHtml(c.name)}" data-contact-phone="${escapeHtml(c.phone || '')}">
                <strong>${escapeHtml(c.name)}</strong><br>
                <small style="color:#667781">${escapeHtml(c.phone || '')}</small>
            </button>
        `).join('');
        box.style.display = 'block';
        box.querySelectorAll('[data-contact-id]').forEach(btn => {
            btn.addEventListener('click', () => {
                selectedContact = {
                    id: Number(btn.dataset.contactId),
                    name: btn.dataset.contactName,
                    phone: btn.dataset.contactPhone,
                };
                renderSelectedContact();
            });
        });
    }

    function openContactCreate(prefill = '') {
        if (!isAgent) return;
        const modal = el('contactCreateModal');
        if (!modal) return;
        el('contactCreateName').value = '';
        el('contactCreatePhone').value = '';
        el('contactCreateAddress').value = '';
        el('contactRequiresInvoice').checked = false;
        el('contactBillingType').value = 'cedula';
        el('contactBillingId').value = '';
        el('contactBillingEmail').value = '';
        const compactValue = String(prefill).trim();
        if (/^[+\d\s()-]+$/.test(compactValue)) el('contactCreatePhone').value = compactValue;
        syncInvoiceFields();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => (el('contactCreateName').value ? el('contactCreatePhone') : el('contactCreateName')).focus(), 80);
    }

    function closeContactCreate() {
        const modal = el('contactCreateModal');
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function syncInvoiceFields() {
        const requested = el('contactRequiresInvoice')?.checked;
        el('contactInvoiceFields')?.classList.toggle('is-open', !!requested);
        el('contactBillingId').required = !!requested;
        el('contactBillingEmail').required = !!requested;
        el('contactCreateAddress').required = !!requested;
        el('contactAddressOptional').textContent = requested ? '(obligatoria para factura)' : '(opcional)';
    }

    async function createContact(event) {
        event.preventDefault();
        if (!contactsCreateUrl) return;
        const submitButton = el('submitContactCreate');
        submitButton.disabled = true;
        try {
            const response = await fetch(contactsCreateUrl, {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':csrf },
                body: JSON.stringify({
                    name: el('contactCreateName').value.trim(),
                    phone: el('contactCreatePhone').value.trim(),
                    address: el('contactCreateAddress').value.trim() || null,
                    requires_invoice: el('contactRequiresInvoice').checked,
                    billing_type: el('contactBillingType').value,
                    billing_id: el('contactBillingId').value.trim(),
                    billing_email: el('contactBillingEmail').value.trim(),
                }),
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                throw new Error(firstError || data.message || 'No se pudo guardar el cliente.');
            }
            selectedContact = data.contact;
            closeContactCreate();
            renderSelectedContact();
            toast(data.message || 'Cliente seleccionado');
        } catch (error) {
            toast(error.message || 'No se pudo guardar el cliente.');
        } finally {
            submitButton.disabled = false;
        }
    }

    async function loadCatalog() {
        const q = el('bulkSearch').value.trim();
        const cat = el('bulkCategory').value;
        const params = new URLSearchParams();
        if (q) params.set('q', q);
        if (cat) params.set('category', cat);
        const res = await fetch(catalogUrl + '?' + params.toString());
        const data = await res.json();
        products = data.products || [];

        const sel = el('bulkCategory');
        const current = sel.value;
        if (sel.options.length <= 1) {
            (data.categories || []).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = (c.icon ? c.icon + ' ' : '') + c.title;
                sel.appendChild(opt);
            });
            sel.value = current;
        }

        renderProducts();
        renderCategoryChips(data.categories || []);
    }

    function renderCategoryChips(categories) {
        const box = el('bulkCategoryChips');
        const current = el('bulkCategory').value;
        box.innerHTML = `<button type="button" data-category="" class="${current === '' ? 'is-active' : ''}">${categoryIcon('all')}<span>Todo</span></button>` + categories.map(c =>
            `<button type="button" data-category="${c.id}" class="${String(c.id) === String(current) ? 'is-active' : ''}">${categoryIcon(c.title)}<span>${escapeHtml(c.title)}</span></button>`
        ).join('');
        box.querySelectorAll('[data-category]').forEach(btn => btn.addEventListener('click', () => {
            el('bulkCategory').value = btn.dataset.category;
            loadCatalog();
        }));
    }

    function renderProducts() {
        const box = el('bulkProductList');
        const productCount = el('bulkProductCount');
        if (productCount) {
            productCount.textContent = products.length + (products.length === 1 ? ' producto' : ' productos');
        }
        if (!products.length) {
            box.innerHTML = '<p class="bulk-order-cart-empty">No hay productos con ese filtro.</p>';
            updateProductScrollControls();
            return;
        }
        box.innerHTML = products.map(p => `
            <article class="bulk-order-product-row" data-product-card="${p.id}" role="button" tabindex="0" aria-label="Ver ${escapeHtml(p.name)}, ${fmt(p.price)}">
                ${p.is_promo ? '<span class="bulk-order-promo-ribbon">Promo</span>' : ''}
                <div class="bulk-order-product-media">${p.image ? `<img src="${escapeHtml(p.image)}" alt="${escapeHtml(p.name)}" loading="lazy">` : '<div class="fallback">🍗</div>'}</div>
                <div class="bulk-order-product-content">
                    <strong>${escapeHtml(p.name)}</strong>
                    ${productMetaHtml(p)}
                    <span class="bulk-order-product-price ${p.is_promo ? 'is-promo' : ''}">${fmt(p.price)}</span>
                </div>
            </article>
        `).join('');

        box.querySelectorAll('[data-product-card]').forEach(card => {
            const openProduct = () => openCustomizer(Number(card.dataset.productCard));
            card.addEventListener('click', openProduct);
            card.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openProduct();
                }
            });
        });
        box.scrollTop = 0;
        requestAnimationFrame(updateProductScrollControls);
    }

    function updateProductScrollControls() {
        const box = el('bulkProductList');
        const controls = document.querySelector('.bulk-order-scroll-controls');
        const up = el('bulkProductsUp');
        const down = el('bulkProductsDown');
        if (!box || !controls || !up || !down) return;

        const scrollable = box.scrollHeight > box.clientHeight + 4;
        controls.classList.toggle('is-hidden', !scrollable);
        up.disabled = !scrollable || box.scrollTop <= 4;
        down.disabled = !scrollable || box.scrollTop + box.clientHeight >= box.scrollHeight - 4;
    }

    let customizingProduct = null;
    function openCustomizer(id, existingLine = null) {
        const p = products.find(x => x.id === id) || existingLine?.product;
        if (!p) return;
        customizingProduct = p;
        editingCartIndex = existingLine?.index ?? null;
        el('bulkCustomizerTitle').textContent = p.name;
        el('bulkCustomizerPrice').textContent = 'Desde ' + fmt(p.price);
        let html = `<div class="bulk-order-modal-hero">${p.image ? `<img src="${escapeHtml(p.image)}" alt="${escapeHtml(p.name)}">` : '<div class="fallback">🍗</div>'}</div>`;
        if (p.description) {
            html += `<p class="bulk-order-modal-description">${escapeHtml(p.description)}</p>`;
        }
        if ((p.variations || []).length) {
            html += '<h4 style="margin:8px 0;font-size:.9rem">Elige tu opción</h4>';
            html += p.variations.map((v, i) => `<div class="bulk-order-choice"><label><input type="radio" name="bulkVariation" value="${escapeHtml(v.title)}" data-price="${v.price}" ${(existingLine?.variation ? existingLine.variation === v.title : i === 0) ? 'checked' : ''}> ${escapeHtml(v.title)}</label><small>${fmt(v.price)}</small></div>`).join('');
        }
        if ((p.extras || []).length) {
            html += '<h4 style="margin:18px 0 8px;font-size:.9rem">Hazlo aún mejor <span style="font-weight:400;color:var(--muted)">(opcional)</span></h4>';
            html += p.extras.map(e => `<div class="bulk-order-choice"><label><input type="checkbox" name="bulkExtra" value="${escapeHtml(e.title)}" data-price="${e.price}" ${(existingLine?.extras || []).includes(e.title) ? 'checked' : ''}> ${escapeHtml(e.title)}</label><small>+${fmt(e.price)}</small></div>`).join('');
        }
        if (!html) html = '<p style="color:var(--muted);margin:0">Listo para agregar a tu carrito.</p>';
        el('bulkCustomizerOptions').innerHTML = html;
        el('bulkCustomizerAdd').textContent = existingLine ? 'Guardar cambios' : 'Agregar al carrito';
        el('bulkCustomizer').classList.add('is-open');
        el('bulkCustomizer').setAttribute('aria-hidden', 'false');
    }

    function closeCustomizer() {
        el('bulkCustomizer').classList.remove('is-open');
        el('bulkCustomizer').setAttribute('aria-hidden', 'true');
        customizingProduct = null;
        editingCartIndex = null;
    }

    function addProduct(p, configuration = {}) {
        if (!p) return;
        const variation = configuration.variation || '';
        const extras = configuration.extras || [];
        const variationObj = (p.variations || []).find(v => v.title === variation);
        const extrasObjs = (p.extras || []).filter(e => extras.includes(e.title));
        const unitPrice = Number(variationObj ? variationObj.price : p.price) + extrasObjs.reduce((sum, e) => sum + Number(e.price), 0);
        const id = p.id;
        const key = id + '|' + variation + '|' + extras.join('|');
        const existing = cart.find(x => x.key === key);
        if (existing) {
            existing.quantity = Math.min(existing.max_qty, existing.quantity + 1);
        } else {
            cart.push({
                key,
                product_id: id,
                name: p.name,
                image: p.image || '',
                // Se conserva el precio base para que "Editar selección" no acumule
                // adicionales previamente escogidos al volver a calcular la línea.
                base_price: Number(p.price),
                variations: p.variations || [],
                extras_options: p.extras || [],
                description: p.description || '',
                measurements: p.measurements || '',
                price: unitPrice,
                quantity: p.min_qty || 1,
                min_qty: p.min_qty || 1,
                max_qty: p.max_qty || 99,
                allow_quantity: p.allow_quantity,
                variation,
                extras,
                note: '',
            });
        }
        renderCart();
        addFeedback();
        toast('Producto agregado');
    }

    function removeLine(idx) {
        cart.splice(idx, 1);
        renderCart();
        removeFeedback();
        toast('Producto quitado');
    }

    function renderCart() {
        const box = el('bulkCartItems');
        el('bulkCartEmpty').style.display = cart.length ? 'none' : 'block';
        box.innerHTML = cart.map((line, idx) => {
            // Resumen de una sola línea: en móvil evita que cada adicional se
            // convierta en una etiqueta alta. El detalle completo se edita en el modal.
            const extrasCount = (line.extras || []).length;
            const selectionSummary = [
                line.variation ? `<b>${escapeHtml(line.variation)}</b>` : '',
                extrasCount ? `<span class="bulk-order-extra-count">+ ${extrasCount} adicional${extrasCount === 1 ? '' : 'es'}</span>` : '',
            ].filter(Boolean).join(' · ');
            return `
            <div class="bulk-order-cart-item">
                <div class="bulk-order-cart-main">
                    <div class="bulk-order-cart-thumb">${line.image ? `<img src="${escapeHtml(line.image)}" alt="">` : '<span class="fallback">🍗</span>'}</div>
                    <div class="bulk-order-cart-info">
                        <strong>${escapeHtml(line.name)}</strong>
                        ${selectionSummary ? `<div class="bulk-order-cart-summary">${selectionSummary}</div>` : ''}
                        <span class="bulk-order-line-total">${fmt(line.price * line.quantity)}</span>
                    </div>
                    ${line.allow_quantity ? `
                        <div class="bulk-order-qty-stepper">
                            ${line.quantity <= line.min_qty
                                ? `<button type="button" class="bulk-order-qty-remove" aria-label="Quitar ${escapeHtml(line.name)}" data-remove="${idx}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10l-1 16H8L7 4Zm3-2h4l1 2H9l1-2Z"/></svg></button>`
                                : `<button type="button" aria-label="Menos" data-qty-minus="${idx}">−</button>`}
                            <input type="number" min="${line.min_qty}" max="${line.max_qty}" value="${line.quantity}" data-qty="${idx}" aria-label="Cantidad">
                            <button type="button" aria-label="Más" data-qty-plus="${idx}" ${line.quantity >= line.max_qty ? 'disabled' : ''}>+</button>
                        </div>
                    ` : `
                        <button type="button" class="bulk-order-btn bulk-order-qty-remove" aria-label="Quitar ${escapeHtml(line.name)}" data-remove="${idx}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10l-1 16H8L7 4Zm3-2h4l1 2H9l1-2Z"/></svg></button>
                    `}
                </div>
                <div style="display:flex;gap:14px;margin:8px 0 0 68px">
                    <button type="button" class="bulk-order-cart-edit" data-edit="${idx}">✎ Editar selección</button>
                    <button type="button" class="bulk-order-cart-note-toggle" data-note-toggle="${idx}">${line.note ? 'Editar nota' : '+ Nota'}</button>
                </div>
                <div class="bulk-order-cart-note-editor ${line.note ? 'is-open' : ''}" data-note-editor="${idx}">
                    <textarea rows="2" placeholder="Ej.: sin salsa, más crocante…" data-note="${idx}">${escapeHtml(line.note)}</textarea>
                </div>
            </div>
        `;
        }).join('');

        box.querySelectorAll('[data-remove]').forEach(btn => {
            btn.addEventListener('click', () => removeLine(Number(btn.dataset.remove)));
        });
        box.querySelectorAll('[data-qty-minus]').forEach(btn => {
            btn.addEventListener('click', () => changeQty(Number(btn.dataset.qtyMinus), -1));
        });
        box.querySelectorAll('[data-qty-plus]').forEach(btn => {
            btn.addEventListener('click', () => changeQty(Number(btn.dataset.qtyPlus), 1));
        });
        box.querySelectorAll('[data-qty]').forEach(input => {
            input.addEventListener('change', () => setQty(Number(input.dataset.qty), input.value));
        });
        box.querySelectorAll('[data-note]').forEach(ta => {
            ta.addEventListener('input', () => {
                cart[Number(ta.dataset.note)].note = ta.value;
            });
        });
        box.querySelectorAll('[data-note-toggle]').forEach(button => {
            button.addEventListener('click', () => {
                const editor = box.querySelector(`[data-note-editor="${button.dataset.noteToggle}"]`);
                editor?.classList.toggle('is-open');
                if (editor?.classList.contains('is-open')) editor.querySelector('textarea')?.focus();
            });
        });
        box.querySelectorAll('[data-edit]').forEach(button => {
            button.addEventListener('click', () => {
                const index = Number(button.dataset.edit);
                const line = cart[index];
                if (!line) return;
                openCustomizer(line.product_id, {
                    index,
                    variation: line.variation,
                    extras: line.extras || [],
                    product: {
                        id: line.product_id,
                        name: line.name,
                        price: line.base_price ?? line.price,
                        description: line.description,
                        image: line.image,
                        variations: line.variations || [],
                        extras: line.extras_options || [],
                    },
                });
            });
        });

        const total = cart.reduce((s, l) => s + l.price * l.quantity, 0);
        const count = cart.reduce((s, l) => s + l.quantity, 0);
        el('bulkItemsCount').textContent = count + (count === 1 ? ' unidad' : ' unidades');
        const cartCount = el('bulkCartCount');
        if (cartCount) cartCount.textContent = count ? `${count} ${count === 1 ? 'producto' : 'productos'}` : 'Vacío';
        el('bulkGrandTotal').textContent = fmt(total);
        const cartFab = el('bulkCartFab');
        if (cartFab) {
            cartFab.classList.toggle('is-visible', count > 0);
            el('bulkCartFabCount').textContent = count > 99 ? '99+' : count;
        }
        updateFormEnabled();
    }

    el('bulkCartFab')?.addEventListener('click', () => {
        el('bulkCartPanel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    el('bulkProductsUp')?.addEventListener('click', () => {
        const box = el('bulkProductList');
        box?.scrollBy({ top: -Math.max(260, box.clientHeight * .78), behavior: 'smooth' });
    });
    el('bulkProductsDown')?.addEventListener('click', () => {
        const box = el('bulkProductList');
        box?.scrollBy({ top: Math.max(260, box.clientHeight * .78), behavior: 'smooth' });
    });
    el('bulkProductList')?.addEventListener('scroll', updateProductScrollControls, { passive: true });
    window.addEventListener('resize', debounce(updateProductScrollControls, 120));

    el('bulkSearch').addEventListener('input', debounce(loadCatalog, 300));
    el('bulkCategory').addEventListener('change', loadCatalog);
    el('bulkCustomizerClose').addEventListener('click', closeCustomizer);
    el('bulkCustomizer').addEventListener('click', (event) => {
        if (event.target === el('bulkCustomizer')) closeCustomizer();
    });
    el('bulkCustomizerAdd').addEventListener('click', () => {
        if (!customizingProduct) return;
        const variation = document.querySelector('input[name="bulkVariation"]:checked')?.value || '';
        const extras = Array.from(document.querySelectorAll('input[name="bulkExtra"]:checked')).map(input => input.value);
        if (editingCartIndex !== null && cart[editingCartIndex]) {
            const line = cart[editingCartIndex];
            const variationObj = (customizingProduct.variations || []).find(v => v.title === variation);
            const extrasObjs = (customizingProduct.extras || []).filter(e => extras.includes(e.title));
            line.variation = variation;
            line.extras = extras;
            line.extras_options = customizingProduct.extras || [];
            line.variations = customizingProduct.variations || [];
            line.base_price = Number(customizingProduct.price);
            line.price = Number(variationObj ? variationObj.price : line.base_price)
                + extrasObjs.reduce((sum, extra) => sum + Number(extra.price), 0);
            line.key = line.product_id + '|' + variation + '|' + extras.join('|');
            renderCart();
            toast('Selección actualizada');
        } else {
            addProduct(customizingProduct, { variation, extras });
        }
        closeCustomizer();
    });

    if (isAgent) {
        el('contactSearch').addEventListener('input', debounce((e) => searchContacts(e.target.value), 300));
        el('changeContactBtn').addEventListener('click', () => {
            selectedContact = null;
            renderSelectedContact();
        });
        el('openContactCreate')?.addEventListener('click', () => openContactCreate());
        el('closeContactCreate')?.addEventListener('click', closeContactCreate);
        el('contactCreateForm')?.addEventListener('submit', createContact);
        el('contactRequiresInvoice')?.addEventListener('change', syncInvoiceFields);
        el('contactCreateModal')?.addEventListener('click', (event) => {
            if (event.target === el('contactCreateModal')) closeContactCreate();
        });
        document.addEventListener('click', (e) => {
            if (!el('contactPicker').contains(e.target)) {
                el('contactResults').style.display = 'none';
            }
        });
        renderSelectedContact();
    }

    if (isKiosk) {
        el('kioskStartBtn').addEventListener('click', async () => {
            const nameInput = el('kioskCustomerName');
            const errorBox = el('kioskNameError');
            errorBox.style.display = 'none';
            const name = nameInput.value.trim();
            if (name.length < 2) {
                errorBox.textContent = 'Escribe tu nombre para continuar.';
                errorBox.style.display = 'block';
                return;
            }
            const startBtn = el('kioskStartBtn');
            startBtn.disabled = true;
            try {
                const response = await fetch(contactsCreateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ name }),
                });
                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'No se pudo continuar. Intenta de nuevo.');
                }
                selectedContact = data.contact;
                el('kioskNamePanel').style.display = 'none';
                updateFormEnabled();
            } catch (error) {
                errorBox.textContent = error.message || 'No se pudo continuar. Intenta de nuevo.';
                errorBox.style.display = 'block';
            } finally {
                startBtn.disabled = false;
            }
        });

        const setKioskService = (type) => {
            kioskServiceType = type;
            el('kioskServiceLlevar').classList.toggle('bulk-order-btn-primary', type === 'llevar');
            el('kioskServiceLlevar').classList.toggle('bulk-order-btn-ghost', type !== 'llevar');
            el('kioskServiceServir').classList.toggle('bulk-order-btn-primary', type === 'servir');
            el('kioskServiceServir').classList.toggle('bulk-order-btn-ghost', type !== 'servir');
            el('kioskTableWrap').style.display = type === 'servir' ? 'block' : 'none';
            updateFormEnabled();
        };
        el('kioskServiceLlevar').addEventListener('click', () => setKioskService('llevar'));
        el('kioskServiceServir').addEventListener('click', () => setKioskService('servir'));
        el('kioskNewOrderBtn')?.addEventListener('click', () => window.location.reload());
    }

    el('bulkSubmitBtn').addEventListener('click', async () => {
        if (isSubmitting) return;
        if (isAgent && !selectedContact) {
            toast('Selecciona un cliente');
            return;
        }
        if (isKiosk && !selectedContact) {
            toast('Escribe tu nombre primero');
            return;
        }
        if (isKiosk && !kioskServiceType) {
            toast('Elige si es para llevar o para servir');
            return;
        }
        setSubmitting(true);
        const abortController = new AbortController();
        const submitTimeout = setTimeout(() => abortController.abort(), 120000);
        try {
            const payload = {
                items: cart.map(l => ({
                    product_id: l.product_id,
                    quantity: l.quantity,
                    variation: l.variation || null,
                    extras: l.extras || [],
                    note: l.note || null,
                })),
                order_note: el('bulkOrderNote').value.trim() || null,
            };
            if (isAgent) {
                payload.contact_id = selectedContact.id;
                payload.notify_whatsapp = el('bulkNotifyWhatsapp').checked;
                payload.requires_invoice = !!selectedContact.requires_invoice;
                payload.branch_id = Number(el('bulkBranch')?.value) || null;
            }
            if (isKiosk) {
                payload.contact_id = selectedContact.id;
                payload.service_type = kioskServiceType;
                payload.table_reference = kioskServiceType === 'servir' ? (el('kioskTableReference').value.trim() || null) : null;
                payload.payment_method = el('kioskPaymentMethod').value;
                payload.branch_id = Number(el('bulkBranch')?.value) || null;
            }

            const res = await fetch(submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(payload),
                signal: abortController.signal,
            });

            let data = {};
            try {
                data = await res.json();
            } catch (_) {
                throw new Error('Respuesta inválida del servidor. Intenta de nuevo.');
            }

            if (!res.ok || !data.ok) {
                throw new Error(data.message || 'No se pudo enviar el pedido.');
            }

            el('bulkFormScreen').style.display = 'none';
            el('bulkFooterBar').style.display = 'none';
            el('bulkSuccessScreen').style.display = 'block';
            if (data.order_number) {
                el('bulkSuccessOrderNumber').textContent = data.order_number;
            }
            if (data.pdf_url) {
                const pdfWrap = el('bulkSuccessPdfWrap');
                const pdfLink = el('bulkSuccessPdfLink');
                pdfLink.href = data.pdf_url;
                pdfWrap.style.display = 'block';
            }
            if (isAgent) {
                const hint = el('bulkSuccessHint');
                const parts = ['Pedido registrado para ' + (data.contact_name || 'el cliente') + '.'];
                if (el('bulkNotifyWhatsapp').checked) {
                    parts.push('El PDF con botones de confirmación se enviará por WhatsApp en breve.');
                }
                hint.textContent = parts.join(' ');
            }
            if (isKiosk) {
                el('bulkSuccessHint').textContent = '🧾 Pasa a caja con tu número de pedido para cancelar. ¡Gracias, ' + (data.contact_name || selectedContact.name || '') + '!';
            }
        } catch (e) {
            const msg = e.name === 'AbortError'
                ? 'La operación tardó demasiado. Revisa el listado de pedidos por si ya se registró.'
                : (e.message || 'Error al enviar');
            toast(msg);
        } finally {
            clearTimeout(submitTimeout);
            setSubmitting(false);
        }
    });

    loadCatalog();
})();
</script>
