@extends('admin.layouts.app')

@section('header', 'Pedidos')

@section('content')
@php
    use App\Services\OrderAdminService;

    $statusLabels = [
        'pending' => 'Nuevo · por revisar',
        'confirmed' => 'Aceptado',
        'preparing' => 'En cocina',
        'ready' => 'Listo para entregar',
        'completed' => 'Ya entregado',
        'cancelled' => 'Cancelado',
        'payment_pending' => 'Esperando pago',
        'paid' => 'Pago recibido',
    ];
    $statusOptions = ['pending', 'confirmed', 'payment_pending', 'paid', 'preparing', 'ready', 'completed', 'cancelled'];
    $invoiceLabels = OrderAdminService::INVOICE_STATUSES;
    $canUpdate = auth()->user()?->hasPermission('orders.update') ?? false;
    $canBulkCreate = auth()->user()?->hasPermission('bulk_orders.create') ?? false;
    $canViewBilling = auth()->user()?->hasPermission('orders.billing') ?? false;
    $canViewInternalNotes = auth()->user()?->hasPermission('orders.internal_notes') ?? false;
    $canViewFollowup = auth()->user()?->hasPermission('orders.followup') ?? false;
    $columnHints = [
        'id' => 'Identificador único del pedido en el sistema. Sirve para buscarlo, exportarlo o referenciarlo en notas internas.',
        'client' => 'Nombre del contacto de WhatsApp vinculado al pedido. Si tiene cédula en su perfil, aparece debajo del nombre.',
        'phone' => 'Número de WhatsApp del cliente. Es el canal usado para confirmaciones, comprobantes y seguimiento.',
        'date' => 'Fecha y hora en que se registró el pedido (hora del servidor).',
        'total' => 'Monto total del pedido: suma de productos, cantidades y ajustes aplicados al momento de la compra.',
        'products' => 'Cantidad de líneas de producto incluidas en el pedido (no es la suma de unidades).',
        'tags' => 'Indicadores rápidos: observaciones internas, feedback con el cliente, espera de confirmación por WhatsApp, comprobante de pago recibido o pendiente.',
        'status' => 'Etapa del pedido: Pendiente (nuevo), Confirmado, Pago pendiente, Pagado, Completado o Cancelado. Puede cambiarse desde aquí si tiene permiso.',
        'actions' => 'Abrir chat con el cliente, ver el detalle completo del pedido o descargar el PDF de la orden.',
    ];
    $sectionHints = [
        'products' => 'Detalle de lo que compró el cliente: productos, cantidades, precios unitarios y subtotales que forman el total del pedido.',
        'payment_proof' => 'Comprobante de pago enviado por WhatsApp (foto o PDF). Aparece automáticamente cuando el cliente lo adjunta tras transferencia o tarjeta.',
        'confirmation' => 'Envía al cliente el PDF del pedido con botones para confirmar, modificar o cancelar. Solo disponible en pedidos pendientes o con pago pendiente.',
        'billing' => 'Datos fiscales para emitir factura. Al guardar, se copian al perfil del cliente para futuros pedidos.',
        'fulfillment' => 'Sucursal, tipo de pedido (llevar/servir) y datos de entrega. En delivery, el costo mostrado es referencial hasta que un vendedor lo confirme aquí; al confirmarlo se le avisa al cliente por WhatsApp.',
        'internal_notes' => 'Notas visibles solo para el equipo administrativo. El cliente no las ve en WhatsApp ni en el PDF.',
        'feedback' => 'Registro de comunicaciones y acuerdos con el cliente (envíos, confirmaciones, incidencias). Útil para el historial del pedido.',
    ];
    $fieldHints = [
        'requires_invoice' => 'Actívelo si el cliente pidió factura fiscal. Desbloquea el seguimiento de estado y los datos de facturación.',
        'invoice_status' => 'Progreso de la factura: sin factura, solicitada, datos listos, emitida o entregada al cliente.',
        'billing_type' => 'Tipo de identificación fiscal del cliente: cédula de persona natural o RUC de empresa.',
        'billing_id' => 'Número de cédula o RUC según el tipo seleccionado. Debe coincidir con los datos del SRI.',
        'billing_legal_name' => 'Nombre completo o razón social tal como debe figurar en la factura.',
        'address' => 'Dirección fiscal registrada para la factura electrónica.',
        'confirmation_message' => 'Texto opcional que acompaña el PDF y los botones de confirmación en WhatsApp.',
        'product_name' => 'Nombre del producto o servicio incluido en el pedido.',
        'product_qty' => 'Unidades solicitadas de ese producto.',
        'product_price' => 'Precio unitario al momento de la compra.',
        'product_subtotal' => 'Cantidad × precio unitario para esa línea.',
    ];
@endphp

