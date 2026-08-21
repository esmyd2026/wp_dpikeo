/**
 * Alertas globales de pedidos nuevos — funciona en todo el panel admin,
 * no solo en la pantalla de Pedidos (sondeo, sin websockets en el proyecto).
 */
(function () {
    const config = window.WaOrderAlertsConfig || {};
    const pollUrl = config.pollUrl;
    if (!pollUrl) return;

    const CURSOR_KEY = 'wa_orders_since_id';
    const DISMISSED_KEY = 'wa_orders_dismissed_v1';
    const POLL_MS = 6000;
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

    /** Chime de dos tonos — distinto del de solicitud de asesor. */
    function playOrderChime() {
        unlockAudio();
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = audioContext || new AudioContext();
            audioContext = ctx;

            [0, 0.18].forEach((delay, i) => {
                const t0 = ctx.currentTime + delay;
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(i === 0 ? 740 : 990, t0);
                gain.gain.setValueAtTime(0.0001, t0);
                gain.gain.exponentialRampToValueAtTime(0.11, t0 + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.3);
                osc.connect(gain).connect(ctx.destination);
                osc.start(t0);
                osc.stop(t0 + 0.32);
            });
        } catch (e) { /* ignore */ }
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

    if (broadcast) {
        broadcast.onmessage = function (event) {
            const data = event.data || {};
            if (data.type === 'new_order' && data.order) {
                deliverNotification(data.order, !!data.playSound);
            } else if (data.type === 'dismiss' && data.orderId) {
                dismissOrder(data.orderId, false);
            }
        };
    }

    function poll() {
        fetch(pollUrl + '?since_id=' + getCursor(), {
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

                if (!pollInitialized) {
                    pollInitialized = true;
                    return;
                }

                brandNew.forEach(handleNewOrder);
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
    };
})();
