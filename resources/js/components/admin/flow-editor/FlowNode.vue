<script setup>
import { computed, inject } from 'vue';
import { Handle, Position } from '@vue-flow/core';
import { nodeMeta, nodeHandles, SYSTEM_NODE_TYPES } from './nodeTypes';

const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, required: true },
});

const catalogOptions = inject('catalogOptions', null);

const meta = computed(() => nodeMeta(props.data.node_type));
const isSystem = computed(() => SYSTEM_NODE_TYPES.has(props.data.node_type));
const handles = computed(() => nodeHandles(props.data));

const preview = computed(() => {
    if (props.data.node_type === 'product') {
        const p = catalogOptions?.value?.products?.find((x) => x.id === props.data.config?.product_id);
        return p ? '📦 ' + p.name + ' — $' + p.price.toFixed(2) : '(elige un producto)';
    }
    if (props.data.node_type === 'category') {
        const c = catalogOptions?.value?.categories?.find((x) => x.id === props.data.config?.category_id);
        return c ? '📂 ' + c.title : '(elige una categoría)';
    }
    return (props.data.message_template || '').trim().slice(0, 140);
});
const isCatalogRef = computed(() => ['product', 'category'].includes(props.data.node_type));
</script>

<template>
    <div class="flow-node" :class="{ 'is-start': data.is_start, 'is-disabled': !data.is_enabled }">
        <Handle type="target" :position="Position.Left" class="flow-node-handle-target" />

        <div class="flow-node-header" :style="{ background: meta.color }">
            <i class="fas" :class="meta.icon"></i>
            <span class="flow-node-title">{{ data.name }}</span>
            <span v-if="data.is_start" class="flow-node-badge">INICIO</span>
            <span v-else-if="isSystem" class="flow-node-badge flow-node-badge-system">SISTEMA</span>
            <span v-else-if="isCatalogRef" class="flow-node-badge flow-node-badge-system">CATÁLOGO</span>
        </div>

        <div class="flow-node-body">
            <p v-if="preview" class="flow-node-preview">{{ preview }}<span v-if="!isCatalogRef && (data.message_template || '').length > 140">…</span></p>
            <p v-else class="flow-node-preview flow-node-preview-empty">(sin texto)</p>

            <div v-if="handles.length" class="flow-node-handles">
                <div v-for="h in handles" :key="h.id" class="flow-node-handle-row">
                    <span class="flow-node-handle-label">{{ h.label }}</span>
                    <Handle
                        :id="h.id"
                        type="source"
                        :position="Position.Right"
                        class="flow-node-handle-source"
                    />
                </div>
            </div>
            <div v-else-if="isSystem || isCatalogRef" class="flow-node-handles">
                <div class="flow-node-handle-row">
                    <span class="flow-node-handle-label flow-node-handle-label-muted">
                        {{ data.node_type === 'product' ? 'según variaciones del producto' : 'salida propia del sistema' }}
                    </span>
                    <Handle id="default" type="source" :position="Position.Right" class="flow-node-handle-source" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.flow-node {
    min-width: 220px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 14px rgba(15, 23, 42, .10);
    font-family: inherit;
    overflow: hidden;
}
.flow-node.is-start {
    box-shadow: 0 0 0 2px #128c7e, 0 4px 14px rgba(15, 23, 42, .10);
}
.flow-node.is-disabled {
    opacity: .55;
}
.flow-node-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 12px;
    color: #fff;
    font-weight: 700;
    font-size: .82rem;
}
.flow-node-title {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.flow-node-badge {
    font-size: .6rem;
    letter-spacing: .04em;
    background: rgba(255, 255, 255, .25);
    padding: 2px 6px;
    border-radius: 999px;
}
.flow-node-body {
    padding: 10px 12px 8px;
}
.flow-node-preview {
    margin: 0 0 8px;
    font-size: .74rem;
    color: #334155;
    line-height: 1.35;
}
.flow-node-preview-empty {
    color: #94a3b8;
    font-style: italic;
}
.flow-node-handles {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border-top: 1px dashed #e2e8f0;
    padding-top: 6px;
}
.flow-node-handle-row {
    position: relative;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    font-size: .72rem;
    color: #475569;
    padding-right: 4px;
}
.flow-node-handle-label-muted {
    font-style: italic;
    color: #94a3b8;
}
:deep(.flow-node-handle-target) {
    width: 10px;
    height: 10px;
    background: #128c7e;
    border: 2px solid #fff;
}
:deep(.flow-node-handle-source) {
    position: static;
    transform: none;
    width: 10px;
    height: 10px;
    background: #ff650b;
    border: 2px solid #fff;
    margin-left: 6px;
}
</style>