<style>
    .orders-page { max-width: 1140px; margin: 0 auto; }
    .orders-top { margin-bottom: .85rem; }
    .orders-top h2 { margin: 0 0 .3rem; font-size: 1.35rem; font-weight: 800; color: #0f172a; }
    .orders-top .lead { margin: 0; font-size: .875rem; color: #64748b; }

    .orders-priority {
        display: grid; grid-template-columns: repeat(4, 1fr);
        gap: .65rem; margin-bottom: .85rem;
    }
    @media (max-width: 900px) { .orders-priority { grid-template-columns: repeat(2, 1fr); } }
    .prio-card {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        padding: .85rem 1rem; box-shadow: 0 1px 3px rgba(15,23,42,.04);
    }
    .prio-card .lbl { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
    .prio-card .val { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin-top: .15rem; }
    .prio-card.urgent { border-color: #fde68a; background: linear-gradient(180deg, #fffbeb, #fff); }
    .prio-card.urgent .val { color: #b45309; }
    .prio-card.accent .val { color: #047857; }

    .orders-toolbar {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
        padding: .85rem 1rem; margin-bottom: .85rem;
        display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end;
    }
    .orders-export-form {
        display: flex; flex-wrap: wrap; gap: .5rem; align-items: flex-end; margin-left: auto;
    }
    .orders-export-form .field label {
        display: block; font-size: .65rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .03em; color: #64748b; margin-bottom: .2rem;
    }
    .orders-export-form .field input,
    .orders-export-form .field select {
        border: 1px solid #e2e8f0; border-radius: 8px; padding: .4rem .55rem;
        font-size: .8rem; min-width: 0;
    }
    .o-btn.export {
        background: #0f766e; border-color: #0f766e; color: #fff !important;
    }
    .o-btn.export:hover { background: #115e59; }
    .orders-search { position: relative; flex: 1; min-width: 200px; max-width: 340px; }
    .orders-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: .85rem; }
    .orders-search input {
        width: 100%; border: 1px solid #e2e8f0; border-radius: 10px;
        padding: .5rem .75rem .5rem 2.1rem; font-size: .875rem;
    }

    .orders-table-wrap {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        overflow-x: auto; box-shadow: 0 1px 3px rgba(15,23,42,.04);
    }
    .orders-table {
        width: 100%; border-collapse: collapse; font-size: .82rem;
    }
    .orders-table th {
        padding: .55rem .65rem; text-align: left; font-size: .68rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .03em; color: #64748b;
        background: #f8fafc; border-bottom: 1px solid #e5e7eb; white-space: nowrap;
    }
    .th-label-row {
        display: inline-flex; align-items: center; gap: .3rem; white-space: nowrap;
    }
    .metric-info-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 16px; height: 16px; padding: 0; border: none; background: transparent;
        color: #94a3b8; cursor: help; border-radius: 50%; font-size: .72rem;
        line-height: 1; vertical-align: middle;
    }
    .metric-info-btn:hover { color: #128c7e; }
    .orders-table td {
        padding: .5rem .65rem; border-bottom: 1px solid #f1f5f9;
        vertical-align: middle; color: #334155;
    }
    .orders-table tbody tr:hover { background: #fafbfc; }
    .orders-table tbody tr:last-child td { border-bottom: none; }

    .order-cell-name { font-weight: 700; color: #0f172a; white-space: nowrap; }
    .order-cell-id { font-size: .72rem; color: #94a3b8; font-weight: 600; }
    .order-cell-muted { font-size: .78rem; color: #64748b; white-space: nowrap; }
    .order-cell-money { font-weight: 700; color: #0f172a; white-space: nowrap; }

    .order-tags { display: flex; flex-wrap: wrap; gap: .25rem; }
    .o-tag {
        font-size: .62rem; font-weight: 700; padding: .15rem .4rem; border-radius: 999px;
        display: inline-flex; align-items: center; gap: .2rem; white-space: nowrap;
    }
    .o-tag.notes { background: #e0e7ff; color: #3730a3; }
    .o-tag.feedback { background: #dcfce7; color: #166534; }
    .o-tag.confirm { background: #fef3c7; color: #92400e; }
    .o-tag.proof-ok { background: #dbeafe; color: #1d4ed8; }
    .o-tag.proof-wait { background: #ffedd5; color: #c2410c; }
    .o-tag.new-order { background: #dcfce7; color: #15803d; animation: o-tag-pulse 1.6s ease-in-out infinite; }
    .o-tag.empty { color: #cbd5e1; }
    @keyframes o-tag-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .55; } }

    .o-tag.fulfil-retiro { background: #e0f2fe; color: #0369a1; }
    .o-tag.fulfil-delivery { background: #fae8ff; color: #a21caf; }
    .o-tag.fulfil-servir { background: #f1f5f9; color: #475569; }
    .o-tag.fulfil-pending { background: #fef3c7; color: #92400e; }

    .orders-refresh-banner {
        position: fixed; bottom: 22px; left: 50%; transform: translateX(-50%) translateY(30px);
        opacity: 0; visibility: hidden; pointer-events: none;
        background: #075e54; color: #fff; padding: .7rem 1.1rem; border-radius: 999px;
        display: flex; align-items: center; gap: .6rem; font-size: .85rem; font-weight: 700;
        box-shadow: 0 8px 26px rgba(7,94,84,.35); cursor: pointer; z-index: 40;
        transition: transform .25s ease, opacity .25s ease, visibility .25s;
    }
    .orders-refresh-banner.is-visible {
        transform: translateX(-50%) translateY(0); opacity: 1; visibility: visible; pointer-events: auto;
    }

    .order-status-select {
        appearance: none; border: 1.5px solid transparent; border-radius: 20px;
        padding: .4rem 1.9rem .4rem .85rem; font-size: .74rem; font-weight: 700; cursor: pointer;
        letter-spacing: .01em;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right .6rem center;
        box-shadow: 0 1px 2px rgba(15,23,42,.06);
        transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
    }
    .order-status-select:hover { transform: translateY(-1px); box-shadow: 0 3px 8px rgba(15,23,42,.12); }
    .order-status-select:focus { outline: none; border-color: currentColor; box-shadow: 0 0 0 3px rgba(15,23,42,.08); }
    .order-status-select.status-pending { background-color: #fff8e6; color: #b8860b; }
    .order-status-select.status-confirmed { background-color: #e7f1ff; color: #0d6efd; }
    .order-status-select.status-completed { background-color: #e8f8ef; color: #198754; }
    .order-status-select.status-cancelled { background-color: #fdecea; color: #dc3545; }
    .order-status-select.status-payment_pending { background-color: #fff3e0; color: #e65100; }
    .order-status-select.status-paid { background-color: #ede7f6; color: #6f42c1; }
    .order-status-select.status-preparing { background-color: #fff7ed; color: #c2410c; }
    .order-status-select.status-ready { background-color: #dcfce7; color: #15803d; }

    .order-row-actions { display: flex; gap: .3rem; align-items: center; justify-content: flex-end; white-space: nowrap; }
    .o-btn {
        display: inline-flex; align-items: center; gap: .3rem; padding: .35rem .55rem;
        border-radius: 8px; font-size: .75rem; font-weight: 600; border: 1px solid #e2e8f0;
        background: #fff; color: #475569; cursor: pointer; text-decoration: none;
    }
    .o-btn.primary { background: linear-gradient(135deg, #128c7e, #075e54); border-color: transparent; color: #fff !important; }

    .orders-empty {
        text-align: center; padding: 3rem; background: #fff; border: 1px dashed #e2e8f0; border-radius: 14px; color: #64748b;
    }

    /* Modal */
    .modal-overlay {
        position: fixed; inset: 0; background: rgba(15,23,42,.45); backdrop-filter: blur(4px);
        z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;
        opacity: 0; visibility: hidden; transition: opacity .2s, visibility .2s;
    }
    .modal-overlay.is-open { opacity: 1; visibility: visible; }
    .modal-panel {
        background: #fff; border-radius: 16px; width: 100%; max-width: 720px; max-height: 92vh;
        overflow: hidden; display: flex; flex-direction: column;
        box-shadow: 0 20px 50px rgba(0,0,0,.2); transform: translateY(12px); transition: transform .2s;
    }
    .modal-overlay.is-open .modal-panel { transform: translateY(0); }
    .modal-header {
        padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9;
        display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;
        background: #f8fafc;
    }
    .modal-header h3 { margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; }
    .modal-header .sub { font-size: .78rem; color: #64748b; margin-top: .15rem; }
    .modal-close {
        width: 34px; height: 34px; border: none; background: #e2e8f0; border-radius: 50%;
        cursor: pointer; color: #475569;
    }
    .modal-body {
        padding: 1rem 1.15rem 1.25rem; overflow-y: auto; flex: 1;
        background: linear-gradient(180deg, #e8edf3 0%, #eef2f7 100%);
    }
    .modal-footer {
        padding: .85rem 1.25rem; border-top: 1px solid #e2e8f0;
        display: flex; gap: .5rem; justify-content: flex-end; flex-wrap: wrap;
        background: #fff; box-shadow: 0 -4px 12px rgba(15, 23, 42, .04);
    }

    /* Vista operativa: una orden se debe revisar y despachar, no llenar como factura. */
    .order-command-center { display: flex; flex-direction: column; gap: .8rem; }
    .order-ticket-card, .order-quick-actions, .order-disclosure {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
        box-shadow: 0 2px 9px rgba(15,23,42,.045);
    }
    .order-ticket-card { overflow: hidden; }
    .order-ticket-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .8rem 1rem; background: #f8fafc; border-bottom: 1px solid #edf1f5; }
    .order-ticket-head strong { color: #0f172a; font-size: .9rem; }
    .order-ticket-head span { color: #64748b; font-size: .76rem; }
    .order-compact-line { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: .6rem; padding: .7rem 1rem; border-bottom: 1px solid #f1f5f9; }
    .order-compact-line:last-child { border-bottom: 0; }
    .order-compact-line-name { display: block; color: #0f172a; font-size: .84rem; font-weight: 700; }
    .order-compact-line-meta { display: block; color: #64748b; font-size: .74rem; margin-top: .15rem; }
    .order-compact-line-note { display: block; color: #a16207; font-size: .7rem; margin-top: .18rem; }
    .order-compact-line-total { align-self: center; color: #0f172a; font-size: .84rem; font-weight: 800; white-space: nowrap; }
    .order-ticket-total { display: flex; align-items: center; justify-content: space-between; padding: .8rem 1rem; color: #075e54; background: #f0fdf8; font-weight: 800; }
    .order-ticket-total strong { font-size: 1.05rem; }
    .order-quick-actions { padding: .9rem 1rem; border-left: 4px solid #16a34a; }
    .order-quick-actions-row { display: flex; align-items: center; justify-content: space-between; gap: .8rem; }
    .order-quick-actions-copy strong { display: block; color: #0f172a; font-size: .86rem; }
    .order-quick-actions-copy span { display: block; color: #64748b; font-size: .75rem; margin-top: .12rem; }
    .order-quick-actions .o-btn { flex-shrink: 0; }
    .order-disclosure { overflow: hidden; box-shadow: none; }
    .order-disclosure summary { list-style: none; cursor: pointer; padding: .75rem 1rem; color: #475569; font-size: .78rem; font-weight: 700; }
    .order-disclosure summary::-webkit-details-marker { display: none; }
    .order-disclosure summary::after { content: '+'; float: right; color: #94a3b8; font-size: 1rem; line-height: .8; }
    .order-disclosure[open] summary { border-bottom: 1px solid #edf1f5; color: #0f172a; }
    .order-disclosure[open] summary::after { content: '−'; }
    .order-disclosure-body { padding: .85rem 1rem 1rem; }

    .order-sections-stack { display: flex; flex-direction: column; gap: .9rem; }

    .order-section {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
        overflow: hidden; box-shadow: 0 2px 10px rgba(15, 23, 42, .06);
    }
    .order-section[data-theme="products"] { border-left: 4px solid #64748b; }
    .order-section[data-theme="payment"] { border-left: 4px solid #2563eb; }
    .order-section[data-theme="confirmation"] { border-left: 4px solid #16a34a; }
    .order-section[data-theme="billing"] { border-left: 4px solid #d97706; }
    .order-section[data-theme="notes"] { border-left: 4px solid #6366f1; }
    .order-section[data-theme="feedback"] { border-left: 4px solid #0d9488; }
    .order-section[data-theme="fulfillment"] { border-left: 4px solid #a21caf; }

    .order-section-head {
        padding: .75rem 1rem; border-bottom: 1px solid #f1f5f9;
        display: flex; align-items: center; justify-content: space-between; gap: .5rem;
    }
    .order-section[data-theme="products"] .order-section-head { background: linear-gradient(90deg, #f8fafc 0%, #fff 100%); }
    .order-section[data-theme="payment"] .order-section-head { background: linear-gradient(90deg, #eff6ff 0%, #fff 100%); }
    .order-section[data-theme="confirmation"] .order-section-head { background: linear-gradient(90deg, #ecfdf5 0%, #fff 100%); }
    .order-section[data-theme="billing"] .order-section-head { background: linear-gradient(90deg, #fffbeb 0%, #fff 100%); }
    .order-section[data-theme="notes"] .order-section-head { background: linear-gradient(90deg, #eef2ff 0%, #fff 100%); }
    .order-section[data-theme="feedback"] .order-section-head { background: linear-gradient(90deg, #f0fdfa 0%, #fff 100%); }
    .order-section[data-theme="fulfillment"] .order-section-head { background: linear-gradient(90deg, #fdf4ff 0%, #fff 100%); }

    .order-section-head-main {
        display: flex; align-items: center; gap: .55rem;
        font-size: .84rem; font-weight: 700; color: #0f172a; min-width: 0;
    }
    .order-section-icon {
        width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: .88rem;
    }
    .order-section[data-theme="products"] .order-section-icon { background: #e2e8f0; color: #475569; }
    .order-section[data-theme="payment"] .order-section-icon { background: #dbeafe; color: #1d4ed8; }
    .order-section[data-theme="confirmation"] .order-section-icon { background: #dcfce7; color: #15803d; }
    .order-section[data-theme="billing"] .order-section-icon { background: #fef3c7; color: #b45309; }
    .order-section[data-theme="notes"] .order-section-icon { background: #e0e7ff; color: #4338ca; }
    .order-section[data-theme="feedback"] .order-section-icon { background: #ccfbf1; color: #0f766e; }
    .order-section[data-theme="fulfillment"] .order-section-icon { background: #fae8ff; color: #a21caf; }

    .fulfillment-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .65rem .9rem; }
    .fulfillment-full { grid-column: 1 / -1; }
    .fulfillment-lbl { display: block; font-size: .66rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; font-weight: 700; }
    .fulfillment-val { display: block; color: #0f172a; font-weight: 600; font-size: .84rem; margin-top: .1rem; }

    .order-step { border: 1px solid #e2e8f0; border-radius: 12px; padding: .8rem .9rem; margin-top: .8rem; }
    .order-step-head { display: flex; align-items: center; gap: .5rem; margin-bottom: .5rem; }
    .order-step-num { width: 22px; height: 22px; border-radius: 50%; background: #a21caf; color: #fff; font-size: .74rem; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .order-step-title { font-size: .82rem; font-weight: 800; color: #0f172a; }
    .order-step.is-done .order-step-num { background: #16a34a; }
    .order-step.is-done { border-color: #bbf7d0; background: #f0fdf4; }

    .order-section-body { padding: 1rem; background: #fff; }
    .order-section-body.flush { padding: 0; }

    .section-info-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 18px; height: 18px; padding: 0; border: none; background: transparent;
        color: #94a3b8; cursor: help; border-radius: 50%; font-size: .78rem;
        line-height: 1; flex-shrink: 0; margin-left: .15rem;
    }
    .section-info-btn:hover { color: #128c7e; }

    .field-label-row {
        display: inline-flex; align-items: center; gap: .25rem; flex-wrap: wrap;
    }
    .field-info-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 15px; height: 15px; padding: 0; border: none; background: transparent;
        color: #94a3b8; cursor: help; border-radius: 50%; font-size: .68rem;
        line-height: 1; vertical-align: middle;
    }
    .field-info-btn:hover { color: #128c7e; }

    .order-callout {
        padding: .65rem .75rem; border-radius: 10px; font-size: .8rem; margin-bottom: .75rem;
        border: 1px solid transparent;
    }
    .order-callout.warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .order-callout.info { background: #f8fafc; border-color: #e2e8f0; color: #64748b; }
    .order-callout.sync { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }

    .checklist { list-style: none; margin: 0; padding: 0; }
    .checklist li {
        display: flex; gap: .6rem; padding: .5rem 0; border-bottom: 1px solid #f8fafc;
        font-size: .82rem; align-items: flex-start;
    }
    .checklist li:last-child { border-bottom: none; }
    .checklist .chk {
        width: 20px; height: 20px; border-radius: 6px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: .65rem;
    }
    .checklist .chk.done { background: #dcfce7; color: #15803d; }
    .checklist .chk.pending { background: #f1f5f9; color: #94a3b8; }

    .note-item { padding: .55rem 0; border-bottom: 1px solid #f1f5f9; font-size: .82rem; }
    .note-item:last-child { border-bottom: none; }
    .note-meta { font-size: .72rem; color: #94a3b8; margin-bottom: .15rem; }
    .note-meta strong { color: #475569; }

    .products-table { width: 100%; font-size: .82rem; border-collapse: collapse; }
    .products-table th, .products-table td { padding: .45rem .5rem; border-bottom: 1px solid #f1f5f9; }
    .products-table th { font-size: .68rem; text-transform: uppercase; color: #64748b; text-align: left; }
    .products-table .th-label-row {
        display: inline-flex; align-items: center; gap: .25rem; white-space: nowrap;
    }

    /* Comprobante de pago */
    .payment-proof-card {
        border-radius: 14px;
        overflow: hidden;
        background: linear-gradient(135deg, #f0fdf4 0%, #ecfeff 55%, #eff6ff 100%);
        border: 1px solid #bbf7d0;
        box-shadow: 0 4px 18px rgba(15, 118, 110, .08);
    }
    .payment-proof-card.is-awaiting {
        background: linear-gradient(135deg, #fffbeb 0%, #fff7ed 100%);
        border-color: #fed7aa;
        box-shadow: 0 4px 18px rgba(234, 88, 12, .06);
    }
    .payment-proof-top {
        display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem;
        padding: 1rem 1.1rem .75rem;
    }
    .payment-proof-title-wrap { display: flex; align-items: center; gap: .75rem; min-width: 0; }
    .payment-proof-icon {
        width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #059669, #0d9488);
        color: #fff; font-size: 1.1rem;
        box-shadow: 0 6px 16px rgba(5, 150, 105, .25);
    }
    .payment-proof-card.is-awaiting .payment-proof-icon {
        background: linear-gradient(135deg, #ea580c, #f59e0b);
        box-shadow: 0 6px 16px rgba(234, 88, 12, .2);
    }
    .payment-proof-title { margin: 0; font-size: .95rem; font-weight: 800; color: #0f172a; }
    .payment-proof-sub { margin: .15rem 0 0; font-size: .75rem; color: #64748b; }
    .payment-proof-badge {
        font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em;
        padding: .35rem .65rem; border-radius: 999px; white-space: nowrap;
    }
    .payment-proof-badge.ok { background: #dcfce7; color: #166534; }
    .payment-proof-badge.wait { background: #ffedd5; color: #c2410c; }
    .payment-proof-meta {
        display: flex; flex-wrap: wrap; gap: .5rem 1rem;
        padding: 0 1.1rem .85rem; font-size: .78rem; color: #475569;
    }
    .payment-proof-meta span { display: inline-flex; align-items: center; gap: .35rem; }
    .payment-proof-meta i { color: #0f766e; font-size: .72rem; }
    .payment-proof-preview {
        margin: 0 1.1rem 1rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.8);
    }
    .payment-proof-preview img {
        display: block; width: 100%; max-height: 320px; object-fit: contain;
        background: #0f172a; cursor: zoom-in;
    }
    .payment-proof-doc {
        display: flex; align-items: center; gap: .85rem;
        padding: 1rem 1.1rem;
    }
    .payment-proof-doc-icon {
        width: 52px; height: 52px; border-radius: 12px; flex-shrink: 0;
        background: linear-gradient(135deg, #ef4444, #f97316);
        color: #fff; display: flex; align-items: center; justify-content: center;
        font-size: .72rem; font-weight: 800; letter-spacing: .03em;
    }
    .payment-proof-doc-name {
        font-size: .88rem; font-weight: 700; color: #0f172a;
        word-break: break-word;
    }
    .payment-proof-doc-hint { font-size: .75rem; color: #64748b; margin-top: .15rem; }
    .payment-proof-actions {
        display: flex; flex-wrap: wrap; gap: .5rem;
        padding: 0 1.1rem 1rem;
    }
    .payment-proof-btn {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .5rem .85rem; border-radius: 10px; font-size: .78rem; font-weight: 700;
        text-decoration: none; border: 1px solid transparent; cursor: pointer;
    }
    .payment-proof-btn.primary {
        background: linear-gradient(135deg, #128c7e, #075e54); color: #fff !important;
    }
    .payment-proof-btn.ghost {
        background: #fff; border-color: #cbd5e1; color: #334155 !important;
    }
    .payment-proof-empty {
        padding: 1rem 1.1rem 1.1rem; font-size: .82rem; color: #92400e;
    }
    .payment-proof-empty i { margin-right: .35rem; }

    .toast-orders {
        position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 1100;
        padding: .75rem 1.15rem; border-radius: 10px; color: #fff; font-size: .875rem;
        opacity: 0; transform: translateY(8px); transition: .25s; pointer-events: none;
    }
    .toast-orders.show { opacity: 1; transform: translateY(0); }
    .toast-orders.success { background: #15803d; }
    .toast-orders.error { background: #dc2626; }

    .modal-loading { text-align: center; padding: 2rem; color: #94a3b8; }
    .spinner {
        width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: #128c7e;
        border-radius: 50%; animation: spin .7s linear infinite; margin: 0 auto .65rem;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Detalle del pedido: lectura rápida y una sola prioridad visible. */
    .modal-panel { max-width: 760px; border-radius: 18px; }
    .modal-header { padding: 1rem 1.15rem; background: #fff; align-items: center; }
    .modal-heading { min-width: 0; }
    .modal-title-row { display: flex; align-items: center; flex-wrap: wrap; gap: .55rem; }
    .modal-header h3 { font-size: 1.08rem; font-weight: 800; }
    .modal-header .sub { margin-top: .25rem; }
    .modal-status {
        display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .55rem;
        border-radius: 999px; font-size: .67rem; font-weight: 800;
        background: #f1f5f9; color: #475569;
    }
    .modal-status.status-pending, .modal-status.status-payment_pending { background: #fff7ed; color: #c2410c; }
    .modal-status.status-confirmed, .modal-status.status-paid { background: #eff6ff; color: #1d4ed8; }
    .modal-status.status-preparing { background: #fff7ed; color: #c2410c; }
    .modal-status.status-ready { background: #dcfce7; color: #15803d; }
    .modal-status.status-completed { background: #e2e8f0; color: #475569; }
    .modal-status.status-cancelled { background: #fee2e2; color: #b91c1c; }
    .modal-close { width: 38px; height: 38px; flex-shrink: 0; transition: background .15s ease, transform .15s ease; }
    .modal-close:hover { background: #cbd5e1; transform: rotate(3deg); }
    .modal-body { background: #f3f6f9; }
    .order-next-step {
        display: flex; align-items: flex-start; gap: .75rem; padding: .8rem .9rem;
        border-radius: 12px; background: #ecfdf5; border: 1px solid #a7f3d0;
        color: #166534;
    }
    .order-next-step-icon {
        width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: #fff; color: #059669;
    }
    .order-next-step strong { display: block; color: #14532d; font-size: .83rem; }
    .order-next-step span { display: block; margin-top: .1rem; font-size: .75rem; }
    .order-next-step.is-waiting { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .order-next-step.is-waiting .order-next-step-icon { color: #d97706; }
    .order-next-step.is-waiting strong { color: #78350f; }
    .order-next-step.is-finished { background: #f8fafc; border-color: #e2e8f0; color: #64748b; }
    .order-next-step.is-finished .order-next-step-icon { color: #64748b; }
    .order-next-step.is-finished strong { color: #334155; }
    .order-section {
        border-left: 4px solid #0f766e !important;
        box-shadow: 0 1px 4px rgba(15,23,42,.04);
    }
    .order-section .order-section-head { background: #fff !important; }
    .order-section-icon {
        width: 30px; height: 30px; border-radius: 8px;
        background: #e7f7f3 !important; color: #0f766e !important;
    }
    .payment-proof-card,
    .payment-proof-card.is-awaiting {
        background: #f8fbfa;
        border-color: #d7e7e3;
        box-shadow: none;
    }
    .payment-proof-icon,
    .payment-proof-card.is-awaiting .payment-proof-icon {
        background: #0f766e;
        box-shadow: none;
    }
    .order-ticket-head { background: #fff; }
    .modal-footer .o-btn.primary { min-width: 122px; justify-content: center; }

    /* Cola operativa: prioriza lo que la cajera necesita leer y hacer. */
    .orders-guide {
        display: flex; align-items: center; gap: .7rem; margin-bottom: .85rem;
        padding: .75rem .9rem; border: 1px solid #bbf7d0; border-radius: 12px;
        background: #f0fdf4; color: #166534; font-size: .8rem;
    }
    .orders-guide i { font-size: 1rem; }
    .orders-guide strong { color: #14532d; }
    .orders-count { color: #64748b; font-size: .78rem; font-weight: 700; white-space: nowrap; }

    .orders-report-tools { position: relative; }
    .orders-report-tools > summary { list-style: none; }
    .orders-report-tools > summary::-webkit-details-marker { display: none; }
    .orders-report-tools[open] .orders-report-panel { display: flex; }
    .orders-report-panel {
        display: none; position: absolute; top: calc(100% + .45rem); right: 0; z-index: 20;
        width: max-content; max-width: min(92vw, 680px); padding: .8rem;
        background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
        box-shadow: 0 14px 35px rgba(15,23,42,.14);
    }

    .orders-card-list { display: flex; flex-direction: column; gap: .65rem; }
    .order-card {
        display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 1rem;
        padding: .95rem 1rem; background: #fff; border: 1px solid #e2e8f0;
        border-left: 5px solid #cbd5e1; border-radius: 14px;
        box-shadow: 0 2px 7px rgba(15,23,42,.04);
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    .order-card:hover { border-color: #a7f3d0; box-shadow: 0 7px 20px rgba(15,23,42,.08); transform: translateY(-1px); }
    .order-card.status-pending, .order-card.status-payment_pending { border-left-color: #f59e0b; }
    .order-card.status-confirmed, .order-card.status-paid { border-left-color: #3b82f6; }
    .order-card.status-preparing { border-left-color: #f97316; }
    .order-card.status-ready { border-left-color: #22c55e; }
    .order-card.status-completed { border-left-color: #94a3b8; opacity: .84; }
    .order-card.status-cancelled { border-left-color: #ef4444; opacity: .75; }
    .order-card-main { min-width: 0; }
    .order-card-top { display: flex; align-items: center; flex-wrap: wrap; gap: .45rem; margin-bottom: .45rem; }
    .order-number { color: #0f172a; font-size: .76rem; font-weight: 800; }
    .order-time { color: #64748b; font-size: .74rem; }
    .order-customer-row { display: flex; align-items: baseline; flex-wrap: wrap; gap: .4rem .8rem; }
    .order-customer { color: #0f172a; font-size: 1rem; font-weight: 800; }
    .order-total { color: #075e54; font-size: 1rem; font-weight: 900; }
    .order-meta { display: flex; flex-wrap: wrap; gap: .35rem 1rem; margin-top: .28rem; color: #64748b; font-size: .75rem; }
    .order-meta span { display: inline-flex; align-items: center; gap: .3rem; }
    .order-meta i { color: #94a3b8; }
    .order-card .order-tags { margin-top: .55rem; }
    .order-card-side { display: flex; flex-direction: column; justify-content: space-between; align-items: flex-end; gap: .75rem; }
    .order-stage { display: flex; flex-direction: column; align-items: flex-end; gap: .2rem; }
    .order-stage-label { color: #64748b; font-size: .62rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .order-card-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .35rem; }
    .order-card-actions .o-btn { min-height: 34px; }

    @media (max-width: 760px) {
        .orders-page { padding-bottom: 1rem; }
        .orders-top h2 { font-size: 1.2rem; }
        .orders-priority { gap: .45rem; }
        .prio-card { padding: .7rem .75rem; }
        .prio-card .val { font-size: 1.05rem; }
        .orders-toolbar { align-items: stretch; }
        .orders-search { flex-basis: 100%; max-width: none; }
        .orders-toolbar > .o-btn { flex: 1; justify-content: center; }
        .orders-count { width: 100%; text-align: center; }
        .orders-report-tools { width: 100%; }
        .orders-report-tools > summary { justify-content: center; width: 100%; }
        .orders-report-panel { position: static; width: 100%; max-width: none; margin-top: .45rem; box-shadow: none; }
        .orders-export-form { margin-left: 0; width: 100%; }
        .orders-export-form .field { flex: 1 1 120px; }
        .orders-export-form .field input, .orders-export-form .field select { width: 100%; }
        .orders-export-form .o-btn { width: 100%; justify-content: center; }
        .order-card { grid-template-columns: 1fr; gap: .7rem; padding: .85rem; }
        .order-card-side { align-items: stretch; }
        .order-stage { align-items: stretch; }
        .order-status-select { width: 100%; }
        .order-card-actions { display: grid; grid-template-columns: 1fr 1fr; }
        .order-card-actions .o-btn { justify-content: center; }
        .order-card-actions .primary { grid-column: 1 / -1; grid-row: 1; }
        .modal-panel { max-height: 96vh; }
        .modal-body { padding: .75rem; }
    }
</style>

<div class="orders-page">
    <div class="orders-top">
        <h2><i class="fas fa-cash-register me-1 text-success"></i> Pedidos del día</h2>
        <p class="lead">Revisa lo solicitado, confirma el pago y avanza cada pedido hasta entregarlo.</p>
    </div>

    <div class="orders-priority">
        <div class="prio-card">
            <div class="lbl">Por revisar</div>
            <div class="val">{{ $stats['pending'] ?? 0 }}</div>
        </div>
        <div class="prio-card">
            <div class="lbl">En proceso</div>
            <div class="val">{{ $stats['confirmed'] ?? 0 }}</div>
        </div>
        <div class="prio-card">
            <div class="lbl">Entregados</div>
            <div class="val">{{ $stats['completed'] ?? 0 }}</div>
        </div>
        <div class="prio-card accent">
            <div class="lbl">Ventas registradas</div>
            <div class="val">${{ number_format($stats['revenue'] ?? 0, 0) }}</div>
        </div>
    </div>

    <div class="orders-guide">
        <i class="fas fa-circle-info"></i>
        <span><strong>Flujo rápido:</strong> abre el pedido, verifica productos y pago, y cambia su etapa cuando avances.</span>
    </div>

    <div class="orders-toolbar">
        <div class="orders-search">
            <i class="fas fa-search"></i>
            <input type="text" id="orders-search" placeholder="Buscar cliente, teléfono o cédula" autocomplete="off">
        </div>
        @if($canBulkCreate)
            <a href="{{ route('pos.create') }}" class="o-btn primary" target="_blank" rel="noopener">
                <i class="fas fa-plus"></i> Tomar pedido en caja
            </a>
            <a href="{{ route('admin.orders.bulk.create') }}" class="o-btn">
                <i class="fas fa-pen"></i> Pedido manual
            </a>
        @endif
        <span class="orders-count">{{ $orders->total() }} pedido(s)</span>

        <details class="orders-report-tools">
            <summary class="o-btn"><i class="fas fa-file-excel"></i> Descargar reporte</summary>
        <form class="orders-export-form orders-report-panel" method="get" action="{{ route('admin.orders.export') }}" id="orders-export-form">
            <div class="field">
                <label for="export-status">Estado</label>
                <select name="status" id="export-status">
                    <option value="">Todos</option>
                    @foreach($statusLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="export-from">Desde</label>
                <input type="date" name="date_from" id="export-from">
            </div>
            <div class="field">
                <label for="export-to">Hasta</label>
                <input type="date" name="date_to" id="export-to">
            </div>
            <input type="hidden" name="q" id="export-q" value="">
            <button type="submit" class="o-btn export">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </button>
        </form>
        </details>
    </div>

    <div id="orders-list">
        @if($orders->isEmpty())
            <div class="orders-empty">
                <i class="fas fa-inbox fa-2x mb-2 opacity-50 d-block"></i>
                <p class="mb-0 fw-semibold">No hay pedidos registrados</p>
            </div>
        @else
            <div class="orders-card-list">
                    @foreach($orders as $order)
                        @php
                            $itemsCount = $order->items->count();
                            $contact = $order->contact;
                            $clientNationalId = $contact?->national_id
                                ?: (($contact?->billing_type === 'cedula' && $contact?->billing_id) ? $contact->billing_id : null);
                            $isRecentOrder = $order->created_at?->gt(now()->subMinutes(10));
                            $fulfillmentServiceType = $order->metadata['service_type'] ?? null;
                            $fulfillmentPickupMode = $order->metadata['pickup_mode'] ?? null;
                            $fulfillmentPending = $fulfillmentPickupMode === 'delivery'
                                && ($order->metadata['delivery_fee_pending_review'] ?? false);
                            $hasTags = $isRecentOrder
                                || ($canViewInternalNotes && ($order->internal_notes_count ?? 0) > 0)
                                || ($canViewFollowup && ($order->feedback_count ?? 0) > 0)
                                || (($order->metadata['awaiting_client_confirmation'] ?? false) && $order->status === 'pending')
                                || $order->hasPaymentProof()
                                || $order->isAwaitingPaymentProof()
                                || $fulfillmentServiceType;
                        @endphp
                        <article class="order-card status-{{ $order->status }}" id="order-row-{{ $order->id }}"
                            data-search="{{ strtolower(trim(($contact->name ?? '') . ' ' . ($contact->phone_number ?? '') . ' ' . ($clientNationalId ?? ''))) }}">
                            <div class="order-card-main">
                                <div class="order-card-top">
                                    <span class="order-number">{{ $order->getOrderNumber() }}</span>
                                    <span class="order-time"><i class="far fa-clock me-1"></i>{{ $order->created_at->format('d/m/Y · H:i') }}</span>
                                    @if($isRecentOrder)
                                        <span class="o-tag new-order"><i class="fas fa-bolt"></i> Recién recibido</span>
                                    @endif
                                </div>
                                <div class="order-customer-row">
                                    <span class="order-customer">{{ $contact->name ?? 'Cliente' }}</span>
                                    <span class="order-total">${{ number_format($order->total, 2) }}</span>
                                </div>
                                <div class="order-meta">
                                    <span><i class="fas fa-bag-shopping"></i>{{ $itemsCount }} {{ $itemsCount === 1 ? 'producto' : 'productos' }}</span>
                                    <span><i class="fab fa-whatsapp"></i>{{ $contact->phone_number ?? 'Sin teléfono' }}</span>
                                    @if($clientNationalId)
                                        <span><i class="fas fa-id-card"></i>{{ $clientNationalId }}</span>
                                    @endif
                                </div>
                                <div class="order-tags">
                                    @if($fulfillmentServiceType === 'servir')
                                        <span class="o-tag fulfil-servir"><i class="fas fa-utensils"></i> Para servir</span>
                                    @elseif($fulfillmentPickupMode === 'retiro')
                                        <span class="o-tag fulfil-retiro"><i class="fas fa-store"></i> Retiro</span>
                                    @elseif($fulfillmentPickupMode === 'delivery')
                                        <span class="o-tag fulfil-delivery"><i class="fas fa-motorcycle"></i> Delivery</span>
                                    @endif
                                    @if($fulfillmentPending)
                                        <span class="o-tag fulfil-pending"><i class="fas fa-dollar-sign"></i> Confirmar envío</span>
                                    @endif
                                    @if($canViewInternalNotes && ($order->internal_notes_count ?? 0) > 0)
                                        <span class="o-tag notes"><i class="fas fa-sticky-note"></i>{{ $order->internal_notes_count }}</span>
                                    @endif
                                    @if($canViewFollowup && ($order->feedback_count ?? 0) > 0)
                                        <span class="o-tag feedback"><i class="fas fa-comment"></i>{{ $order->feedback_count }}</span>
                                    @endif
                                    @if(($order->metadata['awaiting_client_confirmation'] ?? false) && $order->status === 'pending')
                                        <span class="o-tag confirm"><i class="fas fa-clock"></i> Espera cliente</span>
                                    @endif
                                    @if($order->hasPaymentProof())
                                        <span class="o-tag proof-ok"><i class="fas fa-receipt"></i> Comprobante</span>
                                    @elseif($order->isAwaitingPaymentProof())
                                        <span class="o-tag proof-wait"><i class="fas fa-hourglass-half"></i> Sin comprobante</span>
                                    @endif
                                    @unless($hasTags)
                                        <span class="o-tag empty"><i class="fas fa-circle-check"></i> Sin novedades</span>
                                    @endunless
                                </div>
                            </div>
                            <div class="order-card-side">
                                <div class="order-stage">
                                <span class="order-stage-label">Etapa del pedido</span>
                                @if($canUpdate)
                                    <select class="order-status-select status-{{ $order->status }}"
                                        id="status-select-{{ $order->id }}"
                                        data-current-status="{{ $order->status }}"
                                        onchange="changeOrderStatus({{ $order->id }}, this)"
                                        aria-label="Estado">
                                        @foreach($statusOptions as $status)
                                            <option value="{{ $status }}" @selected($order->status === $status)>{{ $statusLabels[$status] }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="o-tag">{{ $statusLabels[$order->status] ?? $order->status }}</span>
                                @endif
                                </div>
                                <div class="order-card-actions">
                                    @perm('chats.open')
                                        <a href="{{ route('admin.chat', $order->contact_id) }}" class="o-btn" title="Conversar con el cliente"><i class="fas fa-comments"></i> Chat</a>
                                    @endperm
                                    <button type="button" class="o-btn primary" onclick="showOrderDetails({{ $order->id }})">
                                        <i class="fas fa-eye"></i> Ver pedido
                                    </button>
                                    <a href="{{ route('admin.orders.pdf', $order->id) }}" class="o-btn" title="Descargar PDF" target="_blank" rel="noopener">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
            </div>
        @endif
    </div>

    @if($orders->hasPages())
        <div class="mt-3">{{ $orders->links() }}</div>
    @endif
</div>

<div class="modal-overlay" id="orderModal" role="dialog" aria-modal="true">
    <div class="modal-panel">
        <div class="modal-header">
            <div class="modal-heading">
                <div class="modal-title-row">
                    <h3 id="orderModalTitle">Pedido</h3>
                    <span class="modal-status" id="orderModalStatus"></span>
                </div>
                <p class="sub mb-0" id="orderModalSubtitle"></p>
            </div>
            <button type="button" class="modal-close" onclick="closeOrderModal()" aria-label="Cerrar"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="orderDetails">
            <div class="modal-loading"><div class="spinner"></div>Cargando...</div>
        </div>
        <div class="modal-footer" id="orderModalFooter">
            <button type="button" class="o-btn" onclick="closeOrderModal()">Cerrar</button>
        </div>
    </div>
</div>

<div class="toast-orders" id="orders-toast"></div>

<div class="orders-refresh-banner" id="orders-refresh-banner" onclick="window.location.reload()">
    <i class="fas fa-bolt"></i>
    <span id="orders-refresh-banner-text">Nuevo pedido</span>
    <i class="fas fa-arrow-rotate-right"></i>
</div>

<script>
const STATUS_LABELS = @json($statusLabels);
const INVOICE_LABELS = @json($invoiceLabels);
const SECTION_HINTS = @json($sectionHints);
const FIELD_HINTS = @json($fieldHints);
const CHAT_URL_TEMPLATE = @json(url('/admin/chats/__ID__'));
const CAN_UPDATE = @json($canUpdate);
const CAN_VIEW_BILLING = @json($canViewBilling);
const CAN_VIEW_INTERNAL_NOTES = @json($canViewInternalNotes);
const CAN_VIEW_FOLLOWUP = @json($canViewFollowup);
const CSRF = @json(csrf_token());
const FULFILLMENT_COSTS_URL_TEMPLATE = @json(url('/admin/orders/__ID__/fulfillment-costs'));
let currentOrderId = null;
let currentOrderData = null;

function infoBtn(hint, ariaLabel, btnClass = 'section-info-btn') {
    if (!hint) return '';
    return `<button type="button" class="${btnClass}" title="${esc(hint)}" aria-label="${esc(ariaLabel || hint)}"><i class="fas fa-info-circle"></i></button>`;
}

function sectionHead(title, icon, theme, hintKey) {
    return `<div class="order-section-head">
        <div class="order-section-head-main">
            <span class="order-section-icon"><i class="${icon}"></i></span>
            <span>${esc(title)}</span>
        </div>
    </div>`;
}

function fieldLabel(text, hintKey) {
    return `<span class="field-label-row">${esc(text)} ${infoBtn(FIELD_HINTS[hintKey] || '', text, 'field-info-btn')}</span>`;
}

function productTh(label, hintKey) {
    return `<span class="th-label-row">${esc(label)} ${infoBtn(FIELD_HINTS[hintKey] || '', label, 'field-info-btn')}</span>`;
}

function showToast(msg, type = 'success') {
    const t = document.getElementById('orders-toast');
    t.textContent = msg;
    t.className = 'toast-orders show ' + type;
    clearTimeout(t._t);
    t._t = setTimeout(() => t.classList.remove('show'), 2800);
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleString('es-EC', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
}

function renderOrderNextStep(order) {
    const steps = {
        pending: ['Revisa este pedido', 'Confirma productos, forma de entrega y total antes de aceptarlo.', 'fa-clipboard-check', 'is-waiting'],
        payment_pending: ['Espera o revisa el pago', 'Cuando llegue el comprobante, comprueba el valor antes de confirmar.', 'fa-hourglass-half', 'is-waiting'],
        paid: ['Pago recibido', 'El pedido ya puede continuar a preparación.', 'fa-circle-check', ''],
        confirmed: ['Pedido aceptado', 'Verifica que cocina tenga claro lo solicitado.', 'fa-utensils', ''],
        preparing: ['Pedido en cocina', 'Cuando termine la preparación, márcalo como listo para entregar.', 'fa-fire', ''],
        ready: ['Listo para entregar', 'Entrégalo al cliente o coordina el despacho.', 'fa-bag-shopping', ''],
        completed: ['Pedido finalizado', 'Este pedido ya fue entregado.', 'fa-circle-check', 'is-finished'],
        cancelled: ['Pedido cancelado', 'No requiere más acciones.', 'fa-ban', 'is-finished'],
    };
    const step = steps[order.status] || ['Revisa el pedido', 'Comprueba la información antes de continuar.', 'fa-circle-info', ''];

    return `<div class="order-next-step ${step[3]}">
        <span class="order-next-step-icon"><i class="fas ${step[2]}"></i></span>
        <div><strong>${step[0]}</strong><span>${step[1]}</span></div>
    </div>`;
}

function openModal() {
    document.getElementById('orderModal').classList.add('is-open');
    document.body.style.overflow = 'hidden';
}

function closeOrderModal() {
    document.getElementById('orderModal').classList.remove('is-open');
    document.body.style.overflow = '';
    currentOrderId = null;
}

function renderOrderModal(order) {
    currentOrderId = order.id;
    currentOrderData = order;
    document.getElementById('orderModalTitle').textContent = 'Pedido ' + (order.order_number || ('#' + order.id));
    document.getElementById('orderModalSubtitle').textContent =
        (order.contact?.name ?? 'Cliente') + ' · ' + formatDate(order.created_at) + ' · Total $' + parseFloat(order.total).toFixed(2);
    const modalStatus = document.getElementById('orderModalStatus');
    modalStatus.textContent = STATUS_LABELS[order.status] || order.status;
    modalStatus.className = 'modal-status status-' + order.status;

    const b = order.billing || {};
    let html = '<div class="order-command-center">';
    html += renderOrderNextStep(order);

    // Resumen tipo ticket: el operador identifica el pedido y su total sin
    // recorrer una tabla administrativa ni abrir campos que no necesita.
    html += `<section class="order-ticket-card"><div class="order-ticket-head"><strong><i class="fas fa-bag-shopping me-1"></i>Lo que pidió el cliente</strong><span>${order.items?.length || 0} producto(s)</span></div>`;
    if (order.items?.length) {
        order.items.forEach(item => {
            const sub = (parseFloat(item.price) * parseInt(item.quantity)).toFixed(2);
            html += `<div class="order-compact-line"><div><span class="order-compact-line-name">${esc(item.name)}</span><span class="order-compact-line-meta">${item.quantity} × $${parseFloat(item.price).toFixed(2)}</span>${item.line_note ? `<span class="order-compact-line-note">${esc(item.line_note)}</span>` : ''}</div><span class="order-compact-line-total">$${sub}</span></div>`;
        });
    } else {
        html += `<div class="order-compact-line"><span class="text-muted small">Sin líneas de producto registradas.</span></div>`;
    }
    html += `<div class="order-ticket-total"><span>Total</span><strong>$${parseFloat(order.total).toFixed(2)}</strong></div></section>`;

    html += renderFulfillmentSection(order);
    html += renderPaymentProofSection(order);

    if (CAN_UPDATE && ['pending', 'payment_pending'].includes(order.status)) {
        html += `<section class="order-quick-actions"><div class="order-quick-actions-row"><div class="order-quick-actions-copy"><strong>${order.awaiting_client_confirmation ? 'Esperando confirmación del cliente' : 'Confirmar por WhatsApp'}</strong><span>${order.awaiting_client_confirmation ? 'El ticket ya fue enviado al cliente.' : 'Envía el ticket digital con las acciones de pedido.'}</span></div><button type="button" class="o-btn primary" onclick="sendOrderConfirmation()"><i class="fab fa-whatsapp me-1"></i>${order.awaiting_client_confirmation ? 'Reenviar ticket' : 'Enviar ticket'}</button></div><details class="order-disclosure mt-3"><summary>Agregar mensaje opcional</summary><div class="order-disclosure-body"><textarea class="form-control form-control-sm" id="confirmationMessage" rows="2" placeholder="Ej.: Tu pedido estará listo en 20 minutos."></textarea></div></details></section>`;
    }

    if (CAN_VIEW_BILLING && (order.requires_invoice || CAN_UPDATE)) {
        html += `<details class="order-disclosure" ${order.requires_invoice ? 'open' : ''}><summary><i class="fas fa-file-invoice me-1"></i>Factura${order.requires_invoice ? ' · solicitada por el cliente' : ' · solo si la solicitan'}</summary><div class="order-disclosure-body">`;
        if (CAN_UPDATE) {
            html += `<form id="invoice-form" onsubmit="saveOrderInvoice(event)">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="requires_invoice" name="requires_invoice" ${order.requires_invoice ? 'checked' : ''}>
                    <label class="form-check-label" for="requires_invoice">${fieldLabel('Cliente solicita factura', 'requires_invoice')}</label>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <label class="form-label small d-block">${fieldLabel('Estado factura', 'invoice_status')}</label>
                        <select class="form-select form-select-sm" name="invoice_status" id="invoice_status">
                            ${Object.entries(INVOICE_LABELS).map(([k,v]) => `<option value="${k}" ${order.invoice_status===k?'selected':''}>${esc(v)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small d-block">${fieldLabel('Tipo', 'billing_type')}</label>
                        <select class="form-select form-select-sm" name="billing_type">
                            <option value="cedula" ${b.billing_type==='cedula'?'selected':''}>Cédula</option>
                            <option value="ruc" ${b.billing_type==='ruc'?'selected':''}>RUC</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small d-block">${fieldLabel(b.billing_type==='ruc'?'RUC':'Cédula', 'billing_id')}</label>
                        <input type="text" class="form-control form-control-sm" name="billing_id" value="${esc(b.billing_id || '')}" placeholder="Número">
                    </div>
                    <div class="col-12">
                        <label class="form-label small d-block">${fieldLabel('Nombre / Razón social', 'billing_legal_name')}</label>
                        <input type="text" class="form-control form-control-sm" name="billing_legal_name" value="${esc(b.billing_legal_name || '')}">
                    </div>
                    <div class="col-12">
                        <label class="form-label small d-block">${fieldLabel('Dirección fiscal', 'address')}</label>
                        <input type="text" class="form-control form-control-sm" name="address" value="${esc(b.address || '')}">
                    </div>
                </div>
                <div class="order-callout sync mb-2"><i class="fas fa-sync-alt me-1"></i> Al guardar, los datos fiscales se copian al perfil del cliente.</div>
                <button type="submit" class="o-btn primary btn-sm"><i class="fas fa-save me-1"></i>Guardar facturación</button>
            </form>`;
        } else if (order.requires_invoice) {
            html += `<p class="mb-1"><strong>${esc(order.invoice_status_label)}</strong></p>
                <p class="small text-muted mb-0">${esc(b.billing_type?.toUpperCase())} ${esc(b.billing_id)} · ${esc(b.billing_legal_name)}</p>`;
        }
        if (order.agent_checklist?.length) {
            html += `<ul class="checklist mt-3 pt-2 border-top">`;
            order.agent_checklist.forEach(step => {
                html += `<li>
                    <span class="chk ${step.done?'done':'pending'}"><i class="fas fa-${step.done?'check':'minus'}"></i></span>
                    <div><strong>${esc(step.label)}</strong><br><span class="text-muted">${esc(step.hint)}</span></div>
                </li>`;
            });
            html += `</ul>`;
        }
        html += `</div></details>`;
    }

    if (CAN_VIEW_INTERNAL_NOTES) {
        html += `<details class="order-disclosure"><summary><i class="fas fa-sticky-note me-1"></i>Notas para el equipo</summary><div class="order-disclosure-body">`;
        html += `<div id="internal-notes-list">${renderNotesList(order.notes?.filter(n => n.type === 'internal') || [])}</div>`;
        if (CAN_UPDATE) {
            html += `<form class="mt-3 pt-2 border-top" onsubmit="addOrderNote(event, 'internal')">
                <textarea class="form-control form-control-sm mb-2" name="body" rows="2" placeholder="Nota para el equipo (no la ve el cliente)..." required></textarea>
                <button type="submit" class="o-btn btn-sm"><i class="fas fa-plus me-1"></i>Agregar observación</button>
            </form>`;
        }
        html += `</div></details>`;
    }

    if (CAN_VIEW_FOLLOWUP) {
        html += `<details class="order-disclosure"><summary><i class="fas fa-comment-dots me-1"></i>Conversaciones y seguimiento</summary><div class="order-disclosure-body">`;
        html += `<div id="feedback-notes-list">${renderNotesList(order.notes?.filter(n => n.type === 'feedback') || [])}</div>`;
        if (CAN_UPDATE) {
            html += `<form class="mt-3 pt-2 border-top" onsubmit="addOrderNote(event, 'feedback')">
                <textarea class="form-control form-control-sm mb-2" name="body" rows="2" placeholder="Ej: Envié factura PDF por WhatsApp, cliente confirmó recepción..." required></textarea>
                <button type="submit" class="o-btn btn-sm"><i class="fas fa-plus me-1"></i>Registrar feedback</button>
            </form>`;
        }
        html += `</div></details>`;
    }

    html += '</div>';

    document.getElementById('orderDetails').innerHTML = html;

    const footer = document.getElementById('orderModalFooter');
    footer.innerHTML = `<button type="button" class="o-btn" onclick="closeOrderModal()">Cerrar</button>`;
    footer.innerHTML += `<a href="/admin/orders/${order.id}/pdf" class="o-btn" target="_blank" rel="noopener"><i class="fas fa-file-pdf me-1"></i>Descargar PDF</a>`;
    if (order.contact?.id) {
        footer.innerHTML += `<a href="${CHAT_URL_TEMPLATE.replace('__ID__', order.contact.id)}" class="o-btn primary"><i class="fas fa-comments me-1"></i>Abrir chat</a>`;
    }
}

function deliveryDispatchText(order, f, driver) {
    const lines = [
        '🛵 *Datos para el delivery*',
        '',
        `Pedido: *${order.order_number}*`,
        `Entregar a: ${f.recipient_name || order.contact?.name || 'Cliente'}`,
        `Dirección: ${f.address || 'Sin dirección registrada'}`,
        `Pago: ${f.payment_dispatch_label || 'No especificado'}`,
    ];
    return lines.join('\n');
}

function openOrderDispatchModal(orderId) {
    const order = currentOrderData;
    const f = order?.fulfillment;
    if (!order || !f) return;
    openDriverDispatchModal(orderId, () => deliveryDispatchText(order, f), () => {
        showToast('Cliente avisado y datos listos para el repartidor.');
        showOrderDetails(orderId);
    });
}

/** Reabre WhatsApp con el último repartidor despachado, sin volver a avisarle al cliente. */
function reopenOrderDispatch(orderId) {
    const order = currentOrderData;
    const f = order?.fulfillment;
    const driver = f?.last_dispatch_driver;
    if (!order || !f || !driver) return;
    window.reopenDriverWhatsapp(driver.phone_number, deliveryDispatchText(order, f));
}

function renderFulfillmentSection(order) {
    const f = order.fulfillment;
    if (!f) return '';

    let html = `<section class="order-section" data-theme="fulfillment">${sectionHead('Forma de entrega', 'fas fa-store', 'fulfillment', 'fulfillment')}<div class="order-section-body">`;

    html += `<div class="fulfillment-grid">`;
    if (f.branch) {
        html += `<div><span class="fulfillment-lbl">Sucursal</span><span class="fulfillment-val">${esc(f.branch)}</span></div>`;
    }
    html += `<div><span class="fulfillment-lbl">Tipo de pedido</span><span class="fulfillment-val">${esc(f.service_type_label)}${f.pickup_mode_label ? ' · ' + esc(f.pickup_mode_label) : ''}</span></div>`;
    if (f.pickup_mode === 'delivery' && f.address) {
        html += `<div class="fulfillment-full"><span class="fulfillment-lbl">Dirección</span><span class="fulfillment-val">${esc(f.address)}</span></div>`;
    }
    if (f.pickup_mode === 'delivery' && f.recipient_name) {
        html += `<div><span class="fulfillment-lbl">Recibe</span><span class="fulfillment-val">${esc(f.recipient_name)}</span></div>`;
    }
    html += `</div>`;

    const showsDelivery = f.pickup_mode === 'delivery';
    const showsPickup = f.service_type === 'llevar';
    const isFinalStatus = order.status === 'cancelled' || order.status === 'completed';

    if (showsDelivery || showsPickup) {
        const deliveryFeeVal = f.delivery_fee != null ? parseFloat(f.delivery_fee).toFixed(2) : '';
        const pickupFeeVal = f.pickup_fee != null ? parseFloat(f.pickup_fee).toFixed(2) : '';
        const costsConfirmed = (!showsDelivery || (!f.delivery_fee_pending_review && deliveryFeeVal !== ''))
            && (!showsPickup || pickupFeeVal !== '');

        html += `<div class="order-step${costsConfirmed ? ' is-done' : ''}">
            <div class="order-step-head"><span class="order-step-num">${costsConfirmed ? '<i class="fas fa-check"></i>' : '1'}</span><span class="order-step-title">Confirmar costo${showsDelivery && showsPickup ? 's de envío y para llevar' : (showsDelivery ? ' de envío' : ' para llevar')}</span></div>`;

        if (showsDelivery && f.delivery_fee_pending_review && !isFinalStatus) {
            html += `<div class="order-callout warning mt-3"><i class="fas fa-triangle-exclamation me-1"></i>Costo de envío referencial (mínimo de la sucursal), aún no sumado al total. Confírmalo (junto con el costo para llevar si aplica) antes de despachar: se suman al total y el cliente recibe un solo mensaje con el total final.</div>`;
        } else if (showsDelivery && deliveryFeeVal !== '') {
            html += `<div class="order-callout info mt-3"><i class="fas fa-check-circle me-1"></i>Costo de envío confirmado y ya incluido en el total.</div>`;
        }
        if (showsPickup && pickupFeeVal !== '') {
            html += `<div class="order-callout info mt-3"><i class="fas fa-check-circle me-1"></i>Costo para llevar (tarrinas/empaque) confirmado y ya incluido en el total.</div>`;
        }
        if (isFinalStatus) {
            html += `<div class="order-callout mt-3"><i class="fas fa-lock me-1"></i>Este pedido está ${order.status === 'cancelled' ? 'cancelado' : 'entregado'}: ya no se le pueden mandar cambios de costo.</div>`;
        }

        if (CAN_UPDATE && !isFinalStatus) {
            html += `<form class="mt-2" onsubmit="sendFulfillmentCosts(event)">
                <div class="row g-2 align-items-end">
                    ${showsDelivery ? `<div class="col-6 col-md-4">
                        <label class="form-label small d-block">Costo de envío ($)</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="delivery_fee" value="${deliveryFeeVal}">
                    </div>` : ''}
                    ${showsPickup ? `<div class="col-6 col-md-4">
                        <label class="form-label small d-block">Costo para llevar ($, tarrinas/empaque)</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="pickup_fee" value="${pickupFeeVal || '0.00'}">
                    </div>` : ''}
                    <div class="col-12 col-md-4">
                        <button type="submit" class="o-btn primary btn-sm w-100"><i class="fab fa-whatsapp me-1"></i>Confirmar y avisar al cliente</button>
                    </div>
                </div>
                <p class="small text-muted mb-0 mt-2">Un solo mensaje al cliente con ambos costos y el total final — no se manda uno por cada campo.</p>
            </form>`;
        } else {
            const parts = [];
            if (showsDelivery && deliveryFeeVal !== '') parts.push(`Envío: $${deliveryFeeVal}`);
            if (showsPickup) parts.push(`Para llevar: $${pickupFeeVal || '0.00'}`);
            if (parts.length) html += `<p class="small text-muted mb-0 mt-2">${parts.join(' · ')}</p>`;
        }

        html += `</div>`;
    }

    if (showsDelivery) {
        const lastDriver = f.last_dispatch_driver;
        html += `<div class="order-step">
            <div class="order-step-head"><span class="order-step-num">2</span><span class="order-step-title">Enviar a repartidor</span></div>
            <p class="small text-muted mb-2">Le avisamos al cliente que su pedido va en camino (con el contacto del repartidor) y te abrimos WhatsApp con los datos ya listos para mandárselos a él.</p>
            ${isFinalStatus
                ? `<p class="small text-muted mb-0"><i class="fas fa-lock me-1"></i>Este pedido está ${order.status === 'cancelled' ? 'cancelado' : 'entregado'}.</p>`
                : `<button type="button" class="o-btn primary btn-sm" onclick="openOrderDispatchModal(${order.id})"><i class="fab fa-whatsapp me-1"></i>Enviar a repartidor</button>`}
            ${lastDriver ? `<button type="button" class="o-btn btn-sm ms-2" onclick="reopenOrderDispatch(${order.id})" title="Vuelve a abrir WhatsApp con ${esc(lastDriver.name)}, sin volver a avisarle al cliente"><i class="fas fa-rotate-right me-1"></i>Reenviar a ${esc(lastDriver.name)}</button>` : ''}
        </div>`;
    }

    html += `</div></section>`;
    return html;
}

function sendFulfillmentCosts(e) {
    e.preventDefault();
    if (!currentOrderId) return;
    const form = new FormData(e.target);
    const payload = {};

    if (form.has('delivery_fee')) {
        const raw = form.get('delivery_fee');
        const fee = raw === '' ? null : parseFloat(raw);
        if (fee !== null && (isNaN(fee) || fee < 0)) return;
        payload.delivery_fee = fee;
    }
    if (form.has('pickup_fee')) {
        const raw = form.get('pickup_fee');
        const fee = raw === '' ? 0 : parseFloat(raw);
        if (isNaN(fee) || fee < 0) return;
        payload.pickup_fee = fee;
    }

    fetch(FULFILLMENT_COSTS_URL_TEMPLATE.replace('__ID__', currentOrderId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(data => {
        showToast(data.message || (data.success ? 'Costo guardado' : 'Error'), data.success ? 'success' : 'error');
        if (data.success && data.order) renderOrderModal(data.order);
    })
    .catch(() => showToast('Error al enviar el costo', 'error'));
}

function renderPaymentProofSection(order) {
    const payment = order.payment;
    if (!payment?.visible) return '';

    const state = payment.state;
    const isAwaiting = state === 'awaiting';
    const isSubmitted = state === 'submitted';
    const cardClass = isAwaiting ? 'payment-proof-card is-awaiting' : 'payment-proof-card';
    const badgeClass = isSubmitted ? 'ok' : 'wait';
    const badgeText = isSubmitted ? 'Recibido' : (isAwaiting ? 'Pendiente' : 'Sin envío');

    let html = `<div class="order-section" data-theme="payment">${sectionHead('Pago del pedido', 'fas fa-credit-card', 'payment', 'payment_proof')}<div class="order-section-body flush">`;
    html += `<div class="${cardClass}">`;
    html += `<div class="payment-proof-top">
        <div class="payment-proof-title-wrap">
            <div class="payment-proof-icon"><i class="fas fa-${isSubmitted ? 'file-circle-check' : 'file-invoice-dollar'}"></i></div>
            <div>
                <h4 class="payment-proof-title">${isSubmitted ? 'Comprobante recibido' : 'Esperando comprobante'}</h4>
                <p class="payment-proof-sub">${esc(payment.method_label)} · ${esc(payment.status_label)}</p>
            </div>
        </div>
        <span class="payment-proof-badge ${badgeClass}">${badgeText}</span>
    </div>`;

    html += `<div class="payment-proof-meta">`;
    html += `<span><i class="fas fa-credit-card"></i> Método: <strong>${esc(payment.method_label)}</strong></span>`;
    if (isSubmitted && payment.proof?.received_at) {
        html += `<span><i class="fas fa-clock"></i> Recibido: <strong>${formatDate(payment.proof.received_at)}</strong></span>`;
    }
    html += `</div>`;

    if (isSubmitted && payment.proof?.media_url) {
        const url = esc(payment.proof.media_url);
        const filename = esc(payment.proof.filename || 'comprobante');
        if (payment.proof.type === 'image') {
            html += `<div class="payment-proof-preview">
                <img src="${url}" alt="Comprobante de pago" onclick="window.open('${url}', '_blank')" loading="lazy">
            </div>`;
        } else {
            const ext = filename.split('.').pop().toUpperCase().slice(0, 4) || 'PDF';
            html += `<div class="payment-proof-preview">
                <div class="payment-proof-doc">
                    <div class="payment-proof-doc-icon">${ext}</div>
                    <div>
                        <div class="payment-proof-doc-name">${filename}</div>
                        <div class="payment-proof-doc-hint">Documento enviado por el cliente desde WhatsApp</div>
                    </div>
                </div>
            </div>`;
        }
        html += `<div class="payment-proof-actions">
            <a href="${url}" target="_blank" rel="noopener" class="payment-proof-btn primary"><i class="fas fa-expand"></i> Ver en tamaño completo</a>
            <a href="${url}" download class="payment-proof-btn ghost"><i class="fas fa-download"></i> Descargar</a>
        </div>`;

        const canConfirmPayment = CAN_UPDATE && !['paid', 'completed', 'cancelled'].includes(order.status);
        if (canConfirmPayment) {
            html += `<div class="payment-proof-actions">
                <button type="button" class="payment-proof-btn primary" onclick="confirmOrderPayment(${order.id})">
                    <i class="fas fa-check-circle"></i> Confirmar pago recibido
                </button>
            </div>`;
        }
    } else if (isAwaiting) {
        html += `<div class="payment-proof-empty">
            <i class="fas fa-info-circle"></i>
            El cliente aún no ha enviado foto o PDF del comprobante. Aparecerá aquí automáticamente cuando lo mande por WhatsApp.
        </div>`;
    }

    html += `</div></div></div>`;
    return html;
}

function renderNotesList(notes) {
    if (!notes.length) return '<p class="text-muted small mb-0">Sin registros aún.</p>';
    return notes.map(n => `<article class="note-item">
        <div class="note-meta"><strong>${esc(n.author)}</strong> · ${formatDate(n.created_at)}</div>
        <div>${esc(n.body).replace(/\n/g, '<br>')}</div>
    </article>`).join('');
}

function showOrderDetails(orderId) {
    window.WaOrderAlerts?.dismiss(orderId);
    openModal();
    document.getElementById('orderDetails').innerHTML = '<div class="modal-loading"><div class="spinner"></div>Cargando...</div>';
    fetch(`/admin/orders/${orderId}/details`)
        .then(r => r.json())
        .then(renderOrderModal)
        .catch(() => {
            document.getElementById('orderDetails').innerHTML = '<p class="text-danger">No se pudo cargar el pedido.</p>';
        });
}

function sendOrderConfirmation() {
    if (!currentOrderId) return;
    const message = document.getElementById('confirmationMessage')?.value?.trim() || null;
    fetch(`/admin/orders/${currentOrderId}/send-confirmation`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ message }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Confirmación enviada');
            if (data.order) renderOrderModal(data.order);
        } else {
            showToast(data.message || 'No se pudo enviar', 'error');
        }
    })
    .catch(() => showToast('Error al enviar confirmación', 'error'));
}

function saveOrderInvoice(e) {
    e.preventDefault();
    if (!currentOrderId) return;
    const fd = new FormData(e.target);
    const payload = {
        requires_invoice: fd.get('requires_invoice') === 'on',
        invoice_status: fd.get('invoice_status'),
        billing_type: fd.get('billing_type'),
        billing_id: fd.get('billing_id'),
        billing_legal_name: fd.get('billing_legal_name'),
        address: fd.get('address'),
        sync_profile: true,
    };
    fetch(`/admin/orders/${currentOrderId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Facturación guardada y perfil actualizado');
            renderOrderModal(data.order);
        } else showToast('Error al guardar', 'error');
    })
    .catch(() => showToast('Error al guardar', 'error'));
}

function addOrderNote(e, type) {
    e.preventDefault();
    if (!currentOrderId) return;
    const body = e.target.body.value.trim();
    if (!body) return;
    fetch(`/admin/orders/${currentOrderId}/notes`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ type, body }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(type === 'feedback' ? 'Feedback registrado' : 'Observación agregada');
            showOrderDetails(currentOrderId);
        } else showToast('Error', 'error');
    })
    .catch(() => showToast('Error', 'error'));
}

function changeOrderStatus(orderId, selectEl) {
    const newStatus = selectEl.value;
    const prev = selectEl.getAttribute('data-current-status');
    fetch(`/admin/orders/${orderId}/status`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ status: newStatus }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            selectEl.className = 'order-status-select status-' + newStatus;
            selectEl.setAttribute('data-current-status', newStatus);
            const card = document.getElementById('order-row-' + orderId);
            if (card) {
                Array.from(card.classList)
                    .filter(className => className.startsWith('status-'))
                    .forEach(className => card.classList.remove(className));
                card.classList.add('status-' + newStatus);
            }
            showToast('Etapa del pedido actualizada');
        } else { selectEl.value = prev; showToast('Error', 'error'); }
    })
    .catch(() => { selectEl.value = prev; showToast('Error', 'error'); });
}

/**
 * Botón "Confirmar pago recibido" del comprobante: pasa el pedido a
 * "Pagado" (misma transición que el selector de estado) y refresca el
 * modal. OrderLifecycleService::transition() ya se encarga de avisarle
 * al cliente por WhatsApp que su pago fue confirmado.
 */
function confirmOrderPayment(orderId) {
    if (!confirm('¿Confirmar que el pago de este pedido fue recibido? Se le avisará al cliente por WhatsApp.')) return;

    fetch(`/admin/orders/${orderId}/status`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ status: 'paid' }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Pago confirmado. Se avisó al cliente por WhatsApp.');
            showOrderDetails(orderId);
        } else {
            showToast(data.message || 'No se pudo confirmar el pago', 'error');
        }
    })
    .catch(() => showToast('Error al confirmar el pago', 'error'));
}

document.getElementById('orders-search')?.addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('[id^="order-row-"]').forEach(row => {
        row.style.display = !q || (row.getAttribute('data-search') || '').includes(q) ? '' : 'none';
    });
});

document.getElementById('orders-export-form')?.addEventListener('submit', function() {
    const q = document.getElementById('orders-search')?.value.trim() || '';
    const hidden = document.getElementById('export-q');
    if (hidden) hidden.value = q;
});

document.getElementById('orderModal')?.addEventListener('click', e => {
    if (e.target.id === 'orderModal') closeOrderModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeOrderModal(); });

// --- Aviso de pedidos nuevos ---
// El sondeo en sí vive en admin-order-alerts.js (timbre global del panel,
// funciona en cualquier pantalla). Aquí solo reaccionamos a su evento para
// mostrar el banner "Actualizar" propio de esta pantalla, sin duplicar el
// sonido ni la notificación de escritorio (esas ya las dispara el timbre).
(function () {
    let pendingCount = 0;

    function showRefreshBanner() {
        const banner = document.getElementById('orders-refresh-banner');
        const text = document.getElementById('orders-refresh-banner-text');
        if (!banner || !text) return;
        text.textContent = pendingCount === 1 ? '1 pedido nuevo · Actualizar' : (pendingCount + ' pedidos nuevos · Actualizar');
        banner.classList.add('is-visible');
    }

    window.addEventListener('wa-orders:new', function (e) {
        pendingCount += 1;
        showToast('🆕 Nuevo pedido ' + (e.detail?.order?.order_number || '') + ' de ' + (e.detail?.order?.contact_name || 'Cliente'));
        showRefreshBanner();

        // Auto-actualiza solo si nadie está a mitad de una acción
        // (modal de detalle abierto o un select de estado enfocado).
        const modalOpen = document.getElementById('orderModal')?.classList.contains('is-open');
        const selectFocused = document.activeElement?.classList?.contains('order-status-select');
        if (!modalOpen && !selectFocused) {
            setTimeout(() => window.location.reload(), 2500);
        }
    });

    // Si llegamos aquí desde el timbre de notificaciones (?open_order=123),
    // abrimos ese pedido directamente y limpiamos la URL.
    const openOrderId = new URLSearchParams(window.location.search).get('open_order');
    if (openOrderId) {
        showOrderDetails(parseInt(openOrderId, 10));
        const url = new URL(window.location.href);
        url.searchParams.delete('open_order');
        window.history.replaceState({}, '', url);
    }
})();
</script>

@include('admin.partials.driver-dispatch-modal')
@endsection
