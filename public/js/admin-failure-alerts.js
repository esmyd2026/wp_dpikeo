/**
 * Alertas globales de fallos de envío — funciona en todo el panel admin.
 * El contador siempre refleja la verdad del servidor (fallos sin resolver);
 * el "ya sonó por este" se recuerda en localStorage solo para no repetir
 * el sonido/notificación en cada sondeo mientras siga sin resolverse.
 */
(function () {
    const config = window.WaFailureAlertsConfig || {};
    const pollUrl = config.pollUrl;
    if (!pollUrl) return;

    const STORAGE_KEY = 'wa_failure_seen_v1';
    const POLL_MS = 10000;
    const pageTitleBase = document.title;
    let audioContext = null;
    let pollInitialized = false;
    let titleFlashTimer = null;
    let pollTimer = null;
    let panelOpen = false;
    let latestFailures = [];
    const broadcast = typeof BroadcastChannel !== 'undefined'
        ? new BroadcastChannel('wa_failure_alerts')
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

    function getSeenMap() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        } catch (e) {
            return {};
        }
    }

    function saveSeenMap(map) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(map));
    }

    function isNewFailure(failure) {
        const map = getSeenMap();
        return !map[String(failure.id)];
    }

    function markSeen(failure) {
        const map = getSeenMap();
        map[String(failure.id)] = 1;
        saveSeenMap(map);
    }

    /** Tono grave de alarma — distinto de los otros dos timbres del panel. */
    function playFailureAlert() {
        unlockAudio();
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = audioContext || new AudioContext();
            audioContext = ctx;

            [0, 0.22].forEach((delay) => {
                const t0 = ctx.currentTime + delay;
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(320, t0);
                osc.frequency.exponentialRampToValueAtTime(220, t0 + 0.2);
                gain.gain.setValueAtTime(0.0001, t0);
                gain.gain.linearRampToValueAtTime(0.16, t0 + 0.015);
                gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.24);
                osc.connect(gain).connect(ctx.destination);
                osc.start(t0);
                osc.stop(t0 + 0.26);
            });
        } catch (e) { /* ignore */ }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function indexUrl() {
        return config.indexUrl || '/admin/fallos-envio';
    }

    function showToast(failure) {
        const stack = document.getElementById('wa-agent-toast-stack');
        if (!stack) return;

        const name = failure.contact_name || failure.phone_number || 'Contacto desconocido';
        const toast = document.createElement('div');
        toast.className = 'wa-agent-toast';
        toast.innerHTML = `
            <div class="wa-agent-toast-icon wa-failure-toast-icon"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="wa-agent-toast-body">
                <p class="wa-agent-toast-title">Falló un envío a ${escapeHtml(name)}</p>
                <p class="wa-agent-toast-text">${escapeHtml((failure.error_message || '').slice(0, 90))}</p>
                <div class="wa-agent-toast-time">Ahora · Toca para revisar</div>
            </div>
        `;

        toast.addEventListener('click', function () {
            window.location.href = indexUrl();
            removeToast(toast);
        });

        stack.prepend(toast);
        setTimeout(() => removeToast(toast), 12000);
    }

    function removeToast(toast) {
        if (!toast || toast.dataset.removing) return;
        toast.dataset.removing = '1';
        toast.style.animation = 'waToastOut .25s ease forwards';
        setTimeout(() => toast.remove(), 260);
    }

    function showDesktopNotification(failure) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;

        const name = failure.contact_name || failure.phone_number || 'Contacto desconocido';
        try {
            const notification = new Notification('⚠️ Falló un envío de WhatsApp', {
                body: `${name}: ${(failure.error_message || '').slice(0, 120)}`,
                icon: config.favicon || '',
                badge: config.favicon || '',
                tag: `failure-${failure.id}`,
                requireInteraction: true,
                silent: true,
            });

            notification.onclick = function () {
                window.focus();
                window.location.href = indexUrl();
                notification.close();
            };

            setTimeout(() => notification.close(), 15000);
        } catch (e) { /* ignore */ }
    }

    function flashTitle() {
        clearInterval(titleFlashTimer);
        let showAlert = true;
        titleFlashTimer = setInterval(function () {
            document.title = showAlert ? '(!) ⚠️ Fallo de envío' : pageTitleBase;
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

    function formatFailureTime(iso) {
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
        setBadgeEl(document.getElementById('global-failures-count'), count);
        setBadgeEl(document.getElementById('sidebar-failures-count'), count);

        const panelCount = document.getElementById('wa-failure-notifications-panel-count');
        if (panelCount) {
            if (count > 0) {
                panelCount.textContent = count === 1 ? '1 sin resolver' : `${count} sin resolver`;
                panelCount.style.display = '';
            } else {
                panelCount.textContent = '';
                panelCount.style.display = 'none';
            }
        }
    }

    function renderNotificationPanel() {
        const list = document.getElementById('wa-failure-notifications-list');
        if (!list) return;

        if (!latestFailures.length) {
            list.innerHTML = '<div class="wa-agent-notifications-empty">No hay fallos de envío pendientes 🎉</div>';
            return;
        }

        list.innerHTML = latestFailures.map(failure => {
            const name = escapeHtml(failure.contact_name || failure.phone_number || 'Contacto desconocido');
            const time = formatFailureTime(failure.created_at);
            return `
                <a href="${indexUrl()}" class="wa-agent-notification-item">
                    <div class="ni-icon wa-failure-toast-icon"><i class="fas fa-triangle-exclamation"></i></div>
                    <div>
                        <p class="ni-title">${name}</p>
                        <p class="ni-text">${escapeHtml((failure.error_message || '').slice(0, 90))}</p>
                        ${time ? `<div class="ni-time">${escapeHtml(time)}</div>` : ''}
                    </div>
                </a>
            `;
        }).join('');
    }

    function openPanel() {
        const panel = document.getElementById('wa-failure-notifications-panel');
        const btn = document.getElementById('wa-enable-failure-notifications-btn');
        if (!panel) return;
        panel.classList.remove('hidden');
        panelOpen = true;
        if (btn) btn.setAttribute('aria-expanded', 'true');
        renderNotificationPanel();
    }

    function closePanel() {
        const panel = document.getElementById('wa-failure-notifications-panel');
        const btn = document.getElementById('wa-enable-failure-notifications-btn');
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

    function notifyAllTabs(failure, playSound) {
        if (broadcast) {
            broadcast.postMessage({ type: 'send_failure', failure, playSound });
        }
        deliverNotification(failure, playSound);
    }

    function deliverNotification(failure, playSound) {
        if (playSound) playFailureAlert();
        showToast(failure);
        showDesktopNotification(failure);
        flashTitle();
    }

    if (broadcast) {
        broadcast.onmessage = function (event) {
            if (event.data?.type === 'send_failure' && event.data.failure) {
                deliverNotification(event.data.failure, !!event.data.playSound);
            }
        };
    }

    function handleNewFailure(failure) {
        const claimKey = `wa_failure_claim_${failure.id}`;
        if (localStorage.getItem(claimKey)) {
            markSeen(failure);
            return;
        }
        localStorage.setItem(claimKey, String(Date.now()));
        setTimeout(() => localStorage.removeItem(claimKey), 15000);

        markSeen(failure);
        notifyAllTabs(failure, true);
    }

    function poll() {
        fetch(pollUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !Array.isArray(data.failures)) return;

                latestFailures = data.failures;
                updateGlobalBadge(data.count || 0);
                if (panelOpen) renderNotificationPanel();

                if (!pollInitialized) {
                    data.failures.forEach(f => markSeen(f));
                    pollInitialized = true;
                    return;
                }

                data.failures.forEach(failure => {
                    if (isNewFailure(failure)) {
                        handleNewFailure(failure);
                    }
                });
            })
            .catch(() => { /* ignore */ });
    }

    function startPolling() {
        if (pollTimer) return;
        poll();
        pollTimer = setInterval(poll, POLL_MS);
    }

    document.getElementById('wa-enable-failure-notifications-btn')?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        unlockAudio();
        togglePanel();
    });

    document.getElementById('wa-request-failure-browser-notify')?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        requestNotificationPermission();
    });

    document.addEventListener('click', function (e) {
        if (!panelOpen) return;
        const nav = document.querySelector('.wa-failure-alerts-nav');
        if (nav && !nav.contains(e.target)) closePanel();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panelOpen) closePanel();
    });

    startPolling();

    window.WaFailureAlerts = {
        playTest: playFailureAlert,
    };
})();
