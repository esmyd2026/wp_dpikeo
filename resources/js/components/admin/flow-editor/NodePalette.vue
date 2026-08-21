<script setup>
import { nodeMeta } from './nodeTypes';

defineEmits(['add']);

const creatable = ['message', 'button_menu', 'list_menu', 'product', 'category'];
</script>

<template>
    <div class="palette">
        <span class="palette-label">Agregar nodo</span>
        <button
            v-for="type in creatable"
            :key="type"
            type="button"
            class="palette-btn"
            :style="{ borderColor: nodeMeta(type).color }"
            @click="$emit('add', type)"
        >
            <i class="fas" :class="nodeMeta(type).icon" :style="{ color: nodeMeta(type).color }"></i>
            {{ nodeMeta(type).label }}
        </button>
    </div>
</template>

<style scoped>
.palette {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 5;
    display: flex;
    flex-direction: column;
    gap: 6px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px;
    box-shadow: 0 6px 20px rgba(15, 23, 42, .10);
}
.palette-label {
    font-size: .68rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #94a3b8;
    padding: 0 2px 2px;
}
.palette-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 9px;
    padding: 7px 10px;
    font-size: .78rem;
    font-weight: 700;
    color: #334155;
    cursor: pointer;
    white-space: nowrap;
}
.palette-btn:hover {
    background: #f8fafc;
}
</style>
