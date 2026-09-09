<script setup>
import { ref, watch, computed, inject } from 'vue';
import { nodeMeta, SYSTEM_NODE_TYPES } from './nodeTypes';
import { useFlowApi, apiErrorMessage } from './useFlowApi';

const props = defineProps({
    node: { type: Object, required: true }, // { id (uuid), data }
    saving: { type: Boolean, default: false },
    apiBase: { type: String, required: true },
});

const emit = defineEmits(['save', 'delete', 'close', 'node-updated']);

const catalogOptions = inject('catalogOptions', null);
const api = useFlowApi(props.apiBase);

const meta = computed(() => nodeMeta(props.node.data.node_type));
const isSystem = computed(() => SYSTEM_NODE_TYPES.has(props.node.data.node_type));
const isCheckout = computed(() => props.node.data.node_type === 'checkout');
const isMenu = computed(() => ['button_menu', 'list_menu'].includes(props.node.data.node_type));
const isList = computed(() => props.node.data.node_type === 'list_menu');
const isProduct = computed(() => props.node.data.node_type === 'product');
const isCategory = computed(() => props.node.data.node_type === 'category');
const hasButtonsEditor = computed(() => props.node.data.node_type === 'button_menu');
const maxCustomButtons = computed(() => 3);
const supportsImageUpload = computed(() => ['message', 'button_menu', 'list_menu'].includes(props.node.data.node_type));

// Pasos internos del checkout: no son nodos propios del grafo (el orden y la
// lógica de cuándo se saltan quedan fijos en el código, ver
// UsesMarketingFlowGraph::getCheckoutStepMessage), pero se listan aquí para
// que el admin vea el flujo completo y pueda ajustar los textos.
const CHECKOUT_STEPS = [
    { key: 'sucursal_list', label: '1. Elegir sucursal', hint: 'Cuando el cliente no tiene una sucursal anterior recordada. Siempre se pregunta si hay más de una sucursal activa.', default: '📍 *¿Desde qué sucursal pedirás?*' },
    { key: 'sucursal_repeat', label: '1. Confirmar la misma sucursal', hint: 'Cuando ya pidió antes. Usa {{sucursal}} para el nombre del local.', default: '📍 *Sucursal de tu pedido*\n\n¿Pedirás nuevamente desde *{{sucursal}}*?' },
    {
        key: 'service_type', label: '2. Para llevar o para servir',
        hint: 'También se salta si la sucursal tiene desactivado "servir en mesa" (eso se configura en Sucursales, no aquí).',
        default: '🍽️ *¿Tu pedido es para llevar o para servir?*',
        toggle: { question: '¿Preguntar este paso?', defaultValue: 'llevar', options: [
            { value: 'llevar', label: 'No preguntar: siempre "para llevar"' },
            { value: 'servir', label: 'No preguntar: siempre "para servir"' },
        ] },
    },
    {
        key: 'pickup_mode', label: '3. Retiro en local o delivery',
        hint: 'Solo si el pedido es "para llevar".',
        default: '🚗 *¿Retiras en el local o prefieres delivery?*\n\n🛵 El delivery tiene un costo adicional que te confirmaremos por este chat.',
        toggle: { question: '¿Preguntar este paso?', defaultValue: 'retiro', options: [
            { value: 'retiro', label: 'No preguntar: siempre retiro en local' },
            { value: 'delivery', label: 'No preguntar: siempre delivery' },
        ] },
    },
    { key: 'delivery_location', label: '4. Dirección de entrega', hint: 'Solo si eligió delivery. Es obligatorio para poder entregar el pedido, no se puede desactivar.', default: '📍 Escríbenos la *dirección completa* de entrega (calle, sector, referencia).' },
    { key: 'delivery_recipient_name', label: '4. Nombre de quien recibe', hint: 'Justo después de la dirección (o de compartir ubicación). No se puede desactivar.', default: '🧑 ¿A nombre de quién recibimos el pedido?' },
    {
        key: 'payment_method', label: '6. Método de pago',
        hint: 'No aplica si es "para servir": ahí se paga en caja.',
        default: '💳 *Selecciona el método de pago*\n\nPor favor, elige cómo deseas realizar el pago:',
        paymentMethods: [
            { value: 'transferencia', label: '🏦 Transferencia' },
            { value: 'efectivo', label: '💵 Efectivo' },
            { value: 'tarjeta', label: '💳 Tarjeta' },
        ],
    },
    {
        key: 'invoice_type', label: '7. Factura o consumidor final',
        hint: 'No es parte del checkout inicial -- se pregunta después, cuando el pedido ya quedó pagado (o confirmado, si nunca pasa por "Pagado", como en efectivo). Si elige factura, el bot pide nombre, RUC/cédula, dirección y correo en un solo mensaje (o reusa los de su última factura, con confirmación).',
        default: '🧾 *¿Cómo quieres tu comprobante?*\n\n¿Factura o consumidor final?',
        toggle: { question: '¿Preguntar este paso?', defaultValue: 'consumidor_final', options: [
            { value: 'consumidor_final', label: 'No preguntar: siempre consumidor final' },
            { value: 'factura', label: 'No preguntar: siempre pedir datos de factura' },
        ] },
    },
];

