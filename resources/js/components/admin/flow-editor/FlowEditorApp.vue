<script setup>
import { ref, onMounted, computed, provide } from 'vue';
import { VueFlow, useVueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { MiniMap } from '@vue-flow/minimap';
import FlowNode from './FlowNode.vue';
import NodeInspector from './NodeInspector.vue';
import NodePalette from './NodePalette.vue';
import PublishPanel from './PublishPanel.vue';
import { nodeMeta, SYSTEM_NODE_TYPES } from './nodeTypes';
import { useFlowApi, apiErrorMessage } from './useFlowApi';

const props = defineProps({
    apiBase: { type: String, required: true },
    primaryColor: { type: String, default: '#128c7e' },
});

const api = useFlowApi(props.apiBase);

const loading = ref(true);
const error = ref(null);
const toast = ref(null);
const saving = ref(false);
const flow = ref(null);
const nodes = ref([]);
const edges = ref([]);
const selectedNodeUuid = ref(null);
const catalogOptions = ref({ products: [], categories: [] });
provide('catalogOptions', catalogOptions);

const { onConnect, onNodeDragStop, onEdgeClick, onNodeClick, onPaneClick, findNode } = useVueFlow();

const nodeTypes = {
    start: FlowNode,
    message: FlowNode,
    button_menu: FlowNode,
    list_menu: FlowNode,
    product: FlowNode,
    category: FlowNode,
    catalog: FlowNode,
    cart: FlowNode,
    checkout: FlowNode,
    order_status: FlowNode,
    payment_proof: FlowNode,
    agent_handoff: FlowNode,
};

const legendItems = computed(() => Object.keys(nodeTypes).map((type) => ({
    type,
    ...nodeMeta(type),
    isSystem: SYSTEM_NODE_TYPES.has(type),
})));

const selectedNode = computed(() => nodes.value.find((n) => n.id === selectedNodeUuid.value) || null);

function showToast(message, isError = false) {
    toast.value = { message, isError };
    setTimeout(() => { if (toast.value?.message === message) toast.value = null; }, 3500);
}

function toFlowNode(n) {
    return {
        id: n.id,
        type: n.node_type,
        position: n.position,
        data: n,
        draggable: true,
        connectable: true,
        selectable: true,
    };
}

function toFlowEdge(e) {
    return {
        id: e.id,
        source: e.source,
        sourceHandle: e.source_handle,
        target: e.target,
        style: { stroke: '#94a3b8', strokeWidth: 1.5 },
    };
}

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const [data, options] = await Promise.all([api.fetchGraph(), api.fetchCatalogOptions()]);
        flow.value = data.flow;
        nodes.value = data.nodes.map(toFlowNode);
        edges.value = data.edges.map(toFlowEdge);
        catalogOptions.value = options;
    } catch (e) {
        error.value = apiErrorMessage(e);
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function handlePublished(result) {
    if (flow.value) {
        flow.value.published_version = result.version_number;
        flow.value.published_at = new Date().toISOString();
    }
    showToast('Publicado (v' + result.version_number + '). El bot en vivo ya usa este flujo.');
}

function handleUnpublished() {
    if (flow.value) {
        flow.value.published_version = null;
        flow.value.published_at = null;
    }
    showToast('Despublicado. El bot en vivo vuelve a usar el editor clásico ("Flujo del bot").');
}

onNodeDragStop(async ({ node }) => {
    try {
        await api.moveNode(node.id, { x: Math.round(node.position.x), y: Math.round(node.position.y) });
    } catch (e) {
        showToast(apiErrorMessage(e), true);
    }
});

onConnect(async (params) => {
    if (!params.sourceHandle) {
        showToast('Este nodo no tiene botones/opciones propias para conectar.', true);
        return;
    }
    try {
        const edge = await api.createEdge({
            source_node_uuid: params.source,
            source_handle: params.sourceHandle,
            target_node_uuid: params.target,
        });
        // Un handle solo puede tener un destino: reemplaza cualquier conexión previa desde el mismo botón/fila.
        edges.value = edges.value.filter((e) => !(e.source === params.source && e.sourceHandle === params.sourceHandle));
        edges.value.push(toFlowEdge(edge));
    } catch (e) {
        showToast(apiErrorMessage(e), true);
    }
});

onEdgeClick(async ({ edge }) => {
    if (!confirm('¿Eliminar esta conexión?')) return;
    try {
        await api.deleteEdge(edge.id);
        edges.value = edges.value.filter((e) => e.id !== edge.id);
    } catch (e) {
        showToast(apiErrorMessage(e), true);
    }
});

onNodeClick(({ node }) => {
    selectedNodeUuid.value = node.id;
});

onPaneClick(() => {
    selectedNodeUuid.value = null;
});

async function handleSaveNode(payload) {
    if (!selectedNode.value) return;
    saving.value = true;
    try {
        const updated = await api.updateNode(selectedNode.value.id, payload);
        const idx = nodes.value.findIndex((n) => n.id === updated.id);
        if (idx !== -1) nodes.value[idx] = toFlowNode(updated);
        showToast('Guardado.');
    } catch (e) {
        showToast(apiErrorMessage(e), true);
    } finally {
        saving.value = false;
    }
}

function handleNodeUpdated(updated) {
    const idx = nodes.value.findIndex((n) => n.id === updated.id);
    if (idx !== -1) nodes.value[idx] = toFlowNode(updated);
}

async function handleDeleteNode() {
    if (!selectedNode.value) return;
    if (!confirm('¿Borrar este nodo y sus conexiones?')) return;
    const uuid = selectedNode.value.id;
    try {
        await api.deleteNode(uuid);
        nodes.value = nodes.value.filter((n) => n.id !== uuid);
        edges.value = edges.value.filter((e) => e.source !== uuid && e.target !== uuid);
        selectedNodeUuid.value = null;
    } catch (e) {
        showToast(apiErrorMessage(e), true);
    }
}

const DEFAULT_NODE_CONFIG = {
    button_menu: { interactive_type: 'button', buttons: [] },
    list_menu: { interactive_type: 'list', list: { button: 'Ver opciones', sections: [] } },
    product: {},
    category: {},
};

async function handleAddNode(nodeType) {
    const maxX = nodes.value.reduce((max, n) => Math.max(max, n.position.x), 0);
    const defaultNames = {
        message: 'Nuevo mensaje',
        button_menu: 'Nuevo menú de botones',
        list_menu: 'Nueva lista',
        product: 'Nuevo producto',
        category: 'Nueva categoría',
    };
    try {
        const created = await api.createNode({
            node_type: nodeType,
            name: defaultNames[nodeType] || 'Nuevo nodo',
            message_template: '',
            position: { x: maxX + 460, y: 40 },
            config: DEFAULT_NODE_CONFIG[nodeType] || { interactive_type: 'text' },
        });
        nodes.value.push(toFlowNode(created));
        selectedNodeUuid.value = created.id;
    } catch (e) {
        showToast(apiErrorMessage(e), true);
    }
}
</script>

<template>
    <div class="flow-editor-shell">
        <div class="flow-editor-topbar">
            <div class="flow-editor-topbar-title">
                <i class="fas fa-diagram-project"></i>
                <span>{{ flow?.name || 'Flujo del bot' }}</span>
                <transition name="fade">
                    <span v-if="toast" class="flow-editor-toast" :class="{ 'is-error': toast.isError }">{{ toast.message }}</span>
                </transition>
            </div>
            <PublishPanel
                v-if="flow"
                :api-base="apiBase"
                :published-version="flow.published_version"
                :published-at="flow.published_at"
                @published="handlePublished"
                @unpublished="handleUnpublished"
            />
        </div>

        <div class="flow-editor-legend-bar">
            <span v-for="item in legendItems" :key="item.type" class="flow-editor-legend-item">
                <i class="fas" :class="item.icon" :style="{ color: item.color }"></i>
                {{ item.label }}
            </span>
        </div>

        <div class="flow-editor-body">
            <div class="flow-editor-canvas">
                <div v-if="loading" class="flow-editor-state">Cargando flujo…</div>
                <div v-else-if="error" class="flow-editor-state flow-editor-state-error">{{ error }}</div>
                <template v-else>
                    <NodePalette @add="handleAddNode" />
                    <VueFlow
                        :nodes="nodes"
                        :edges="edges"
                        :node-types="nodeTypes"
                        :nodes-draggable="true"
                        :nodes-connectable="true"
                        :edges-updatable="false"
                        :elements-selectable="true"
                        fit-view-on-init
                        :min-zoom="0.15"
                        :max-zoom="1.5"
                    >
                        <Background pattern-color="#cbd5e1" :gap="18" />
                        <Controls />
                        <MiniMap pannable zoomable />
                    </VueFlow>
                </template>
            </div>

            <NodeInspector
                v-if="selectedNode"
                :key="selectedNode.id"
                :node="selectedNode"
                :saving="saving"
                :api-base="apiBase"
                @save="handleSaveNode"
                @delete="handleDeleteNode"
                @close="selectedNodeUuid = null"
                @node-updated="handleNodeUpdated"
            />
        </div>
    </div>
</template>

<style scoped>
.flow-editor-shell {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 90px);
    min-height: 560px;
    background: #f8fafc;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}
.flow-editor-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    padding: 12px 16px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
}
.flow-editor-topbar-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 800;
    color: #0f172a;
}
.flow-editor-topbar-title i {
    color: v-bind(primaryColor);
}
.flow-editor-toast {
    font-size: .7rem;
    font-weight: 700;
    color: #166534;
    background: #dcfce7;
    padding: 3px 9px;
    border-radius: 999px;
}
.flow-editor-toast.is-error {
    color: #991b1b;
    background: #fee2e2;
}
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.flow-editor-legend-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 8px 16px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
}
.flow-editor-legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: .7rem;
    color: #64748b;
}
.flow-editor-body {
    flex: 1;
    display: flex;
    min-height: 0;
}
.flow-editor-canvas {
    flex: 1;
    position: relative;
}
.flow-editor-state {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: .9rem;
}
.flow-editor-state-error {
    color: #dc2626;
}
</style>
