import { createApp } from 'vue';
import FlowEditorApp from './components/admin/flow-editor/FlowEditorApp.vue';

import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';
import '@vue-flow/minimap/dist/style.css';

const el = document.getElementById('flow-editor-app');

if (el) {
    createApp(FlowEditorApp, {
        apiBase: el.dataset.apiBase,
        primaryColor: el.dataset.primaryColor || '#128c7e',
        secondaryColor: el.dataset.secondaryColor || '#075e54',
    }).mount(el);
}