const uploadingImage = ref(false);
const imageError = ref(null);

const currentImagePath = computed(() => {
    const config = props.node.data.config || {};
    return config.message_image_path || config.header?.image_path || null;
});

const form = ref(cloneForm(props.node));

function cloneForm(node) {
    const config = node.data.config || {};
    return {
        name: node.data.name || '',
        message_template: node.data.message_template || '',
        is_enabled: node.data.is_enabled,
        footer: config.footer || '',
        buttons: (config.buttons || []).map((b) => ({ id: b.id, title: b.title })),
        sections: (config.list?.sections || []).map((s) => ({
            title: s.title || '',
            rows: (s.rows || []).map((r) => ({ id: r.id, title: r.title, description: r.description || '' })),
        })),
        list_button: config.list?.button || 'Ver opciones',
        product_id: config.product_id || null,
        category_id: config.category_id || null,
        checkoutSteps: CHECKOUT_STEPS.map((step) => ({
            key: step.key,
            message: config.steps?.[step.key]?.message || '',
            enabled: config.steps?.[step.key]?.enabled !== false,
            defaultValue: config.steps?.[step.key]?.default || step.toggle?.defaultValue || '',
            paymentMethods: (step.paymentMethods || []).map((m) => ({
                value: m.value,
                enabled: config.steps?.[step.key]?.payment_methods?.[m.value]?.enabled !== false,
            })),
        })),
    };
}

async function handleImageChange(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    uploadingImage.value = true;
    imageError.value = null;
    try {
        const updated = await api.uploadNodeImage(props.node.id, file);
        emit('node-updated', updated);
    } catch (e) {
        imageError.value = apiErrorMessage(e);
    } finally {
        uploadingImage.value = false;
        event.target.value = '';
    }
}

async function removeImage() {
    uploadingImage.value = true;
    imageError.value = null;
    try {
        const updated = await api.deleteNodeImage(props.node.id);
        emit('node-updated', updated);
    } catch (e) {
        imageError.value = apiErrorMessage(e);
    } finally {
        uploadingImage.value = false;
    }
}

watch(() => props.node.id, () => {
    form.value = cloneForm(props.node);
    saveError.value = null;
});

const totalRows = computed(() => form.value.sections.reduce((n, s) => n + s.rows.length, 0));

let handleSeq = 0;
function newHandleId(prefix) {
    handleSeq += 1;
    return prefix + '_' + Date.now().toString(36) + handleSeq;
}

function addButton() {
    if (form.value.buttons.length >= maxCustomButtons.value) return;
    form.value.buttons.push({ id: newHandleId('btn'), title: 'Nuevo botón' });
}
function removeButton(index) {
    form.value.buttons.splice(index, 1);
}

function addSection() {
    form.value.sections.push({ title: 'Sección', rows: [] });
}
function removeSection(index) {
    form.value.sections.splice(index, 1);
}
function addRow(sectionIndex) {
    if (totalRows.value >= 10) return;
    form.value.sections[sectionIndex].rows.push({ id: newHandleId('row'), title: 'Nueva opción', description: '' });
}
function removeRow(sectionIndex, rowIndex) {
    form.value.sections[sectionIndex].rows.splice(rowIndex, 1);
}

const saveError = ref(null);

