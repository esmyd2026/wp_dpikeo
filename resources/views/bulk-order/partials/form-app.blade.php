@php
    $storefrontSettings = $storefrontSettings ?? null;
    $isAgent = ($mode ?? 'public') === 'agent';
    $isKiosk = ($mode ?? 'public') === 'kiosk';
    $isStorefront = ($mode ?? 'public') === 'storefront';
    // El POS conserva su flujo operativo, pero comparte el detalle moderno
    // (variaciones obligatorias, adicionales y cantidad) con el ecommerce.
    $usesEnhancedCustomizer = $isStorefront || $isAgent || $isKiosk;
    $brandPrimary = $storefrontSettings?->primary_color ?? '#E85D04';
    $brandSecondary = $storefrontSettings?->secondary_color ?? '#7C2D12';
    $brandAccent = $storefrontSettings?->accent_color ?? '#FFD166';
    $logoUrl = $storefrontSettings?->logoUrl() ?? ($logoUrl ?? null);
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
        --wa: {{ $brandPrimary }};
        --wa-dark: {{ $brandSecondary }};
        --lime: {{ $brandAccent }};
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
    .bulk-order-product-media { height:132px; background:#fff; overflow:hidden; }
    button.bulk-order-product-media { width:100%; border:0; padding:0; cursor:pointer; position:relative; text-align:inherit; }
    button.bulk-order-product-media::after { content:'Ver detalles'; position:absolute; right:9px; bottom:9px; padding:5px 8px; border-radius:999px; background:rgba(0,0,0,.68); color:#fff; font-size:.68rem; font-weight:800; opacity:0; transform:translateY(4px); transition:.18s ease; }
    button.bulk-order-product-media:hover::after, button.bulk-order-product-media:focus-visible::after { opacity:1; transform:translateY(0); }
    .bulk-order-product-media img { width:100%;height:100%;object-fit:cover;display:block;transition:transform .25s ease; }
    .bulk-order-product-row:hover .bulk-order-product-media img { transform:scale(1.04); }
    .bulk-order-product-media .fallback { height:100%;display:grid;place-items:center;font-size:2.8rem;background:#f8fafc; }
    .catalog-image-shell { position:relative; overflow:hidden; background:#eef0f2; }
    .catalog-image-shell::before {
        content:'';
        position:absolute;
        z-index:0;
        inset:0;
        background:linear-gradient(105deg,#eceff1 20%,#f7f8f9 38%,#eceff1 56%);
        background-size:220% 100%;
        animation:catalog-image-shimmer 1.25s ease-in-out infinite;
        transition:opacity .18s ease;
    }
    .catalog-image-shell > img.catalog-loading-image { position:relative; z-index:1; opacity:0; transition:opacity .2s ease,transform .25s ease; }
    .catalog-image-shell.is-image-loaded { background:transparent; }
    .catalog-image-shell.is-image-loaded::before { opacity:0; pointer-events:none; }
    .catalog-image-shell.is-image-loaded > img.catalog-loading-image { opacity:1; }
    .catalog-image-shell.is-image-error::before { animation:none; background:#eef0f2; }
    .catalog-image-shell.is-image-error > img.catalog-loading-image { visibility:hidden; }
    @keyframes catalog-image-shimmer { to { background-position-x:-220%; } }
    @media(prefers-reduced-motion:reduce){.catalog-image-shell::before{animation:none}.catalog-image-shell > img.catalog-loading-image{transition:none}}
    .storefront-account-benefit{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:0 0 16px;padding:13px 14px;border:1px solid #f1dca4;border-radius:11px;background:#fffaf0}.storefront-account-benefit-copy{display:grid;gap:3px}.storefront-account-benefit-copy strong{font-size:.82rem}.storefront-account-benefit-copy small{color:#665b45;font-size:.72rem;line-height:1.35}.storefront-account-benefit button{flex:0 0 auto;border:0;border-radius:8px;padding:10px 13px;background:var(--accent);font:inherit;font-size:.74rem;font-weight:850;cursor:pointer}.storefront-account-benefit[hidden]{display:none}
    /* Ya no es un "tip" opcional -- sin cuenta no se puede confirmar el
       pedido, así que lleva un tratamiento más parecido a una advertencia. */
    .storefront-account-benefit.is-required{border-color:#f4c28a;background:#fff4e6}.storefront-account-benefit.is-required .storefront-account-benefit-copy strong{color:#a34e00}
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
    .bulk-order-cart-thumb { width:58px; height:58px; overflow:hidden; border:1px solid #f1f1ef; border-radius:10px; background:#fff; display:grid; place-items:center; }
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
        width: min(420px, calc(100vw - 28px)); text-align: center;
        font-size: .85rem; font-weight: 750; z-index: 2200; display: none;
        box-shadow: 0 12px 34px rgba(0,0,0,.28);
    }
    .bulk-order-menu-layout { display:block; margin-top:12px; }
    .bulk-order-category-chips { display:flex; gap:8px; overflow:auto; padding:2px 0 4px; scrollbar-width:none; }
    .bulk-order-category-chips button { border:1px solid #fed7aa; background:#fff7ed; color:#9a3412; padding:8px 11px; border-radius:999px; white-space:nowrap; font:inherit; font-size:.78rem; font-weight:700; cursor:pointer; }
    .bulk-order-category-chips button.is-active { background:var(--wa); color:#fff; border-color:var(--wa); }
    body.bulk-order-modal-open { overflow:hidden; }
    .bulk-order-modal {
        position:fixed; inset:0; z-index:1200;
        display:none; align-items:flex-end; justify-content:center;
        padding:12px 0 0;
        background:rgba(42,15,0,.58); backdrop-filter:blur(4px);
    }
    .bulk-order-modal.is-open { display:flex; }
    .bulk-order-modal-card {
        display:flex;
        flex-direction:column;
        width:min(100%,560px);
        max-height:calc(100vh - 12px);
        max-height:calc(100dvh - 12px);
        overflow:hidden;
        background:#fff;
        border-radius:24px 24px 0 0;
        animation:bulk-order-sheet .24s ease-out;
    }
    @keyframes bulk-order-sheet { from { transform:translateY(100%); } to { transform:translateY(0); } }
    .bulk-order-modal-head { flex:0 0 auto;display:flex;justify-content:space-between;gap:12px;align-items:flex-start;padding:18px 18px 14px;background:#fff;border-bottom:1px solid #f3eee9; }
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
        background:#fff;
        border-bottom:1px solid #eee5dc;
    }
    .bulk-order-modal-hero img {
        display:block;
        width:100%;
        height:auto;
        max-height:320px;
        object-fit:contain;
    }
    .bulk-order-modal-hero .fallback { height:150px; display:grid; place-items:center; font-size:4rem; background:#f8fafc; }
    /* white-space:pre-line respeta los saltos de línea que el admin escribió
       en "Descripción" (ej. una lista con • por renglón) -- por defecto un
       <p> los colapsa y todo queda pegado en un solo párrafo. */
    .bulk-order-modal-description { margin:8px 0 0; color:#555; line-height:1.48; font-size:.9rem; white-space:pre-line; }
    .bulk-order-close { flex:0 0 42px;border:0;background:#f3f4f6;color:#334155;border-radius:50%;width:42px;height:42px;font-size:1.35rem;line-height:1;cursor:pointer; }
    .bulk-order-close:hover, .bulk-order-close:focus-visible { background:#e2e8f0;outline:3px solid rgba(232,93,4,.2); }
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
        content:attr(data-guide);
        flex:1;
        color:var(--muted);
        font-size:.8rem;
        font-weight:700;
    }
    /* Antes de iniciar el pedido no mostramos una advertencia pasiva: la
       acción principal ya explica exactamente qué debe hacer el cliente. */
    .bulk-order-app[data-channel="storefront"]:not(.is-order-started) .bulk-order-modal-footer::before {
        display:none;
    }
    .bulk-order-modal-add {
        flex:1.35;
        width:auto;
        margin:0;
        padding:14px;
        border-radius:13px;
        font-size:.9rem;
    }
    @media (min-width:640px) {
        .bulk-order-product-list { grid-template-columns:repeat(3,minmax(0,1fr)); }
        .bulk-order-product-media { height:145px; }
        .bulk-order-modal { align-items:center; padding:24px; }
        .bulk-order-modal-card { max-height:calc(100vh - 48px); max-height:calc(100dvh - 48px); border-radius:24px; box-shadow:0 26px 70px rgba(42,15,0,.3); }
    }
    @media (max-width:380px) { .bulk-order-product-list { gap:7px; } .bulk-order-product-content { padding:0 9px 10px; } .bulk-order-product-row strong { font-size:.84rem; } }
    .bulk-order-success {
        display: none;
        text-align: center;
        padding: 40px 20px;
    }
    .bulk-order-success .icon { font-size: 3rem; margin-bottom: 12px; }
    .bulk-order-contact-picker { position:relative; }
    .bulk-order-contact-label { display:block; margin-bottom:7px; color:#334155; font-size:.78rem; font-weight:850; }
    .bulk-order-contact-search {
        position:relative;
        display:flex;
        align-items:center;
        min-height:48px;
        border:1px solid #cbd5e1;
        border-radius:11px;
        background:#fff;
        box-shadow:0 2px 8px rgba(15,23,42,.05);
        transition:border-color .16s ease, box-shadow .16s ease;
    }
    .bulk-order-contact-search:focus-within { border-color:var(--wa); box-shadow:0 0 0 4px rgba(232,93,4,.11); }
    .bulk-order-contact-search > i:first-child { width:44px; color:#64748b; text-align:center; pointer-events:none; }
    .bulk-order-contact-search input { min-width:0; flex:1; border:0; padding:13px 4px; background:transparent; box-shadow:none; color:#172033; font:inherit; font-size:.9rem; }
    .bulk-order-contact-search input:focus { outline:none; box-shadow:none; }
    .bulk-order-contact-search input::placeholder { color:#94a3b8; }
    .bulk-order-contact-chevron { width:42px; color:#94a3b8; text-align:center; pointer-events:none; transition:transform .16s ease; }
    .bulk-order-contact-search.is-open .bulk-order-contact-chevron { transform:rotate(180deg); }
    .bulk-order-contact-help { margin:7px 2px 0; color:#64748b; font-size:.74rem; line-height:1.35; }
    .bulk-order-contact-selected {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: 10px 12px; border: 1px solid #bbf7d0; background: #f0fdf4; border-radius: 10px;
    }
    .bulk-order-contact-selected strong { display: block; color: #14532d; }
    .bulk-order-contact-selected small { color: #166534; }
    .bulk-order-contact-results {
        position:absolute; left:0; right:0; top:calc(100% + 7px);
        max-height:320px; overflow:auto; z-index:30; display:none;
        padding:6px;
        background:#fff; border:1px solid #dbe2ea; border-radius:13px;
        box-shadow:0 18px 44px rgba(15,23,42,.16);
    }
    .bulk-order-contact-results button {
        display:flex; width:100%; align-items:center; gap:10px; text-align:left;
        border:0; border-radius:9px; background:#fff; padding:10px; cursor:pointer; font:inherit;
    }
    .bulk-order-contact-results button:hover,
    .bulk-order-contact-results button.is-active { background:#f8fafc; box-shadow:inset 3px 0 0 var(--wa); }
    .bulk-order-contact-avatar { display:grid; flex:0 0 36px; width:36px; height:36px; place-items:center; border-radius:10px; background:#ecfdf5; color:#047857; font-size:.78rem; font-weight:900; }
    .bulk-order-contact-option-copy { min-width:0; flex:1; }
    .bulk-order-contact-option-copy strong { display:block; overflow:hidden; color:#172033; font-size:.85rem; text-overflow:ellipsis; white-space:nowrap; }
    .bulk-order-contact-option-copy small { display:block; margin-top:3px; overflow:hidden; color:#64748b; font-size:.73rem; text-overflow:ellipsis; white-space:nowrap; }
    .bulk-order-contact-check { color:var(--wa); opacity:0; }
    .bulk-order-contact-results button:hover .bulk-order-contact-check,
    .bulk-order-contact-results button.is-active .bulk-order-contact-check { opacity:1; }
    .bulk-order-contact-empty { padding:12px 10px 8px; color:#64748b; font-size:.8rem; line-height:1.4; }
    .bulk-order-contact-create-option { margin-top:5px; border-top:1px solid #eef2f7 !important; border-radius:0 0 9px 9px !important; color:#9a3412; font-size:.8rem; font-weight:850; }
    .bulk-order-contact-create-option i { display:grid; flex:0 0 34px; width:34px; height:34px; place-items:center; border-radius:9px; background:#fff7ed; color:#ea580c; }
    .bulk-order-contact-modal { z-index:1220; align-items:center; padding:16px; }
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
    #storefrontCartNext.is-loading {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        cursor: wait;
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
        /* Debe quedar por encima del carrito (120), adicionales (1400) y
           selector de entrega (1600), no escondido detrás de ellos. */
        z-index: 2100;
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
        background: #fff;
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
    /* La foto llena toda la tarjeta (recortada si hace falta) en vez de
       quedar chica con margen blanco alrededor -- mismo look que el resto
       del catálogo (ver regla base .bulk-order-product-media img). */
    .bulk-order-app[data-mode="public"] .bulk-order-product-media { background:#fff; }
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
        background:#fff;
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

    /* Identidad común para ecommerce, POS y pedido manual. */
    .bulk-order-app[data-channel="storefront"],
    .bulk-order-app[data-channel="kiosk"],
    .bulk-order-app[data-channel="agent"] {
        --wa: {{ $brandPrimary }};
        --wa-dark: {{ $brandSecondary }};
        --lime: {{ $brandAccent }};
    }
    .bulk-order-app[data-channel="storefront"] .bulk-order-header,
    .bulk-order-app[data-channel="kiosk"] .bulk-order-header {
        min-height:92px;
        padding:18px max(22px,calc((100% - 1460px)/2));
        color:#242424;
        background:#fff;
        border-bottom:4px solid var(--lime);
        box-shadow:0 5px 18px rgba(0,0,0,.07);
    }
    .bulk-order-app[data-channel="agent"] .bulk-order-header {
        color:#fff;
        background:linear-gradient(115deg,var(--wa-dark),var(--wa));
        border-bottom-color:var(--lime);
    }
    .bulk-order-app[data-channel="agent"] .bulk-order-header .bulk-order-brand { display:flex; align-items:center; gap:12px; }
    .bulk-order-app[data-channel="agent"] .bulk-order-header .bulk-order-brand img { width:52px; height:52px; object-fit:contain; padding:4px; border-radius:12px; background:#fff; }
    .bulk-order-app[data-channel="storefront"] .bulk-order-header p::before,
    .bulk-order-app[data-channel="kiosk"] .bulk-order-header p::before,
    .bulk-order-app[data-channel="storefront"] .bulk-order-panel h2::before,
    .bulk-order-app[data-channel="kiosk"] .bulk-order-panel h2::before { content:none; }
    .bulk-order-app[data-channel="storefront"] .bulk-order-header h1,
    .bulk-order-app[data-channel="kiosk"] .bulk-order-header h1 { text-transform:none; letter-spacing:-.04em; }
    .bulk-order-app[data-channel="storefront"] .bulk-order-header .bulk-order-brand img,
    .bulk-order-app[data-channel="kiosk"] .bulk-order-header .bulk-order-brand img { background:#fff; box-shadow:none; object-fit:contain; }
    .bulk-order-app[data-channel="storefront"] .bulk-order-category-chips button.is-active,
    .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips button.is-active { color:#222; background:#fff; border-color:#eee; box-shadow:0 4px 0 var(--lime); }
    .bulk-order-app[data-channel="storefront"] .bulk-order-category-chips button.is-active .bulk-order-category-icon,
    .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips button.is-active .bulk-order-category-icon { color:#fff; background:var(--wa); }
    .bulk-order-category-icon img { width:100%; height:100%; object-fit:contain; display:block; border-radius:6px; }
    .bulk-order-app[data-channel="storefront"] .bulk-order-product-row { border:0; border-radius:6px; box-shadow:0 7px 20px rgba(0,0,0,.1); }
    .bulk-order-app[data-channel="storefront"] .bulk-order-product-media { height:180px; }
    .bulk-order-app[data-channel="storefront"] .bulk-order-product-price { color:#222; }
    .bulk-order-app[data-channel="storefront"] .bulk-order-footer { background:#fff; border-top:1px solid #ddd; }
    .bulk-order-app[data-channel="storefront"] .bulk-order-total { color:#333; }
    .bulk-order-app[data-channel="storefront"] .bulk-order-total strong { color:#222; }
    .bulk-order-app[data-channel="storefront"] .bulk-order-footer .bulk-order-btn-primary { background:var(--lime); color:#222; box-shadow:none; text-transform:none; }
    .bulk-order-app[data-channel="storefront"] .bulk-order-cart-fab { background:#252525; color:#fff; }
    .bulk-order-storefront-customer { display:grid; gap:10px; margin-top:16px; padding-top:16px; border-top:1px solid #ececec; }
    .bulk-order-storefront-customer h3 { margin:0; font-size:1rem; }
    .bulk-order-storefront-customer label { display:grid; gap:5px; color:#444; font-size:.76rem; font-weight:800; }
    .bulk-order-storefront-customer label span { color:#8a8a8a; font-weight:500; }
    .bulk-order-storefront-customer input,.bulk-order-storefront-customer select { width:100%; border:1px solid #d6d6d6; border-radius:8px; padding:10px 11px; background:#fff; font:inherit; }
    .bulk-order-storefront-customer input.is-invalid { border-color:#b42318!important; background:#fff5f5; }
    .bulk-order-field-error { display:none; margin-top:-2px; color:#b42318; font-size:.72rem; font-weight:700; }
    .storefront-checkout-block{display:grid;gap:10px;margin-top:6px;padding:14px;border:1px solid #e5e5e5;border-radius:12px;background:#fafafa}.storefront-checkout-block h4{margin:0;font-size:.9rem}.storefront-checkout-block p{margin:0;color:#666;font-size:.78rem;line-height:1.45}.storefront-bank-instructions{padding:12px;border-left:3px solid var(--brand);background:#fff8e7;white-space:normal}.storefront-invoice-fields{display:none;gap:10px}.storefront-invoice-fields.is-open{display:grid}.storefront-payment-fields,.storefront-card-fields{display:none}.storefront-payment-fields.is-open,.storefront-card-fields.is-open{display:grid;gap:10px}.storefront-card-fields{border-color:#f4c95d;background:#fff9e9}.storefront-card-link{display:flex;min-height:48px;align-items:center;justify-content:center;border-radius:8px;background:var(--brand);color:#fff;text-decoration:none;font-weight:850}.storefront-card-link:hover{filter:brightness(.94)}.storefront-proof-status{font-weight:750;color:#087f5b}.bulk-order-success .storefront-proof-status{margin:12px auto;max-width:520px}
    .storefront-invoice-required-note{margin:0 0 4px;padding:8px 10px;border-radius:8px;background:#fff4e6;color:#a34e00;font-size:.72rem;font-weight:700;line-height:1.4}
    .storefront-invoice-fields label span{color:#b42318!important;font-weight:800!important}
    /* Punto de venta: mismo lenguaje visual del micrositio (tarjetas de
       producto, categorías como chips con ícono grande, pie de página claro)
       -- sin tocar la mecánica del POS (nombre del cliente, para
       llevar/servir, mesa), que sigue igual que antes. */
    .bulk-order-app[data-channel="kiosk"] .bulk-order-menu-panel { margin:0; padding:0; border:0; border-radius:0; background:transparent; box-shadow:none; }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-category-title { display:none; }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips { flex-direction:row; align-items:flex-start; gap:20px; max-height:none; margin:0 0 26px; padding:2px 2px 10px 0; background:none; border:0; }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips button { width:104px; min-width:104px; flex:0 0 104px; flex-direction:column; justify-content:flex-start; min-height:118px; padding:2px 4px 12px; border:0; background:#fff; box-shadow:none; text-align:center; white-space:normal; }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips button span:last-child { white-space:normal; line-height:1.2; font-size:.78rem; }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-category-icon { width:60px; height:56px; padding:0; background:transparent!important; color:inherit!important; font-size:2.1rem; }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-product-row { border:0; border-radius:16px; box-shadow:0 7px 20px rgba(0,0,0,.08); }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-product-media { height:190px; }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-product-price { color:#222; }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-footer { background:#fff; border-top:1px solid #ddd; box-shadow:0 -8px 20px rgba(0,0,0,.06); }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-total { color:#333; }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-total strong { color:#222; }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-footer .bulk-order-btn-primary { background:var(--lime); color:#222; box-shadow:none; text-transform:none; }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-cart-fab { background:#252525; color:#fff; }
    /* El botón del carrito debe llevar el total junto al ícono (igual que en
       el micrositio) para que la operadora vea de un vistazo cuánto lleva el
       pedido antes de abrirlo -- por defecto esas piezas vienen ocultas y
       solo se activan para [data-channel="storefront"]. */
    .bulk-order-app[data-channel="kiosk"] .bulk-order-cart-fab-total { display:block; font-size:1.1rem; }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-cart-fab-arrow { display:block; margin-left:auto; font-size:1.6rem; }
    .bulk-order-app[data-channel="kiosk"] .bulk-order-cart-fab-count { position:static; order:-1; min-width:24px; height:24px; line-height:24px; background:#fff; color:#222; }
    /* El carrito deja de ser una columna fija siempre visible y pasa a ser
       una tarjeta flotante que se abre con el botón del carrito, igual que
       en el micrositio -- sin la ficha de datos del cliente/facturación que
       usa el micrositio, porque en el POS eso ya lo cubre el panel de
       nombre/forma de pago/mesa que sigue igual. */
    .bulk-order-app[data-channel="kiosk"] #bulkOrderFormBody { display:block; }
    .bulk-order-app[data-channel="kiosk"] #bulkCartPanel { display:none; }
    .bulk-order-app[data-channel="kiosk"] #bulkCartPanel.is-storefront-open {
        /* !important: a esta misma tarjeta (.bulk-order-panel, 2do hijo de
           #bulkOrderFormBody) el layout de escritorio del micrositio clásico
           ([data-mode="public"]) le pone position:sticky con más
           especificidad -- sin esto, el carrito flotante del POS queda
           pegado en su lugar normal del documento (al final de la página) en
           vez de flotar fijo sobre el contenido. */
        display:block !important; position:fixed !important; z-index:120;
        top:74px !important; right:32px !important; left:auto !important; bottom:auto !important;
        width:min(416px,calc(100% - 48px)); max-height:calc(100dvh - 98px);
        margin:0; padding:0; border:0; border-radius:18px; background:#fff;
        box-shadow:0 20px 55px rgba(0,0,0,.22); overflow-x:hidden; overflow-y:auto;
    }
    .bulk-order-app[data-channel="kiosk"] #bulkCartPanel.is-storefront-open>.bulk-order-section-head { display:none; }
    .bulk-order-app[data-channel="kiosk"] #bulkCartPanel.is-storefront-open .storefront-cart-header { display:flex; position:sticky; top:0; z-index:2; align-items:center; justify-content:space-between; padding:18px 20px 10px; background:#fff; }
    .bulk-order-app[data-channel="kiosk"] #bulkCartPanel.is-storefront-open .storefront-cart-header h2 { margin:0; font-size:1.1rem; }
    .bulk-order-app[data-channel="kiosk"] .storefront-cart-back { font-size:0; }
    .bulk-order-app[data-channel="kiosk"] .storefront-cart-back:after { content:'×'; font-size:1.6rem; }
    .bulk-order-app[data-channel="kiosk"] .storefront-cart-clear { text-decoration:underline; color:#075985; }
    .bulk-order-app[data-channel="kiosk"] #bulkCartPanel.is-storefront-open #bulkCartItems { padding:0 20px; }
    .bulk-order-app[data-channel="kiosk"] #bulkCartPanel.is-storefront-open .bulk-order-cart-item { border:0; border-bottom:1px solid #eee; border-radius:0; box-shadow:none; margin:0; padding:14px 0; }
    .bulk-order-app[data-channel="kiosk"] #bulkCartPanel.is-storefront-open #bulkCartEmpty { padding:0 20px 16px; }
    .bulk-order-app[data-channel="kiosk"] #bulkCartPanel.is-storefront-open>label,
    .bulk-order-app[data-channel="kiosk"] #bulkCartPanel.is-storefront-open>textarea,
    .bulk-order-app[data-channel="kiosk"] #bulkCartPanel.is-storefront-open>div:not(.storefront-cart-header) { margin-left:20px; margin-right:20px; }
    .bulk-order-app[data-channel="kiosk"] #bulkCartPanel.is-storefront-open>#bulkOrderNote {
        width:calc(100% - 40px);
    }
    .kiosk-order-options{padding:20px 0 22px;border-top:1px solid #ececec}
    .kiosk-order-options-title{display:block;margin:0 0 12px;color:#222;font-size:.93rem;font-weight:850}
    .kiosk-service-options{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px}
    .kiosk-service-option{position:relative;display:grid;min-width:0;grid-template-columns:42px minmax(0,1fr) 22px;align-items:center;gap:10px;padding:13px 12px;border:1px solid #dedede;border-radius:12px;background:#fff;color:#222;text-align:left;font:inherit;cursor:pointer;transition:border-color .18s,background .18s,box-shadow .18s}
    .kiosk-service-option:hover{border-color:#c8c8c8;background:#fafafa}
    .kiosk-service-option.is-selected{border-color:var(--lime);background:#fff9e8;box-shadow:inset 4px 0 var(--lime)}
    .kiosk-service-icon{display:grid;width:42px;height:42px;place-items:center;border-radius:50%;background:#f5f5f5;font-size:1.25rem}
    .kiosk-service-copy{display:grid;gap:2px;min-width:0}
    .kiosk-service-copy strong{font-size:.88rem;line-height:1.2}
    .kiosk-service-copy small{overflow:hidden;color:#777;font-size:.7rem;line-height:1.25;text-overflow:ellipsis;white-space:nowrap}
    .kiosk-service-check{display:grid;width:21px;height:21px;place-items:center;border:2px solid #d7d7d7;border-radius:50%;color:transparent;font-size:.74rem;font-weight:900}
    .kiosk-service-option.is-selected .kiosk-service-check{border-color:var(--lime);background:var(--lime);color:#222}
    .kiosk-payment-field{display:grid;gap:7px}
    .kiosk-payment-field>span{color:#222;font-size:.9rem;font-weight:850}
    .kiosk-select-wrap{position:relative;display:flex;align-items:center}
    .kiosk-select-icon{position:absolute;z-index:1;left:14px;font-size:1.15rem;pointer-events:none}
    .kiosk-select-wrap:after{content:'⌄';position:absolute;right:15px;top:50%;transform:translateY(-58%);color:#444;font-size:1.2rem;font-weight:900;pointer-events:none}
    .bulk-order-app[data-channel="kiosk"] #kioskPaymentMethod{width:100%;min-height:52px;padding:0 42px 0 48px;border:1px solid #d8d8d8;border-radius:12px;appearance:none;background:#fff;color:#222;font:inherit;font-weight:750;cursor:pointer}
    .bulk-order-app[data-channel="kiosk"] #kioskPaymentMethod:focus{border-color:var(--lime);outline:3px solid color-mix(in srgb,var(--lime) 24%,transparent)}
    .kiosk-payment-help{color:#777;font-size:.72rem;line-height:1.35}
    .bulk-order-app[data-channel="kiosk"] #kioskTableWrap{padding:13px;border-radius:12px;background:#f7f7f7}
    .bulk-order-app[data-channel="kiosk"] #kioskTableReference{width:100%;min-height:46px;border:1px solid #d8d8d8;border-radius:9px;padding:0 12px;font:inherit}
    @media(max-width:480px){.kiosk-service-options{grid-template-columns:1fr}.kiosk-service-copy small{white-space:normal}}
    @media (min-width:720px) {
        .bulk-order-app[data-channel="kiosk"] .bulk-order-menu-layout { display:block; }
        .bulk-order-app[data-channel="kiosk"] .bulk-order-category-rail { position:static; top:auto; width:100%; max-height:none; }
        .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips { flex:none; width:100%; position:static; top:auto; max-height:none; overflow-x:auto; overflow-y:hidden; }
        .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips button { width:104px; }
    }
    /* POS: catálogo con la misma escala y jerarquía visual del micrositio. */
    .bulk-order-app[data-channel="kiosk"]{min-height:100dvh;padding-bottom:90px;overflow-x:clip;background:#fff}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-header{display:none}
    .bulk-order-app[data-channel="kiosk"] .storefront-catalog-header{display:block;background:#fff;box-shadow:0 12px 26px rgba(0,0,0,.05)}
    .bulk-order-app[data-channel="kiosk"] .storefront-catalog-admin{display:inline-flex;min-height:44px;margin-left:auto;padding:0 18px;align-items:center;gap:9px;border:1px solid #dedede;border-radius:10px;background:#fff;color:#242424;text-decoration:none;font-size:.88rem;font-weight:800}
    .bulk-order-app[data-channel="kiosk"] .storefront-catalog-admin:hover{border-color:var(--lime);background:#fff9e8}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-wrap{max-width:1800px;padding:18px 28px 110px}
    .bulk-order-app[data-channel="kiosk"] #kioskNamePanel{max-width:1180px;margin:0 auto 28px;border:1px solid #e7e7e7;border-radius:18px;box-shadow:none}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-menu-panel>.bulk-order-section-head{display:none}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-filters{display:none}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips{justify-content:center;gap:26px;margin:0 0 42px;padding:0 10px 7px;scrollbar-width:none}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips::-webkit-scrollbar{display:none}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips button{position:relative;flex:0 0 118px;width:118px;min-width:118px;min-height:128px;padding:2px 5px 18px;gap:8px;border-radius:0}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips button.is-active{border:0;background:#fff;box-shadow:none}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips button.is-active:after{content:'';position:absolute;left:0;right:0;bottom:0;height:5px;background:var(--lime)}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-category-icon{width:80px;height:74px;font-size:3rem}
    .bulk-order-app[data-channel="kiosk"] .storefront-category-heading{display:block;margin:0 0 24px;font-size:2rem;line-height:1.15;letter-spacing:-.035em}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-product-list{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:36px;max-height:none!important;padding:0;overflow:visible!important}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-product-row{min-height:365px;border-radius:6px}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-product-media{height:210px;background:#fff}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-product-content{display:flex;flex:1;flex-direction:column;padding:20px}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-product-price{margin-top:auto;padding-top:8px;font-weight:850}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-scroll-controls{display:none!important}
    .bulk-order-app[data-channel="kiosk"] .storefront-product-title{display:block;margin:18px 0 8px;font-size:1.7rem}
    .bulk-order-app[data-channel="kiosk"] .storefront-product-base-price{display:block;margin-bottom:16px;font-size:1.15rem;font-weight:850}
    .bulk-order-app[data-channel="kiosk"] .storefront-variation-section{display:block;margin:22px 0}
    .bulk-order-app[data-channel="kiosk"] .storefront-extra-trigger{display:flex;width:100%;margin:22px 0;padding:20px 0;justify-content:space-between;border:0;border-top:1px solid #eee;border-bottom:1px solid #eee;background:#fff;font:inherit;font-weight:800}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-inline-extras{display:none}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-addon-sheet.is-open{display:flex;position:fixed;z-index:1400;inset:0;align-items:flex-end;background:rgba(0,0,0,.48)}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-modal{align-items:stretch;padding:0;background:#fff}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-modal-card{display:flex;width:100%;max-width:none;height:100dvh;max-height:none;flex-direction:column;border-radius:0}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-modal-head{flex-direction:row-reverse;justify-content:flex-end;gap:14px;padding:20px 28px;border:0}
    .bulk-order-app[data-channel="kiosk"] #bulkCustomizerPrice{display:none}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-close{font-size:0}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-close:after{content:'‹';font-size:2.2rem}
    .bulk-order-app[data-channel="kiosk"] .bulk-order-modal-footer{position:fixed;z-index:2;left:0;right:0;bottom:0;display:flex;flex-wrap:wrap;align-items:center;gap:14px;padding:18px 28px 0;background:#fff;box-shadow:0 -8px 30px rgba(0,0,0,.12)}
    .bulk-order-app[data-channel="kiosk"] .storefront-detail-qty{display:flex;align-items:center;border:1px solid #aaa;border-radius:999px;overflow:hidden}
    .bulk-order-app[data-channel="kiosk"] .storefront-detail-total{display:block;margin-left:auto;font-size:1.7rem}
    .bulk-order-app[data-channel="kiosk"] .storefront-detail-actions{display:grid;width:calc(100% + 56px);margin:0 -28px;grid-template-columns:1fr}
    .bulk-order-app[data-channel="kiosk"] .storefront-detail-actions .bulk-order-modal-add{width:100%;min-height:62px;margin:0;border-radius:0;background:var(--lime);color:#222}
    @media(min-width:900px){
        .bulk-order-app[data-channel="kiosk"] .bulk-order-category-rail{position:sticky;z-index:70;top:0;margin:-18px -28px 0;padding:12px 28px 10px;background:rgba(255,255,255,.98);box-shadow:0 10px 22px rgba(0,0,0,.07);backdrop-filter:blur(10px)}
        .bulk-order-app[data-channel="kiosk"] .bulk-order-addon-sheet.is-open{align-items:center;justify-content:center;padding:30px}
        .bulk-order-app[data-channel="kiosk"] .bulk-order-addon-sheet-card{width:min(820px,100%);max-height:86dvh;border-radius:22px}
        .bulk-order-app[data-channel="kiosk"] .bulk-order-modal-card{overflow-y:auto}
        .bulk-order-app[data-channel="kiosk"] .bulk-order-modal-options{flex:none;width:min(1360px,calc(100% - 64px));display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);column-gap:clamp(36px,5vw,76px);align-content:start;padding:28px 0 110px;overflow:visible}
        .bulk-order-app[data-channel="kiosk"] .bulk-order-modal-hero{grid-column:1;grid-row:1 / span 12;width:100%;height:410px;margin-top:10px}
        .bulk-order-app[data-channel="kiosk"] .storefront-product-title,.bulk-order-app[data-channel="kiosk"] .storefront-product-base-price,.bulk-order-app[data-channel="kiosk"] .bulk-order-modal-description,.bulk-order-app[data-channel="kiosk"] .storefront-variation-section,.bulk-order-app[data-channel="kiosk"] .storefront-extra-trigger{grid-column:2;min-width:0}
        .bulk-order-app[data-channel="kiosk"] .storefront-product-title{margin-top:28px;font-size:clamp(2.25rem,3.2vw,3rem)}
    }
    @media(min-width:900px) and (max-width:1199.98px){.bulk-order-app[data-channel="kiosk"] .bulk-order-product-list{grid-template-columns:repeat(4,minmax(0,1fr));gap:22px}}
    @media(min-width:720px) and (max-width:899.98px){.bulk-order-app[data-channel="kiosk"] .bulk-order-product-list{grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}}
    @media(max-width:719.98px){
        .bulk-order-app[data-channel="kiosk"] .storefront-catalog-header{display:none}
        .bulk-order-app[data-channel="kiosk"] .bulk-order-wrap{padding:14px 16px 110px}
        .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips{justify-content:flex-start;gap:8px;margin-bottom:26px;padding:2px 4px 10px;scroll-snap-type:x mandatory}
        .bulk-order-app[data-channel="kiosk"] .bulk-order-category-chips button{flex:0 0 102px;width:102px;min-width:102px;height:142px;min-height:142px;padding:2px 4px 12px;overflow:hidden;scroll-snap-align:start}
        .bulk-order-app[data-channel="kiosk"] .bulk-order-category-icon{width:72px;height:78px;min-height:78px}
        .bulk-order-app[data-channel="kiosk"] .bulk-order-product-list{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .bulk-order-app[data-channel="kiosk"] .bulk-order-product-row{min-height:320px}
        .bulk-order-app[data-channel="kiosk"] .bulk-order-product-media{height:160px}
        .bulk-order-app[data-channel="kiosk"] .bulk-order-product-content{padding:14px}
        .bulk-order-app[data-channel="kiosk"] .storefront-catalog-admin-label{display:none}
        .bulk-order-app[data-channel="kiosk"] .storefront-catalog-admin{width:44px;padding:0;justify-content:center}
    }
    .storefront-fulfillment{display:none}
    .bulk-order-app[data-channel="storefront"]{min-height:100dvh;padding-bottom:90px;overflow:visible;overflow-x:clip;background:#fff}
    .bulk-order-app[data-channel="storefront"] .storefront-fulfillment{display:none!important}
    .bulk-order-app[data-channel="storefront"] .bulk-order-header,.bulk-order-app[data-channel="storefront"] .bulk-order-footer{display:none}
    .storefront-catalog-header{display:none}.bulk-order-app[data-channel="storefront"] .storefront-catalog-header{display:block;background:#fff;box-shadow:0 12px 26px rgba(0,0,0,.05)}.storefront-catalog-nav{display:flex;min-height:124px;align-items:center;padding:20px 34px}.storefront-catalog-menu{width:36px;height:36px;padding:3px;border:0;background:transparent;cursor:pointer}.storefront-catalog-menu span{display:block;height:3px;margin:6px 0;border-radius:4px;background:#2d2d2d}.storefront-catalog-logo{width:58px;height:58px;margin-left:20px;object-fit:contain}.storefront-catalog-tabs{display:flex;height:62px;padding:0 82px;align-items:flex-end}.storefront-catalog-tabs span{position:relative;padding-bottom:17px}.storefront-catalog-tabs span:after{content:'';position:absolute;left:0;right:0;bottom:0;height:4px;background:var(--lime)}
    .bulk-order-app[data-channel="storefront"] .bulk-order-wrap{max-width:1800px;padding:18px 28px 110px}
    .bulk-order-app[data-channel="storefront"] #bulkOrderFormBody{display:block}
    .bulk-order-app[data-channel="storefront"] .storefront-fulfillment{display:flex;width:min(720px,calc(100% - 30px));margin:0 auto 44px;padding:15px 22px;gap:14px;align-items:center;border:0;border-radius:999px;background:#f7f7f7;text-align:left;font:inherit}.storefront-fulfillment-icon{font-size:1.8rem}.storefront-fulfillment span{display:block}.storefront-fulfillment strong{font-size:1rem}.storefront-fulfillment small{margin-top:2px;color:#696969;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .bulk-order-app[data-channel="storefront"] .bulk-order-menu-panel{margin:0;padding:0;border:0;border-radius:0;background:#fff;box-shadow:none}
    .bulk-order-app[data-channel="storefront"] .bulk-order-menu-panel>.bulk-order-section-head,.bulk-order-app[data-channel="storefront"] .bulk-order-filters{display:none}
    .bulk-order-app[data-channel="storefront"] .bulk-order-menu-layout{display:block!important;min-width:0}
    .bulk-order-app[data-channel="storefront"] .bulk-order-category-rail{position:static;top:auto;min-width:0}
    .bulk-order-app[data-channel="storefront"] .bulk-order-category-title{display:none}
    .bulk-order-app[data-channel="storefront"] .bulk-order-category-chips{display:flex;flex-direction:row;align-items:flex-start;justify-content:center;gap:26px;max-height:none;margin:0 0 42px;padding:0 10px 7px;overflow-x:auto;overflow-y:hidden;-ms-overflow-style:none;scrollbar-width:none}
    .bulk-order-app[data-channel="storefront"] .bulk-order-category-chips::-webkit-scrollbar{display:none}
    .bulk-order-app[data-channel="storefront"] .bulk-order-category-chips button{position:relative;flex:0 0 118px;width:118px;min-width:118px;min-height:128px;padding:2px 5px 18px;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;gap:8px;border:0;border-radius:0;background:#fff;color:#333;box-shadow:none;text-align:center;white-space:normal}
    .bulk-order-app[data-channel="storefront"] .bulk-order-category-chips button.is-active{border:0;background:#fff;box-shadow:none}
    .bulk-order-app[data-channel="storefront"] .bulk-order-category-chips button.is-active:after{content:'';position:absolute;left:0;right:0;bottom:0;height:5px;background:var(--lime)}
    .bulk-order-app[data-channel="storefront"] .bulk-order-category-icon{width:80px;height:74px;padding:0;background:transparent!important;color:inherit!important;font-size:3rem}
    .bulk-order-app[data-channel="storefront"] .storefront-category-heading{display:block;margin:0 0 24px;font-size:2rem;line-height:1.15;letter-spacing:-.035em}
    .bulk-order-app[data-channel="storefront"] .bulk-order-product-list{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:36px;max-height:none!important;padding:0;overflow:visible!important}
    .bulk-order-app[data-channel="storefront"] .bulk-order-product-row{min-height:300px;display:flex;flex-direction:column;overflow:hidden}
    .bulk-order-app[data-channel="storefront"] .bulk-order-product-media{height:210px;background:#fff}
    .bulk-order-app[data-channel="storefront"] .bulk-order-product-content{display:flex;flex:1;flex-direction:column;padding:20px}.bulk-order-app[data-channel="storefront"] .bulk-order-product-content strong{font-size:1.05rem}.bulk-order-app[data-channel="storefront"] .bulk-order-product-price{margin-top:auto;padding-top:12px;font-weight:850}
    .bulk-order-app[data-channel="storefront"] .bulk-order-scroll-controls{display:none!important}
    .bulk-order-app[data-channel="storefront"] #bulkCartPanel{display:none}
    .bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-storefront-open{display:block;position:fixed;z-index:120;inset:0;margin:0;padding:0 28px 210px;border:0;border-radius:0;background:#fff;overflow:auto}
    .storefront-cart-header,.storefront-cart-delivery,.storefront-cart-promo,.storefront-cart-summary{display:none}
    .bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-storefront-open .storefront-cart-header{display:flex;position:sticky;z-index:3;top:0;margin:0 -28px 28px;padding:24px 28px;align-items:center;justify-content:space-between;background:#fff}.storefront-cart-header h2{margin:0;font-size:1.5rem}.storefront-cart-back,.storefront-cart-clear{border:0;background:none;font:inherit;cursor:pointer}.storefront-cart-back{font-size:2rem}.storefront-cart-clear{text-decoration:underline;color:#075985}
    .bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-storefront-open>.bulk-order-section-head{display:none}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-storefront-open .storefront-cart-delivery,.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-storefront-open .storefront-cart-promo{display:flex;max-width:900px;margin:0 auto 28px;padding:22px;gap:14px;align-items:center;border-radius:22px;background:#f8f8f8}.storefront-cart-delivery{width:100%;border:0;color:#151515;text-align:left;font:inherit;cursor:pointer}.storefront-cart-delivery:hover,.storefront-cart-delivery:focus-visible{background:#fff8e7!important;outline:2px solid var(--brand);outline-offset:2px}.storefront-cart-delivery div{display:grid;min-width:0}.storefront-cart-delivery small{overflow:hidden;color:#666;text-overflow:ellipsis;white-space:nowrap}.storefront-cart-change{margin-left:auto;color:var(--brand);font-size:.82rem;font-weight:850;white-space:nowrap}.storefront-cart-promo{width:100%;justify-content:space-between;border:0;color:#151515;font:inherit;font-weight:800;text-align:left;cursor:pointer}.storefront-cart-promo:hover,.storefront-cart-promo:focus-visible{background:#fff8e7!important;outline:2px solid var(--brand);outline-offset:2px}
    .bulk-order-app[data-channel="storefront"] #bulkCartItems{max-width:900px;margin:auto}.bulk-order-app[data-channel="storefront"] .bulk-order-cart-item{border:0;border-bottom:1px solid #eee;border-radius:0;box-shadow:none}
    .bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-storefront-open .bulk-order-storefront-customer,.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-storefront-open>label,.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-storefront-open>#bulkOrderNote{display:none;max-width:900px;margin-left:auto;margin-right:auto}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-checkout-step .bulk-order-storefront-customer,.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-checkout-step>label,.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-checkout-step>#bulkOrderNote{display:grid}
    .bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-storefront-open .storefront-cart-summary{display:block;position:fixed;z-index:4;left:0;right:0;bottom:0;padding:24px 28px 0;border-radius:36px 36px 0 0;background:#fff;box-shadow:0 -10px 35px rgba(0,0,0,.14)}.storefront-cart-total-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;font-size:1.4rem}.storefront-cart-total-row strong{font-size:1.8rem}.storefront-cart-actions{display:grid;grid-template-columns:1fr 1fr;margin:0 -28px}.storefront-cart-actions button{min-height:76px;border:1px solid #bbb;background:#fff;font:inherit;font-weight:750}.storefront-cart-actions button:last-child{border-color:var(--lime);background:var(--lime)}
    .bulk-order-app[data-channel="storefront"] .bulk-order-cart-fab{z-index:90;left:50%;right:auto;bottom:92px;width:min(660px,calc(100% - 48px));height:68px;padding:0 22px;border-radius:999px;transform:translate(-50%,18px);display:none;align-items:center;gap:13px}.bulk-order-app[data-channel="storefront"] .bulk-order-cart-fab.is-visible{display:flex;transform:translate(-50%,0)}.bulk-order-cart-fab-total{display:none}.bulk-order-app[data-channel="storefront"] .bulk-order-cart-fab-total{display:block;font-size:1.25rem}.bulk-order-cart-fab-arrow{display:none}.bulk-order-app[data-channel="storefront"] .bulk-order-cart-fab-arrow{display:block;margin-left:auto;font-size:2rem}.bulk-order-app[data-channel="storefront"] .bulk-order-cart-fab-count{position:static;order:-1;min-width:24px;height:24px;line-height:24px;background:#fff;color:#222}
    .storefront-bottom-nav,.bulk-order-app[data-channel="storefront"] .storefront-bottom-nav{display:none}.storefront-bottom-nav button{border:0;background:transparent;color:#777;font:inherit}.storefront-bottom-nav button:first-child{color:var(--wa);font-weight:800}.storefront-bottom-nav span{display:flex;justify-content:center;margin-bottom:3px}.storefront-bottom-nav span svg{display:block}
    .bulk-order-app[data-channel="storefront"] .bulk-order-modal{align-items:stretch;padding:0;background:#fff}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-card{width:100%;max-width:none;height:100dvh;max-height:none;border-radius:0;display:flex;flex-direction:column}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-head{flex-direction:row-reverse;justify-content:flex-end;gap:14px;padding:20px 28px;border:0}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-head h3{display:block;margin:0;font-size:1rem}.bulk-order-app[data-channel="storefront"] #bulkCustomizerPrice{display:none}.bulk-order-app[data-channel="storefront"] .bulk-order-close{font-size:0}.bulk-order-app[data-channel="storefront"] .bulk-order-close:after{content:'‹';font-size:2.2rem}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-options{width:min(850px,100%);margin:auto;padding:0 28px 180px}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-hero{height:360px;border:0;background:#fff}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-hero img{object-fit:contain}.storefront-product-title{display:none}.bulk-order-app[data-channel="storefront"] .storefront-product-title{display:block;margin:22px 0 10px;font-size:2rem;line-height:1.15}.storefront-product-base-price{display:none}.bulk-order-app[data-channel="storefront"] .storefront-product-base-price{display:block;margin:8px 0 20px;font-size:1.25rem;font-weight:850}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-description{font-size:1rem;line-height:1.55;color:#686868}.bulk-order-app[data-channel="storefront"] .bulk-order-choice{border:0}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-footer{position:fixed;z-index:2;left:0;right:0;bottom:0;display:flex;flex-wrap:wrap;align-items:center;gap:14px;padding:18px 28px 0;background:#fff;box-shadow:0 -8px 30px rgba(0,0,0,.12)}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-add{margin:0;min-height:62px;border-radius:0;background:var(--lime);color:#222}.storefront-detail-qty{display:none}.bulk-order-app[data-channel="storefront"] .storefront-detail-qty{display:flex;align-items:center;border:1px solid #aaa;border-radius:999px;overflow:hidden}.storefront-detail-qty button{width:44px;height:42px;border:0;background:#fff;font-size:1.5rem}.storefront-detail-qty span{min-width:36px;text-align:center}.storefront-detail-total{display:none}.bulk-order-app[data-channel="storefront"] .storefront-detail-total{display:block;margin-left:auto;font-size:1.7rem}.storefront-detail-actions{display:none}.bulk-order-app[data-channel="storefront"] .storefront-detail-actions{display:grid;width:calc(100% + 56px);margin:0 -28px;grid-template-columns:1fr 1fr}.storefront-detail-actions>button{min-height:62px;border:1px solid #bbb;background:#fff;font:inherit}.storefront-detail-actions .bulk-order-modal-add{width:100%}
    .bulk-order-app[data-channel="storefront"] .bulk-order-modal-options>h4{font-size:1.2rem!important;margin-top:28px!important}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-options>.bulk-order-choice{display:inline-flex;width:auto;margin:10px 14px 10px 0;padding:0}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-options>.bulk-order-choice label{display:flex;width:76px;height:76px;align-items:center;justify-content:center;border:1px solid #bbb;border-radius:50%;font-size:1.1rem}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-options>.bulk-order-choice:has(input:checked) label{border:3px solid var(--lime)}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-options>.bulk-order-choice small{display:none}
    .bulk-order-addon-sheet{display:none}.bulk-order-app[data-channel="storefront"] .bulk-order-addon-sheet.is-open{display:flex;position:fixed;z-index:1400;inset:0;align-items:flex-end;background:rgba(0,0,0,.55)}.bulk-order-addon-sheet-card{display:flex;width:100%;max-height:82dvh;padding:0;border-radius:34px 34px 0 0;background:#fff;overflow:hidden;flex-direction:column}.bulk-order-addon-head{display:flex;flex:0 0 auto;align-items:center;gap:18px;margin:0;padding:26px 28px 12px}.bulk-order-addon-head button{border:0;background:none;font-size:2rem}.bulk-order-addon-head h3{margin:0;font-size:1.45rem}.bulk-order-addon-options{min-height:0;padding:0 28px 18px;overflow:auto}.bulk-order-addon-section{margin-top:22px}.bulk-order-addon-section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}.bulk-order-addon-section-head h4{margin:0;font-size:1.05rem}.bulk-order-required-badge{padding:5px 9px;border-radius:999px;background:#fff2bf;color:#6b4b00;font-size:.7rem;font-weight:850}.bulk-order-addon-option{display:flex;width:100%;min-height:76px;align-items:center;gap:15px;border:0;background:#fff;text-align:left;font:inherit;font-weight:750}.bulk-order-addon-option.is-selected{background:#fff9e8;box-shadow:inset 4px 0 var(--lime)}.bulk-order-addon-option-icon{display:flex;width:54px;height:54px;flex:0 0 54px;align-items:center;justify-content:center;border-radius:50%;background:#fafafa}.bulk-order-addon-option-icon.is-choice{border:2px solid #dedede;background:#fff;color:transparent}.bulk-order-addon-option-icon.is-choice:after{content:''}.bulk-order-addon-option.is-selected .bulk-order-addon-option-icon.is-choice{border-color:var(--lime);background:var(--lime);color:#222}.bulk-order-addon-option.is-selected .bulk-order-addon-option-icon.is-choice:after{content:'✓';font-size:1.25rem;font-weight:900}.bulk-order-addon-option small{margin-left:auto;color:#666;font-weight:500}.bulk-order-addon-actions{flex:0 0 auto;padding:14px 28px 22px;border-top:1px solid #ececec;background:#fff}.bulk-order-addon-status{min-height:20px;margin:0 0 8px;color:#8a5a00;font-size:.78rem;font-weight:750}.bulk-order-addon-add{position:relative;width:100%;min-height:56px;border:0;border-radius:6px;background:var(--lime);color:#222;font:inherit;font-weight:850;cursor:pointer}.bulk-order-addon-add:disabled{cursor:not-allowed;opacity:.5}.bulk-order-addon-add.is-loading{color:transparent}.bulk-order-addon-add.is-loading:after{content:'';position:absolute;left:50%;top:50%;width:20px;height:20px;margin:-10px;border:3px solid rgba(34,34,34,.28);border-top-color:#222;border-radius:50%;animation:bulk-order-spin .7s linear infinite}@keyframes bulk-order-spin{to{transform:rotate(360deg)}}.storefront-extra-trigger{display:none}.bulk-order-app[data-channel="storefront"] .storefront-extra-trigger{display:flex;width:100%;margin:22px 0;padding:20px 0;justify-content:space-between;border:0;border-top:1px solid #eee;border-bottom:1px solid #eee;background:#fff;font:inherit;font-weight:800;cursor:pointer}
    .bulk-order-addon-option-icon{color:#555;font-size:1.65rem;font-weight:400}.bulk-order-addon-option-icon img{width:42px;height:42px;object-fit:contain}
    .bulk-order-addon-option.is-selected .bulk-order-addon-option-icon:not(.is-choice){background:var(--lime);color:#222}
    .bulk-order-addon-option.is-selected .bulk-order-addon-option-icon:not(.is-choice)>*{display:none}
    .bulk-order-addon-option.is-selected .bulk-order-addon-option-icon:not(.is-choice)::after{content:'✓';font-size:1.25rem;font-weight:900}
    .bulk-order-addon-copy{display:grid;gap:3px;min-width:0}.bulk-order-addon-copy strong{line-height:1.25}.bulk-order-addon-copy span{color:#777;font-size:.78rem;font-weight:500;line-height:1.25}.bulk-order-addon-option .bulk-order-addon-price{margin-left:auto;color:#666;font-size:.84rem;font-weight:500;white-space:nowrap}
    .bulk-order-app[data-channel="storefront"] .bulk-order-inline-extras{display:none}
    .storefront-variation-section{display:none}.bulk-order-app[data-channel="storefront"] .storefront-variation-section,.bulk-order-app[data-channel="agent"] .storefront-variation-section{display:block;margin:22px 0}.storefront-variation-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}.storefront-variation-head h3{margin:0;font-size:1.05rem}.storefront-variation-list{display:grid;gap:10px}.storefront-variation-option{display:grid;width:100%;grid-template-columns:42px minmax(0,1fr) auto;align-items:center;gap:13px;padding:14px;border:1px solid #e2e2e2;border-radius:10px;background:#fff;color:#222;text-align:left;font:inherit;cursor:pointer}.storefront-variation-option:hover{border-color:#c9c9c9}.storefront-variation-option.is-selected{border-color:var(--lime);background:#fff9e8;box-shadow:inset 4px 0 var(--lime)}.storefront-variation-check{display:grid;width:36px;height:36px;place-items:center;border:2px solid #ddd;border-radius:50%;font-weight:900}.storefront-variation-option.is-selected .storefront-variation-check{border-color:var(--lime);background:var(--lime)}.storefront-variation-copy{display:grid;gap:3px;min-width:0}.storefront-variation-copy strong{font-size:.94rem}.storefront-variation-copy small{overflow:hidden;color:#747474;font-size:.78rem;line-height:1.35;text-overflow:ellipsis;white-space:nowrap}.storefront-variation-price{font-size:.9rem;font-weight:850;white-space:nowrap}
    .storefront-cart-products-title{display:none}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-storefront-open .storefront-cart-products-title{display:block;max-width:900px;margin:34px auto 18px;font-size:2rem}
    .storefront-product-info{display:none}
    .bulk-order-app[data-channel="storefront"]:not(.is-order-started) .bulk-order-modal-options>h4,.bulk-order-app[data-channel="storefront"]:not(.is-order-started) .bulk-order-modal-options>.bulk-order-choice,.bulk-order-app[data-channel="storefront"]:not(.is-order-started) .storefront-variation-section,.bulk-order-app[data-channel="storefront"]:not(.is-order-started) .storefront-extra-trigger,.bulk-order-app[data-channel="storefront"]:not(.is-order-started) .storefront-product-base-price,.bulk-order-app[data-channel="storefront"]:not(.is-order-started) .storefront-detail-qty,.bulk-order-app[data-channel="storefront"]:not(.is-order-started) .storefront-detail-total,.bulk-order-app[data-channel="storefront"]:not(.is-order-started) #storefrontPayNow{display:none}.bulk-order-app[data-channel="storefront"]:not(.is-order-started) .storefront-detail-actions{grid-template-columns:1fr}.bulk-order-app[data-channel="storefront"]:not(.is-order-started) .bulk-order-modal-add{width:100%;min-height:72px;background:var(--lime)}
    @media(min-width:720px){.bulk-order-app[data-channel="storefront"] .bulk-order-category-rail{position:sticky;z-index:70;top:0;margin:-18px -28px 0;padding:12px 28px 10px;background:rgba(255,255,255,.98);box-shadow:0 10px 22px rgba(0,0,0,.07);backdrop-filter:blur(10px)}.bulk-order-app[data-channel="storefront"] .bulk-order-category-chips{position:static!important;margin:0;padding-bottom:7px}.bulk-order-app[data-channel="storefront"] .bulk-order-products-stage{padding-top:34px}}
    @media(min-width:720px){
        .bulk-order-app[data-channel="storefront"]{min-height:0;padding-bottom:0}
        .bulk-order-app[data-channel="storefront"] .bulk-order-wrap{padding-bottom:36px}
        .bulk-order-app[data-channel="storefront"] #bulkOrderFormBody,
        .bulk-order-app[data-channel="storefront"] .bulk-order-menu-panel,
        .bulk-order-app[data-channel="storefront"] .bulk-order-menu-layout,
        .bulk-order-app[data-channel="storefront"] .bulk-order-products-stage,
        .bulk-order-app[data-channel="storefront"] .bulk-order-product-list{
            width:100%;
            height:auto!important;
            min-height:0!important;
            max-height:none!important;
            overflow:visible!important;
            scrollbar-gutter:auto!important;
            overscroll-behavior:auto!important;
        }
    }
    /* El pedido manual conserva su panel de cliente y carrito, pero comparte el
       lenguaje visual del prototipo para navegar y abrir productos. */
    .bulk-order-app[data-channel="agent"]{overflow-x:hidden}.bulk-order-app[data-channel="agent"] .bulk-order-filters{display:none}.bulk-order-app[data-channel="agent"] .bulk-order-menu-layout{display:block!important}.bulk-order-app[data-channel="agent"] .bulk-order-category-title{display:none!important}.bulk-order-app[data-channel="agent"] .bulk-order-category-chips{display:flex!important;flex-direction:row!important;gap:12px!important;max-height:none!important;margin:0 0 22px!important;padding:0 0 7px!important;overflow-x:auto!important;overflow-y:hidden!important;-ms-overflow-style:none;scrollbar-width:none}.bulk-order-app[data-channel="agent"] .bulk-order-category-chips::-webkit-scrollbar{display:none}.bulk-order-app[data-channel="agent"] .bulk-order-category-chips button{flex:0 0 118px!important;width:118px!important;min-height:108px!important;padding-top:2px!important;flex-direction:column!important;justify-content:flex-start!important;text-align:center!important}.bulk-order-app[data-channel="agent"] .bulk-order-category-icon{width:72px;height:66px;padding:0}.bulk-order-app[data-channel="agent"] .bulk-order-category-chips button.is-active:after{left:8px!important;right:8px!important}.bulk-order-app[data-channel="agent"] .bulk-order-product-list{grid-template-columns:repeat(auto-fill,minmax(175px,1fr))!important;max-height:none!important;padding:3px!important;overflow:visible!important;scrollbar-gutter:auto!important}.bulk-order-app[data-channel="agent"] .bulk-order-scroll-controls{display:none!important}.bulk-order-app[data-channel="agent"] .bulk-order-modal-card{width:min(900px,calc(100% - 28px));max-height:94dvh}.bulk-order-app[data-channel="agent"] .storefront-product-title{display:block;margin:18px 0 8px;font-size:1.7rem}.bulk-order-app[data-channel="agent"] .storefront-product-base-price{display:block;margin-bottom:16px;font-size:1.15rem;font-weight:850}.bulk-order-app[data-channel="agent"] .storefront-extra-trigger{display:flex;width:100%;margin:22px 0;padding:20px 0;justify-content:space-between;border:0;border-top:1px solid #eee;border-bottom:1px solid #eee;background:#fff;font:inherit;font-weight:800}.bulk-order-app[data-channel="agent"] .bulk-order-inline-extras{display:none}.bulk-order-app[data-channel="agent"] .bulk-order-addon-sheet.is-open{display:flex;position:fixed;z-index:1400;inset:0;align-items:flex-end;background:rgba(0,0,0,.48)}
    @media(min-width:900px){.bulk-order-app[data-channel="storefront"] .bulk-order-modal-card{overflow-y:auto}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-options{flex:none;width:min(1360px,calc(100% - 64px));display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);grid-auto-flow:row;column-gap:clamp(36px,5vw,76px);align-content:start;padding:28px 0 110px;overflow:visible}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-hero{grid-column:1;grid-row:1 / span 12;width:100%;min-width:0;height:410px;margin-top:10px}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-hero img{width:100%;max-width:100%}.bulk-order-app[data-channel="storefront"] .storefront-product-title,.bulk-order-app[data-channel="storefront"] .storefront-product-base-price,.bulk-order-app[data-channel="storefront"] .bulk-order-modal-description,.bulk-order-app[data-channel="storefront"] .bulk-order-modal-options>h4,.bulk-order-app[data-channel="storefront"] .bulk-order-modal-options>.bulk-order-choice,.bulk-order-app[data-channel="storefront"] .storefront-extra-trigger{grid-column:2;min-width:0}.bulk-order-app[data-channel="storefront"] .storefront-product-title{margin-top:28px;font-size:clamp(2.25rem,3.2vw,3rem)}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-description{font-size:1.05rem}.bulk-order-app[data-channel="storefront"]:not(.is-order-started) .bulk-order-modal-footer{position:absolute;left:max(32px,calc((100% - 1360px)/2));right:auto;top:520px;bottom:auto;width:min(calc((100% - 128px)/2),612px);padding:0;background:transparent;box-shadow:none}.bulk-order-app[data-channel="storefront"]:not(.is-order-started) .storefront-detail-actions{width:100%;margin:0}.bulk-order-app[data-channel="storefront"]:not(.is-order-started) .bulk-order-modal-add{min-height:48px;border-radius:4px}.bulk-order-app[data-channel="storefront"] .bulk-order-cart-fab{left:auto;right:28px;bottom:28px;width:min(410px,calc(100% - 56px));transform:translateY(18px)}.bulk-order-app[data-channel="storefront"] .bulk-order-cart-fab.is-visible{transform:translateY(0)}.bulk-order-app[data-channel="storefront"] .bulk-order-addon-sheet.is-open{align-items:center;justify-content:center;padding:30px}.bulk-order-app[data-channel="storefront"] .bulk-order-addon-sheet-card{width:min(820px,100%);max-height:86dvh;border-radius:22px}.bulk-order-app[data-channel="storefront"] .bulk-order-addon-head{padding:32px 42px 12px}.bulk-order-app[data-channel="storefront"] .bulk-order-addon-options{padding:0 42px 20px}.bulk-order-app[data-channel="storefront"] .bulk-order-addon-actions{padding:16px 42px 28px}}
    @media(min-width:900px){.bulk-order-app[data-channel="storefront"] .storefront-variation-section{grid-column:2;min-width:0}}
    @media(min-width:900px) and (max-width:1199.98px){.bulk-order-app[data-channel="storefront"] .bulk-order-product-list{grid-template-columns:repeat(4,minmax(0,1fr));gap:22px}}
    @media(min-width:720px) and (max-width:899.98px){.bulk-order-app[data-channel="storefront"] .bulk-order-product-list{grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}}
    @media(min-width:900px){.bulk-order-app[data-channel="agent"] .bulk-order-addon-sheet.is-open{align-items:center;justify-content:center;padding:30px}.bulk-order-app[data-channel="agent"] .bulk-order-addon-sheet-card{width:min(820px,100%);max-height:86dvh;border-radius:22px}.bulk-order-app[data-channel="agent"] .bulk-order-addon-head{padding:32px 42px 12px}.bulk-order-app[data-channel="agent"] .bulk-order-addon-options{padding:0 42px 20px}.bulk-order-app[data-channel="agent"] .bulk-order-addon-actions{padding:16px 42px 28px}}
    @media(min-width:900px){.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-storefront-open.is-cart-preview{inset:74px 116px auto auto;width:min(416px,calc(100% - 48px));max-height:calc(100dvh - 98px);padding:0;border-radius:0;background:#fff;box-shadow:0 10px 32px rgba(0,0,0,.18);overflow:auto}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview .storefront-cart-header{position:static;margin:0;padding:18px 24px 10px}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview .storefront-cart-header h2{display:none}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview .storefront-cart-back{font-size:0}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview .storefront-cart-back:after{content:'×';font-size:1.7rem}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview .storefront-cart-delivery,.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview .storefront-cart-promo{display:none}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview .storefront-cart-products-title{margin:8px 24px 14px;font-size:1.15rem}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview #bulkCartItems{margin:0;padding:0 24px}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview .bulk-order-cart-item{padding:14px 0}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview .bulk-order-cart-note-toggle,.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview .bulk-order-cart-note-editor{display:none}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview .storefront-cart-summary{position:sticky;left:auto;right:auto;bottom:0;margin-top:18px;padding:18px 24px 0;border-radius:0;background:#f7f7f7;box-shadow:none}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview .storefront-cart-total-row{margin-bottom:16px;font-size:1.25rem}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview .storefront-cart-actions{display:block;margin:0 -24px}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview #storefrontContinueShopping{display:none}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-cart-preview #storefrontCartNext{width:100%;min-height:56px}}
    @media(max-width:719.98px){.bulk-order-app[data-channel="storefront"] .storefront-catalog-header{display:none}.bulk-order-app[data-channel="storefront"] .storefront-bottom-nav{display:flex;position:fixed;z-index:80;left:0;right:0;bottom:0;height:76px;justify-content:space-around;align-items:center;border-top:1px solid #ddd;background:#fff}.bulk-order-app[data-channel="storefront"] .bulk-order-wrap{padding:14px 16px 110px}.bulk-order-app[data-channel="storefront"] .storefront-fulfillment{margin-bottom:32px}.bulk-order-app[data-channel="storefront"] .bulk-order-category-chips{justify-content:flex-start;gap:8px;margin:0 0 26px;padding:2px 4px 10px;border:0;background:#fff;scroll-snap-type:x mandatory}.bulk-order-app[data-channel="storefront"] .bulk-order-category-chips button{flex:0 0 102px;width:102px;min-width:102px;height:142px;min-height:142px;padding:2px 4px 12px;justify-content:flex-start;gap:8px;overflow:hidden;scroll-snap-align:start}.bulk-order-app[data-channel="storefront"] .bulk-order-category-icon{width:72px;height:78px;min-height:78px;padding:0}.bulk-order-app[data-channel="storefront"] .bulk-order-category-chips button span:last-child{display:-webkit-box;min-height:34px;max-width:94px;overflow:hidden;white-space:normal;line-height:1.2;-webkit-line-clamp:2;-webkit-box-orient:vertical}.bulk-order-app[data-channel="storefront"] .bulk-order-product-list{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.bulk-order-app[data-channel="storefront"] .bulk-order-product-row{min-height:285px}.bulk-order-app[data-channel="storefront"] .bulk-order-product-media{height:160px}.bulk-order-app[data-channel="storefront"] .bulk-order-product-content{padding:14px}.bulk-order-app[data-channel="storefront"] .bulk-order-modal-hero{height:300px}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-storefront-open{padding-left:18px;padding-right:18px}.bulk-order-app[data-channel="storefront"] #bulkCartPanel.is-storefront-open .storefront-cart-header{margin-left:-18px;margin-right:-18px;padding-left:18px;padding-right:18px}}
    .bulk-order-app[data-channel="storefront"] .bulk-order-product-row{min-height:365px}.bulk-order-app[data-channel="storefront"] .bulk-order-product-desc,.bulk-order-app[data-channel="agent"] .bulk-order-product-desc,.bulk-order-app[data-channel="kiosk"] .bulk-order-product-desc{display:-webkit-box;min-height:2.8em;max-height:2.8em;margin:7px 0 8px;overflow:hidden;color:#666;font-size:.82rem;line-height:1.4;-webkit-box-orient:vertical;-webkit-line-clamp:2}.bulk-order-app[data-channel="storefront"] .bulk-order-product-content>strong,.bulk-order-app[data-channel="agent"] .bulk-order-product-content>strong,.bulk-order-app[data-channel="kiosk"] .bulk-order-product-content>strong{-webkit-line-clamp:2}.bulk-order-app[data-channel="storefront"] .bulk-order-product-price{padding-top:8px}
    @media(max-width:719.98px){.bulk-order-app[data-channel="storefront"] .bulk-order-product-row{min-height:320px}.bulk-order-app[data-channel="storefront"] .bulk-order-product-desc{display:-webkit-box;min-height:2.7em;max-height:2.7em;font-size:.76rem;line-height:1.35}}
</style>

<div class="bulk-order-app" id="bulkOrderApp" data-mode="{{ $isAgent ? 'agent' : 'public' }}" data-channel="{{ $isAgent ? 'agent' : ($isKiosk ? 'kiosk' : ($isStorefront ? 'storefront' : 'microsite')) }}">
    <header class="bulk-order-header">
        @if($isAgent && !empty($ordersUrl))
            <div class="bulk-order-header-nav">
                <a href="{{ $ordersUrl }}" class="bulk-order-back-link"><i class="fas fa-arrow-left"></i> Volver a pedidos</a>
                <a href="{{ route('pos.create') }}" class="bulk-order-touch-link"><i class="fas fa-desktop"></i><span>Modo pantalla táctil</span></a>
            </div>
        @endif
        <div class="bulk-order-brand">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="Logo">
            @endif
            <h1>{{ $headerTitle }}</h1>
        </div>
        <p>{{ $headerSubtitle }}</p>
    </header>
    @if($isStorefront || $isKiosk)
    <div class="storefront-catalog-header"><div class="storefront-catalog-nav"><button type="button" class="storefront-catalog-menu" id="storefrontCatalogMenu" aria-label="Abrir menú"><span></span><span></span><span></span></button>@if(!empty($logoUrl))<img src="{{ $logoUrl }}" class="storefront-catalog-logo" alt="Logo">@endif @if($isKiosk && !empty($adminUrl))<a href="{{ $adminUrl }}" class="storefront-catalog-admin" aria-label="Ir al panel administrativo"><span aria-hidden="true">⚙</span><span class="storefront-catalog-admin-label">Panel administrativo</span></a>@endif</div><div class="storefront-catalog-tabs"><span>Productos</span></div></div>
    @endif

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
            @if(!$isAgent && !$isKiosk && !$isStorefront)
                @if(!empty($branches) && count($branches) > 1)
                    <section class="bulk-order-panel">
                        <h2>¿Dónde retirás o recibís tu pedido?</h2>
                        <label for="bulkBranch" style="display:block;margin-bottom:6px;font-size:.78rem;font-weight:800;color:#475569">Selecciona una sucursal</label>
                        <select id="bulkBranch" required style="width:100%;border:1px solid #cbd5e1;border-radius:9px;padding:11px;background:#fff;font:inherit">
                            <option value="">Elegir sucursal…</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}{{ $branch->code ? ' · '.$branch->code : '' }}</option>
                            @endforeach
                        </select>
                    </section>
                @else
                    <input type="hidden" id="bulkBranch" value="{{ !empty($branches) ? $branches->first()?->id : '' }}">
                @endif
            @elseif($isStorefront)
                <input type="hidden" id="bulkBranch" value="{{ !empty($branches) ? $branches->firstWhere('is_default', true)?->id ?? $branches->first()?->id : '' }}">
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
                        <label class="bulk-order-contact-label" for="contactSearch">Selecciona un cliente</label>
                        <div class="bulk-order-contact-search" id="contactSearchControl">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="search" id="contactSearch" placeholder="Buscar por nombre, cédula o WhatsApp" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="contactResults">
                            <i class="fas fa-chevron-down bulk-order-contact-chevron" aria-hidden="true"></i>
                        </div>
                        <p class="bulk-order-contact-help">Escribe para buscar o abre la lista. Si no existe, podrás registrarlo enseguida.</p>
                        <div class="bulk-order-contact-results" id="contactResults" role="listbox" aria-label="Clientes encontrados"></div>
                    </div>
                </div>
            </section>
            @endif

            <div id="bulkOrderFormBody" @if($isAgent || $isKiosk) class="bulk-order-form-disabled" @endif>
                @if($isStorefront)
                <button type="button" class="storefront-fulfillment" id="storefrontFulfillmentBar"><span class="storefront-fulfillment-icon">🛵</span><span><strong id="storefrontFulfillmentTitle">Enviar a</strong><small id="storefrontFulfillmentAddress">Selecciona tu dirección</small></span></button>
                @endif
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
                            @if($isStorefront || $isKiosk)<h2 class="storefront-category-heading" id="storefrontCategoryHeading">Todo el menú</h2>@endif
                            <div class="bulk-order-product-list" id="bulkProductList"></div>
                            <div class="bulk-order-scroll-controls" aria-label="Desplazar productos">
                                <button type="button" id="bulkProductsUp" aria-label="Subir en el menú" title="Subir"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg></button>
                                <button type="button" id="bulkProductsDown" aria-label="Bajar en el menú" title="Bajar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bulk-order-panel" id="bulkCartPanel">
                    @if($isStorefront)
                    <div class="storefront-cart-header"><button type="button" class="storefront-cart-back" id="storefrontCartBack" aria-label="Volver">‹</button><h2>Mi pedido</h2><button type="button" class="storefront-cart-clear" id="storefrontCartClear">Limpiar</button></div>
                    <button type="button" class="storefront-cart-delivery" id="storefrontCartDeliveryChange" aria-label="Cambiar forma de entrega o ubicación"><span style="font-size:1.8rem" aria-hidden="true">🛵</span><div><strong id="storefrontCartDeliveryTitle">Enviar a</strong><small id="storefrontCartDeliveryAddress">Dirección seleccionada</small></div><span class="storefront-cart-change">Cambiar ›</span></button>
                    <button type="button" class="storefront-cart-promo" id="storefrontCartPromotions" aria-label="Ver descuentos y promociones"><span>🏷️ &nbsp; Descuentos y promociones</span><span aria-hidden="true">›</span></button>
                    <h2 class="storefront-cart-products-title">Productos</h2>
                    @elseif($isKiosk)
                    <div class="storefront-cart-header"><button type="button" class="storefront-cart-back" id="storefrontCartBack" aria-label="Cerrar">‹</button><h2>Tu pedido</h2><button type="button" class="storefront-cart-clear" id="storefrontCartClear">Limpiar</button></div>
                    @endif
                    <div class="bulk-order-section-head">
                        <h2>{{ $isAgent ? 'Lista del pedido' : 'Tu pedido' }}</h2>
                        <span class="bulk-order-cart-count" id="bulkCartCount">Vacío</span>
                    </div>
                    <div id="bulkCartItems"></div>
                    <p class="bulk-order-cart-empty" id="bulkCartEmpty">Aún no agregaste productos.</p>
                    @if($isStorefront)
                    <div class="bulk-order-storefront-customer">
                        <h3>Datos para confirmar</h3>
                        <aside class="storefront-account-benefit is-required" id="storefrontCheckoutAccountBenefit">
                            <span class="storefront-account-benefit-copy"><strong>Necesitas una cuenta para confirmar</strong><small>Inicia sesión o regístrate con tu teléfono o con Google -- así puedes ver el estado de tu pedido y reutilizar tus datos la próxima vez.</small></span>
                            <button type="button" id="storefrontCheckoutAccountButton">Iniciar sesión / Crear cuenta</button>
                        </aside>
                        <label>Nombre completo<input type="text" id="storefrontCustomerName" maxlength="120" autocomplete="name" placeholder="¿Quién recibe el pedido?"></label>
                        <label>Teléfono<input type="tel" id="storefrontCustomerPhone" maxlength="30" autocomplete="tel" placeholder="Ej.: 099 123 4567"><small class="bulk-order-field-error" id="storefrontCustomerPhoneError"></small></label>
                        <label>Correo de contacto <span>(opcional)</span><input type="email" id="storefrontCustomerEmail" maxlength="255" autocomplete="email" placeholder="correo@ejemplo.com"><small class="bulk-order-field-error" id="storefrontCustomerEmailError"></small></label>
                        <label>Forma de pago<select id="storefrontPaymentMethod"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="tarjeta">Tarjeta</option></select></label>
                        <section class="storefront-checkout-block storefront-payment-fields" id="storefrontTransferFields">
                            <h4>Datos para realizar la transferencia</h4>
                            @if(filled($bankTransferInstructions ?? null))
                                <p class="storefront-bank-instructions">{!! nl2br(e($bankTransferInstructions)) !!}</p>
                            @else
                                <p>La empresa todavía no ha publicado sus datos bancarios. Puedes confirmar el pedido y solicitarlos por WhatsApp.</p>
                            @endif
                            <label>Comprobante <span>(puedes cargarlo ahora o después desde Mi cuenta)</span><input type="file" id="storefrontPaymentProof" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
                        </section>
                        <section class="storefront-checkout-block storefront-card-fields" id="storefrontCardFields" aria-live="polite">
                            <h4>Pago seguro con tarjeta</h4>
                            <p>Por el momento este micrositio no procesa pagos con tarjeta. Para pagar de manera segura, continúa en la página web de la empresa.</p>
                            @if(filled($cardPaymentUrl ?? null))
                                <a class="storefront-card-link" id="storefrontCheckoutCardPaymentLink" href="{{ $cardPaymentUrl }}" target="_blank" rel="noopener noreferrer">Continuar en {{ parse_url($cardPaymentUrl, PHP_URL_HOST) ?: 'la página de pago' }}</a>
                            @else
                                <p><strong>El enlace de pago todavía no está configurado.</strong> Selecciona efectivo o transferencia para continuar.</p>
                            @endif
                        </section>
                        <section class="storefront-checkout-block">
                            <h4>Datos para el comprobante de venta</h4>
                            <label>¿Cómo deseas tu comprobante?<select id="storefrontInvoicePreference"><option value="consumer">Consumidor final</option><option value="invoice">Factura con datos</option></select></label>
                            <div class="storefront-invoice-fields" id="storefrontInvoiceFields">
                                <p class="storefront-invoice-required-note">Si eliges "Factura con datos", estos campos son <strong>obligatorios</strong> -- son distintos del correo de contacto de arriba.</p>
                                <label>Tipo de identificación<select id="storefrontBillingType"><option value="cedula">Cédula</option><option value="ruc">RUC</option><option value="pasaporte">Pasaporte</option></select></label>
                                <label>Cédula, RUC o pasaporte <span>(obligatorio)</span><input id="storefrontBillingId" maxlength="20" autocomplete="off"></label>
                                <label>Nombre o razón social <span>(obligatorio)</span><input id="storefrontBillingLegalName" maxlength="255" autocomplete="name"></label>
                                <label>Dirección de facturación <span>(obligatorio)</span><input id="storefrontBillingAddress" maxlength="500" autocomplete="street-address"></label>
                                <label>Correo de facturación <span>(obligatorio)</span><input id="storefrontBillingEmail" type="email" maxlength="255" autocomplete="email"></label>
                            </div>
                        </section>
                    </div>
                    @endif
                    <label for="bulkOrderNote" style="display:block;margin-top:12px;font-size:.85rem;color:var(--muted)">Nota general del pedido (opcional)</label>
                    <textarea id="bulkOrderNote" rows="2" placeholder="Instrucciones de entrega, facturación…"></textarea>

                    @if($isKiosk)
                    <div class="kiosk-order-options">
                        <span class="kiosk-order-options-title">¿Cómo entregarás este pedido?</span>
                        <div class="kiosk-service-options" role="group" aria-label="Forma de entrega">
                            <button type="button" class="kiosk-service-option" id="kioskServiceLlevar" aria-pressed="false"><span class="kiosk-service-icon">🥡</span><span class="kiosk-service-copy"><strong>Para llevar</strong><small>El cliente retira su pedido</small></span><span class="kiosk-service-check">✓</span></button>
                            <button type="button" class="kiosk-service-option" id="kioskServiceServir" aria-pressed="false"><span class="kiosk-service-icon">🍽️</span><span class="kiosk-service-copy"><strong>Para servir</strong><small>Se consume en el local</small></span><span class="kiosk-service-check">✓</span></button>
                        </div>
                        <div id="kioskTableWrap" style="display:none;margin-bottom:18px">
                            <label for="kioskTableReference" style="display:block;margin-bottom:6px;font-size:.85rem;color:var(--muted)">Mesa / referencia (opcional)</label>
                            <input type="text" id="kioskTableReference" placeholder="Ej.: Mesa 4" maxlength="120">
                        </div>
                        <label class="kiosk-payment-field" for="kioskPaymentMethod"><span>Forma de pago</span><span class="kiosk-select-wrap"><span class="kiosk-select-icon" id="kioskPaymentIcon" aria-hidden="true">💵</span><select id="kioskPaymentMethod"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="tarjeta">Tarjeta</option></select></span><small class="kiosk-payment-help">Selecciona cómo pagará el cliente en caja.</small></label>
                    </div>
                    @endif
                    @if($isStorefront)
                    <div class="storefront-cart-summary"><div class="storefront-cart-total-row"><b>Total</b><strong id="storefrontCartTotal">$0.00</strong></div><div class="storefront-cart-actions"><button type="button" id="storefrontContinueShopping">Continuar comprando</button><button type="button" id="storefrontCartNext">Siguiente</button></div></div>
                    @endif
                </section>
            </div>
        </div>

        <div class="bulk-order-success" id="bulkSuccessScreen">
            <div class="icon">✅</div>
            <h2>Pedido enviado</h2>
            <p id="bulkSuccessOrderNumber" style="font-size:1.1rem;font-weight:700;color:var(--wa-dark);margin:12px 0;"></p>
            <p id="bulkSuccessHint">{{ $successWhatsappHint }}</p>
            @if($isStorefront)<p class="storefront-proof-status" id="storefrontSuccessPaymentStatus" style="display:none"></p>@endif
            @if($isStorefront)
                <p style="display:none;margin-top:16px" id="storefrontCardPaymentWrap">
                    <a href="#" id="storefrontCardPaymentLink" class="bulk-order-btn bulk-order-btn-primary" style="display:inline-block;text-decoration:none" target="_blank" rel="noopener">
                        Pagar en línea de forma segura
                    </a>
                </p>
            @endif
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
            @if($isStorefront)
                <p style="margin-top:16px">
                    <button type="button" class="bulk-order-btn bulk-order-btn-primary" id="storefrontSuccessHomeBtn">Volver al inicio</button>
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
            <button type="button" class="bulk-order-btn bulk-order-btn-primary" id="bulkSubmitBtn" disabled>{{ $isKiosk ? 'Revisar y ordenar' : ($isAgent ? 'Registrar pedido' : ($isStorefront ? 'Siguiente' : 'Confirmar pedido')) }}</button>
        </div>
    </div>

    <div class="bulk-order-toast" id="bulkToast"></div>

    @if(!$isAgent && !$isKiosk)
        <button type="button" class="bulk-order-cart-fab" id="bulkCartFab" aria-label="Ver mi pedido">
            <span class="bulk-order-cart-fab-icon">🛒</span>
            <span class="bulk-order-cart-fab-count" id="bulkCartFabCount">0</span>
            <strong class="bulk-order-cart-fab-total" id="bulkCartFabTotal">$0.00</strong>
            <span class="bulk-order-cart-fab-arrow">›</span>
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
            <div class="bulk-order-modal-footer" data-guide="Listo para tu pedido">
                @if($isStorefront || $isKiosk)<div class="storefront-detail-qty"><button type="button" id="storefrontDetailMinus" aria-label="Restar">−</button><span id="storefrontDetailQty">1</span><button type="button" id="storefrontDetailPlus" aria-label="Sumar">+</button></div><strong class="storefront-detail-total" id="storefrontDetailTotal">$0.00</strong><div class="storefront-detail-actions">@if($isStorefront)<button type="button" id="storefrontPayNow">Pagar ahora</button>@endif @endif
                <button type="button" class="bulk-order-btn bulk-order-btn-primary bulk-order-modal-add" id="bulkCustomizerAdd">Agregar al carrito</button>
                @if($isStorefront || $isKiosk)</div>@endif
            </div>
        </div>
    </div>

    @if($usesEnhancedCustomizer)
    <div class="bulk-order-addon-sheet" id="bulkAddonSheet" aria-hidden="true"><div class="bulk-order-addon-sheet-card" role="dialog" aria-modal="true"><div class="bulk-order-addon-head"><button type="button" id="bulkAddonClose" aria-label="Cerrar">×</button><h3 id="bulkAddonTitle">Selecciona tus adicionales</h3></div><div id="bulkAddonOptions" class="bulk-order-addon-options"></div><div class="bulk-order-addon-actions"><p class="bulk-order-addon-status" id="bulkAddonStatus" aria-live="polite"></p><button type="button" class="bulk-order-addon-add" id="bulkAddonAdd">Agregar al carrito</button></div></div></div>
    @endif
    @if($isStorefront)
    <nav class="storefront-bottom-nav" aria-label="Navegación principal">
        <button type="button" data-storefront-nav="home"><span><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9a1 1 0 0 0 1 1H10v-6h4v6h3.5a1 1 0 0 0 1-1v-9"/></svg></span>Inicio</button>
        <button type="button" data-storefront-nav="branches"><span><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.2a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.8" r="2.6"/></svg></span>Sucursales</button>
        <button type="button" data-storefront-nav="account"><span><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4.5 20c0-4 3.5-6.2 7.5-6.2s7.5 2.2 7.5 6.2"/></svg></span>Mi cuenta</button>
    </nav>
    @endif

    @if($isAgent)
    <div class="bulk-order-modal bulk-order-contact-modal" id="contactCreateModal" aria-hidden="true">
        <div class="bulk-order-contact-modal-card" role="dialog" aria-modal="true" aria-labelledby="contactCreateTitle">
            <div class="bulk-order-contact-modal-head"><h3 id="contactCreateTitle">Nuevo cliente</h3><p>Solo necesitamos los datos esenciales para registrar el pedido.</p></div>
            <form id="contactCreateForm">
                <div class="bulk-order-contact-form">
                    <label>Nombre completo<input id="contactCreateName" name="name" required minlength="2" maxlength="120" autocomplete="name" placeholder="Ej.: María Pérez"></label>
                    <label>Cédula o identificación <span style="font-weight:500;color:#94a3b8">(opcional)</span><input id="contactCreateNationalId" name="national_id" maxlength="20" autocomplete="off" placeholder="Ej.: 0912345678"></label>
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
    const isStorefront = @json($isStorefront);
    const usesEnhancedCustomizer = @json($usesEnhancedCustomizer);
    const catalogUrl = @json($catalogUrl);
    const submitUrl = @json($submitUrl);
    const contactsSearchUrl = @json($contactsSearchUrl ?? null);
    const contactsCreateUrl = @json($contactsCreateUrl ?? null);
    const initialContact = @json($initialContact ?? null);
    const persistenceKey = @json($persistenceKey ?? null);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let products = [];
    let cart = restorePersistedCart();
    let editingCartIndex = null;
    let cartTransitioning = false;
    let storefrontNextPreviousLabel = '';
    let selectedContact = initialContact ? { ...initialContact } : null;
    let isSubmitting = false;
    let kioskServiceType = null;
    let contactSearchRequest = 0;
    let activeContactOption = -1;
    let customizerQuantity = 1;
    let customizerExtras = [];
    let customizerVariation = '';
    let customizerOptionsReviewed = false;
    let customizerSubmitting = false;
    let showingPromotions = false;

    const root = document.getElementById('bulkOrderApp');
    const el = (id) => document.getElementById(id);
    const fmt = (n) => '$' + Number(n).toFixed(2);
    const submitBtnDefaultLabel = el('bulkSubmitBtn')?.textContent.trim() || 'Confirmar pedido';

    function restorePersistedCart() {
        if (!isStorefront || !persistenceKey) return [];
        try {
            const saved = JSON.parse(localStorage.getItem(persistenceKey) || '[]');
            return Array.isArray(saved)
                ? saved.filter(line => line && Number(line.product_id) > 0 && Number(line.quantity) > 0)
                : [];
        } catch (_) {
            localStorage.removeItem(persistenceKey);
            return [];
        }
    }

    function persistCart() {
        if (!isStorefront || !persistenceKey) return;
        try {
            if (cart.length) localStorage.setItem(persistenceKey, JSON.stringify(cart));
            else localStorage.removeItem(persistenceKey);
        } catch (_) {
            // El flujo continúa aunque el navegador tenga bloqueado localStorage.
        }
    }

    function restoreStorefrontDraft() {
        if (!isStorefront || !persistenceKey) return;
        try {
            const draft = JSON.parse(localStorage.getItem(persistenceKey + '_checkout') || 'null');
            if (!draft || typeof draft !== 'object') return;
            const fields = {
                storefrontCustomerName: draft.name,
                storefrontCustomerPhone: draft.phone,
                storefrontCustomerEmail: draft.email,
                storefrontPaymentMethod: draft.payment_method,
                storefrontInvoicePreference: draft.invoice_preference,
                storefrontBillingType: draft.billing_type,
                storefrontBillingId: draft.billing_id,
                storefrontBillingLegalName: draft.billing_legal_name,
                storefrontBillingAddress: draft.billing_address,
                storefrontBillingEmail: draft.billing_email,
                bulkOrderNote: draft.order_note,
            };
            Object.entries(fields).forEach(([id, value]) => {
                if (el(id) && typeof value === 'string') el(id).value = value;
            });
        } catch (_) {
            localStorage.removeItem(persistenceKey + '_checkout');
        }
    }

    function persistStorefrontDraft() {
        if (!isStorefront || !persistenceKey) return;
        try {
            localStorage.setItem(persistenceKey + '_checkout', JSON.stringify({
                name: el('storefrontCustomerName')?.value || '',
                phone: el('storefrontCustomerPhone')?.value || '',
                email: el('storefrontCustomerEmail')?.value || '',
                payment_method: el('storefrontPaymentMethod')?.value || 'efectivo',
                invoice_preference: el('storefrontInvoicePreference')?.value || 'consumer',
                billing_type: el('storefrontBillingType')?.value || 'cedula',
                billing_id: el('storefrontBillingId')?.value || '',
                billing_legal_name: el('storefrontBillingLegalName')?.value || '',
                billing_address: el('storefrontBillingAddress')?.value || '',
                billing_email: el('storefrontBillingEmail')?.value || '',
                order_note: el('bulkOrderNote')?.value || '',
            }));
        } catch (_) {
            // El checkout continúa aunque el navegador bloquee localStorage.
        }
    }

    function syncStorefrontCheckoutFields() {
        if (!isStorefront) return;
        const isTransfer = el('storefrontPaymentMethod')?.value === 'transferencia';
        const isCard = el('storefrontPaymentMethod')?.value === 'tarjeta';
        const wantsInvoice = el('storefrontInvoicePreference')?.value === 'invoice';
        el('storefrontTransferFields')?.classList.toggle('is-open', isTransfer);
        el('storefrontCardFields')?.classList.toggle('is-open', isCard);
        el('storefrontInvoiceFields')?.classList.toggle('is-open', wantsInvoice);
    }

    function setSubmitting(submitting) {
        isSubmitting = submitting;
        const btn = el('bulkSubmitBtn');
        const storefrontNext = isStorefront ? el('storefrontCartNext') : null;
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
            if (storefrontNext) {
                storefrontNextPreviousLabel = storefrontNext.textContent.trim() || 'Confirmar pedido';
                storefrontNext.disabled = true;
                storefrontNext.classList.add('is-loading');
                storefrontNext.setAttribute('aria-busy', 'true');
                storefrontNext.innerHTML = '<span class="bulk-order-btn-spinner" aria-hidden="true"></span> Procesando pedido…';
            }
            document.body.style.overflow = 'hidden';
        } else {
            btn.classList.remove('is-loading');
            btn.textContent = submitBtnDefaultLabel;
            if (storefrontNext) {
                storefrontNext.disabled = false;
                storefrontNext.classList.remove('is-loading');
                storefrontNext.removeAttribute('aria-busy');
                storefrontNext.textContent = storefrontNextPreviousLabel || (el('bulkCartPanel')?.classList.contains('is-checkout-step') ? 'Confirmar pedido' : 'Siguiente');
            }
            document.body.style.overflow = '';
            updateFormEnabled();
        }
    }

    function toast(msg) {
        const t = el('bulkToast');
        t.textContent = msg;
        t.setAttribute('role', 'alert');
        t.style.display = 'block';
        setTimeout(() => { t.style.display = 'none'; }, 5000);
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function catalogImage(src, alt = '', lazy = false) {
        return `<img class="catalog-loading-image" data-catalog-image src="${escapeHtml(src)}" alt="${escapeHtml(alt)}"${lazy ? ' loading="lazy"' : ''}>`;
    }

    function settleCatalogImage(image, loaded) {
        if (!(image instanceof HTMLImageElement) || !image.matches('[data-catalog-image]')) return;
        const shell = image.closest('.catalog-image-shell');
        if (!shell) return;
        shell.classList.toggle('is-image-loaded', loaded);
        shell.classList.toggle('is-image-error', !loaded);
    }

    // Se usa captura porque load/error de las imágenes no burbujean.
    document.addEventListener('load', event => settleCatalogImage(event.target, true), true);
    document.addEventListener('error', event => settleCatalogImage(event.target, false), true);
    document.querySelectorAll('img[data-catalog-image]').forEach(image => {
        if (image.complete) settleCatalogImage(image, image.naturalWidth > 0);
    });

    function debounce(fn, ms) {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), ms);
        };
    }

    function productMetaHtml(p, { truncateDesc = 110 } = {}) {
        let html = '';
        const productDescription = String(p.description || '').trim()
            || 'Consulta las opciones disponibles de este producto.';
        const desc = truncateDesc && productDescription.length > truncateDesc
            ? productDescription.slice(0, truncateDesc - 1).trimEnd() + '…'
            : productDescription;
        html += `<p class="bulk-order-product-desc">${escapeHtml(desc)}</p>`;
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
            // En el POS la primera acción es revisar. La forma de entrega se
            // elige dentro de ese resumen, por eso no debe bloquear su apertura.
            btn.disabled = cart.length === 0 || !enabled;
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
            el('selectedContactPhone').textContent = [
                selectedContact.identity ? 'Identificación: ' + selectedContact.identity : '',
                selectedContact.phone ? 'WhatsApp: ' + selectedContact.phone : '',
            ].filter(Boolean).join(' · ') || 'Cliente presencial';
        } else {
            el('contactSearch').value = '';
            setContactResultsOpen(false);
        }
        updateFormEnabled();
    }

    function setContactResultsOpen(open) {
        const box = el('contactResults');
        const input = el('contactSearch');
        const control = el('contactSearchControl');
        if (!box || !input || !control) return;
        box.style.display = open ? 'block' : 'none';
        input.setAttribute('aria-expanded', open ? 'true' : 'false');
        control.classList.toggle('is-open', open);
        if (!open) activeContactOption = -1;
    }

    function contactInitials(name) {
        return String(name || 'Cliente').trim().split(/\s+/).slice(0, 2).map(part => part.charAt(0)).join('').toUpperCase() || 'CL';
    }

    function selectContactButton(button) {
        selectedContact = {
            id: Number(button.dataset.contactId),
            name: button.dataset.contactName,
            phone: button.dataset.contactPhone,
            identity: button.dataset.contactIdentity,
        };
        renderSelectedContact();
    }

    async function searchContacts(q) {
        const box = el('contactResults');
        if (!contactsSearchUrl || !box) return;
        const term = q.trim();
        const requestId = ++contactSearchRequest;
        box.innerHTML = '<div class="bulk-order-contact-empty"><i class="fas fa-spinner fa-spin"></i> Buscando clientes…</div>';
        setContactResultsOpen(true);

        try {
            const res = await fetch(contactsSearchUrl + '?q=' + encodeURIComponent(term));
            if (!res.ok) throw new Error('No se pudo consultar clientes.');
            const data = await res.json();
            if (requestId !== contactSearchRequest) return;
            const list = data.contacts || [];
            const emptyState = list.length ? '' : '<div class="bulk-order-contact-empty">No encontramos coincidencias. Puedes registrar al cliente sin salir del pedido.</div>';
            const options = list.map(c => {
                const details = [c.identity ? 'Cédula: ' + c.identity : '', c.phone ? 'WhatsApp: ' + c.phone : ''].filter(Boolean).join(' · ') || 'Cliente presencial';
                return `
                    <button type="button" role="option" data-contact-id="${c.id}" data-contact-name="${escapeHtml(c.name)}" data-contact-phone="${escapeHtml(c.phone || '')}" data-contact-identity="${escapeHtml(c.identity || '')}">
                        <span class="bulk-order-contact-avatar">${escapeHtml(contactInitials(c.name))}</span>
                        <span class="bulk-order-contact-option-copy"><strong>${escapeHtml(c.name)}</strong><small>${escapeHtml(details)}</small></span>
                        <i class="fas fa-check bulk-order-contact-check" aria-hidden="true"></i>
                    </button>`;
            }).join('');
            const createLabel = term ? `Registrar nuevo cliente con “${escapeHtml(term)}”` : 'Registrar un cliente nuevo';
            box.innerHTML = emptyState + options + `
                <button type="button" class="bulk-order-contact-create-option" data-create-from-search>
                    <i class="fas fa-user-plus" aria-hidden="true"></i><span>${createLabel}</span>
                </button>`;
            setContactResultsOpen(true);
            box.querySelectorAll('[data-contact-id]').forEach(btn => btn.addEventListener('click', () => selectContactButton(btn)));
            box.querySelector('[data-create-from-search]')?.addEventListener('click', () => openContactCreate(term));
        } catch (error) {
            if (requestId !== contactSearchRequest) return;
            box.innerHTML = `<div class="bulk-order-contact-empty">${escapeHtml(error.message || 'No se pudo consultar clientes.')}</div>`;
            setContactResultsOpen(true);
        }
    }

    function syncBulkModalScrollLock() {
        document.body.classList.toggle('bulk-order-modal-open', !!document.querySelector('.bulk-order-modal.is-open'));
    }

    function openContactCreate(prefill = '') {
        if (!isAgent) return;
        const modal = el('contactCreateModal');
        if (!modal) return;
        el('contactCreateName').value = '';
        el('contactCreateNationalId').value = '';
        el('contactCreatePhone').value = '';
        el('contactCreateAddress').value = '';
        el('contactRequiresInvoice').checked = false;
        el('contactBillingType').value = 'cedula';
        el('contactBillingId').value = '';
        el('contactBillingEmail').value = '';
        const compactValue = String(prefill).trim();
        if (/^\+/.test(compactValue)) {
            el('contactCreatePhone').value = compactValue;
        } else if (/^\d{8,20}$/.test(compactValue.replace(/\s+/g, ''))) {
            el('contactCreateNationalId').value = compactValue.replace(/\s+/g, '');
        } else if (compactValue) {
            el('contactCreateName').value = compactValue;
        }
        syncInvoiceFields();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        syncBulkModalScrollLock();
        setTimeout(() => (el('contactCreateName').value ? el('contactCreatePhone') : el('contactCreateName')).focus(), 80);
    }

    function closeContactCreate() {
        const modal = el('contactCreateModal');
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        syncBulkModalScrollLock();
    }

    function syncInvoiceFields() {
        const requested = el('contactRequiresInvoice')?.checked;
        el('contactInvoiceFields')?.classList.toggle('is-open', !!requested);
        if (requested && el('contactBillingType')?.value === 'cedula' && !el('contactBillingId').value.trim()) {
            el('contactBillingId').value = el('contactCreateNationalId').value.trim();
        }
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
                    national_id: el('contactCreateNationalId').value.trim() || null,
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
        if (showingPromotions) params.set('promo', '1');
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
        renderCategoryChips(data.categories || [], data.all_category || {});
    }

    function renderCategoryChips(categories, allCategory = {}) {
        const box = el('bulkCategoryChips');
        const current = el('bulkCategory').value;
        const allCategoryVisual = allCategory.image
            ? `<span class="bulk-order-category-icon catalog-image-shell">${catalogImage(allCategory.image, allCategory.title || 'Todos')}</span>`
            : categoryIcon('all');
        box.innerHTML = `<button type="button" data-category="" class="${current === '' && !showingPromotions ? 'is-active' : ''}">${allCategoryVisual}<span>${escapeHtml(allCategory.title || 'Todos')}</span></button>` + categories.map(c =>
            `<button type="button" data-category="${c.id}" class="${String(c.id) === String(current) && !showingPromotions ? 'is-active' : ''}">${c.image ? `<span class="bulk-order-category-icon catalog-image-shell">${catalogImage(c.image, c.title)}</span>` : categoryIcon(c.title)}<span>${escapeHtml(c.title)}</span></button>`
        ).join('');
        box.querySelectorAll('[data-category]').forEach(btn => btn.addEventListener('click', async () => {
            showingPromotions = false;
            el('bulkCategory').value = btn.dataset.category;
            await loadCatalog();
            moveCatalogHeaderOutOfView();
        }));
        if (isStorefront || isKiosk) {
            const selected = categories.find(category => String(category.id) === String(current));
            el('storefrontCategoryHeading').textContent = showingPromotions ? 'Promociones' : (selected?.title || 'Todo el menú');
        }
    }

    function moveCatalogHeaderOutOfView() {
        if (!isStorefront || !window.matchMedia('(min-width: 720px)').matches) return;

        const categoryRail = document.querySelector('.bulk-order-app[data-channel="storefront"] .bulk-order-category-rail');
        if (!categoryRail) return;

        const targetTop = window.scrollY + categoryRail.getBoundingClientRect().top;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        window.scrollTo({
            top: Math.max(0, targetTop),
            behavior: reduceMotion ? 'auto' : 'smooth',
        });
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
                <div class="bulk-order-product-media ${p.image ? 'catalog-image-shell' : ''}">${p.image ? catalogImage(p.image, p.name, true) : '<div class="fallback">🍗</div>'}</div>
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
        customizerQuantity = existingLine?.quantity || p.min_qty || 1;
        customizerExtras = [...(existingLine?.extras || [])];
        customizerVariation = existingLine?.variation || '';
        customizerOptionsReviewed = Boolean(existingLine) || (p.extras || []).length === 0;
        if (el('storefrontDetailQty')) el('storefrontDetailQty').textContent = customizerQuantity;
        el('bulkCustomizerTitle').textContent = (isStorefront || isKiosk) ? (p.category || 'Productos') : p.name;
        el('bulkCustomizerPrice').textContent = 'Desde ' + fmt(p.price);
        let html = `<div class="bulk-order-modal-hero ${p.image ? 'catalog-image-shell' : ''}">${p.image ? catalogImage(p.image, p.name) : '<div class="fallback">🍗</div>'}</div><h2 class="storefront-product-title">${escapeHtml(p.name)}</h2><div class="storefront-product-base-price">${fmt(p.price)}</div>`;
        if (p.description) {
            html += `<p class="bulk-order-modal-description">${escapeHtml(p.description)}</p>`;
        }
        if ((p.variations || []).length) {
            if (usesEnhancedCustomizer) {
                html += `<section class="storefront-variation-section" id="storefrontVariationSection"><div class="storefront-variation-head"><h3>Elige una opción</h3><span class="bulk-order-required-badge">Obligatorio</span></div><div class="storefront-variation-list">${p.variations.map(variation => `
                    <button type="button" class="storefront-variation-option ${customizerVariation === variation.title ? 'is-selected' : ''}" data-inline-variation="${escapeHtml(variation.title)}">
                        <span class="storefront-variation-check">${customizerVariation === variation.title ? '✓' : ''}</span>
                        <span class="storefront-variation-copy"><strong>${escapeHtml(variation.title)}</strong><small>${escapeHtml(variation.description || `Selecciona ${variation.title}`)}</small></span>
                        <span class="storefront-variation-price">${fmt(variation.price)}</span>
                    </button>`).join('')}</div></section>`;
            } else {
                html += '<h4 style="margin:8px 0;font-size:.9rem">Elige tu opción</h4>';
                html += p.variations.map((v, i) => `<div class="bulk-order-choice"><label><input type="radio" name="bulkVariation" value="${escapeHtml(v.title)}" data-price="${v.price}" ${(existingLine?.variation ? existingLine.variation === v.title : i === 0) ? 'checked' : ''}> ${escapeHtml(v.title)}</label><small>${fmt(v.price)}</small></div>`).join('');
            }
        }
        if ((p.extras || []).length) {
            const requiredCopy = 'Opcional: puedes agregarlos o continuar sin ellos';
            html += `<button type="button" class="storefront-extra-trigger" id="storefrontExtraTrigger"><span>Ver adicionales <small style="display:block;color:#777;font-weight:500">${requiredCopy}</small></span><span id="storefrontExtraSummary">${customizerExtras.length ? customizerExtras.length + ' elegidos' : '›'}</span></button>`;
        }
        if ((p.extras || []).length) {
            html += `<div class="bulk-order-inline-extras"><h4 style="margin:18px 0 8px;font-size:.9rem">Hazlo aún mejor <span style="font-weight:400;color:var(--muted)">(opcional)</span></h4>${p.extras.map(e => `<div class="bulk-order-choice"><label><input type="checkbox" name="bulkExtra" value="${escapeHtml(e.title)}" data-price="${e.price}" ${customizerExtras.includes(e.title) ? 'checked' : ''}> ${escapeHtml(e.title)}</label><small>+${fmt(e.price)}</small></div>`).join('')}</div>`;
        }
        if (!html) html = '<p style="color:var(--muted);margin:0">Listo para agregar a tu carrito.</p>';
        el('bulkCustomizerOptions').innerHTML = html;
        el('bulkCustomizerOptions').scrollTop = 0;
        el('bulkCustomizerOptions').querySelectorAll('input[name="bulkVariation"]').forEach(input => input.addEventListener('change', updateCustomizerTotal));
        el('bulkCustomizerOptions').querySelectorAll('[data-inline-variation]').forEach(button => button.addEventListener('click', () => {
            customizerVariation = button.dataset.inlineVariation;
            el('bulkCustomizerOptions').querySelectorAll('[data-inline-variation]').forEach(item => {
                const selected = item === button;
                item.classList.toggle('is-selected', selected);
                item.querySelector('.storefront-variation-check').textContent = selected ? '✓' : '';
            });
            updateCustomizerSelectionSummary();
            updateCustomizerTotal();
            syncCustomizerActions();
        }));
        el('bulkCustomizer').classList.add('is-open');
        el('bulkCustomizer').setAttribute('aria-hidden', 'false');
        el('storefrontExtraTrigger')?.addEventListener('click', openAddonSheet);
        syncBulkModalScrollLock();
        updateCustomizerTotal();
        syncCustomizerActions();
        setTimeout(() => el('bulkCustomizerClose').focus(), 0);
    }

    function closeCustomizer() {
        el('bulkCustomizer').classList.remove('is-open');
        el('bulkCustomizer').setAttribute('aria-hidden', 'true');
        syncBulkModalScrollLock();
        customizingProduct = null;
        editingCartIndex = null;
    }

    function openAddonSheet() {
        if (!usesEnhancedCustomizer || !customizingProduct) return;
        customizerOptionsReviewed = true;
        const options = el('bulkAddonOptions');
        let sheetHtml = '';
        if ((customizingProduct.extras || []).length) {
            sheetHtml += `<section class="bulk-order-addon-section"><div class="bulk-order-addon-section-head"><h4>Adicionales</h4><span style="color:#777;font-size:.75rem">Opcional</span></div>${customizingProduct.extras.map(extra => `
            <button type="button" class="bulk-order-addon-option ${customizerExtras.includes(extra.title) ? 'is-selected' : ''}" data-addon="${escapeHtml(extra.title)}" aria-pressed="${customizerExtras.includes(extra.title) ? 'true' : 'false'}">
                <span class="bulk-order-addon-option-icon">${extra.image ? `<img src="${escapeHtml(extra.image)}" alt="">` : '<span aria-hidden="true">＋</span>'}</span>
                <span class="bulk-order-addon-copy"><strong>${escapeHtml(extra.title)}</strong>${extra.description ? `<span>${escapeHtml(extra.description)}</span>` : ''}</span>
                <small class="bulk-order-addon-price">${Number(extra.price) > 0 ? '+' + fmt(extra.price) : 'Incluido'}</small>
            </button>`).join('')}</section>`;
        }
        options.innerHTML = sheetHtml;
        options.querySelectorAll('[data-addon]').forEach(button => button.addEventListener('click', () => {
            const title = button.dataset.addon;
            customizerExtras = customizerExtras.includes(title)
                ? customizerExtras.filter(value => value !== title)
                : [...customizerExtras, title];
            const selected = customizerExtras.includes(title);
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            updateCustomizerSelectionSummary();
            updateCustomizerTotal();
            syncCustomizerActions();
        }));
        el('bulkAddonSheet').classList.add('is-open');
        el('bulkAddonSheet').setAttribute('aria-hidden', 'false');
        syncCustomizerActions();
    }

    function closeAddonSheet() {
        el('bulkAddonSheet')?.classList.remove('is-open');
        el('bulkAddonSheet')?.setAttribute('aria-hidden', 'true');
    }

    function updateCustomizerSelectionSummary() {
        const summary = el('storefrontExtraSummary');
        if (!summary) return;
        const count = customizerExtras.length;
        summary.textContent = count ? `${count} elegidos` : '›';
    }

    function syncCustomizerActions() {
        if (!customizingProduct) return;

        const mustStartOrder = isStorefront && !window.storefrontOrder?.confirmed;
        const hasOptionalExtras = (customizingProduct.extras || []).length > 0;
        const missingRequiredOption = !mustStartOrder
            && (customizingProduct.variations || []).length > 0
            && !customizerVariation;
        const shouldReviewOptions = !mustStartOrder && hasOptionalExtras && !customizerOptionsReviewed;
        const mainButton = el('bulkCustomizerAdd');
        const addonButton = el('bulkAddonAdd');
        const status = el('bulkAddonStatus');
        const footer = document.querySelector('#bulkCustomizer .bulk-order-modal-footer');
        const finalLabel = editingCartIndex !== null ? 'Guardar cambios' : 'Agregar al carrito';

        if (mainButton && !customizerSubmitting) {
            mainButton.textContent = mustStartOrder
                ? 'Elegir entrega y comenzar'
                : (missingRequiredOption
                    ? 'Elige una opción para continuar'
                    : (shouldReviewOptions ? 'Revisar adicionales' : finalLabel));
            // Las variaciones obligatorias se eligen en el detalle. El botón
            // interno de adicionales permanece bloqueado si aún falta una.
            mainButton.disabled = false;
            mainButton.setAttribute('aria-disabled', 'false');
        }
        if (addonButton && !customizerSubmitting) {
            addonButton.textContent = finalLabel;
            addonButton.disabled = missingRequiredOption;
            addonButton.setAttribute('aria-disabled', missingRequiredOption ? 'true' : 'false');
        }
        if (footer) {
            footer.dataset.guide = mustStartOrder
                ? ''
                : (missingRequiredOption
                    ? 'Selecciona una opción obligatoria'
                    : (shouldReviewOptions
                        ? 'Revisa los adicionales opcionales'
                        : 'Listo para agregar a tu pedido'));
        }
        if (status && !customizerSubmitting) {
            status.textContent = missingRequiredOption
                ? 'Selecciona la opción obligatoria para continuar.'
                : 'Tu selección está lista.';
        }
    }
    window.syncStorefrontCustomizerActions = syncCustomizerActions;

    function updateCustomizerTotal() {
        if (!customizingProduct || !el('storefrontDetailTotal')) return;
        const variation = usesEnhancedCustomizer ? customizerVariation : (document.querySelector('input[name="bulkVariation"]:checked')?.value || '');
        const variationObject = (customizingProduct.variations || []).find(item => item.title === variation);
        const extrasTotal = (customizingProduct.extras || []).filter(item => customizerExtras.includes(item.title)).reduce((sum, item) => sum + Number(item.price), 0);
        el('storefrontDetailTotal').textContent = fmt((Number(variationObject ? variationObject.price : customizingProduct.price) + extrasTotal) * customizerQuantity);
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
        const requestedQuantity = Math.max(p.min_qty || 1, Number(configuration.quantity || p.min_qty || 1));
        if (existing) {
            existing.quantity = Math.min(existing.max_qty, existing.quantity + requestedQuantity);
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
                quantity: Math.min(p.max_qty || 99, requestedQuantity),
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
                persistCart();
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
            if (el('bulkCartFabTotal')) el('bulkCartFabTotal').textContent = fmt(total);
        }
        const homeFab = el('storefrontHomeCartFab');
        if (homeFab) {
            const browsing = document.getElementById('storefrontGateway')?.classList.contains('is-browsing');
            homeFab.classList.toggle('is-visible', count > 0 && !browsing);
            el('storefrontHomeCartFabCount').textContent = count > 99 ? '99+' : count;
            el('storefrontHomeCartFabTotal').textContent = fmt(total);
        }
        if (el('storefrontCartTotal')) el('storefrontCartTotal').textContent = fmt(total);
        persistCart();
        updateFormEnabled();
    }
    if (isStorefront) window.renderStorefrontCartFab = renderCart;

    el('bulkCartFab')?.addEventListener('click', () => {
        if (isStorefront) {
            const panel = el('bulkCartPanel');
            const desktopPreview = window.matchMedia('(min-width: 900px)').matches;
            panel?.classList.add('is-storefront-open');
            panel?.classList.toggle('is-cart-preview', desktopPreview);
            if (el('storefrontCartNext')) el('storefrontCartNext').textContent = desktopPreview ? 'Ver carrito' : 'Siguiente';
            document.body.style.overflow = desktopPreview ? '' : 'hidden';
        } else if (isKiosk) {
            el('bulkCartPanel')?.classList.add('is-storefront-open');
        } else {
            el('bulkCartPanel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    function closeStorefrontCart() {
        el('bulkCartPanel')?.classList.remove('is-storefront-open', 'is-cart-preview', 'is-checkout-step');
        if (el('storefrontCartNext')) el('storefrontCartNext').textContent = 'Siguiente';
        if (isKiosk && el('bulkSubmitBtn')) el('bulkSubmitBtn').textContent = 'Revisar y ordenar';
        document.body.style.overflow = '';
    }
    el('storefrontCartBack')?.addEventListener('click', closeStorefrontCart);
    el('storefrontContinueShopping')?.addEventListener('click', closeStorefrontCart);
    const openStorefrontFulfillmentSelector = () => {
        if (typeof window.openStorefrontOrderMode === 'function') {
            window.openStorefrontOrderMode();
        } else {
            window.dispatchEvent(new CustomEvent('storefront:start-order'));
        }
    };
    el('storefrontFulfillmentBar')?.addEventListener('click', openStorefrontFulfillmentSelector);
    el('storefrontCartDeliveryChange')?.addEventListener('click', openStorefrontFulfillmentSelector);
    el('storefrontCartClear')?.addEventListener('click', () => { cart = []; renderCart(); closeStorefrontCart(); });
    el('storefrontCartPromotions')?.addEventListener('click', async () => {
        closeStorefrontCart();
        showingPromotions = true;
        el('bulkSearch').value = '';
        el('bulkCategory').value = '';
        await loadCatalog();
        if (!products.length) toast('No hay promociones disponibles en este momento.');
        moveCatalogHeaderOutOfView();
    });
    el('storefrontCartNext')?.addEventListener('click', async () => {
        if (cartTransitioning || isSubmitting) return;
        const panel = el('bulkCartPanel');
        if (panel.classList.contains('is-cart-preview')) {
            panel.classList.remove('is-cart-preview');
            el('storefrontCartNext').textContent = 'Siguiente';
            document.body.style.overflow = 'hidden';
            panel.scrollTo({ top: 0 });
            return;
        }
        if (!panel.classList.contains('is-checkout-step')) {
            const nextButton = el('storefrontCartNext');
            cartTransitioning = true;
            nextButton.disabled = true;
            nextButton.classList.add('is-loading');
            nextButton.setAttribute('aria-busy', 'true');
            nextButton.innerHTML = '<span class="bulk-order-btn-spinner" aria-hidden="true"></span> Preparando…';
            await new Promise(resolve => setTimeout(resolve, 180));
            panel.classList.add('is-checkout-step');
            nextButton.textContent = 'Confirmar pedido';
            nextButton.classList.remove('is-loading');
            nextButton.removeAttribute('aria-busy');
            nextButton.disabled = false;
            cartTransitioning = false;
            panel.scrollTo({ top: panel.scrollHeight, behavior: 'smooth' });
            return;
        }
        el('bulkSubmitBtn')?.click();
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
    el('bulkCategory').addEventListener('change', () => { showingPromotions = false; loadCatalog(); });
    el('bulkCustomizerClose').addEventListener('click', closeCustomizer);
    el('bulkAddonClose')?.addEventListener('click', closeAddonSheet);
    el('bulkAddonSheet')?.addEventListener('click', event => { if (event.target === el('bulkAddonSheet')) closeAddonSheet(); });
    el('storefrontDetailMinus')?.addEventListener('click', () => {
        customizerQuantity = Math.max(customizingProduct?.min_qty || 1, customizerQuantity - 1);
        el('storefrontDetailQty').textContent = customizerQuantity;
        updateCustomizerTotal();
    });
    el('storefrontDetailPlus')?.addEventListener('click', () => {
        customizerQuantity = Math.min(customizingProduct?.max_qty || 99, customizerQuantity + 1);
        el('storefrontDetailQty').textContent = customizerQuantity;
        updateCustomizerTotal();
    });
    el('storefrontPayNow')?.addEventListener('click', () => {
        el('bulkCustomizerAdd')?.click();
        setTimeout(() => {
            el('bulkCartPanel')?.classList.add('is-storefront-open', 'is-checkout-step');
            if (el('storefrontCartNext')) el('storefrontCartNext').textContent = 'Confirmar pedido';
            document.body.style.overflow = 'hidden';
        }, 0);
    });
    el('bulkCustomizer').addEventListener('click', (event) => {
        if (event.target === el('bulkCustomizer')) closeCustomizer();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (el('contactCreateModal')?.classList.contains('is-open')) {
            closeContactCreate();
        } else if (el('bulkAddonSheet')?.classList.contains('is-open')) {
            closeAddonSheet();
        } else if (el('bulkCustomizer').classList.contains('is-open')) {
            closeCustomizer();
        }
    });

    async function commitCustomizerSelection() {
        if (!customizingProduct || customizerSubmitting) return;

        const variation = usesEnhancedCustomizer ? customizerVariation : (document.querySelector('input[name="bulkVariation"]:checked')?.value || '');
        const extras = usesEnhancedCustomizer ? customizerExtras : Array.from(document.querySelectorAll('input[name="bulkExtra"]:checked')).map(input => input.value);
        if ((customizingProduct.variations || []).length && !variation) {
            toast('Selecciona la opción obligatoria');
            openAddonSheet();
            return;
        }

        customizerSubmitting = true;
        [el('bulkCustomizerAdd'), el('bulkAddonAdd')].filter(Boolean).forEach(button => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.classList.add('is-loading');
            button.textContent = 'Agregando...';
        });
        if (el('bulkAddonStatus')) el('bulkAddonStatus').textContent = 'Agregando tu producto al carrito...';

        // Permitimos que el navegador pinte el estado de carga antes de actualizar
        // el carrito, especialmente útil en teléfonos de menor rendimiento.
        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));

        try {
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
                if (isStorefront || isKiosk) line.quantity = customizerQuantity;
                renderCart();
                toast('Selección actualizada');
            } else {
                addProduct(customizingProduct, { variation, extras, quantity: customizerQuantity });
            }
            closeAddonSheet();
            closeCustomizer();
        } catch (error) {
            console.error(error);
            toast('No se pudo agregar el producto. Inténtalo nuevamente.');
        } finally {
            customizerSubmitting = false;
            [el('bulkCustomizerAdd'), el('bulkAddonAdd')].filter(Boolean).forEach(button => {
                button.removeAttribute('aria-busy');
                button.classList.remove('is-loading');
            });
            syncCustomizerActions();
        }
    }

    el('bulkCustomizerAdd').addEventListener('click', () => {
        if (!customizingProduct) return;
        if (isStorefront && !window.storefrontOrder?.confirmed) {
            if (typeof window.openStorefrontOrderMode === 'function') {
                window.openStorefrontOrderMode();
            } else {
                window.dispatchEvent(new CustomEvent('storefront:start-order'));
            }
            return;
        }
        const missingRequiredOption = (customizingProduct.variations || []).length > 0
            && !customizerVariation;
        if (usesEnhancedCustomizer && missingRequiredOption) {
            el('storefrontVariationSection')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            toast('Selecciona una opción obligatoria para continuar');
            return;
        }
        if (usesEnhancedCustomizer && (customizingProduct.extras || []).length > 0 && !customizerOptionsReviewed) {
            openAddonSheet();
            return;
        }
        commitCustomizerSelection();
    });
    el('bulkAddonAdd')?.addEventListener('click', commitCustomizerSelection);

    if (isAgent) {
        el('contactSearch').addEventListener('input', debounce((e) => searchContacts(e.target.value), 300));
        el('contactSearch').addEventListener('focus', (event) => searchContacts(event.target.value));
        el('contactSearch').addEventListener('keydown', (event) => {
            const options = Array.from(el('contactResults').querySelectorAll('button'));
            if (event.key === 'Escape') {
                setContactResultsOpen(false);
                return;
            }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (!options.length) return;
                const direction = event.key === 'ArrowDown' ? 1 : -1;
                activeContactOption = (activeContactOption + direction + options.length) % options.length;
                options.forEach((option, index) => option.classList.toggle('is-active', index === activeContactOption));
                options[activeContactOption].scrollIntoView({ block: 'nearest' });
                return;
            }
            if (event.key === 'Enter' && activeContactOption >= 0 && options[activeContactOption]) {
                event.preventDefault();
                options[activeContactOption].click();
            }
        });
        el('changeContactBtn').addEventListener('click', () => {
            selectedContact = null;
            renderSelectedContact();
            setTimeout(() => el('contactSearch').focus(), 0);
        });
        el('closeContactCreate')?.addEventListener('click', closeContactCreate);
        el('contactCreateForm')?.addEventListener('submit', createContact);
        el('contactRequiresInvoice')?.addEventListener('change', syncInvoiceFields);
        el('contactCreateNationalId')?.addEventListener('input', () => {
            if (el('contactRequiresInvoice').checked && el('contactBillingType').value === 'cedula') {
                el('contactBillingId').value = el('contactCreateNationalId').value.trim();
            }
        });
        el('contactCreateModal')?.addEventListener('click', (event) => {
            if (event.target === el('contactCreateModal')) closeContactCreate();
        });
        document.addEventListener('click', (e) => {
            if (!el('contactPicker').contains(e.target)) {
                setContactResultsOpen(false);
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
            el('kioskServiceLlevar').classList.toggle('is-selected', type === 'llevar');
            el('kioskServiceLlevar').setAttribute('aria-pressed', type === 'llevar' ? 'true' : 'false');
            el('kioskServiceServir').classList.toggle('is-selected', type === 'servir');
            el('kioskServiceServir').setAttribute('aria-pressed', type === 'servir' ? 'true' : 'false');
            el('kioskTableWrap').style.display = type === 'servir' ? 'block' : 'none';
            updateFormEnabled();
        };
        el('kioskServiceLlevar').addEventListener('click', () => setKioskService('llevar'));
        el('kioskServiceServir').addEventListener('click', () => setKioskService('servir'));
        el('kioskPaymentMethod').addEventListener('change', event => {
            el('kioskPaymentIcon').textContent = {
                efectivo: '💵',
                transferencia: '🏦',
                tarjeta: '💳',
            }[event.target.value] || '💵';
        });
        el('kioskNewOrderBtn')?.addEventListener('click', () => window.location.reload());
    }

    if (isStorefront) {
        el('storefrontSuccessHomeBtn')?.addEventListener('click', () => {
            // Se inicia una sesión de compra completamente nueva. No basta con
            // ocultar la pantalla de éxito: su estado DOM permanecía activo y
            // reaparecía al seleccionar el siguiente producto.
            cart = [];
            persistCart();
            try {
                if (persistenceKey) localStorage.removeItem(persistenceKey + '_checkout');
            } catch (_) {
                // La recarga sigue siendo suficiente si el navegador bloquea storage.
            }
            window.clearStorefrontOrderState?.();
            window.location.reload();
        });
    }

    const storefrontPhoneDigits = value => (value || '').replace(/\D+/g, '');
    const isValidStorefrontPhone = value => { const digits = storefrontPhoneDigits(value); return digits.length >= 8 && digits.length <= 15; };
    const isValidStorefrontEmail = value => !value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    const setStorefrontFieldError = (inputId, errorId, message) => {
        const input = el(inputId), errorBox = el(errorId);
        if (input) input.classList.toggle('is-invalid', !!message);
        if (errorBox) { errorBox.textContent = message || ''; errorBox.style.display = message ? 'block' : 'none'; }
    };
    if (isStorefront) {
        el('storefrontCustomerPhone')?.addEventListener('blur', () => {
            const value = el('storefrontCustomerPhone').value.trim();
            setStorefrontFieldError('storefrontCustomerPhone', 'storefrontCustomerPhoneError', value && !isValidStorefrontPhone(value) ? 'Ingresa un teléfono válido (8 a 15 dígitos).' : '');
        });
        el('storefrontCustomerEmail')?.addEventListener('blur', () => {
            const value = el('storefrontCustomerEmail').value.trim();
            setStorefrontFieldError('storefrontCustomerEmail', 'storefrontCustomerEmailError', value && !isValidStorefrontEmail(value) ? 'Ingresa un correo con formato válido.' : '');
        });
    }
    el('bulkSubmitBtn').addEventListener('click', async () => {
        if (isSubmitting) return;
        if (isKiosk && !el('bulkCartPanel')?.classList.contains('is-storefront-open')) {
            el('bulkCartPanel')?.classList.add('is-storefront-open');
            el('bulkSubmitBtn').textContent = 'Confirmar pedido';
            return;
        }
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
        if (!isAgent && !isKiosk && !Number(el('bulkBranch')?.value)) {
            toast('Selecciona la sucursal que atenderá tu pedido');
            el('bulkBranch')?.focus();
            return;
        }
        if (isStorefront && !window.storefrontCustomerAuthenticated) {
            toast('Inicia sesión o crea una cuenta para confirmar tu pedido');
            window.openStorefrontAccount?.();
            return;
        }
        if (isStorefront && (!el('storefrontCustomerName')?.value.trim() || !el('storefrontCustomerPhone')?.value.trim())) {
            toast('Ingresa tu nombre y teléfono para continuar');
            (el('storefrontCustomerName')?.value.trim() ? el('storefrontCustomerPhone') : el('storefrontCustomerName'))?.focus();
            return;
        }
        if (isStorefront) {
            const phoneValue = el('storefrontCustomerPhone').value.trim();
            const emailValue = el('storefrontCustomerEmail').value.trim();
            const phoneInvalid = !isValidStorefrontPhone(phoneValue);
            const emailInvalid = !!emailValue && !isValidStorefrontEmail(emailValue);
            setStorefrontFieldError('storefrontCustomerPhone', 'storefrontCustomerPhoneError', phoneInvalid ? 'Ingresa un teléfono válido (8 a 15 dígitos).' : '');
            setStorefrontFieldError('storefrontCustomerEmail', 'storefrontCustomerEmailError', emailInvalid ? 'Ingresa un correo con formato válido.' : '');
            if (phoneInvalid || emailInvalid) {
                toast('Revisa los datos resaltados en rojo');
                (phoneInvalid ? el('storefrontCustomerPhone') : el('storefrontCustomerEmail'))?.focus();
                return;
            }
        }
        if (isStorefront && el('storefrontInvoicePreference')?.value === 'invoice') {
            const requiredInvoiceFields = ['storefrontBillingId', 'storefrontBillingLegalName', 'storefrontBillingAddress', 'storefrontBillingEmail'];
            const missingInvoiceField = requiredInvoiceFields.find(id => !el(id)?.value.trim());
            if (missingInvoiceField) {
                toast('Completa todos los datos requeridos para la factura');
                el(missingInvoiceField)?.focus();
                return;
            }
        }
        const storefrontProofFile = isStorefront ? el('storefrontPaymentProof')?.files?.[0] : null;
        if (storefrontProofFile && storefrontProofFile.size > 12 * 1024 * 1024) {
            toast('El comprobante debe pesar máximo 12 MB');
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
            if (!isAgent && !isKiosk) {
                payload.branch_id = Number(el('bulkBranch')?.value) || null;
            }
            if (isStorefront) {
                payload.name = el('storefrontCustomerName').value.trim();
                payload.phone = el('storefrontCustomerPhone').value.trim();
                payload.email = el('storefrontCustomerEmail').value.trim() || null;
                payload.payment_method = el('storefrontPaymentMethod').value;
                payload.requires_invoice = el('storefrontInvoicePreference').value === 'invoice';
                payload.billing_type = el('storefrontBillingType').value;
                payload.billing_id = el('storefrontBillingId').value.trim() || null;
                payload.billing_legal_name = el('storefrontBillingLegalName').value.trim() || null;
                payload.billing_address = el('storefrontBillingAddress').value.trim() || null;
                payload.billing_email = el('storefrontBillingEmail').value.trim() || null;
                payload.service_type = window.storefrontOrder?.service_type || 'pickup';
                payload.address = window.storefrontOrder?.address || null;
                payload.reference = window.storefrontOrder?.reference || null;
                payload.latitude = window.storefrontOrder?.latitude ?? null;
                payload.longitude = window.storefrontOrder?.longitude ?? null;
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
                // Ej.: la sesión venció justo entre cargar la página y
                // confirmar -- se le pide iniciar sesión de nuevo en vez de
                // solo mostrar un error genérico.
                if (data.needs_account) window.openStorefrontAccount?.();
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
            if (isStorefront && data.card_payment_redirect) {
                el('bulkSuccessHint').textContent = data.message || 'Por el momento no procesamos pagos con tarjeta aquí. Continúa tu compra en línea de forma segura.';
                const cardWrap = el('storefrontCardPaymentWrap');
                el('storefrontCardPaymentLink').href = data.payment_url;
                cardWrap.style.display = 'block';
            }
            if (isStorefront) {
                const paymentStatus = el('storefrontSuccessPaymentStatus');
                if (payload.payment_method === 'transferencia') {
                    paymentStatus.style.display = 'block';
                    paymentStatus.textContent = storefrontProofFile
                        ? 'Cargando tu comprobante…'
                        : 'Transferencia pendiente: podrás cargar el comprobante desde Mi cuenta.';
                }
                if (storefrontProofFile && data.proof_upload_url && data.order_access_token) {
                    try {
                        const proofData = new FormData();
                        proofData.append('proof', storefrontProofFile);
                        proofData.append('token', data.order_access_token);
                        const proofResponse = await fetch(data.proof_upload_url, {
                            method: 'POST',
                            headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                            body: proofData,
                        });
                        const proofResult = await proofResponse.json();
                        if (!proofResponse.ok || !proofResult.ok) throw new Error(proofResult.message || 'No se pudo cargar el comprobante.');
                        paymentStatus.textContent = '✓ Comprobante recibido. El equipo verificará tu pago.';
                    } catch (proofError) {
                        paymentStatus.textContent = 'El pedido fue creado, pero no se pudo cargar el comprobante. Inténtalo desde Mi cuenta.';
                    }
                }
                cart = [];
                // renderCart() ya hace persistCart() y ya oculta bulkCartFab
                // (toggle 'is-visible' según count) -- antes se armaba el
                // array vacío pero nunca se llamaba renderCart(), así que el
                // botón flotante del carrito seguía mostrando lo de antes de
                // pedir, encima de la pantalla de éxito.
                renderCart();
                if (persistenceKey) localStorage.removeItem(persistenceKey + '_checkout');
                window.clearStorefrontOrderState?.();
                // Quedaba con scroll bloqueado (se fija en 'hidden' al abrir
                // el checkout) y sin ningún botón de navegación visible --
                // el cliente literalmente no podía salir de esta pantalla.
                document.body.style.overflow = '';
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

    restoreStorefrontDraft();
    if (isStorefront) {
        syncStorefrontCheckoutFields();
        ['storefrontCustomerName', 'storefrontCustomerPhone', 'storefrontCustomerEmail', 'storefrontPaymentMethod', 'storefrontInvoicePreference', 'storefrontBillingType', 'storefrontBillingId', 'storefrontBillingLegalName', 'storefrontBillingAddress', 'storefrontBillingEmail', 'bulkOrderNote']
            .forEach(id => el(id)?.addEventListener(['storefrontPaymentMethod', 'storefrontInvoicePreference', 'storefrontBillingType'].includes(id) ? 'change' : 'input', () => { syncStorefrontCheckoutFields(); persistStorefrontDraft(); }));
    }
    renderCart();
    loadCatalog();
})();
</script>
