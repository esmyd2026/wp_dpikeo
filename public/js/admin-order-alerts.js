/**
 * Alertas globales de pedidos nuevos — funciona en todo el panel admin,
 * no solo en la pantalla de Pedidos (sondeo, sin websockets en el proyecto).
 */
(function () {
    const config = window.WaOrderAlertsConfig || {};
    const pollUrl = config.pollUrl;
    if (!pollUrl) return;

    const CURSOR_KEY = 'wa_orders_since_id';
    const EVENT_CURSOR_KEY = 'wa_order_events_since_id';
    const DISMISSED_KEY = 'wa_orders_dismissed_v1';
    const POLL_MS = 6000;

    /**
     * Pedido explícito: "quiero que sea más ruidoso o permíteme seleccionar
     * los sonidos desde el panel administrativo". Cada perfil es un patrón
     * de tonos distinto (volumen y repeticiones) -- el admin elige cuál usa
     * cada evento desde Configuración del chatbot (config.alert_sounds),
     * inyectado en window.WaOrderAlertsConfig.sounds.
     */
    const TONE_PRESETS = {
        suave: { freqs: [660], gain: 0.07, duration: 0.3, repeats: 1, gapMs: 0 },
        normal: { freqs: [740, 990], gain: 0.11, duration: 0.32, repeats: 1, gapMs: 180 },
        fuerte: { freqs: [740, 990], gain: 0.22, duration: 0.4, repeats: 1, gapMs: 180 },
        urgente: { freqs: [880, 1180], gain: 0.26, duration: 0.28, repeats: 3, gapMs: 160 },
    };
    const DEFAULT_SOUNDS = {
        new_order: 'fuerte',
        payment_proof: 'fuerte',
        invoice_confirmed: 'normal',
        agent_request: 'urgente',
    };
    const soundConfig = Object.assign({}, DEFAULT_SOUNDS, config.sounds || {});

    const EVENT_LABELS = {
        payment_proof: { icon: 'fa-receipt', title: 'Comprobante enviado' },
        invoice_confirmed: { icon: 'fa-file-invoice', title: 'Factura confirmada' },
        agent_request: { icon: 'fa-headset', title: 'Pidió hablar con un asesor' },
    };
    const pageTitleBase = document.title;
    let audioContext = null;
    let pollInitialized = false;
    let titleFlashTimer = null;
    let pollTimer = null;
    let panelOpen = false;
    let pendingOrders = [];
    const broadcast = typeof BroadcastChannel !== 'undefined'
        ? new BroadcastChannel('wa_order_alerts')
        : null;

    function unlockAudio() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            if (!audioContext) audioContext = new AudioContext();
            if (audioContext.state === 'suspended') audioContext.resume();
        } catch (e) { /* ignore */ }
    }

    document.addEventListener('click', unlockAudio, { once: true, passive: true });
    document.addEventListener('keydown', unlockAudio, { once: true });

    function getCursor() {
        const v = parseInt(localStorage.getItem(CURSOR_KEY) || '0', 10);
        return Number.isNaN(v) ? 0 : v;
    }

    function setCursor(id) {
        localStorage.setItem(CURSOR_KEY, String(id));
    }

    function getEventCursor() {
        const v = parseInt(localStorage.getItem(EVENT_CURSOR_KEY) || '0', 10);
        return Number.isNaN(v) ? 0 : v;
    }

    function setEventCursor(id) {
        localStorage.setItem(EVENT_CURSOR_KEY, String(id));
    }

    function getDismissed() {
        try {
            return JSON.parse(localStorage.getItem(DISMISSED_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function setDismissed(ids) {
        localStorage.setItem(DISMISSED_KEY, JSON.stringify(ids));
    }

    /**
     * Reproduce un patrón de tonos (ver TONE_PRESETS). presetName puede ser
     * cualquier clave de TONE_PRESETS; si no existe, cae a "normal".
     */
    function playTone(presetName) {
        unlockAudio();
        const preset = TONE_PRESETS[presetName] || TONE_PRESETS.normal;
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = audioContext || new AudioContext();
            audioContext = ctx;

            const repeatSpacing = preset.freqs.length * preset.gapMs + 260;
            for (let r = 0; r < preset.repeats; r++) {
                preset.freqs.forEach((freq, i) => {
                    const t0 = ctx.currentTime + (r * repeatSpacing + i * preset.gapMs) / 1000;
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, t0);
                    gain.gain.setValueAtTime(0.0001, t0);
                    gain.gain.exponentialRampToValueAtTime(preset.gain, t0 + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + preset.duration);
                    osc.connect(gain).connect(ctx.destination);
                    osc.start(t0);
                    osc.stop(t0 + preset.duration + 0.02);
                });
            }
        } catch (e) { /* ignore */ }
    }

    /** Compat: el sonido de pedido nuevo usa su propio preset configurable. */
    function playOrderChime() {
        playTone(soundConfig.new_order);
    }

    function playEventTone(eventType) {
        playTone(soundConfig[eventType] || 'normal');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function ordersUrl(orderId) {
        const base = (config.ordersUrl || '/admin/orders').replace(/\/$/, '');
        return orderId ? `${base}?open_order=${orderId}` : base;
    }

    function showToast(order) {
        const stack = document.getElementById('wa-agent-toast-stack');
        if (!stack) return;

        const name = order.contact_name || 'Cliente';
        const toast = document.createElement('div');
        toast.className = 'wa-agent-toast';
        toast.innerHTML = `
            <div class="wa-agent-toast-icon wa-order-toast-icon"><i class="fas fa-receipt"></i></div>
            <div class="wa-agent-toast-body">
                <p class="wa-agent-toast-title">Nuevo pedido ${escapeHtml(order.order_number || ('#' + order.id))}</p>
                <p class="wa-agent-toast-text">${escapeHtml(name)} · $${Number(order.total || 0).toFixed(2)}</p>
                <div class="wa-agent-toast-time">Ahora · Toca para abrir el pedido</div>
            </div>
        `;

        toast.addEventListener('click', function () {
            window.location.href = ordersUrl(order.id);
            removeToast(toast);
        });

        stack.prepend(toast);
        setTimeout(() => removeToast(toast), 10000);
    }

    function removeToast(toast) {
        if (!toast || toast.dataset.removing) return;
        toast.dataset.removing = '1';
        toast.style.animation = 'waToastOut .25s ease forwards';
        setTimeout(() => toast.remove(), 260);
    }

    /** Toast para los eventos de pedidos ya conocidos (comprobante, factura, asesor). */
    function showEventToast(event) {
        const stack = document.getElementById('wa-agent-toast-stack');
        if (!stack) return;

        const meta = EVENT_LABELS[event.type] || { icon: 'fa-bell', title: 'Actualización de pedido' };
        const name = event.contact_name || 'Cliente';
        const toast = document.createElement('div');
        toast.className = 'wa-agent-toast';
        toast.innerHTML = `
            <div class="wa-agent-toast-icon wa-order-toast-icon"><i class="fas ${meta.icon}"></i></div>
            <div class="wa-agent-toast-body">
                <p class="wa-agent-toast-title">${escapeHtml(meta.title)} · ${escapeHtml(event.order_number || ('#' + event.order_id))}</p>
                <p class="wa-agent-toast-text">${escapeHtml(name)}</p>
                <div class="wa-agent-toast-time">Ahora · Toca para abrir el pedido</div>
            </div>
        `;

        toast.addEventListener('click', function () {
            window.location.href = ordersUrl(event.order_id);
            removeToast(toast);
        });

        stack.prepend(toast);
        setTimeout(() => removeToast(toast), 10000);
    }

    function showDesktopNotification(order) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;

        const name = order.contact_name || 'Cliente';
        try {
            const notification = new Notification('🆕 Nuevo pedido · ' + (order.order_number || ('#' + order.id)), {
                body: `${name} · $${Number(order.total || 0).toFixed(2)}`,
                icon: config.favicon || '',
                badge: config.favicon || '',
                tag: `order-${order.id}`,
                requireInteraction: true,
                silent: true,
            });

            notification.onclick = function () {
                window.focus();
                window.location.href = ordersUrl(order.id);
                notification.close();
            };

            setTimeout(() => notification.close(), 15000);
        } catch (e) { /* ignore */ }
    }

    function flashTitle() {
        clearInterval(titleFlashTimer);
        let showAlert = true;
        titleFlashTimer = setInterval(function () {
            document.title = showAlert ? '(1) 🆕 Pedido nuevo' : pageTitleBase;
            showAlert = !showAlert;
        }, 900);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            clearInterval(titleFlashTimer);
            titleFlashTimer = null;
            document.title = pageTitleBase;
        }
    });

    function formatOrderTime(iso) {
        if (!iso) return '';
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) return '';
        const now = new Date();
        const diffMs = now - date;
        if (diffMs < 60000) return 'Hace un momento';
        if (diffMs < 3600000) return `Hace ${Math.floor(diffMs / 60000)} min`;
        if (diffMs < 86400000) return `Hace ${Math.floor(diffMs / 3600000)} h`;
        return date.toLocaleString('es', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    }

    function setBadgeEl(el, count) {
        if (!el) return;
        if (count > 0) {
            el.textContent = count > 99 ? '99+' : String(count);
            el.classList.remove('hidden');
        } else {
            el.textContent = '';
            el.classList.add('hidden');
        }
    }

    function updateGlobalBadge(count) {
        setBadgeEl(document.getElementById('global-orders-count'), count);
        setBadgeEl(document.getElementById('sidebar-orders-new-count'), count);

        const panelCount = document.getElementById('wa-order-notifications-panel-count');
        if (panelCount) {
            if (count > 0) {
                panelCount.textContent = count === 1 ? '1 nuevo' : `${count} nuevos`;
                panelCount.style.display = '';
            } else {
                panelCount.textContent = '';
                panelCount.style.display = 'none';
            }
        }
    }

    function renderNotificationPanel() {
        const list = document.getElementById('wa-order-notifications-list');
        if (!list) return;

        if (!pendingOrders.length) {
            list.innerHTML = '<div class="wa-agent-notifications-empty">No hay pedidos nuevos sin abrir</div>';
            return;
        }

        list.innerHTML = pendingOrders.map(order => {
            const name = escapeHtml(order.contact_name || 'Cliente');
            const time = formatOrderTime(order.created_at);
            const href = ordersUrl(order.id);
            return `
                <a href="${href}" class="wa-agent-notification-item" data-order-id="${order.id}">
                    <div class="ni-icon wa-order-toast-icon"><i class="fas fa-receipt"></i></div>
                    <div>
                        <p class="ni-title">${escapeHtml(order.order_number || ('#' + order.id))}</p>
                        <p class="ni-text">${name} · $${Number(order.total || 0).toFixed(2)}</p>
                        ${time ? `<div class="ni-time">${escapeHtml(time)}</div>` : ''}
                    </div>
                </a>
            `;
        }).join('');

        list.querySelectorAll('.wa-agent-notification-item').forEach(item => {
            item.addEventListener('click', function () {
                closePanel();
                // El propio destino (pantalla de Pedidos) llama a dismiss()
                // cuando abre el detalle; no lo hacemos aquí para no
                // adelantarnos si la navegación falla.
            });
        });
    }

    function openPanel() {
        const panel = document.getElementById('wa-order-notifications-panel');
        const btn = document.getElementById('wa-enable-order-notifications-btn');
        if (!panel) return;
        panel.classList.remove('hidden');
        panelOpen = true;
        if (btn) btn.setAttribute('aria-expanded', 'true');
        renderNotificationPanel();
    }

    function closePanel() {
        const panel = document.getElementById('wa-order-notifications-panel');
        const btn = document.getElementById('wa-enable-order-notifications-btn');
        if (!panel) return;
        panel.classList.add('hidden');
        panelOpen = false;
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }

    function togglePanel() {
        if (panelOpen) closePanel();
        else openPanel();
    }

    async function requestNotificationPermission() {
        unlockAudio();
        if (!('Notification' in window)) {
            alert('Tu navegador no soporta notificaciones de escritorio.');
            return 'denied';
        }
        if (Notification.permission === 'granted') return 'granted';
        if (Notification.permission === 'denied') {
            alert('Las notificaciones están bloqueadas. Habilítalas en la configuración del navegador.');
            return 'denied';
        }
        return Notification.requestPermission();
    }

    /**
     * Quita un pedido de la lista de pendientes — se llama cuando el admin
     * abre su detalle. Si no queda nada pendiente, adelanta el cursor y
     * limpia la lista de descartados para no acumularla para siempre.
     */
    function dismissOrder(orderId, broadcastIt) {
        const id = Number(orderId);
        if (!id) return;

        const dismissed = getDismissed();
        if (!dismissed.includes(id)) {
            dismissed.push(id);
            setDismissed(dismissed);
        }

        pendingOrders = pendingOrders.filter(o => Number(o.id) !== id);
        updateGlobalBadge(pendingOrders.length);
        if (panelOpen) renderNotificationPanel();

        if (broadcastIt !== false && broadcast) {
            broadcast.postMessage({ type: 'dismiss', orderId: id });
        }

        if (pendingOrders.length === 0) {
            setCursor(Math.max(getCursor(), id));
            setDismissed([]);
        }
    }

    function deliverNotification(order, playSound) {
        if (playSound) playOrderChime();
        showToast(order);
        showDesktopNotification(order);
        flashTitle();
        window.dispatchEvent(new CustomEvent('wa-orders:new', { detail: { order } }));
    }

    function handleNewOrder(order) {
        const claimKey = `wa_order_claim_${order.id}`;
        if (localStorage.getItem(claimKey)) return;
        localStorage.setItem(claimKey, String(Date.now()));
        setTimeout(() => localStorage.removeItem(claimKey), 15000);

        if (broadcast) broadcast.postMessage({ type: 'new_order', order, playSound: true });
        deliverNotification(order, true);
    }

    /**
     * Igual que handleNewOrder pero para los eventos de pedidos YA
     * conocidos (comprobante, factura, asesor) -- cada tipo tiene su propio
     * sonido configurable (ver soundConfig) y dispara 'wa-orders:alert' para
     * que la pantalla de Pedidos se refresque, igual que ya hace con
     * 'wa-orders:new'.
     */
    function deliverEvent(event, playSound) {
        if (playSound) playEventTone(event.type);
        showEventToast(event);
        flashTitle();
        window.dispatchEvent(new CustomEvent('wa-orders:alert', { detail: { event } }));
    }

    function handleOrderEvent(event) {
        const claimKey = `wa_order_event_claim_${event.id}`;
        if (localStorage.getItem(claimKey)) return;
        localStorage.setItem(claimKey, String(Date.now()));
        setTimeout(() => localStorage.removeItem(claimKey), 15000);

        if (broadcast) broadcast.postMessage({ type: 'order_event', event, playSound: true });
        deliverEvent(event, true);
    }

    if (broadcast) {
        broadcast.onmessage = function (event) {
            const data = event.data || {};
            if (data.type === 'new_order' && data.order) {
                deliverNotification(data.order, !!data.playSound);
            } else if (data.type === 'order_event' && data.event) {
                deliverEvent(data.event, !!data.playSound);
            } else if (data.type === 'dismiss' && data.orderId) {
                dismissOrder(data.orderId, false);
            }
        };
    }

    function poll() {
        fetch(pollUrl + '?since_id=' + getCursor() + '&since_event_id=' + getEventCursor(), {
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(r => {
                if (!r.ok) throw new Error('poll http ' + r.status);
                return r.json();
            })
            .then(data => {
                const incoming = Array.isArray(data.orders) ? data.orders : [];
                const dismissed = getDismissed();
                const stillPending = incoming.filter(o => !dismissed.includes(Number(o.id)));
                const knownIds = new Set(pendingOrders.map(o => Number(o.id)));
                const brandNew = stillPending.filter(o => !knownIds.has(Number(o.id)));

                pendingOrders = stillPending;
                updateGlobalBadge(pendingOrders.length);
                if (panelOpen) renderNotificationPanel();

                const incomingEvents = Array.isArray(data.events) ? data.events : [];
                if (typeof data.latest_event_id === 'number') {
                    setEventCursor(data.latest_event_id);
                }

                if (!pollInitialized) {
                    pollInitialized = true;
                    return;
                }

                brandNew.forEach(handleNewOrder);
                incomingEvents.forEach(handleOrderEvent);
            })
            .catch(err => { console.warn('[WaOrderAlerts] poll falló', err); });
    }

    function startPolling() {
        if (pollTimer) return;
        poll();
        pollTimer = setInterval(poll, POLL_MS);
    }

    // Los navegadores frenan (o casi congelan) los setInterval de una pestaña
    // en segundo plano, así que si el admin deja esta pestaña de fondo un
    // rato, el sondeo de 6s deja de correr a tiempo y los pedidos nuevos no
    // llegan hasta que hace algo (como refrescar) que la vuelve a poner en
    // primer plano. Forzamos un sondeo inmediato cada vez que la pestaña
    // vuelve a ser visible para no depender de que el timer haya sobrevivido.
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && pollInitialized) {
            poll();
        }
    });

    document.getElementById('wa-enable-order-notifications-btn')?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        unlockAudio();
        togglePanel();
    });

    document.getElementById('wa-request-order-browser-notify')?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        requestNotificationPermission();
    });

    document.addEventListener('click', function (e) {
        if (!panelOpen) return;
        const nav = document.querySelector('.wa-order-alerts-nav');
        if (nav && !nav.contains(e.target)) closePanel();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panelOpen) closePanel();
    });

    startPolling();

    window.WaOrderAlerts = {
        dismiss: dismissOrder,
        playTest: playOrderChime,
        playPreset: playTone,
        presets: Object.keys(TONE_PRESETS),
    };
})();