function save() {
    saveError.value = null;

    if (isCheckout.value) {
        const paymentStep = form.value.checkoutSteps.find((s) => s.key === 'payment_method');
        if (paymentStep && paymentStep.paymentMethods.every((m) => !m.enabled)) {
            saveError.value = 'Debe quedar al menos un método de pago activo.';
            return;
        }
    }

    const config = {};
    const existing = props.node.data.config || {};
    const type = props.node.data.node_type;

    if (type === 'button_menu') {
        config.interactive_type = 'button';
        config.buttons = form.value.buttons;
        if (form.value.footer) config.footer = form.value.footer;
        if (existing.header) { config.header = existing.header; config.header_mode = existing.header_mode; }
    } else if (type === 'list_menu') {
        config.interactive_type = 'list';
        config.list = { button: form.value.list_button || 'Ver opciones', sections: form.value.sections };
        if (form.value.footer) config.footer = form.value.footer;
        if (existing.header) { config.header = existing.header; config.header_mode = existing.header_mode; }
    } else if (type === 'product') {
        config.product_id = form.value.product_id;
    } else if (type === 'category') {
        config.category_id = form.value.category_id;
    } else if (!isSystem.value) {
        config.interactive_type = 'text';
        if (existing.message_image_path) config.message_image_path = existing.message_image_path;
    } else {
        // Nodo de sistema: se conserva su config actual (catalog_source, etc.),
        // solo se tocan nombre/mensaje/estado desde aquí por ahora.
        Object.assign(config, existing);
        if (form.value.footer) config.footer = form.value.footer;

        if (isCheckout.value) {
            const steps = {};
            for (const step of form.value.checkoutSteps) {
                const meta = CHECKOUT_STEPS.find((s) => s.key === step.key);
                const entry = {};
                if (step.message.trim()) entry.message = step.message;
                if (meta?.toggle) {
                    entry.enabled = step.enabled;
                    if (!step.enabled) entry.default = step.defaultValue || meta.toggle.defaultValue;
                }
                if (meta?.paymentMethods) {
                    entry.payment_methods = Object.fromEntries(
                        step.paymentMethods.map((m) => [m.value, { enabled: m.enabled }])
                    );
                }
                if (Object.keys(entry).length) steps[step.key] = entry;
            }
            config.steps = steps;
        }
    }

    emit('save', {
        name: form.value.name,
        message_template: form.value.message_template,
        is_enabled: form.value.is_enabled,
        config,
    });
}
</script>

