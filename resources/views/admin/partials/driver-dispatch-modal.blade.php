<style>
    .driver-modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; padding:1rem; z-index:1200; opacity:0; visibility:hidden; transition:.2s; }
    .driver-modal-overlay.is-open { opacity:1; visibility:visible; }
    .driver-modal { background:#fff; border-radius:16px; width:100%; max-width:420px; padding:1.25rem; max-height:90vh; overflow-y:auto; }
    .driver-modal h4 { margin:0 0 .3rem; font-size:1rem; color:#0f172a; display:flex; align-items:center; gap:.4rem; }
    .driver-modal .step-hint { font-size:.78rem; color:#64748b; margin-bottom:.9rem; }
    .driver-modal label { display:block; font-size:.78rem; font-weight:700; color:#475569; margin-bottom:.3rem; margin-top:.7rem; }
    .driver-modal select, .driver-modal input[type="text"], .driver-modal input[type="tel"] { width:100%; border:1px solid #e2e8f0; border-radius:9px; padding:.55rem .65rem; font-size:.85rem; }
    .driver-modal-new-fields { display:none; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; padding:.7rem .8rem; margin-top:.7rem; }
    .driver-modal-new-fields.is-visible { display:block; }
    .driver-modal-toggle { font-size:.78rem; color:#7c3aed; font-weight:700; cursor:pointer; margin-top:.5rem; display:inline-block; }
    .driver-modal-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1.1rem; }
    .driver-modal-btn { padding:.55rem .9rem; border-radius:9px; font-size:.82rem; font-weight:700; border:1px solid #e2e8f0; background:#fff; color:#475569; cursor:pointer; }
    .driver-modal-btn.primary { background:linear-gradient(135deg,#16a34a,#15803d); border-color:transparent; color:#fff; }
    .driver-modal-btn:disabled { opacity:.6; cursor:not-allowed; }
    .driver-modal-error { color:#b91c1c; font-size:.78rem; margin-top:.5rem; display:none; }
    .driver-phone-input { display:flex; align-items:center; border:1px solid #e2e8f0; border-radius:9px; overflow:hidden; }
    .driver-phone-prefix { background:#f1f5f9; color:#475569; font-size:.82rem; font-weight:700; padding:.55rem .6rem; border-right:1px solid #e2e8f0; white-space:nowrap; }
    .driver-phone-input input[type="tel"] { border:none; border-radius:0; }
    .driver-phone-input input[type="tel"]:focus { outline:none; box-shadow:none; }
    .driver-phone-hint { font-size:.72rem; color:#94a3b8; margin-top:.3rem; }
</style>

<div class="driver-modal-overlay" id="driverDispatchModal">
    <div class="driver-modal">
        <h4><i class="fab fa-whatsapp"></i>Enviar a repartidor</h4>
        <p class="step-hint">Paso 1: elige o agrega el repartidor. Paso 2: se le avisa al cliente que su pedido va en camino (con el contacto del repartidor) y se abre WhatsApp para que le mandes los datos.</p>

        <form id="driverDispatchForm">
            <label for="driverDispatchSelect">Repartidor</label>
            <select id="driverDispatchSelect">
                <option value="">Cargando repartidores…</option>
                <option value="__new__">+ Nuevo repartidor</option>
            </select>

            <div class="driver-modal-new-fields" id="driverDispatchNewFields">
                <label for="driverDispatchFirstName">Nombre</label>
                <input type="text" id="driverDispatchFirstName" maxlength="100" placeholder="Ej: Juan">
                <label for="driverDispatchLastName">Apellido</label>
                <input type="text" id="driverDispatchLastName" maxlength="100" placeholder="Ej: Pérez">
                <label for="driverDispatchPhone">Teléfono (WhatsApp)</label>
                <div class="driver-phone-input">
                    <span class="driver-phone-prefix">🇪🇨 +593</span>
                    <input type="tel" id="driverDispatchPhone" inputmode="numeric" maxlength="10" placeholder="0991234567">
                </div>
                <p class="driver-phone-hint">10 dígitos empezando por 09 (celular), sin espacios ni código de país.</p>
            </div>

            <div class="driver-modal-error" id="driverDispatchError"></div>

            <div class="driver-modal-actions">
                <button type="button" class="driver-modal-btn" id="driverDispatchCancel">Cancelar</button>
                <button type="submit" class="driver-modal-btn primary" id="driverDispatchSubmit"><i class="fas fa-paper-plane me-1"></i>Enviar y avisar al cliente</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const driversUrl = @json(route('admin.delivery.drivers'));
    const dispatchUrlTemplate = @json(route('admin.delivery.dispatch', ['id' => '__ORDER__']));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let cachedDrivers = null;
    let currentOrderId = null;
    let currentDispatchTextBuilder = null;
    let currentOnDone = null;

    const overlay = document.getElementById('driverDispatchModal');
    const select = document.getElementById('driverDispatchSelect');
    const newFields = document.getElementById('driverDispatchNewFields');
    const errorBox = document.getElementById('driverDispatchError');
    const form = document.getElementById('driverDispatchForm');
    const submitBtn = document.getElementById('driverDispatchSubmit');

    async function loadDrivers() {
        if (cachedDrivers) return cachedDrivers;
        try {
            const res = await fetch(driversUrl, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            cachedDrivers = data.drivers || [];
        } catch (_) {
            cachedDrivers = [];
        }
        return cachedDrivers;
    }

    function renderOptions(drivers) {
        select.innerHTML = '';
        if (drivers.length) {
            drivers.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = `${d.name} · ${d.phone_number}`;
                select.appendChild(opt);
            });
        }
        const newOpt = document.createElement('option');
        newOpt.value = '__new__';
        newOpt.textContent = '+ Nuevo repartidor';
        select.appendChild(newOpt);
        if (!drivers.length) select.value = '__new__';
        toggleNewFields();
    }

    function toggleNewFields() {
        const isNew = select.value === '__new__';
        newFields.classList.toggle('is-visible', isNew);
    }

    select.addEventListener('change', toggleNewFields);

    // Solo dígitos, sin espacios, tope de 10 (formato local: 0 + 9 dígitos).
    const phoneInput = document.getElementById('driverDispatchPhone');
    phoneInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D+/g, '').slice(0, 10);
    });

    /** "0991234567" (local, 10 dígitos) -> "593991234567" (para guardar/enviar). */
    function toEcuadorE164(localPhone) {
        const digits = localPhone.replace(/\D+/g, '');
        if (digits.length !== 10 || !digits.startsWith('09')) return null;
        return '593' + digits.slice(1);
    }

    /**
     * Reabre WhatsApp con el chat de un repartidor ya despachado antes, sin
     * pasar por el backend (no vuelve a avisarle al cliente) -- para el
     * botón "Reenviar al repartidor" cuando el mensaje no le llegó.
     */
    window.reopenDriverWhatsapp = function (driverPhone, text) {
        const digits = (driverPhone || '').replace(/\D+/g, '');
        window.open(`https://wa.me/${digits}?text=${encodeURIComponent(text)}`, '_blank');
    };

    /**
     * Abre el modal. dispatchTextBuilder recibe {name, phone_number} del
     * repartidor ya guardado y debe devolver el texto a mandarle por
     * WhatsApp (cada pantalla arma ese texto con sus propios datos del
     * pedido). onDone(response) se llama después de un envío exitoso.
     */
    window.openDriverDispatchModal = async function (orderId, dispatchTextBuilder, onDone) {
        currentOrderId = orderId;
        currentDispatchTextBuilder = dispatchTextBuilder;
        currentOnDone = onDone;
        errorBox.style.display = 'none';
        document.getElementById('driverDispatchFirstName').value = '';
        document.getElementById('driverDispatchLastName').value = '';
        document.getElementById('driverDispatchPhone').value = '';

        overlay.classList.add('is-open');
        const drivers = await loadDrivers();
        renderOptions(drivers);
    };

    function closeModal() {
        overlay.classList.remove('is-open');
    }

    document.getElementById('driverDispatchCancel').addEventListener('click', closeModal);
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        errorBox.style.display = 'none';

        const isNew = select.value === '__new__';
        const body = {};
        if (isNew) {
            const firstName = document.getElementById('driverDispatchFirstName').value.trim();
            const localPhone = phoneInput.value.replace(/\D+/g, '');
            if (!firstName || !localPhone) {
                errorBox.textContent = 'Escribe al menos el nombre y el teléfono del repartidor.';
                errorBox.style.display = 'block';
                return;
            }
            const e164Phone = toEcuadorE164(localPhone);
            if (!e164Phone) {
                errorBox.textContent = 'El teléfono debe tener 10 dígitos y empezar por 09 (ej: 0991234567).';
                errorBox.style.display = 'block';
                return;
            }
            body.first_name = firstName;
            body.last_name = document.getElementById('driverDispatchLastName').value.trim();
            body.phone_number = e164Phone;
        } else {
            if (!select.value) {
                errorBox.textContent = 'Elige un repartidor.';
                errorBox.style.display = 'block';
                return;
            }
            body.driver_id = select.value;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Enviando…';

        try {
            const res = await fetch(dispatchUrlTemplate.replace('__ORDER__', currentOrderId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'No se pudo despachar el pedido.');

            cachedDrivers = null; // se agregó/actualizó un repartidor, refrescar la próxima vez
            closeModal();

            const text = currentDispatchTextBuilder ? currentDispatchTextBuilder(data.driver) : '';
            window.reopenDriverWhatsapp(data.driver.phone_number, text);

            if (currentOnDone) currentOnDone(data);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Enviar y avisar al cliente';
        }
    });
})();
</script>
