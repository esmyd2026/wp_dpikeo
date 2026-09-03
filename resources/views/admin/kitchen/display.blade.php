<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $activeCompany?->name ? $activeCompany->name.' · ' : '' }}Estado de pedidos</title>
    <style>
        :root {
            --bg:#07110f; --surface:#0d1c19; --card:#132521;
            --line:rgba(203,232,224,.13); --text:#f8fffc; --muted:#9eb7b0;
            --brand:#20b486; --brand-soft:#8ce4c7; --amber:#f2b84b;
            --green:#32cf88; --danger:#ff6b6b;
        }
        * { box-sizing:border-box; }
        html, body { width:100%; min-height:100%; }
        body {
            display:flex; flex-direction:column; height:100vh; margin:0; overflow:hidden;
            color:var(--text); background:radial-gradient(circle at 16% -10%,rgba(32,180,134,.12),transparent 30%),linear-gradient(145deg,#081512,#050b09 70%);
            font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;
        }
        .screen-head {
            flex:0 0 auto; min-height:92px; display:flex; align-items:center; justify-content:space-between;
            gap:2rem; padding:15px 3vw; background:rgba(13,28,25,.96); border-bottom:1px solid var(--line);
            box-shadow:0 10px 30px rgba(0,0,0,.2);
        }
        .screen-brand { display:flex; align-items:center; gap:15px; min-width:0; }
        .screen-logo { width:58px; height:58px; flex:0 0 58px; object-fit:cover; border-radius:15px; border:1px solid rgba(255,255,255,.16); box-shadow:0 7px 18px rgba(0,0,0,.25); }
        .screen-brand-title { font-size:clamp(1.35rem,2.1vw,2.25rem); font-weight:900; letter-spacing:.13em; line-height:1; }
        .screen-brand-copy small { display:block; margin-top:.5rem; color:var(--brand-soft); font-size:clamp(.62rem,.8vw,.78rem); font-weight:800; letter-spacing:.17em; text-transform:uppercase; }
        .screen-head-right { display:flex; align-items:center; gap:1.5rem; }
        .screen-sync { display:flex; align-items:center; gap:.55rem; color:var(--muted); font-size:clamp(.68rem,.8vw,.8rem); font-weight:700; }
        .screen-sync-dot { width:8px; height:8px; border-radius:50%; background:var(--brand); box-shadow:0 0 0 5px rgba(32,180,134,.12); }
        .screen-sync.is-offline { color:#ffb1b1; }
        .screen-sync.is-offline .screen-sync-dot { background:var(--danger); box-shadow:0 0 0 5px rgba(255,107,107,.12); }
        .screen-clock { min-width:180px; text-align:right; font-size:clamp(1.55rem,2.4vw,2.5rem); font-weight:900; line-height:1; font-variant-numeric:tabular-nums; }
        .screen-date { margin-top:.42rem; color:var(--muted); font-size:clamp(.62rem,.75vw,.76rem); font-weight:650; text-transform:capitalize; }
        .screen-board { display:grid; flex:1 1 auto; min-height:0; grid-template-columns:1fr 1fr; gap:1.2rem; padding:1.25rem 3vw 1.5rem; }
        .screen-column { display:flex; flex-direction:column; min-width:0; min-height:0; padding:1rem; border:1px solid var(--line); border-radius:22px; background:rgba(13,28,25,.68); box-shadow:inset 0 1px 0 rgba(255,255,255,.025),0 18px 45px rgba(0,0,0,.12); }
        .screen-column-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
        .screen-title { display:flex; align-items:center; gap:.75rem; margin:0; font-size:clamp(1.05rem,1.65vw,1.7rem); font-weight:900; letter-spacing:.055em; text-transform:uppercase; }
        .screen-stage-icon { width:39px; height:39px; display:grid; place-items:center; border-radius:11px; background:rgba(242,184,75,.13); color:var(--amber); font-size:.8rem; font-weight:900; }
        .screen-column.ready .screen-stage-icon { background:rgba(50,207,136,.13); color:var(--green); }
        .screen-count { min-width:40px; height:30px; display:grid; place-items:center; padding:0 .55rem; border-radius:999px; background:rgba(255,255,255,.07); color:#dcebe7; font-size:.82rem; font-weight:900; }
        .screen-cards { display:grid; flex:1 1 auto; min-height:0; grid-template-columns:repeat(auto-fill,minmax(205px,1fr)); gap:.85rem; align-content:start; overflow-y:auto; padding:0 5px 6px 0; scrollbar-width:thin; scrollbar-color:rgba(140,228,199,.35) transparent; }
        .screen-cards::-webkit-scrollbar { width:6px; }
        .screen-cards::-webkit-scrollbar-thumb { border-radius:999px; background:rgba(140,228,199,.35); }
        .screen-card { position:relative; min-height:130px; padding:1rem; overflow:hidden; border:1px solid var(--line); border-radius:17px; background:linear-gradient(145deg,rgba(25,46,40,.98),rgba(14,29,25,.98)); box-shadow:0 9px 24px rgba(0,0,0,.18); }
        .screen-card::before { content:""; position:absolute; top:0; right:0; bottom:0; width:5px; background:var(--amber); }
        .screen-column.ready .screen-card::before { background:var(--green); }
        .screen-card-top { display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
        .screen-status { color:var(--amber); font-size:clamp(.58rem,.68vw,.7rem); font-weight:900; letter-spacing:.11em; text-transform:uppercase; }
        .screen-column.ready .screen-status { color:var(--green); }
        .screen-wait-label { color:var(--muted); font-size:.66rem; font-weight:700; }
        .screen-number { margin-top:.55rem; color:#fff; font-size:clamp(2.65rem,4.2vw,4.7rem); font-weight:950; line-height:.92; letter-spacing:.025em; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .screen-empty { display:grid; min-height:190px; place-items:center; padding:1.5rem; border:1px dashed rgba(158,183,176,.22); border-radius:17px; color:var(--muted); text-align:center; }
        .screen-empty-icon { display:grid; width:48px; height:48px; margin:0 auto .75rem; place-items:center; border-radius:50%; background:rgba(255,255,255,.045); color:var(--brand-soft); font-size:1.3rem; }
        .screen-empty strong { display:block; color:#dcebe7; font-size:.9rem; }
        .screen-empty span { display:block; margin-top:.25rem; font-size:.75rem; }
        .screen-queue { flex:0 0 auto; margin-top:.9rem; padding-top:.8rem; border-top:1px solid var(--line); }
        .screen-queue-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:.65rem; }
        .screen-queue-title { margin:0; color:var(--muted); font-size:.7rem; font-weight:850; letter-spacing:.11em; text-transform:uppercase; }
        .screen-queue-count { display:inline-grid; min-width:22px; height:22px; margin-left:.35rem; padding:0 5px; place-items:center; border-radius:999px; background:rgba(32,180,134,.13); color:var(--brand-soft); font-size:.68rem; }
        .screen-queue-hint { color:#78918a; font-size:.66rem; }
        .screen-queue-list { display:flex; gap:.5rem; overflow:hidden; }
        .screen-queue-ticket { display:flex; align-items:center; gap:.5rem; min-width:max-content; padding:.48rem .65rem; border:1px solid var(--line); border-radius:10px; background:rgba(255,255,255,.035); color:var(--muted); font-size:.75rem; }
        .screen-queue-ticket strong { color:#fff; font-size:.88rem; font-variant-numeric:tabular-nums; }
        @media (max-width:1050px) { .screen-cards { grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); } }
        @media (max-width:780px) {
            body { height:auto; min-height:100vh; overflow:auto; }
            .screen-head { min-height:78px; padding:11px 1rem; }
            .screen-logo { width:46px; height:46px; flex-basis:46px; }
            .screen-sync { display:none; }
            .screen-clock { min-width:110px; }
            .screen-board { grid-template-columns:1fr; min-height:auto; padding:1rem; }
            .screen-column { min-height:430px; }
            .screen-cards { overflow:visible; }
        }
    </style>
</head>
<body>
    <header class="screen-head">
        <div class="screen-brand">
            <div class="screen-brand-copy"><div class="screen-brand-title">{{ $activeCompany?->name ?? 'Cocina' }}</div><small>Estado de pedidos</small></div>
        </div>
        <div class="screen-head-right">
            <div class="screen-sync" id="syncStatus"><span class="screen-sync-dot"></span><span id="syncText">Actualización automática</span></div>
            <div class="screen-clock"><div id="clock">--:--</div><div class="screen-date" id="screenDate"></div></div>
        </div>
    </header>

    <main class="screen-board">
        <section class="screen-column preparing">
            <div class="screen-column-head"><h1 class="screen-title"><span class="screen-stage-icon">01</span> En preparación</h1><span class="screen-count" id="preparingCount">0</span></div>
            <div class="screen-cards" id="preparing"></div>
            <aside class="screen-queue">
                <div class="screen-queue-head"><h2 class="screen-queue-title">Próximos en cola <span class="screen-queue-count" id="queueCount">0</span></h2><span class="screen-queue-hint">Esperando preparación</span></div>
                <div class="screen-queue-list" id="queue"></div>
            </aside>
        </section>
        <section class="screen-column ready">
            <div class="screen-column-head"><h1 class="screen-title"><span class="screen-stage-icon">02</span> Listos para entregar</h1><span class="screen-count" id="readyCount">0</span></div>
            <div class="screen-cards" id="ready"></div>
        </section>
    </main>

    <script>
        const endpoint = @json(route('admin.kitchen.data'));
        const escapeText = value => { const node = document.createElement('div'); node.textContent = value ?? ''; return node.innerHTML; };

        function updateClock() {
            const now = new Date();
            document.getElementById('clock').textContent = now.toLocaleTimeString('es-EC', { hour:'2-digit', minute:'2-digit' });
            document.getElementById('screenDate').textContent = now.toLocaleDateString('es-EC', { weekday:'long', day:'2-digit', month:'long' });
        }

        function ticket(order) {
            return `<article class="screen-card">
                <div class="screen-card-top"><span class="screen-status">${escapeText(order.status_label)}</span><span class="screen-wait-label">Turno</span></div>
                <div class="screen-number">${escapeText(order.display_number || order.number)}</div>
            </article>`;
        }

        function queueTicket(order) {
            return `<div class="screen-queue-ticket"><strong>${escapeText(order.display_number || order.number)}</strong></div>`;
        }

        function emptyState(message, hint) {
            return `<div class="screen-empty"><div><span class="screen-empty-icon">✓</span><strong>${message}</strong><span>${hint}</span></div></div>`;
        }

        async function refresh() {
            const sync = document.getElementById('syncStatus');
            const syncText = document.getElementById('syncText');
            try {
                const response = await fetch(endpoint, { headers:{ Accept:'application/json' } });
                if (!response.ok) throw new Error();
                const data = await response.json();
                const orders = data.orders || [];
                const preparing = orders.filter(order => order.status === 'preparing');
                const ready = orders.filter(order => order.status === 'ready');
                const queued = orders.filter(order => ['confirmed', 'paid'].includes(order.status));

                document.getElementById('preparing').innerHTML = preparing.length ? preparing.map(ticket).join('') : emptyState('Cocina al día', 'No hay pedidos en preparación');
                document.getElementById('ready').innerHTML = ready.length ? ready.map(ticket).join('') : emptyState('Sin pedidos listos', 'Los turnos aparecerán aquí al terminarse');
                document.getElementById('preparingCount').textContent = preparing.length;
                document.getElementById('readyCount').textContent = ready.length;
                document.getElementById('queueCount').textContent = queued.length;
                document.getElementById('queue').innerHTML = queued.slice(0, 7).map(queueTicket).join('') || '<span class="screen-queue-hint">No hay pedidos esperando</span>';

                sync.classList.remove('is-offline');
                syncText.textContent = 'Actualizado ahora';
                updateClock();
            } catch (_) {
                sync.classList.add('is-offline');
                syncText.textContent = 'Sin conexión';
            }
        }

        updateClock();
        refresh();
        setInterval(refresh, 10000);
        setInterval(updateClock, 1000);
    </script>
</body>
</html>
