/**
 * Alertas globales de solicitud de asesor — funciona en todo el panel admin.
 */
(function () {
    const config = window.WaAgentAlertsConfig || {};
    const pollUrl = config.pollUrl;
    if (!pollUrl) return;

    const STORAGE_KEY = 'wa_agent_seen_v2';
    const POLL_MS = 4000;
    const pageTitleBase = document.title;
    let audioContext = null;
    let pollInitialized = false;
    let titleFlashTimer = null;
    let pollTimer = null;
    let panelOpen = false;
    let latestRequests = [];
    const broadcast = typeof BroadcastChannel !== 'undefined'
        ? new BroadcastChannel('wa_agent_alerts')
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

    function alertToken(contact) {
        return String(contact.alert_token || contact.requested_at || contact.id);
    }

    function isNewRequest(contact) {
        const map = getSeenMap();
        return map[String(contact.id)] !== alertToken(contact);
    }

    function markSeen(contact) {
        const map = getSeenMap();
        map[String(contact.id)] = alertToken(contact);
        saveSeenMap(map);
    }

    function clearContact(contactId) {
        const map = getSeenMap();
        delete map[String(contactId)];
        saveSeenMap(map);
    }

    /** Sonido de alerta fuerte — triple campana tipo notificación urgente. */
    function playLoudAgentAlert() {
        unlockAudio();
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            const ctx = audioContext || new AudioContext();
            audioContext = ctx;

            const playTone = (start, freq, duration, volume, type) => {
                const t0 = ctx.currentTime + start;
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                const filter = ctx.createBiquadFilter();

                osc.type = type || 'square';
                osc.frequency.setValueAtTime(freq, t0);
                if (type === 'square') {
                    osc.frequency.exponentialRampToValueAtTime(freq * 0.7, t0 + duration * 0.85);
                }

                filter.type = 'bandpass';
                filter.frequency.setValueAtTime(Math.min(freq * 2.2, 4000), t0);
                filter.Q.setValueAtTime(1.2, t0);

                osc.connect(filter);
                filter.connect(gain);
                gain.connect(ctx.destination);

                gain.gain.setValueAtTime(0.0001, t0);
                gain.gain.linearRampToValueAtTime(volume, t0 + 0.008);
                gain.gain.setValueAtTime(volume * 0.85, t0 + duration * 0.4);
                gain.gain.exponentialRampToValueAtTime(0.0001, t0 + duration);

                osc.start(t0);
                osc.stop(t0 + duration + 0.05);
            };

            // Patrón urgente: 3 pares de tonos altos
            playTone(0, 880, 0.16, 0.72, 'square');
            playTone(0.18, 1100, 0.16, 0.68, 'square');
            playTone(0.38, 880, 0.16, 0.72, 'square');
            playTone(0.56, 1100, 0.16, 0.68, 'square');
            playTone(0.78, 1320, 0.28, 0.62, 'sine');
            playTone(1.05, 988, 0.35, 0.58, 'sine');
        } catch (e) { /* ignore */ }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function chatUrl(contactId) {
        const base = (config.chatUrl || '/admin/chats').replace(/\/$/, '');
        return `${base}/${contactId}`;
    }

    function openChat(contact) {
        if (typeof window.loadContactChat === 'function') {
            window.loadContactChat(contact.id);
            return;
        }
        window.location.href = chatUrl(contact.id);
    }

    function showToast(contact) {
        const stack = document.getElementById('wa-agent-toast-stack');
        if (!stack) return;

        const name = contact.name || 'Cliente';
        const toast = document.createElement('div');
        toast.className = 'wa-agent-toast';
        toast.innerHTML = `
            <div class="wa-agent-toast-icon"><i class="fas fa-headset"></i></div>
            <div class="wa-agent-toast-body">
                <p class="wa-agent-toast-title">${escapeHtml(name)}</p>
                <p class="wa-agent-toast-text">Solicita hablar con un asesor humano</p>
                <div class="wa-agent-toast-time">Ahora · Toca para abrir el chat</div>
            </div>
        `;

        toast.addEventListener('click', function () {
            openChat(contact);
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

    function showDesktopNotification(contact) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;

        const name = contact.name || 'Cliente';
        try {
            const notification = new Notification('🎧 Asesor solicitado · WhatsApp', {
                body: `${name} quiere hablar con un humano`,
                icon: config.favicon || '',
                badge: config.favicon || '',
                tag: `agent-request-${contact.id}-${alertToken(contact)}`,
                requireInteraction: true,
                silent: true,
            });

            notification.onclick = function () {
                window.focus();
                openChat(contact);
                notification.close();
            };

            setTimeout(() => notification.close(), 15000);
        } catch (e) { /* ignore */ }
    }

    function flashTitle() {
        clearInterval(titleFlashTimer);
        let showAlert = true;
        titleFlashTimer = setInterval(function () {
            document.title = showAlert ? '(1) 🎧 Asesor solicitado' : pageTitleBase;
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

    function formatRequestTime(iso) {
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
        setBadgeEl(document.getElementById('global-agent-requests-count'), count);
        setBadgeEl(document.getElementById('sidebar-chats-agent-count'), count);

        const panelCount = document.getElementById('wa-notifications-panel-count');
        if (panelCount) {
            if (count > 0) {
                panelCount.textContent = count === 1 ? '1 pendiente' : `${count} pendientes`;
                panelCount.style.display = '';
            } else {
                panelCount.textContent = '';
                panelCount.style.display = 'none';
            }
        }
    }

    function renderNotificationPanel() {
        const list = document.getElementById('wa-agent-notifications-list');
        if (!list) return;

        if (!latestRequests.length) {
            list.innerHTML = '<div class="wa-agent-notifications-empty">No hay solicitudes pendientes</div>';
            return;
        }

        list.innerHTML = latestRequests.map(contact => {
            const name = escapeHtml(contact.name || 'Cliente');
            const phone = contact.phone_number ? escapeHtml(contact.phone_number) : '';
            const time = formatRequestTime(contact.requested_at);
            const href = chatUrl(contact.id);
            return `
                <a href="${href}" class="wa-agent-notification-item" data-contact-id="${contact.id}">
                    <div class="ni-icon"><i class="fas fa-headset"></i></div>
                    <div>
                        <p class="ni-title">${name}</p>
                        <p class="ni-text">Solicita hablar con un asesor humano${phone ? ' · ' + phone : ''}</p>
                        ${time ? `<div class="ni-time">${escapeHtml(time)}</div>` : ''}
                    </div>
                </a>
            `;
        }).join('');

        list.querySelectorAll('.wa-agent-notification-item').forEach(item => {
            item.addEventListener('click', function (e) {
                const id = parseInt(item.dataset.contactId, 10);
                const contact = latestRequests.find(c => c.id === id);
                if (typeof window.loadContactChat === 'function') {
                    e.preventDefault();
                    closePanel();
                    window.loadContactChat(id);
                } else {
                    closePanel();
                }
            });
        });
    }

    function openPanel() {
        const panel = document.getElementById('wa-agent-notifications-panel');
        const btn = document.getElementById('wa-enable-notifications-btn');
        if (!panel) return;
        panel.classList.remove('hidden');
        panelOpen = true;
        if (btn) btn.setAttribute('aria-expanded', 'true');
        renderNotificationPanel();
    }

    function closePanel() {
        const panel = document.getElementById('wa-agent-notifications-panel');
        const btn = document.getElementById('wa-enable-notifications-btn');
        if (!panel) return;
        panel.classList.add('hidden');
        panelOpen = false;
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }

    function togglePanel() {
        if (panelOpen) closePanel();
        else openPanel();
    }

    function updateNotifyButtonState() {
        const btn = document.getElementById('wa-enable-notifications-btn');
        if (!btn || !('Notification' in window)) return;

        const icon = btn.querySelector('i');
        btn.classList.remove('is-active', 'is-blocked');

        if (Notification.permission === 'granted') {
            btn.classList.add('is-active');
            btn.title = 'Ver notificaciones de asesor';
            if (icon) icon.className = 'fas fa-bell';
        } else if (Notification.permission === 'denied') {
            btn.classList.add('is-blocked');
            btn.title = 'Ver notificaciones (alertas del navegador bloqueadas)';
            if (icon) icon.className = 'far fa-bell';
        } else {
            btn.title = 'Ver notificaciones de asesor';
            if (icon) icon.className = 'far fa-bell';
        }
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
        const result = await Notification.requestPermission();
        updateNotifyButtonState();
        return result;
    }

    function notifyAllTabs(contact, playSound) {
        if (broadcast) {
            broadcast.postMessage({ type: 'agent_request', contact, playSound });
        }
        deliverNotification(contact, playSound);
    }

    function deliverNotification(contact, playSound) {
        if (playSound) playLoudAgentAlert();
        showToast(contact);
        showDesktopNotification(contact);
        flashTitle();
    }

    if (broadcast) {
        broadcast.onmessage = function (event) {
            if (event.data?.type === 'agent_request' && event.data.contact) {
                deliverNotification(event.data.contact, !!event.data.playSound);
            }
        };
    }

    function handleNewRequest(contact) {
        const claimKey = `wa_claim_${contact.id}_${alertToken(contact)}`;
        if (localStorage.getItem(claimKey)) {
            markSeen(contact);
            return;
        }
        localStorage.setItem(claimKey, String(Date.now()));
        setTimeout(() => localStorage.removeItem(claimKey), 15000);

        markSeen(contact);
        notifyAllTabs(contact, true);
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
                if (!data.success || !Array.isArray(data.requests)) return;

                latestRequests = data.requests;
                updateGlobalBadge(data.count || 0);
                if (panelOpen) renderNotificationPanel();

                if (!pollInitialized) {
                    data.requests.forEach(c => markSeen(c));
                    pollInitialized = true;
                    return;
                }

                data.requests.forEach(contact => {
                    if (isNewRequest(contact)) {
                        handleNewRequest(contact, true);
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

    document.getElementById('wa-enable-notifications-btn')?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        unlockAudio();
        togglePanel();
    });

    document.getElementById('wa-request-browser-notify')?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        requestNotificationPermission();
    });

    document.addEventListener('click', function (e) {
        if (!panelOpen) return;
        const nav = document.querySelector('.wa-agent-alerts-nav');
        if (nav && !nav.contains(e.target)) closePanel();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panelOpen) closePanel();
    });

    updateNotifyButtonState();
    startPolling();

    window.WaAgentAlerts = {
        clearContact,
        playTest: playLoudAgentAlert,
    };
})();
