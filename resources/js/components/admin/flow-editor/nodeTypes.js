// Metadatos visuales por tipo de nodo. Los tipos "de sistema" envuelven
// lógica de negocio que ya existe en el bot (catálogo real, carrito,
// checkout, etc.) y se distinguen visualmente de los nodos de mensaje/menú
// que el usuario crea libremente.
export const SYSTEM_NODE_TYPES = new Set([
    'catalog',
    'cart',
    'checkout',
    'order_status',
    'payment_proof',
    'agent_handoff',
]);

export const NODE_META = {
    start: { label: 'Inicio', icon: 'fa-hand-sparkles', color: '#128c7e' },
    message: { label: 'Mensaje', icon: 'fa-comment-dots', color: '#64748b' },
    button_menu: { label: 'Menú de botones', icon: 'fa-list-ul', color: '#027eb5' },
    list_menu: { label: 'Menú de lista', icon: 'fa-bars', color: '#027eb5' },
    product: { label: 'Producto', icon: 'fa-tag', color: '#16a34a' },
    category: { label: 'Categoría', icon: 'fa-folder-open', color: '#16a34a' },
    catalog: { label: 'Catálogo (sistema)', icon: 'fa-bag-shopping', color: '#ff650b' },
    cart: { label: 'Carrito (sistema)', icon: 'fa-cart-shopping', color: '#ff650b' },
    checkout: { label: 'Checkout (sistema)', icon: 'fa-credit-card', color: '#ff650b' },
    order_status: { label: 'Estado de pedido (sistema)', icon: 'fa-box', color: '#6f42c1' },
    payment_proof: { label: 'Comprobante de pago (sistema)', icon: 'fa-receipt', color: '#6f42c1' },
    agent_handoff: { label: 'Agente humano (sistema)', icon: 'fa-headset', color: '#e4002b' },
};

export function nodeMeta(nodeType) {
    return NODE_META[nodeType] || { label: nodeType, icon: 'fa-circle-question', color: '#64748b' };
}

export function nodeHandles(node) {
    const handles = [];
    const buttons = node.config?.buttons || [];
    for (const button of buttons) {
        if (button.id) handles.push({ id: button.id, label: button.title || button.id });
    }
    const sections = node.config?.list?.sections || [];
    for (const section of sections) {
        for (const row of section.rows || []) {
            if (row.id) handles.push({ id: row.id, label: row.title || row.id });
        }
    }
    return handles;
}
