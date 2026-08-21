import axios from 'axios';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

export function useFlowApi(apiBase) {
    return {
        fetchGraph: () => axios.get(apiBase + '/data').then((r) => r.data),
        createNode: (payload) => axios.post(apiBase + '/nodes', payload).then((r) => r.data),
        updateNode: (nodeUuid, payload) => axios.put(apiBase + '/nodes/' + nodeUuid, payload).then((r) => r.data),
        moveNode: (nodeUuid, position) => axios.put(apiBase + '/nodes/' + nodeUuid, { position }).then((r) => r.data),
        deleteNode: (nodeUuid) => axios.delete(apiBase + '/nodes/' + nodeUuid).then((r) => r.data),
        createEdge: (payload) => axios.post(apiBase + '/edges', payload).then((r) => r.data),
        deleteEdge: (edgeId) => axios.delete(apiBase + '/edges/' + edgeId).then((r) => r.data),
        publish: () => axios.post(apiBase + '/publish').then((r) => r.data),
        fetchVersions: () => axios.get(apiBase + '/versions').then((r) => r.data),
        restoreVersion: (versionId) => axios.post(apiBase + '/versions/' + versionId + '/restore').then((r) => r.data),
        fetchCatalogOptions: () => axios.get(apiBase + '/catalog-options').then((r) => r.data),
        uploadNodeImage: (nodeUuid, file) => {
            const form = new FormData();
            form.append('image', file);
            return axios.post(apiBase + '/nodes/' + nodeUuid + '/image', form, {
                headers: { 'Content-Type': 'multipart/form-data' },
            }).then((r) => r.data);
        },
        deleteNodeImage: (nodeUuid) => axios.delete(apiBase + '/nodes/' + nodeUuid + '/image').then((r) => r.data),
    };
}

export function apiErrorMessage(error) {
    return error?.response?.data?.message
        || Object.values(error?.response?.data?.errors || {})[0]?.[0]
        || 'Ocurrió un error inesperado.';
}
