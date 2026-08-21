<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DPIKEOS · Estado de pedidos</title>
    <style>
        :root { --surface:#24170f; --orange:#f36a17; --gold:#ffbb35; --green:#21c56e; --white:#fffaf4; }
        * { box-sizing:border-box; }
        body { display:flex; flex-direction:column; height:100vh; margin:0; overflow:hidden; color:var(--white); background:radial-gradient(circle at 50% -20%,#482313 0,#1b100b 42%,#0e0906 100%); font-family:Arial,sans-serif; }
        .screen-head { flex:0 0 112px; display:flex; align-items:center; justify-content:space-between; gap:2rem; padding:16px 4vw; background:linear-gradient(104deg,#8a2106 0%,#c63a08 48%,#f17a16 100%); border-bottom:5px solid var(--gold); box-shadow:0 4px 20px rgba(0,0,0,.22); }
        .screen-brand { display:flex; align-items:center; gap:18px; min-width:0; }
        .screen-logo { width:50px; height:50px; flex:0 0 50px; object-fit:cover; border-radius:12px; border:1px solid rgba(255,255,255,.34); box-shadow:0 3px 10px rgba(0,0,0,.2); }
        .screen-brand-copy { min-width:0; }
        .screen-brand-title { font-size:clamp(1.35rem,2.5vw,2.5rem); font-weight:900; letter-spacing:.08em; line-height:1; }
        .screen-brand-copy small { display:block; margin-top:.48rem; color:#fff5e9; font-size:clamp(.66rem,1vw,.9rem); font-weight:700; letter-spacing:.18em; text-transform:uppercase; }
        .screen-clock { flex:0 0 auto; text-align:right; font-size:clamp(1.5rem,2.6vw,2.7rem); font-weight:900; font-variant-numeric:tabular-nums; }
        .screen-clock span { display:block; margin-top:.25rem; color:#fff4e7; font-size:.31em; font-weight:700; letter-spacing:.08em; }
        .screen-board { position:relative; display:grid; flex:1 1 auto; min-height:0; grid-template-columns:1fr 1fr; gap:3vw; padding:3.4vh 4vw; }
        .screen-board::before { content:""; position:absolute; top:3.4vh; bottom:3.4vh; left:50%; width:1px; background:linear-gradient(to bottom,transparent,rgba(255,213,157,.38) 12%,rgba(255,213,157,.38) 88%,transparent); }
        .screen-column { display:flex; flex-direction:column; min-width:0; min-height:0; }
        .screen-title { display:flex; align-items:center; gap:.7rem; margin:0 0 1.25rem; color:#fff9f1; font-size:clamp(1.2rem,2vw,2.15rem); font-weight:900; letter-spacing:.09em; text-transform:uppercase; }
        .screen-dot { width:15px; height:15px; flex:0 0 15px; border-radius:50%; background:var(--gold); box-shadow:0 0 20px rgba(255,187,53,.75); }
        .screen-column.ready .screen-dot { background:var(--green); box-shadow:0 0 20px rgba(33,197,110,.72); }
        .screen-cards { display:grid; flex:1 1 auto; min-height:0; grid-template-columns:repeat(auto-fill,minmax(145px,1fr)); gap:.85rem; align-content:start; overflow-y:auto; padding:0 8px 8px 0; scrollbar-width:thin; scrollbar-color:rgba(255,187,53,.55) transparent; }
        .screen-cards::-webkit-scrollbar { width:7px; }.screen-cards::-webkit-scrollbar-thumb { border-radius:999px; background:rgba(255,187,53,.55); }
        .screen-card { position:relative; min-height:112px; padding:.85rem .9rem .75rem; overflow:hidden; border:1px solid rgba(255,188,100,.34); border-radius:15px; background:linear-gradient(145deg,rgba(68,38,25,.98),rgba(37,23,16,.98)); box-shadow:0 8px 18px rgba(0,0,0,.16); }
        .screen-card::after { content:""; position:absolute; left:0; right:0; bottom:0; height:5px; background:var(--gold); }
        .screen-column.ready .screen-card { border-color:rgba(62,224,133,.48); background:linear-gradient(145deg,#17442d,#0d2d1d); }
        .screen-column.ready .screen-card::after { background:var(--green); }
        .screen-status { display:inline-flex; margin-bottom:.48rem; color:#ffdba1; font-size:clamp(.54rem,.68vw,.68rem); font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
        .screen-column.ready .screen-status { color:#a4f5c3; }
        .screen-number { color:#fff; font-size:clamp(2rem,3.7vw,4.2rem); font-weight:900; line-height:1.04; letter-spacing:.02em; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .screen-time { margin-top:.4rem; color:#ffc553; font-size:clamp(.8rem,1vw,1.05rem); font-weight:850; font-variant-numeric:tabular-nums; }
        .screen-column.ready .screen-time { color:#7df0a9; }
        .screen-empty { display:grid; place-items:center; min-height:148px; padding:1rem; border:1px dashed rgba(255,214,147,.35); border-radius:18px; color:#c6a997; font-size:clamp(.9rem,1.1vw,1.1rem); text-align:center; }
        .screen-queue { flex:0 0 auto; margin-top:.7rem; padding-top:.8rem; border-top:1px solid rgba(255,213,157,.22); }
        .screen-queue-title { display:flex; align-items:center; gap:.55rem; margin:0 0 .65rem; color:#c5ab98; font-size:clamp(.7rem,.85vw,.84rem); font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
        .screen-queue-title span { display:grid; min-width:20px; height:20px; padding:0 5px; place-items:center; border-radius:999px; background:rgba(255,187,53,.14); color:#ffd083; font-size:.68rem; }
        .screen-queue-list { display:flex; flex-wrap:wrap; gap:.5rem; }
        .screen-queue-ticket { display:flex; align-items:center; gap:.42rem; padding:.42rem .56rem; border:1px solid rgba(255,213,157,.18); border-radius:9px; background:rgba(255,255,255,.035); color:#d8c4b5; font-size:clamp(.75rem,.9vw,.9rem); }
        .screen-queue-ticket strong { color:#fff6ee; font-variant-numeric:tabular-nums; }
        .screen-queue-ticket small { color:#bb9c88; }
        @media (max-width:850px) { body { height:auto; min-height:100vh; overflow:auto; } .screen-head { flex-basis:88px; padding:12px 1.1rem; } .screen-logo { width:42px; height:42px; flex-basis:42px; } .screen-brand { gap:10px; } .screen-board { grid-template-columns:1fr; min-height:auto; padding:1.25rem; gap:2rem; } .screen-board::before { display:none; } .screen-column { min-height:420px; } .screen-cards { overflow:visible; } }
    </style>
</head>
<body>
    <header class="screen-head">
        <div class="screen-brand">
            <img class="screen-logo" src="{{ asset('storage/img/dpikeologo.jpg') }}" alt="DPIKEOS">
            <div class="screen-brand-copy"><div class="screen-brand-title">DPIKEOS</div><small>Estado de pedidos</small></div>
        </div>
        <div class="screen-clock" id="clock">--:--<span>Actualización automática</span></div>
    </header>

    <main class="screen-board">
        <section class="screen-column preparing"><h1 class="screen-title"><span class="screen-dot"></span> En preparación</h1><div class="screen-cards" id="preparing"></div><aside class="screen-queue"><h2 class="screen-queue-title">En cola <span id="queueCount">0</span></h2><div class="screen-queue-list" id="queue"></div></aside></section>
        <section class="screen-column ready"><h1 class="screen-title"><span class="screen-dot"></span> Listos para entregar</h1><div class="screen-cards" id="ready"></div></section>
    </main>

    <script>
        const endpoint = @json(route('admin.kitchen.data'));
        const escapeText = value => { const node = document.createElement('div'); node.textContent = value ?? ''; return node.innerHTML; };
        function updateClock() { const now = new Date(); document.getElementById('clock').innerHTML = now.toLocaleTimeString('es-EC', { hour:'2-digit', minute:'2-digit' }) + '<span>Actualización automática</span>'; }
        function ticket(order) { return `<article class="screen-card"><div class="screen-status">${escapeText(order.status_label)}</div><div class="screen-number">${escapeText(order.display_number || order.number)}</div><div class="screen-time">${escapeText(order.elapsed_label || 'Recién ingresado')}</div></article>`; }
        function queueTicket(order) { return `<div class="screen-queue-ticket"><strong>${escapeText(order.display_number || order.number)}</strong><small>${escapeText(order.elapsed_label || 'Recién ingresado')}</small></div>`; }
        async function refresh() {
            try {
                const response = await fetch(endpoint, { headers:{ Accept:'application/json' } });
                const data = await response.json();
                const orders = data.orders || [];
                for (const status of ['preparing', 'ready']) {
                    const list = orders.filter(order => order.status === status);
                    document.getElementById(status).innerHTML = list.length ? list.map(ticket).join('') : '<div class="screen-empty">Sin pedidos por el momento</div>';
                }
                const queued = orders.filter(order => ['confirmed', 'paid'].includes(order.status));
                document.getElementById('queueCount').textContent = queued.length;
                document.getElementById('queue').innerHTML = queued.slice(0, 5).map(queueTicket).join('') || '<span style="color:#856e60;font-size:.78rem">Sin pedidos en cola</span>';
                updateClock();
            } catch (_) { document.querySelector('#clock span').textContent = 'Sin conexión'; }
        }
        refresh(); setInterval(refresh, 10000); setInterval(updateClock, 1000);
    </script>
</body>
</html>
