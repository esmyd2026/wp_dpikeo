<script setup>
import { ref, computed } from 'vue';
import { useFlowApi, apiErrorMessage } from './useFlowApi';

const props = defineProps({
    apiBase: { type: String, required: true },
    publishedVersion: { type: Number, default: null },
    publishedAt: { type: String, default: null },
});

const emit = defineEmits(['published']);

const api = useFlowApi(props.apiBase);

const open = ref(false);
const publishing = ref(false);
const loadingVersions = ref(false);
const versions = ref([]);
const error = ref(null);

const statusLabel = computed(() => {
    if (!props.publishedVersion) return 'Sin publicar';
    return 'Publicado v' + props.publishedVersion;
});

async function toggle() {
    open.value = !open.value;
    if (open.value) {
        await loadVersions();
    }
}

async function loadVersions() {
    loadingVersions.value = true;
    try {
        versions.value = await api.fetchVersions();
    } catch (e) {
        error.value = apiErrorMessage(e);
    } finally {
        loadingVersions.value = false;
    }
}

async function doPublish() {
    if (!confirm('Esto actualizará el bot en vivo para todos tus clientes con lo que armaste en el lienzo. ¿Publicar ahora?')) {
        return;
    }
    publishing.value = true;
    error.value = null;
    try {
        const result = await api.publish();
        emit('published', result);
        await loadVersions();
    } catch (e) {
        error.value = apiErrorMessage(e);
    } finally {
        publishing.value = false;
    }
}

async function doRestore(version) {
    if (!confirm('¿Restaurar la versión ' + version.version_number + '? Esto también actualiza el bot en vivo de inmediato.')) {
        return;
    }
    try {
        const result = await api.restoreVersion(version.id);
        emit('published', { version_number: result.version_number });
        await loadVersions();
    } catch (e) {
        error.value = apiErrorMessage(e);
    }
}

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('es-EC', { dateStyle: 'medium', timeStyle: 'short' });
}
</script>

<template>
    <div class="publish">
        <button type="button" class="publish-status" :class="{ 'is-live': publishedVersion }" @click="toggle">
            <i class="fas" :class="publishedVersion ? 'fa-circle-check' : 'fa-circle-exclamation'"></i>
            {{ statusLabel }}
            <i class="fas fa-chevron-down publish-caret"></i>
        </button>
        <button type="button" class="publish-btn" :disabled="publishing" @click="doPublish">
            <i class="fas fa-cloud-arrow-up"></i> {{ publishing ? 'Publicando…' : 'Publicar' }}
        </button>

        <div v-if="open" class="publish-dropdown">
            <div class="publish-dropdown-header">Historial de versiones</div>
            <p v-if="error" class="publish-error">{{ error }}</p>
            <p v-else-if="loadingVersions" class="publish-empty">Cargando…</p>
            <p v-else-if="!versions.length" class="publish-empty">Todavía no has publicado ninguna versión.</p>
            <div v-else class="publish-version-list">
                <div v-for="v in versions" :key="v.id" class="publish-version" :class="{ 'is-current': v.is_current }">
                    <div>
                        <strong>v{{ v.version_number }}</strong>
                        <span v-if="v.is_current" class="publish-version-tag">actual</span>
                        <div class="publish-version-meta">
                            {{ formatDate(v.published_at) }}<span v-if="v.published_by"> · {{ v.published_by }}</span> · {{ v.nodes_count }} nodos
                        </div>
                    </div>
                    <button v-if="!v.is_current" type="button" class="publish-restore" @click="doRestore(v)">Restaurar</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.publish {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
}
.publish-status {
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    border-radius: 999px;
    padding: 5px 10px;
    font-size: .72rem;
    font-weight: 700;
    cursor: pointer;
}
.publish-status.is-live {
    color: #166534;
    background: #dcfce7;
    border-color: #bbf7d0;
}
.publish-caret {
    font-size: .6rem;
    opacity: .6;
}
.publish-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    border: 0;
    background: #128c7e;
    color: #fff;
    border-radius: 8px;
    padding: 7px 12px;
    font-size: .78rem;
    font-weight: 800;
    cursor: pointer;
}
.publish-btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}
.publish-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    z-index: 20;
    width: 320px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, .15);
    padding: 12px;
}
.publish-dropdown-header {
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #94a3b8;
    margin-bottom: 8px;
}
.publish-empty, .publish-error {
    font-size: .78rem;
    color: #94a3b8;
    margin: 0;
}
.publish-error {
    color: #dc2626;
}
.publish-version-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 260px;
    overflow-y: auto;
}
.publish-version {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px;
    border-radius: 8px;
    background: #f8fafc;
    font-size: .76rem;
    color: #334155;
}
.publish-version.is-current {
    background: #ecfdf5;
}
.publish-version-tag {
    font-size: .6rem;
    font-weight: 800;
    color: #166534;
    background: #bbf7d0;
    border-radius: 999px;
    padding: 1px 7px;
    margin-left: 6px;
}
.publish-version-meta {
    color: #94a3b8;
    font-size: .68rem;
    margin-top: 2px;
}
.publish-restore {
    border: 1px solid #cbd5e1;
    background: #fff;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: .7rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
}
</style>