<template>
    <aside class="inspector">
        <div class="inspector-header" :style="{ background: meta.color }">
            <i class="fas" :class="meta.icon"></i>
            <span>{{ meta.label }}</span>
            <button type="button" class="inspector-close" @click="emit('close')"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="inspector-body">
            <label class="inspector-field">
                <span>Nombre del nodo</span>
                <input v-model="form.name" type="text" maxlength="255" />
            </label>

            <label v-if="!isCategory && !isProduct" class="inspector-field">
                <span>Mensaje <small>({{ form.message_template.length }}/1024)</small></span>
                <textarea v-model="form.message_template" rows="4" maxlength="1024"></textarea>
            </label>

            <label class="inspector-field inspector-field-inline">
                <input v-model="form.is_enabled" type="checkbox" />
                <span>Nodo habilitado</span>
            </label>

            <p v-if="isSystem" class="inspector-hint">
                Este nodo envuelve una función del bot que ya existe (catálogo real, carrito, checkout, etc.).
                Por ahora solo puedes editar su nombre, mensaje y las conexiones de salida — su configuración
                avanzada se sigue manejando desde el editor clásico.
            </p>

            <template v-if="isCheckout">
                <div class="inspector-section-title">
                    <span>Pasos internos del checkout</span>
                </div>
                <p class="inspector-hint">
                    El orden y cuándo se salta cada paso están fijos en el bot (dependen del carrito y de la
                    sucursal). Aquí solo puedes cambiar lo que dice cada uno; deja el campo vacío para usar el
                    texto por defecto.
                </p>
                <div v-for="step in form.checkoutSteps" :key="step.key" class="inspector-checkout-step">
                    <label class="inspector-field">
                        <span>{{ CHECKOUT_STEPS.find(s => s.key === step.key)?.label }}
                            <small>{{ CHECKOUT_STEPS.find(s => s.key === step.key)?.hint }}</small>
                        </span>
                        <textarea
                            v-model="step.message"
                            rows="2"
                            maxlength="1024"
                            :disabled="CHECKOUT_STEPS.find(s => s.key === step.key)?.toggle && !step.enabled"
                            :placeholder="CHECKOUT_STEPS.find(s => s.key === step.key)?.default"
                        ></textarea>
                    </label>

                    <template v-if="CHECKOUT_STEPS.find(s => s.key === step.key)?.toggle">
                        <label class="inspector-field inspector-field-inline">
                            <input v-model="step.enabled" type="checkbox" />
                            <span>{{ CHECKOUT_STEPS.find(s => s.key === step.key)?.toggle.question }}</span>
                        </label>
                        <label v-if="!step.enabled" class="inspector-field">
                            <span>Valor fijo a usar</span>
                            <select v-model="step.defaultValue">
                                <option v-for="opt in CHECKOUT_STEPS.find(s => s.key === step.key)?.toggle.options" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </label>
                    </template>

                    <template v-if="step.paymentMethods.length">
                        <span class="inspector-field-label">Métodos de pago activos</span>
                        <label v-for="m in step.paymentMethods" :key="m.value" class="inspector-field inspector-field-inline">
                            <input v-model="m.enabled" type="checkbox" />
                            <span>{{ CHECKOUT_STEPS.find(s => s.key === step.key)?.paymentMethods.find(o => o.value === m.value)?.label }}</span>
                        </label>
                    </template>
                </div>
                <p class="inspector-hint">
                    Después de elegir el método de pago (o al confirmar un pedido "para servir", que no pide
                    método de pago) el bot pide una nota opcional y cierra el pedido; esos dos mensajes finales
                    todavía no son editables aquí. El paso de factura (7) es aparte: llega después, una vez que
                    el pedido ya está pagado o confirmado.
                </p>
            </template>

            <template v-if="isCategory">
                <label class="inspector-field">
                    <span>Categoría a mostrar</span>
                    <select v-model.number="form.category_id">
                        <option :value="null" disabled>Elige una categoría…</option>
                        <option v-for="c in catalogOptions?.categories || []" :key="c.id" :value="c.id">{{ c.title }}</option>
                    </select>
                </label>
                <p class="inspector-hint">
                    Al llegar aquí, el cliente ve directo los productos de esta categoría (con foto y precio, como el
                    catálogo normal) — sin tener que elegir la categoría primero. Las listas de WhatsApp no admiten
                    imagen en el mensaje en sí.
                </p>
            </template>

            <template v-if="isProduct">
                <label class="inspector-field">
                    <span>Producto a mostrar</span>
                    <select v-model.number="form.product_id">
                        <option :value="null" disabled>Elige un producto…</option>
                        <option v-for="p in catalogOptions?.products || []" :key="p.id" :value="p.id">{{ p.name }} — ${{ p.price.toFixed(2) }}</option>
                    </select>
                </label>
                <p class="inspector-hint">
                    Se muestra igual que en el catálogo normal: foto del producto (o el logo si no tiene),
                    nombre, precio y sus botones — si el producto tiene variaciones, el cliente elige cuál
                    antes de agregarlo al carrito; si no tiene, un solo botón lo agrega directo.
                </p>
            </template>

            <template v-if="supportsImageUpload">
                <div class="inspector-field">
                    <span>Imagen <small>(opcional, máx. 3 MB)</small></span>
                    <div v-if="currentImagePath" class="inspector-image-preview">
                        <img :src="'/storage/' + currentImagePath" alt="" />
                        <button type="button" class="inspector-add" :disabled="uploadingImage" @click="removeImage">Quitar imagen</button>
                    </div>
                    <input type="file" accept="image/*" :disabled="uploadingImage" @change="handleImageChange" />
                    <span v-if="uploadingImage" class="inspector-hint-inline">Subiendo…</span>
                    <span v-if="imageError" class="inspector-hint-inline inspector-hint-error">{{ imageError }}</span>
                </div>
            </template>

            <template v-if="isMenu">
                <label class="inspector-field">
                    <span>Pie de página <small>(opcional, máx. 60)</small></span>
                    <input v-model="form.footer" type="text" maxlength="60" />
                </label>
            </template>

            <template v-if="hasButtonsEditor">
                <div class="inspector-section-title">
                    <span>Botones ({{ form.buttons.length }}/{{ maxCustomButtons }})</span>
                    <button type="button" class="inspector-add" :disabled="form.buttons.length >= maxCustomButtons" @click="addButton">
                        <i class="fas fa-plus"></i> Botón
                    </button>
                </div>
                <div v-for="(btn, i) in form.buttons" :key="btn.id" class="inspector-row">
                    <input v-model="btn.title" type="text" maxlength="20" placeholder="Texto del botón" />
                    <button type="button" class="inspector-remove" @click="removeButton(i)"><i class="fas fa-trash"></i></button>
                </div>
            </template>

            <template v-if="isList">
                <label class="inspector-field">
                    <span>Texto del botón que abre la lista <small>(máx. 20)</small></span>
                    <input v-model="form.list_button" type="text" maxlength="20" />
                </label>

                <div class="inspector-section-title">
                    <span>Opciones ({{ totalRows }}/10)</span>
                    <button type="button" class="inspector-add" @click="addSection"><i class="fas fa-plus"></i> Sección</button>
                </div>
                <div v-for="(section, si) in form.sections" :key="si" class="inspector-list-section">
                    <div class="inspector-row">
                        <input v-model="section.title" type="text" maxlength="24" placeholder="Título de sección" />
                        <button type="button" class="inspector-remove" @click="removeSection(si)"><i class="fas fa-trash"></i></button>
                    </div>
                    <div v-for="(row, ri) in section.rows" :key="row.id" class="inspector-row inspector-row-nested">
                        <div class="inspector-row-fields">
                            <input v-model="row.title" type="text" maxlength="24" placeholder="Título de la opción" />
                            <input v-model="row.description" type="text" maxlength="72" placeholder="Descripción (opcional)" />
                        </div>
                        <button type="button" class="inspector-remove" @click="removeRow(si, ri)"><i class="fas fa-trash"></i></button>
                    </div>
                    <button type="button" class="inspector-add inspector-add-row" :disabled="totalRows >= 10" @click="addRow(si)">
                        <i class="fas fa-plus"></i> Opción
                    </button>
                </div>
            </template>
        </div>

        <p v-if="saveError" class="inspector-hint inspector-hint-error-block">{{ saveError }}</p>

        <div class="inspector-footer">
            <button type="button" class="inspector-btn inspector-btn-danger" :disabled="node.data.is_start" @click="emit('delete')">
                <i class="fas fa-trash"></i> Borrar nodo
            </button>
            <button type="button" class="inspector-btn inspector-btn-primary" :disabled="saving" @click="save">
                <i class="fas fa-check"></i> {{ saving ? 'Guardando…' : 'Guardar cambios' }}
            </button>
        </div>
    </aside>
</template>

<style scoped>
.inspector {
    display: flex;
    flex-direction: column;
    width: 320px;
    flex: 0 0 320px;
    background: #fff;
    border-left: 1px solid #e2e8f0;
    height: 100%;
    overflow: hidden;
}
.inspector-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 14px;
    color: #fff;
    font-weight: 800;
    font-size: .85rem;
}
.inspector-close {
    margin-left: auto;
    background: none;
    border: 0;
    color: #fff;
    opacity: .85;
    cursor: pointer;
}
.inspector-body {
    flex: 1;
    overflow-y: auto;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.inspector-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-size: .76rem;
    font-weight: 700;
    color: #334155;
}
.inspector-field input[type="text"],
.inspector-field textarea,
.inspector-field select,
.inspector-field input[type="file"] {
    font: inherit;
    font-weight: 400;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 7px 9px;
    resize: vertical;
    background: #fff;
}
.inspector-image-preview {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}
.inspector-image-preview img {
    width: 56px;
    height: 56px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.inspector-hint-inline {
    font-size: .7rem;
    color: #64748b;
}
.inspector-hint-error {
    color: #dc2626;
}
.inspector-field-inline {
    flex-direction: row;
    align-items: center;
    gap: 8px;
}
.inspector-checkout-step {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}
.inspector-field-label {
    font-size: .76rem;
    font-weight: 700;
    color: #334155;
}
.inspector-hint-error-block {
    margin: 0 14px 8px;
    background: #fef2f2;
    color: #b91c1c;
}
.inspector-hint {
    font-size: .72rem;
    color: #92400e;
    background: #fef3c7;
    border-radius: 8px;
    padding: 8px 10px;
    margin: 0;
}
.inspector-section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: .76rem;
    font-weight: 800;
    color: #334155;
    border-top: 1px dashed #e2e8f0;
    padding-top: 10px;
}
.inspector-add {
    font-size: .68rem;
    font-weight: 700;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    border-radius: 999px;
    padding: 3px 9px;
    cursor: pointer;
}
.inspector-add:disabled {
    opacity: .4;
    cursor: not-allowed;
}
.inspector-add-row {
    align-self: flex-start;
    margin-top: 4px;
}
.inspector-row {
    display: flex;
    align-items: center;
    gap: 6px;
}
.inspector-row input {
    flex: 1;
    font: inherit;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 6px 8px;
    font-size: .8rem;
}
.inspector-row-nested {
    padding-left: 10px;
}
.inspector-row-fields {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.inspector-remove {
    border: 0;
    background: none;
    color: #dc2626;
    cursor: pointer;
    padding: 4px;
}
.inspector-list-section {
    display: flex;
    flex-direction: column;
    gap: 6px;
    background: #f8fafc;
    border-radius: 10px;
    padding: 8px;
}
.inspector-footer {
    display: flex;
    gap: 8px;
    padding: 12px 14px;
    border-top: 1px solid #e2e8f0;
}
.inspector-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border-radius: 8px;
    border: 0;
    padding: 9px;
    font-weight: 700;
    font-size: .8rem;
    cursor: pointer;
}
.inspector-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}
.inspector-btn-primary {
    background: #128c7e;
    color: #fff;
}
.inspector-btn-danger {
    background: #fef2f2;
    color: #dc2626;
}
</style>
