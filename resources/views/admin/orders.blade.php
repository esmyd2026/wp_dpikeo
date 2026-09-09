@extends('admin.layouts.app')

@section('header', 'Pedidos')

@section('content')
@php
    use App\Services\OrderAdminService;
    use App\Services\OrderLifecycleService;

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
    $segmentIcons = [
        'all' => 'fa-layer-group', 'new' => 'fa-bell', 'payment' => 'fa-credit-card',
        'accepted' => 'fa-circle-check', 'preparing' => 'fa-fire-burner', 'ready' => 'fa-bag-shopping',
        'closed' => 'fa-box-archive',
    ];
    $statusTransitions = [];
    foreach ($statusOptions as $statusOption) {
        $statusTransitions[$statusOption] = OrderLifecycleService::allowedTransitionsFor($statusOption);
    }
    $statusMeta = [
        'pending' => ['icon' => 'fa-inbox', 'description' => 'Pedido recién recibido; todavía debe revisarse.'],
        'confirmed' => ['icon' => 'fa-circle-check', 'description' => 'El pedido fue revisado y aceptado.'],
        'payment_pending' => ['icon' => 'fa-clock', 'description' => 'Falta recibir o validar el pago del cliente.'],
        'paid' => ['icon' => 'fa-receipt', 'description' => 'El pago fue confirmado y el pedido puede continuar.'],
        'preparing' => ['icon' => 'fa-utensils', 'description' => 'El pedido se encuentra en preparación.'],
        'ready' => ['icon' => 'fa-bag-shopping', 'description' => 'El pedido está listo para servir, retirar o despachar.'],
        'completed' => ['icon' => 'fa-check-double', 'description' => 'El pedido ya fue entregado al cliente.'],
        'cancelled' => ['icon' => 'fa-ban', 'description' => 'El pedido no continuará con su preparación o entrega.'],
    ];
    $invoiceLabels = OrderAdminService::INVOICE_STATUSES;
    $canUpdate = auth()->user()?->hasPermission('orders.update') ?? false;
    $canBulkCreate = auth()->user()?->hasPermission('bulk_orders.create') ?? false;
    $canViewBilling = auth()->user()?->hasPermission('orders.billing') ?? false;
    $canViewInternalNotes = auth()->user()?->hasPermission('orders.internal_notes') ?? false;
    $canViewFollowup = auth()->user()?->hasPermission('orders.followup') ?? false;
    $canDelete = auth()->user()?->hasPermission('orders.delete') ?? false;
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
    .orders-command-strip {
        display: flex; align-items: center; justify-content: space-between; gap: .75rem;
        margin-bottom: .55rem;
    }
    .orders-title-group { position: relative; display: flex; align-items: center; gap: .5rem; min-width: 0; }
    .orders-title-group h2 { margin: 0; font-size: 1.2rem; font-weight: 850; color: #0f172a; white-space: nowrap; }
    .orders-help { position: relative; }
    .orders-help > summary {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; list-style: none; cursor: pointer;
        border: 1px solid #dbe3ea; border-radius: 50%; background: #fff; color: #64748b;
        touch-action: manipulation;
    }
    .orders-help > summary::-webkit-details-marker { display: none; }
    .orders-help > summary:hover, .orders-help[open] > summary { border-color: #99f6e4; color: #0f766e; background: #f0fdfa; }
    .orders-help-card {
        position: absolute; top: calc(100% + .45rem); left: 0; z-index: 25;
        width: min(320px, calc(100vw - 2rem)); padding: .75rem .85rem;
        border: 1px solid #dbe3ea; border-radius: 11px; background: #fff;
        color: #475569; font-size: .78rem; line-height: 1.45;
        box-shadow: 0 14px 35px rgba(15,23,42,.14);
    }
    .orders-help-card strong { color: #0f172a; }
    .orders-kpis {
        display: flex; align-items: center; justify-content: flex-end; gap: .35rem;
        min-width: 0; overflow-x: auto; scrollbar-width: none;
    }
    .orders-kpis::-webkit-scrollbar { display: none; }
    .orders-kpi {
        min-height: 34px; display: inline-flex; align-items: center; gap: .4rem;
        padding: .32rem .58rem; border: 1px solid #e2e8f0; border-radius: 9px;
        background: #fff; color: #64748b; white-space: nowrap;
    }
    .orders-kpi small { font-size: .65rem; font-weight: 750; }
    .orders-kpi strong { font-size: .78rem; color: #0f172a; }
    .orders-kpi.accent strong { color: #047857; }

    .orders-toolbar {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
        padding: .65rem; margin: 0;
        display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end;
    }
    .orders-segments {
        display: flex; gap: .3rem; margin-bottom: .5rem; padding: .25rem;
        overflow-x: auto; overscroll-behavior-inline: contain; scrollbar-width: thin;
        background: #e9eef3; border: 1px solid #dbe3ea; border-radius: 15px;
    }
    .orders-segment {
        min-height: 44px; flex: 1 0 auto; min-width: 108px; padding: .42rem .62rem;
        display: flex; align-items: center; justify-content: center; gap: .5rem;
        border: 1px solid transparent; border-radius: 11px; color: #526174;
        font-size: .76rem; font-weight: 800; text-decoration: none; white-space: nowrap;
        transition: background .15s ease, color .15s ease, box-shadow .15s ease;
        touch-action: manipulation;
    }
    .orders-segment:hover { background: rgba(255,255,255,.7); color: #0f766e; }
    .orders-segment.is-active {
        background: #fff; color: #0f766e; border-color: #cbd5e1;
        box-shadow: 0 3px 10px rgba(15,23,42,.08);
    }
    .orders-segment-count {
        min-width: 21px; height: 21px; padding: 0 .35rem; border-radius: 999px;
        display: inline-flex; align-items: center; justify-content: center;
        background: #dfe7ee; color: #526174; font-size: .68rem;
    }
    .orders-segment.is-active .orders-segment-count { background: #ccfbf1; color: #0f766e; }
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
    .o-tag.cancel-reason { background: #fee2e2; color: #991b1b; }
    .o-tag.new-order { background: #dcfce7; color: #15803d; animation: o-tag-pulse 1.6s ease-in-out infinite; }
    .o-tag.empty { color: #cbd5e1; }
    @keyframes o-tag-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .55; } }

    .o-tag.fulfil-retiro { background: #e0f2fe; color: #0369a1; }
    .o-tag.fulfil-delivery { background: #fae8ff; color: #a21caf; }
    .o-tag.fulfil-servir { background: #f1f5f9; color: #475569; }
    .o-tag.fulfil-pending { background: #fef3c7; color: #92400e; }
    .o-tag.order-branch { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .o-tag.order-branch.missing { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }

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

    .order-stage-button {
        width: 100%; min-width: 185px; border: 1px solid #dbe3ea; border-radius: 11px;
        padding: .5rem .6rem .5rem .7rem; display: flex; align-items: center; gap: .5rem;
        background: #fff; color: #334155; cursor: pointer; text-align: left;
        box-shadow: 0 1px 3px rgba(15,23,42,.06);
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    .order-stage-button:hover { border-color: #0f766e; box-shadow: 0 4px 12px rgba(15,118,110,.12); transform: translateY(-1px); }
    .order-stage-button.is-locked {
        cursor: default; background: #f8fafc; box-shadow: none; transform: none;
    }
    .order-stage-button.is-locked:hover { border-color: #dbe3ea; box-shadow: none; transform: none; }
    .order-stage-button:focus-visible { outline: 3px solid rgba(15,118,110,.18); border-color: #0f766e; }
    .order-stage-icon {
        width: 28px; height: 28px; border-radius: 8px; display: inline-flex;
        align-items: center; justify-content: center; flex: 0 0 auto; background: #e2e8f0; color: #475569;
    }
    .order-stage-value { flex: 1; min-width: 0; font-size: .75rem; font-weight: 800; line-height: 1.2; }
    .order-stage-action { display: inline-flex; align-items: center; gap: .25rem; color: #64748b; font-size: .65rem; font-weight: 700; }
    .order-stage-button.status-pending .order-stage-icon,
    .order-stage-button.status-payment_pending .order-stage-icon { background: #fef3c7; color: #a16207; }
    .order-stage-button.status-confirmed .order-stage-icon,
    .order-stage-button.status-paid .order-stage-icon,
    .order-stage-button.status-preparing .order-stage-icon { background: #dbeafe; color: #1d4ed8; }
    .order-stage-button.status-ready .order-stage-icon,
    .order-stage-button.status-completed .order-stage-icon { background: #dcfce7; color: #15803d; }
    .order-stage-button.status-cancelled .order-stage-icon { background: #fee2e2; color: #b91c1c; }

    #statusModal { z-index: 1070; }
    .status-modal-panel { max-width: 650px; }
    .status-modal-body { background: #f8fafc; }
    .status-current {
        display: flex; align-items: center; justify-content: space-between; gap: .75rem;
        padding: .7rem .8rem; margin-bottom: .8rem; border: 1px solid #e2e8f0;
        border-radius: 10px; background: #fff; color: #475569; font-size: .78rem;
    }
    .status-current strong { color: #0f172a; }
    .status-progress {
        display: grid; grid-template-columns: repeat(6, minmax(0, 1fr));
        margin: 0 0 .85rem; padding: .75rem .5rem; overflow-x: auto;
        border: 1px solid #dbe3ea; border-radius: 12px; background: #fff;
    }
    .status-progress-step {
        position: relative; min-width: 82px; display: flex; flex-direction: column;
        align-items: center; gap: .35rem; color: #94a3b8; text-align: center;
        font-size: .6rem; font-weight: 800; line-height: 1.15;
    }
    .status-progress-step::before {
        content: ''; position: absolute; z-index: 0; top: 15px; right: 50%; width: 100%; height: 3px; background: #e2e8f0;
    }
    .status-progress-step:first-child::before { display: none; }
    .status-progress-dot {
        position: relative; z-index: 1; width: 32px; height: 32px; border-radius: 999px;
        display: inline-flex; align-items: center; justify-content: center; background: #eef2f6; color: #64748b;
        border: 3px solid #fff; box-shadow: 0 0 0 1px #dbe3ea;
    }
    .status-progress-step.is-complete::before, .status-progress-step.is-current::before { background: #5eead4; }
    .status-progress-step.is-complete .status-progress-dot { background: #ccfbf1; color: #0f766e; box-shadow: 0 0 0 1px #5eead4; }
    .status-progress-step.is-current { color: #0f766e; }
    .status-progress-step.is-current .status-progress-dot { background: #0f766e; color: #fff; box-shadow: 0 0 0 3px #ccfbf1; }
    .status-progress.is-cancelled { border-color: #fecaca; background: #fff7f7; }
    .status-progress.is-cancelled .status-progress-step { opacity: .55; }
    .status-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .55rem; }
    .status-option {
        position: relative; width: 100%; min-height: 78px; padding: .7rem .75rem;
        display: grid; grid-template-columns: 34px minmax(0,1fr) 20px; gap: .65rem;
        align-items: center; border: 1px solid #dfe5ec; border-radius: 11px;
        background: #fff; color: #334155; cursor: pointer; text-align: left;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .status-option:hover:not(:disabled) { border-color: #0f766e; box-shadow: 0 3px 10px rgba(15,118,110,.1); }
    .status-option:focus-visible { outline: 3px solid rgba(15,118,110,.16); }
    .status-option.is-selected { border-color: #0f766e; background: #f0fdfa; box-shadow: 0 0 0 2px rgba(15,118,110,.1); }
    .status-option.is-current { cursor: default; background: #f1f5f9; border-color: #cbd5e1; opacity: .72; }
    .status-option.is-danger:not(.is-current) { border-color: #fecaca; }
    .status-option.is-danger.is-selected { border-color: #dc2626; background: #fef2f2; box-shadow: 0 0 0 2px rgba(220,38,38,.08); }
    .status-option-icon {
        width: 34px; height: 34px; border-radius: 9px; background: #e2e8f0; color: #475569;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .status-option.is-selected .status-option-icon { background: #ccfbf1; color: #0f766e; }
    .status-option.is-danger.is-selected .status-option-icon { background: #fee2e2; color: #b91c1c; }
    .status-option-copy strong { display: block; color: #0f172a; font-size: .8rem; }
    .status-option-copy small { display: block; margin-top: .15rem; color: #64748b; font-size: .69rem; line-height: 1.25; }
    .status-option-check { color: #0f766e; opacity: 0; }
    .status-option.is-selected .status-option-check { opacity: 1; }
    .status-option-current { position: absolute; right: .55rem; top: .4rem; color: #64748b; font-size: .58rem; font-weight: 800; text-transform: uppercase; }
    .status-confirmation {
        margin-top: .8rem; padding: .7rem .8rem; border-radius: 10px;
        background: #fffbeb; border: 1px solid #fde68a; color: #854d0e; font-size: .76rem;
    }
    .status-confirmation strong { color: #713f12; }
    .status-confirm-button { min-width: 175px; justify-content: center; }
    .status-confirm-button:disabled { opacity: .5; cursor: not-allowed; }
    .status-confirm-button.is-danger { background: #b91c1c; }

    .order-row-actions { display: flex; gap: .3rem; align-items: center; justify-content: flex-end; white-space: nowrap; }
    .o-btn {
        display: inline-flex; align-items: center; gap: .3rem; padding: .35rem .55rem;
        border-radius: 8px; font-size: .75rem; font-weight: 600; border: 1px solid #e2e8f0;
        background: #fff; color: #475569; cursor: pointer; text-decoration: none;
    }
    .o-btn.primary { background: linear-gradient(135deg, #128c7e, #075e54); border-color: transparent; color: #fff !important; }
    .o-btn.danger { color: #dc2626; border-color: #fecaca; }
    .o-btn.danger:hover { background: #fef2f2; }

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
    .order-process {
        display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
        padding: .85rem 1rem; background: #fff; border: 1px solid #dbe4ea;
        border-radius: 14px; box-shadow: 0 1px 4px rgba(15,23,42,.04);
    }
    .order-process-step { position: relative; min-width: 0; text-align: center; color: #94a3b8; }
    .order-process-step:not(:last-child)::after {
        content: ''; position: absolute; top: 15px; left: calc(50% + 19px);
        right: calc(-50% + 19px); height: 2px; background: #dbe4ea;
    }
    .order-process-step.is-done:not(:last-child)::after { background: #0f766e; }
    .order-process-dot {
        position: relative; z-index: 1; width: 30px; height: 30px; margin: 0 auto .35rem;
        display: flex; align-items: center; justify-content: center; border-radius: 50%;
        border: 2px solid #dbe4ea; background: #fff; font-size: .7rem;
    }
    .order-process-step.is-done { color: #0f766e; }
    .order-process-step.is-done .order-process-dot { border-color: #0f766e; background: #0f766e; color: #fff; }
    .order-process-step.is-current { color: #0f172a; font-weight: 800; }
    .order-process-step.is-current .order-process-dot {
        border-color: #0f766e; color: #0f766e; box-shadow: 0 0 0 4px #dff5ef;
    }
    .order-process-label { display: block; font-size: .68rem; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .order-process-cancelled { grid-column: 1 / -1; display: flex; align-items: center; gap: .65rem; color: #991b1b; font-size: .8rem; font-weight: 700; }
    .order-process-cancelled .order-process-dot { margin: 0; border-color: #fecaca; background: #fef2f2; color: #dc2626; }

    .order-snapshot {
        display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
        background: #fff; border: 1px solid #dbe4ea; border-radius: 14px; overflow: hidden;
    }
    .order-snapshot-item { min-width: 0; padding: .72rem .85rem; border-right: 1px solid #edf1f5; }
    .order-snapshot-item:last-child { border-right: 0; }
    .order-snapshot-label { display: block; color: #64748b; font-size: .62rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .order-snapshot-value { display: block; margin-top: .18rem; color: #0f172a; font-size: .8rem; font-weight: 750; overflow-wrap: anywhere; }
    .order-snapshot-item.is-total .order-snapshot-value { color: #0f766e; font-size: .94rem; }
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
    .order-ticket-payment { display: flex; align-items: center; justify-content: space-between; padding: .6rem 1rem; color: #475569; font-size: .8rem; border-bottom: 1px solid #f1f5f9; }
    .order-ticket-payment strong { color: #0f172a; }
    .order-ticket-total { display: flex; align-items: center; justify-content: space-between; padding: .8rem 1rem; color: #075e54; background: #f0fdf8; font-weight: 800; }
    .order-ticket-total strong { font-size: 1.05rem; }
    .order-quick-actions { padding: .9rem 1rem; border-left: 4px solid #16a34a; }
    .order-quick-actions-row { display: flex; align-items: center; justify-content: space-between; gap: .8rem; }
    .order-quick-actions-copy strong { display: block; color: #0f172a; font-size: .86rem; }
    .order-quick-actions-copy span { display: block; color: #64748b; font-size: .75rem; margin-top: .12rem; }
    .order-quick-actions .o-btn { flex-shrink: 0; }
    .order-disclosure { overflow: hidden; box-shadow: none; }
    .order-disclosure summary { position: relative; min-height: 46px; list-style: none; cursor: pointer; padding: .75rem 2.2rem .75rem 1rem; color: #475569; font-size: .78rem; font-weight: 700; touch-action: manipulation; }
    .order-disclosure summary::-webkit-details-marker { display: none; }
    .order-disclosure summary::after { content: '+'; position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1rem; line-height: .8; }
    .order-disclosure[open] summary { border-bottom: 1px solid #edf1f5; color: #0f172a; }
    .order-disclosure[open] summary::after { content: '−'; }
    .order-disclosure-body { padding: .85rem 1rem 1rem; }
    .order-disclosure-summary-main { display: inline-flex; align-items: center; gap: .45rem; min-width: 0; }
    .order-disclosure-summary-meta { margin-left: .45rem; color: #64748b; font-weight: 600; }
    .order-disclosure.is-priority { border-color: #99d5c9; box-shadow: 0 0 0 2px rgba(15,118,110,.07); }
    .order-disclosure.is-priority > summary { color: #0f766e; background: #f4fbf9; }

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
    .order-step-num { width: 22px; height: 22px; border-radius: 50%; background: #0f766e; color: #fff; font-size: .68rem; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
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
    #orderModal .modal-panel {
        width: min(760px, calc(100vw - 2rem));
        max-width: 760px;
        border-radius: 18px;
    }
    #statusModal .status-modal-panel {
        width: min(650px, calc(100vw - 2rem));
        max-width: 650px;
    }
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
        display: flex; align-items: center; gap: .8rem; padding: .9rem 1rem;
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
    .order-next-step-kicker { display: block; margin-bottom: .13rem; color: currentColor; font-size: .61rem; font-weight: 850; letter-spacing: .06em; text-transform: uppercase; }
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

    /* Herramientas secundarias: permanecen recogidas para dar prioridad a la cola. */
    .orders-tools-disclosure {
        margin-bottom: .55rem; border: 1px solid #e2e8f0; border-radius: 11px;
        background: #fff; overflow: visible;
    }
    .orders-tools-summary {
        min-height: 40px; padding: .45rem .7rem; list-style: none; cursor: pointer;
        display: flex; align-items: center; justify-content: space-between; gap: .75rem;
        color: #526174; font-size: .76rem; font-weight: 800; touch-action: manipulation;
    }
    .orders-tools-summary::-webkit-details-marker { display: none; }
    .orders-tools-summary-main, .orders-tools-summary-meta { display: inline-flex; align-items: center; gap: .45rem; }
    .orders-tools-summary-main i { color: #0f766e; }
    .orders-tools-summary-meta { color: #64748b; font-weight: 700; }
    .orders-tools-chevron { transition: transform .15s ease; }
    .orders-tools-disclosure[open] .orders-tools-chevron { transform: rotate(180deg); }
    .orders-tools-disclosure[open] .orders-toolbar { border-top: 1px solid #eef2f7; }
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
        .orders-command-strip { align-items: center; }
        .orders-kpis { flex: 1; justify-content: flex-start; }
        .orders-kpi { flex: 0 0 auto; }
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
        .order-stage-button { min-width: 0; }
        .status-options { grid-template-columns: 1fr; }
        .order-card-actions { display: grid; grid-template-columns: 1fr 1fr; }
        .order-card-actions .o-btn { justify-content: center; }
        .order-card-actions .primary { grid-column: 1 / -1; grid-row: 1; }
        .modal-panel { max-height: 96vh; }
        .modal-body { padding: .75rem; }
    }

    /* Móvil: ningún aviso, tarjeta o modal debe ampliar el viewport. */
    .orders-page { min-width: 0; }
    .order-card,
    .modal-header,
    .modal-body,
    .modal-footer,
    .order-command-center,
    .order-section,
    .order-section-body { max-width: 100%; }
    .modal-body { overflow-x: hidden; overscroll-behavior: contain; }
    .modal-body img,
    .modal-body video,
    .modal-body iframe { max-width: 100%; height: auto; }
    .modal-body input,
    .modal-body select,
    .modal-body textarea { max-width: 100%; }
    .modal-heading,
    .modal-header .sub,
    .order-section-head-main,
    .order-section-body,
    .order-disclosure summary,
    .order-callout,
    .fulfillment-val,
    .status-option-copy,
    .order-customer,
    .order-meta span { overflow-wrap: anywhere; }

    @media (max-width: 575.98px) {
        .orders-page { width: 100%; overflow: hidden; }
        .orders-command-strip { padding: 0 .1rem; gap: .45rem; }
        .orders-title-group h2 { font-size: 1.05rem; }
        .orders-help > summary { width: 38px; height: 38px; }
        .orders-help { position: static; }
        .orders-help-card { right: auto; left: 0; }
        .orders-kpi small { display: none; }
        .orders-kpi { min-width: 42px; justify-content: center; }
        .orders-tools-summary { min-height: 46px; }
        .orders-toolbar { padding: .7rem; gap: .55rem; }
        .order-card { width: 100%; padding: .78rem; border-radius: 12px; }
        .order-customer-row { align-items: center; justify-content: space-between; gap: .35rem .6rem; }
        .order-customer { flex: 1 1 150px; font-size: .94rem; }
        .order-total { flex: 0 0 auto; font-size: .94rem; }
        .order-meta { display: grid; grid-template-columns: 1fr; gap: .3rem; }
        .order-stage-button { width: 100%; min-height: 48px; }
        .order-stage-action { margin-left: auto; }
        .order-card-actions { grid-template-columns: 1fr; }
        .order-card-actions .primary { grid-column: auto; grid-row: auto; }
        .order-card-actions .o-btn { width: 100%; min-height: 42px; white-space: normal; }

        .modal-overlay {
            align-items: flex-end;
            padding: 0;
        }
        #orderModal .modal-panel,
        #statusModal .status-modal-panel {
            width: 100%;
            max-width: 100%;
            max-height: min(94dvh, 94vh);
            border-radius: 18px 18px 0 0;
            transform: translateY(24px);
        }
        .modal-header { padding: .82rem .9rem; gap: .65rem; }
        .modal-title-row { align-items: flex-start; gap: .38rem; }
        .modal-header h3 { flex: 1 1 170px; font-size: 1rem; line-height: 1.2; }
        .modal-header .sub { font-size: .72rem; line-height: 1.35; }
        .modal-close { width: 40px; height: 40px; }
        .modal-body { padding: .65rem; }
        .modal-footer {
            display: grid;
            grid-template-columns: 1fr;
            gap: .45rem;
            padding: .7rem;
        }
        .modal-footer .o-btn,
        .status-confirm-button { width: 100%; min-width: 0; min-height: 44px; justify-content: center; white-space: normal; }
        #orderModalFooter .o-btn.primary { order: -1; }

        .status-current { align-items: flex-start; flex-direction: column; gap: .28rem; }
        .status-options { grid-template-columns: 1fr; gap: .45rem; }
        .status-option {
            min-height: 68px;
            grid-template-columns: 34px minmax(0, 1fr) 18px;
            gap: .55rem;
            padding: .62rem;
        }
        .status-option-copy strong { padding-right: 2.8rem; }
        .status-option-copy small { font-size: .68rem; }
        .status-confirmation { font-size: .73rem; line-height: 1.4; }

        .order-ticket-head,
        .order-section-head,
        .payment-proof-top { align-items: flex-start; flex-wrap: wrap; padding: .7rem .75rem; }
        .order-compact-line { padding: .65rem .75rem; }
        .order-compact-line-name,
        .order-compact-line-meta,
        .order-compact-line-note { overflow-wrap: anywhere; }
        .order-quick-actions { padding: .75rem; }
        .order-quick-actions-row { align-items: stretch; flex-direction: column; }
        .order-quick-actions .o-btn { width: 100%; min-height: 42px; justify-content: center; }
        .order-process { padding: .75rem .4rem; }
        .order-process-label { font-size: .6rem; }
        .order-process-step:not(:last-child)::after { left: calc(50% + 17px); right: calc(-50% + 17px); }
        .order-snapshot { grid-template-columns: 1fr 1fr; }
        .order-snapshot-item { border-bottom: 1px solid #edf1f5; }
        .order-snapshot-item:nth-child(2n) { border-right: 0; }
        .order-snapshot-item:nth-last-child(-n+2) { border-bottom: 0; }
        .order-section-body,
        .order-disclosure-body { padding: .75rem; }
        .order-disclosure summary { padding: .72rem .75rem; }
        .order-disclosure summary { padding-right: 2rem; }
        .order-disclosure-summary-main { display: flex; }
        .order-disclosure-summary-meta { display: block; margin: .18rem 0 0 1.25rem; font-size: .68rem; overflow-wrap: anywhere; }
        .fulfillment-grid { grid-template-columns: 1fr; }
        .fulfillment-full { grid-column: auto; }
        .payment-proof-title-wrap { width: 100%; }
        .payment-proof-badge { margin-left: 56px; }
        .payment-proof-meta,
        .payment-proof-actions { padding-left: .75rem; padding-right: .75rem; }
        .payment-proof-preview { margin-left: .75rem; margin-right: .75rem; }
        .payment-proof-actions { display: grid; grid-template-columns: 1fr; }
        .payment-proof-btn { width: 100%; min-height: 42px; justify-content: center; }

        .orders-refresh-banner {
            right: 10px;
            bottom: calc(10px + env(safe-area-inset-bottom));
            left: 10px;
            width: auto;
            max-width: none;
            justify-content: center;
            padding: .72rem .85rem;
            border-radius: 12px;
            text-align: center;
            line-height: 1.3;
            transform: translateY(28px);
        }
        .orders-refresh-banner.is-visible { transform: translateY(0); }
        .toast-orders {
            right: 10px;
            bottom: calc(10px + env(safe-area-inset-bottom));
            left: 10px;
            max-width: none;
            overflow-wrap: anywhere;
            text-align: center;
        }
    }
</style>

<div class="orders-page">
    <div class="orders-command-strip">
        <div class="orders-title-group">
            <h2><i class="fas fa-cash-register me-1 text-success"></i> Pedidos</h2>
            <details class="orders-help">
                <summary aria-label="Mostrar ayuda de pedidos" title="¿Cómo gestionar los pedidos?">
                    <i class="fas fa-question"></i>
                </summary>
                <div class="orders-help-card">
                    <strong>Flujo rápido</strong><br>
                    Abre el pedido, verifica los productos y el pago, y cambia su etapa conforme avance hasta la entrega.
                </div>
            </details>
        </div>
        <div class="orders-kpis" role="list" aria-label="Resumen de pedidos del día">
            <span class="orders-kpi" role="listitem"><small>Por revisar</small><strong>{{ $stats['pending'] ?? 0 }}</strong></span>
            <span class="orders-kpi" role="listitem"><small>En proceso</small><strong>{{ $stats['confirmed'] ?? 0 }}</strong></span>
            <span class="orders-kpi" role="listitem"><small>Entregados</small><strong>{{ $stats['completed'] ?? 0 }}</strong></span>
            <span class="orders-kpi accent" role="listitem"><small>Ventas</small><strong>${{ number_format($stats['revenue'] ?? 0, 0) }}</strong></span>
        </div>
    </div>

    <nav class="orders-segments" aria-label="Segmentar pedidos por etapa">
        @foreach($orderSegments as $segmentKey => $segment)
            <a href="{{ route('admin.orders', $segmentKey === 'all' ? [] : ['segment' => $segmentKey]) }}"
                class="orders-segment {{ $activeSegment === $segmentKey ? 'is-active' : '' }}"
                aria-current="{{ $activeSegment === $segmentKey ? 'page' : 'false' }}">
                <i class="fas {{ $segmentIcons[$segmentKey] ?? 'fa-circle' }}"></i>
                <span>{{ $segment['label'] }}</span>
                <span class="orders-segment-count">{{ $segmentCounts[$segmentKey] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <details class="orders-tools-disclosure">
        <summary class="orders-tools-summary">
            <span class="orders-tools-summary-main"><i class="fas fa-sliders"></i> Buscar y acciones</span>
            <span class="orders-tools-summary-meta">
                {{ $orders->total() }} pedido(s)
                <i class="fas fa-chevron-down orders-tools-chevron"></i>
            </span>
        </summary>
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
    </details>

    <div id="orders-list">
        @if($orders->isEmpty())
            <div class="orders-empty">
                <i class="fas fa-inbox fa-2x mb-2 opacity-50 d-block"></i>
                <p class="mb-0 fw-semibold">{{ $activeSegment === 'all' ? 'No hay pedidos registrados' : 'No hay pedidos en esta etapa' }}</p>
            </div>
        @else
            <div class="orders-card-list">
                    @foreach($orders as $order)
                        @php
                            $itemsCount = $order->items->count();
                            $contact = $order->contact;
                            $branch = $order->branch;
                            $branchIdentifier = $branch
                                ? (($branch->code ?: 'SUC-'.$branch->id).' · '.$branch->name)
                                : 'Sin sucursal';
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
                                || $order->status === 'cancelled'
                                || $fulfillmentServiceType;
                            $allowedStatusTransitions = $statusTransitions[$order->status] ?? [];
                            // Si ya llegó el comprobante, "Esperando pago" es
                            // engañoso -- no se espera nada más del cliente,
                            // el pedido queda en cancha del operador.
                            $stageLabel = $statusLabels[$order->status] ?? $order->status;
                            $stageIcon = $statusMeta[$order->status]['icon'] ?? 'fa-circle';
                            if ($order->status === 'payment_pending' && $order->hasPaymentProof()) {
                                $stageLabel = 'Revisar pago';
                                $stageIcon = 'fa-magnifying-glass-dollar';
                            }
                        @endphp
                        <article class="order-card status-{{ $order->status }}" id="order-row-{{ $order->id }}"
                            data-search="{{ strtolower(trim(($contact->name ?? '') . ' ' . ($contact->phone_number ?? '') . ' ' . ($clientNationalId ?? '') . ' ' . ($branch?->name ?? '') . ' ' . ($branch?->code ?? ''))) }}">
                            <div class="order-card-main">
                                <div class="order-card-top">
                                    <span class="order-number">{{ $order->getOrderNumber() }}</span>
                                    <span class="o-tag order-branch {{ $branch ? '' : 'missing' }}" title="Sucursal donde se realizó el pedido">
                                        <i class="fas fa-store"></i> {{ $branchIdentifier }}
                                    </span>
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
                                    @if($order->status === 'cancelled' && $order->cancellationReasonLabel())
                                        <span class="o-tag cancel-reason"><i class="fas fa-ban"></i> {{ $order->cancellationReasonLabel() }}</span>
                                    @endif
                                    @unless($hasTags)
                                        <span class="o-tag empty"><i class="fas fa-circle-check"></i> Sin novedades</span>
                                    @endunless
                                </div>
                            </div>
                            <div class="order-card-side">
                                <div class="order-stage">
                                <span class="order-stage-label">Etapa del pedido</span>
                                @if($canUpdate && !empty($allowedStatusTransitions))
                                    <button type="button" class="order-stage-button status-{{ $order->status }}"
                                        id="status-button-{{ $order->id }}"
                                        data-current-status="{{ $order->status }}"
                                        data-order-number="{{ $order->getOrderNumber() }}"
                                        data-fulfillment-pickup-mode="{{ $fulfillmentPickupMode }}"
                                        onclick="openStatusModal({{ $order->id }}, this)"
                                        aria-haspopup="dialog" aria-label="Cambiar etapa de {{ $order->getOrderNumber() }}">
                                        <span class="order-stage-icon"><i class="fas {{ $stageIcon }}"></i></span>
                                        <span class="order-stage-value">{{ $stageLabel }}</span>
                                        <span class="order-stage-action">Cambiar <i class="fas fa-chevron-right"></i></span>
                                    </button>
                                @elseif($canUpdate)
                                    <div class="order-stage-button status-{{ $order->status }} is-locked" aria-label="Etapa final: {{ $stageLabel }}">
                                        <span class="order-stage-icon"><i class="fas {{ $stageIcon }}"></i></span>
                                        <span class="order-stage-value">{{ $stageLabel }}</span>
                                        <span class="order-stage-action"><i class="fas fa-lock"></i> Estado final</span>
                                    </div>
                                @else
                                    <span class="o-tag">{{ $stageLabel }}</span>
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
                                    @if($canDelete)
                                        <button type="button" class="o-btn danger" title="Eliminar pedido definitivamente"
                                            onclick="confirmDeleteOrder({{ $order->id }}, {{ Js::from($order->getOrderNumber()) }})">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    @endif
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

@if($canUpdate)
<div class="modal-overlay" id="statusModal" role="dialog" aria-modal="true" aria-labelledby="statusModalTitle" aria-describedby="statusModalSubtitle">
    <div class="modal-panel status-modal-panel">
        <div class="modal-header">
            <div class="modal-heading">
                <h3 id="statusModalTitle">Cambiar etapa del pedido</h3>
                <p class="sub mb-0" id="statusModalSubtitle">Selecciona la nueva etapa y confirma el cambio.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeStatusModal()" aria-label="Cerrar"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body status-modal-body">
            <div class="status-current">
                <span><i class="fas fa-receipt me-1"></i> <strong id="statusModalOrder">Pedido</strong></span>
                <span>Etapa actual: <strong id="statusModalCurrent">—</strong></span>
            </div>
            <div class="status-progress" id="statusProgress" aria-label="Avance del pedido">
                @foreach([
                    ['pending', 'Recibido', 'fa-inbox'],
                    ['payment', 'Pago', 'fa-credit-card'],
                    ['confirmed', 'Aceptado', 'fa-circle-check'],
                    ['preparing', 'En cocina', 'fa-fire-burner'],
                    ['ready', 'Listo', 'fa-bag-shopping'],
                    ['completed', 'Entregado', 'fa-check-double'],
                ] as [$flowStatus, $flowLabel, $flowIcon])
                    <div class="status-progress-step" data-flow-status="{{ $flowStatus }}">
                        <span class="status-progress-dot"><i class="fas {{ $flowIcon }}"></i></span>
                        <span>{{ $flowLabel }}</span>
                    </div>
                @endforeach
            </div>
            <div class="status-options" id="statusOptions" role="radiogroup" aria-label="Etapas disponibles">
                @foreach($statusOptions as $status)
                    <button type="button"
                        class="status-option {{ $status === 'cancelled' ? 'is-danger' : '' }}"
                        data-status="{{ $status }}" role="radio" aria-checked="false"
                        onclick="selectOrderStatus('{{ $status }}', this)">
                        <span class="status-option-icon"><i class="fas {{ $statusMeta[$status]['icon'] }}"></i></span>
                        <span class="status-option-copy">
                            <strong>{{ $statusLabels[$status] }}</strong>
                            <small>{{ $statusMeta[$status]['description'] }}</small>
                        </span>
                        <i class="fas fa-circle-check status-option-check"></i>
                    </button>
                @endforeach
            </div>
            <div class="status-confirmation" id="statusConfirmation" hidden>
                <i class="fas fa-triangle-exclamation me-1"></i>
                Vas a cambiar el pedido de <strong id="statusFromLabel"></strong> a <strong id="statusToLabel"></strong>.
                Revisa la selección antes de confirmar; el cambio puede activar acciones automáticas del pedido.
            </div>
        </div>
        <div class="modal-footer">
            <a href="#" id="statusDeliveryShortcut" class="o-btn" target="_blank" rel="noopener" hidden>
                <i class="fas fa-motorcycle"></i> Enviar al repartidor
            </a>
            <button type="button" class="o-btn" onclick="closeStatusModal()">Volver sin cambiar</button>
            <button type="button" class="o-btn primary status-confirm-button" id="confirmStatusButton" onclick="confirmOrderStatusChange()" disabled>
                <i class="fas fa-check"></i> Confirmar cambio
            </button>
        </div>
    </div>
</div>
@endif

<div class="modal-overlay" id="orderModal" role="dialog" aria-modal="true">
    <div class="modal-panel">
        <div class="modal-header">
            <div class="modal-heading">
                <div class="modal-title-row">
                    <h3 id="orderModalTitle">Pedido</h3>
                    <span class="modal-status" id="orderModalStatus"></span>
                    <span class="o-tag cancel-reason" id="orderModalCancelReason" hidden></span>
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
const STATUS_META = @json($statusMeta);
const STATUS_TRANSITIONS = @json($statusTransitions);
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
const DELIVERY_INDEX_URL = @json(route('admin.delivery.index'));
let currentOrderId = null;
let currentOrderData = null;
let pendingStatusChange = null;
let statusChangeInProgress = false;

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
    const proofReceived = order.payment?.state === 'submitted';
    const steps = {
        pending: ['Revisa este pedido', 'Confirma productos, forma de entrega y total antes de aceptarlo.', 'fa-clipboard-check', 'is-waiting'],
        payment_pending: proofReceived
            ? ['Revisa el comprobante', 'Compara el valor recibido con el total y confirma el pago.', 'fa-file-circle-check', 'is-waiting']
            : ['Espera el comprobante', 'No necesitas hacer nada todavía. Aparecerá aquí cuando el cliente lo envíe.', 'fa-hourglass-half', 'is-waiting'],
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
        <div><small class="order-next-step-kicker">Qué debes hacer ahora</small><strong>${step[0]}</strong><span>${step[1]}</span></div>
    </div>`;
}

function renderOrderProgress(order) {
    if (order.status === 'cancelled') {
        return `<div class="order-process"><div class="order-process-cancelled"><span class="order-process-dot"><i class="fas fa-ban"></i></span><span>Proceso detenido: pedido cancelado</span></div></div>`;
    }

    const stages = [
        ['Pedido', 'fa-receipt'],
        ['Pago', 'fa-credit-card'],
        ['Preparación', 'fa-utensils'],
        ['Entrega', 'fa-bag-shopping'],
    ];
    const currentByStatus = { pending: 0, confirmed: 1, payment_pending: 1, paid: 2, preparing: 2, ready: 3, completed: 4 };
    const current = currentByStatus[order.status] ?? 0;

    return `<div class="order-process" aria-label="Avance del pedido">${stages.map((stage, index) => {
        const state = current >= stages.length || index < current ? 'is-done' : (index === current ? 'is-current' : '');
        const icon = state === 'is-done' ? 'fa-check' : stage[1];
        return `<div class="order-process-step ${state}"><span class="order-process-dot"><i class="fas ${icon}"></i></span><span class="order-process-label">${stage[0]}</span></div>`;
    }).join('')}</div>`;
}

function renderOrderSnapshot(order) {
    const fulfillment = order.fulfillment || {};
    const deliveryLabel = [fulfillment.service_type_label, fulfillment.pickup_mode_label].filter(Boolean).join(' · ') || '—';
    const paymentLabel = order.payment?.method_label || '—';
    return `<div class="order-snapshot" aria-label="Resumen rápido del pedido">
        <div class="order-snapshot-item"><span class="order-snapshot-label">Sucursal</span><span class="order-snapshot-value">${esc(fulfillment.branch || '—')}</span></div>
        <div class="order-snapshot-item"><span class="order-snapshot-label">Entrega</span><span class="order-snapshot-value">${esc(deliveryLabel)}</span></div>
        <div class="order-snapshot-item"><span class="order-snapshot-label">Pago</span><span class="order-snapshot-value">${esc(paymentLabel)}</span></div>
        <div class="order-snapshot-item is-total"><span class="order-snapshot-label">Total</span><span class="order-snapshot-value">$${parseFloat(order.total).toFixed(2)}</span></div>
    </div>`;
}

function renderOrderItems(order) {
    const count = order.items?.length || 0;
    const shouldOpen = ['pending', 'confirmed', 'preparing'].includes(order.status);
    let html = `<details class="order-disclosure${order.status === 'pending' ? ' is-priority' : ''}" ${shouldOpen ? 'open' : ''}>
        <summary><span class="order-disclosure-summary-main"><i class="fas fa-bag-shopping"></i>Productos del pedido</span><span class="order-disclosure-summary-meta">${count} producto(s)</span></summary>
        <div class="order-disclosure-body p-0">`;
    if (count) {
        order.items.forEach(item => {
            const sub = (parseFloat(item.price) * parseInt(item.quantity)).toFixed(2);
            html += `<div class="order-compact-line"><div><span class="order-compact-line-name">${esc(item.name)}</span><span class="order-compact-line-meta">${item.quantity} × $${parseFloat(item.price).toFixed(2)}</span>${item.line_note ? `<span class="order-compact-line-note">${esc(item.line_note)}</span>` : ''}</div><span class="order-compact-line-total">$${sub}</span></div>`;
        });
    } else {
        html += `<div class="order-compact-line"><span class="text-muted small">Sin líneas de producto registradas.</span></div>`;
    }
    const deliveryFee = parseFloat(order.fulfillment?.delivery_fee ?? 0);
    html += `<div class="order-ticket-total"><span>${deliveryFee > 0 ? 'Total (incluye envío)' : 'Total'}</span><strong>$${parseFloat(order.total).toFixed(2)}</strong></div></div></details>`;
    return html;
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

    const cancelReasonEl = document.getElementById('orderModalCancelReason');
    if (order.status === 'cancelled' && order.cancellation_reason_label) {
        cancelReasonEl.innerHTML = `<i class="fas fa-ban"></i> ${esc(order.cancellation_reason_label)}`;
        cancelReasonEl.hidden = false;
    } else {
        cancelReasonEl.hidden = true;
    }

    const b = order.billing || {};
    let html = '<div class="order-command-center">';
    html += renderOrderProgress(order);
    html += renderOrderNextStep(order);
    html += renderOrderSnapshot(order);

    // El bloque que requiere atención aparece primero. El resto conserva sus
    // controles, pero queda recogido para que un operador nuevo no se pierda.
    if (order.status === 'payment_pending') html += renderPaymentProofSection(order);
    html += renderFulfillmentSection(order);
    html += renderOrderItems(order);
    if (order.status !== 'payment_pending') html += renderPaymentProofSection(order);

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

    const needsCostReview = f.pickup_mode === 'delivery' && f.delivery_fee_pending_review && !['cancelled', 'completed'].includes(order.status);
    const shouldOpen = needsCostReview || order.status === 'pending' || order.status === 'ready';
    const fulfillmentSummary = [f.branch, f.service_type_label, f.pickup_mode_label].filter(Boolean).join(' · ');
    let html = `<details class="order-disclosure${needsCostReview ? ' is-priority' : ''}" ${shouldOpen ? 'open' : ''}>
        <summary><span class="order-disclosure-summary-main"><i class="fas fa-store"></i>Entrega y sucursal</span><span class="order-disclosure-summary-meta">${esc(fulfillmentSummary)}</span></summary>
        <div class="order-disclosure-body">`;

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
    const isFinalStatus = order.status === 'cancelled' || order.status === 'completed';

    if (showsDelivery) {
        const deliveryFeeVal = f.delivery_fee != null ? parseFloat(f.delivery_fee).toFixed(2) : '';
        const costsConfirmed = !f.delivery_fee_pending_review && deliveryFeeVal !== '';

        html += `<div class="order-step${costsConfirmed ? ' is-done' : ''}">
            <div class="order-step-head"><span class="order-step-num"><i class="fas fa-${costsConfirmed ? 'check' : 'triangle-exclamation'}"></i></span><span class="order-step-title">Costo de envío</span></div>`;

        if (f.delivery_fee_pending_review && !isFinalStatus) {
            html += `<div class="order-callout warning mt-3"><i class="fas fa-triangle-exclamation me-1"></i>No se pudo calcular solo (dirección a mano, fuera de la tabla de tramos, o sucursal sin tabla configurada). Confírmalo a mano antes de despachar: se suma al total y el cliente recibe el mensaje con el total final.</div>`;
        } else if (deliveryFeeVal !== '') {
            html += `<div class="order-callout info mt-3"><i class="fas fa-check-circle me-1"></i>Costo de envío calculado automáticamente y ya incluido en el total. Podés corregirlo abajo si hace falta.</div>`;
        }
        if (isFinalStatus) {
            html += `<div class="order-callout mt-3"><i class="fas fa-lock me-1"></i>Este pedido está ${order.status === 'cancelled' ? 'cancelado' : 'entregado'}: ya no se le pueden mandar cambios de costo.</div>`;
        }

        if (CAN_UPDATE && !isFinalStatus) {
            html += `<form class="mt-2" onsubmit="sendFulfillmentCosts(event)">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-4">
                        <label class="form-label small d-block">Costo de envío ($)</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="delivery_fee" value="${deliveryFeeVal}" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <button type="submit" class="o-btn primary btn-sm w-100"><i class="fab fa-whatsapp me-1"></i>Confirmar y avisar al cliente</button>
                    </div>
                </div>
            </form>`;
        } else if (deliveryFeeVal !== '') {
            html += `<p class="small text-muted mb-0 mt-2">Envío: $${deliveryFeeVal}</p>`;
        }

        html += `</div>`;
    }

    if (showsDelivery) {
        const lastDriver = f.last_dispatch_driver;
        html += `<div class="order-step">
            <div class="order-step-head"><span class="order-step-num"><i class="fas fa-motorcycle"></i></span><span class="order-step-title">Repartidor</span></div>
            <p class="small text-muted mb-2">Le avisamos al cliente que su pedido va en camino (con el contacto del repartidor) y te abrimos WhatsApp con los datos ya listos para mandárselos a él.</p>
            ${isFinalStatus
                ? `<p class="small text-muted mb-0"><i class="fas fa-lock me-1"></i>Este pedido está ${order.status === 'cancelled' ? 'cancelado' : 'entregado'}.</p>`
                : `<button type="button" class="o-btn primary btn-sm" onclick="openOrderDispatchModal(${order.id})"><i class="fab fa-whatsapp me-1"></i>Enviar a repartidor</button>`}
            ${lastDriver ? `<button type="button" class="o-btn btn-sm ms-2" onclick="reopenOrderDispatch(${order.id})" title="Vuelve a abrir WhatsApp con ${esc(lastDriver.name)}, sin volver a avisarle al cliente"><i class="fas fa-rotate-right me-1"></i>Reenviar a ${esc(lastDriver.name)}</button>` : ''}
        </div>`;
    }

    html += `</div></details>`;
    return html;
}

function sendFulfillmentCosts(e) {
    e.preventDefault();
    if (!currentOrderId) return;
    const form = new FormData(e.target);
    const payload = {};

    const raw = form.get('delivery_fee');
    const fee = parseFloat(raw);
    if (raw === '' || isNaN(fee) || fee < 0) return;
    payload.delivery_fee = fee;

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
    const isPriority = order.status === 'payment_pending';

    let html = isPriority
        ? `<section class="order-section" data-theme="payment">${sectionHead('Paso actual · Pago del pedido', 'fas fa-credit-card', 'payment', 'payment_proof')}<div class="order-section-body flush">`
        : `<details class="order-disclosure"><summary><span class="order-disclosure-summary-main"><i class="fas fa-credit-card"></i>Pago del pedido</span><span class="order-disclosure-summary-meta">${esc(payment.method_label)} · ${esc(payment.status_label)}</span></summary><div class="order-disclosure-body p-0">`;
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

    html += `</div></div>${isPriority ? '</section>' : '</details>'}`;
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

function openStatusModal(orderId, triggerEl) {
    const currentStatus = triggerEl.getAttribute('data-current-status');
    const allowedStatuses = STATUS_TRANSITIONS[currentStatus] || [];
    if (!allowedStatuses.length) {
        showToast('Este pedido ya está en un estado final y no admite más cambios.', 'error');
        return;
    }
    pendingStatusChange = { orderId, triggerEl, currentStatus, newStatus: null };

    const deliveryShortcut = document.getElementById('statusDeliveryShortcut');
    const isDeliveryOrder = triggerEl.dataset.fulfillmentPickupMode === 'delivery';
    const isFinalStatus = ['completed', 'cancelled'].includes(currentStatus);
    deliveryShortcut.hidden = !(isDeliveryOrder && !isFinalStatus);
    deliveryShortcut.href = `${DELIVERY_INDEX_URL}?order=${orderId}`;

    document.getElementById('statusModalOrder').textContent = triggerEl.dataset.orderNumber || ('Pedido #' + orderId);
    document.getElementById('statusModalCurrent').textContent = STATUS_LABELS[currentStatus] || currentStatus;
    document.getElementById('statusConfirmation').hidden = true;
    updateStatusProgress(currentStatus);

    const confirmButton = document.getElementById('confirmStatusButton');
    confirmButton.disabled = true;
    confirmButton.classList.remove('is-danger');
    confirmButton.innerHTML = '<i class="fas fa-check"></i> Confirmar cambio';

    document.querySelectorAll('#statusOptions .status-option').forEach(option => {
        const isCurrent = option.dataset.status === currentStatus;
        const isAllowed = allowedStatuses.includes(option.dataset.status);
        option.hidden = !isAllowed;
        option.disabled = !isAllowed;
        option.classList.remove('is-selected');
        option.classList.remove('is-current');
        option.setAttribute('aria-checked', 'false');
        option.querySelector('.status-option-current')?.remove();
    });

    const modal = document.getElementById('statusModal');
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => modal.querySelector('.status-option:not([hidden]):not(:disabled)')?.focus(), 80);
}

function selectOrderStatus(status, optionEl) {
    if (!pendingStatusChange || status === pendingStatusChange.currentStatus || statusChangeInProgress) return;

    pendingStatusChange.newStatus = status;
    document.querySelectorAll('#statusOptions .status-option').forEach(option => {
        const selected = option === optionEl;
        option.classList.toggle('is-selected', selected);
        option.setAttribute('aria-checked', selected ? 'true' : 'false');
    });

    document.getElementById('statusFromLabel').textContent = STATUS_LABELS[pendingStatusChange.currentStatus] || pendingStatusChange.currentStatus;
    document.getElementById('statusToLabel').textContent = STATUS_LABELS[status] || status;
    document.getElementById('statusConfirmation').hidden = false;
    updateStatusProgress(status);

    const confirmButton = document.getElementById('confirmStatusButton');
    confirmButton.disabled = false;
    confirmButton.classList.toggle('is-danger', status === 'cancelled');
    confirmButton.innerHTML = `<i class="fas ${status === 'cancelled' ? 'fa-ban' : 'fa-check'}"></i> Confirmar: ${esc(STATUS_LABELS[status] || status)}`;
}

function updateStatusProgress(status) {
    const progress = document.getElementById('statusProgress');
    if (!progress) return;

    const normalizedStatus = ['payment_pending', 'paid'].includes(status) ? 'payment' : status;
    const order = ['pending', 'payment', 'confirmed', 'preparing', 'ready', 'completed'];
    const currentIndex = order.indexOf(normalizedStatus);
    progress.classList.toggle('is-cancelled', status === 'cancelled');

    progress.querySelectorAll('.status-progress-step').forEach((step, index) => {
        step.classList.toggle('is-complete', currentIndex >= 0 && index < currentIndex);
        step.classList.toggle('is-current', currentIndex >= 0 && index === currentIndex);
    });
}

function closeStatusModal(force = false) {
    if (statusChangeInProgress && !force) return;
    const modal = document.getElementById('statusModal');
    if (!modal) return;

    modal.classList.remove('is-open');
    if (!document.getElementById('orderModal')?.classList.contains('is-open')) {
        document.body.style.overflow = '';
    }

    const triggerEl = pendingStatusChange?.triggerEl;
    pendingStatusChange = null;
    if (triggerEl && document.body.contains(triggerEl)) setTimeout(() => triggerEl.focus(), 50);
}

/**
 * Refleja un cambio de etapa en la tarjeta de la lista sin recargar la
 * página -- usado tanto por el selector de etapa como por "Confirmar pago
 * recibido" del comprobante, para que ninguno de los dos deje la lista
 * mostrando una etapa vieja mientras el modal ya muestra la nueva.
 */
function applyOrderStatusToRow(orderId, newStatus) {
    const triggerEl = document.getElementById('status-button-' + orderId);
    if (triggerEl) {
        triggerEl.className = 'order-stage-button status-' + newStatus;
        triggerEl.setAttribute('data-current-status', newStatus);
        const valueEl = triggerEl.querySelector('.order-stage-value');
        if (valueEl) valueEl.textContent = STATUS_LABELS[newStatus] || newStatus;
        const iconEl = triggerEl.querySelector('.order-stage-icon');
        if (iconEl) iconEl.innerHTML = `<i class="fas ${STATUS_META[newStatus]?.icon || 'fa-circle'}"></i>`;
        if (!(STATUS_TRANSITIONS[newStatus] || []).length) {
            triggerEl.classList.add('is-locked');
            triggerEl.removeAttribute('onclick');
            triggerEl.removeAttribute('aria-haspopup');
            const actionEl = triggerEl.querySelector('.order-stage-action');
            if (actionEl) actionEl.innerHTML = '<i class="fas fa-lock"></i> Estado final';
        }
    }
    const card = document.getElementById('order-row-' + orderId);
    if (card) {
        Array.from(card.classList)
            .filter(className => className.startsWith('status-'))
            .forEach(className => card.classList.remove(className));
        card.classList.add('status-' + newStatus);
    }
}

function confirmOrderStatusChange() {
    if (!pendingStatusChange?.newStatus || statusChangeInProgress) return;

    const { orderId, newStatus } = pendingStatusChange;
    const confirmButton = document.getElementById('confirmStatusButton');
    statusChangeInProgress = true;
    confirmButton.disabled = true;
    confirmButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    fetch(`/admin/orders/${orderId}/status`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ status: newStatus }),
    })
    .then(async response => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'No se pudo actualizar la etapa');
        return data;
    })
    .then(data => {
        if (data.success) {
            applyOrderStatusToRow(orderId, newStatus);
            statusChangeInProgress = false;
            closeStatusModal(true);
            showToast(`Pedido actualizado a “${STATUS_LABELS[newStatus] || newStatus}”`);
        } else {
            throw new Error(data.message || 'No se pudo actualizar la etapa');
        }
    })
    .catch(error => {
        statusChangeInProgress = false;
        confirmButton.disabled = false;
        confirmButton.innerHTML = `<i class="fas ${newStatus === 'cancelled' ? 'fa-ban' : 'fa-check'}"></i> Confirmar: ${esc(STATUS_LABELS[newStatus] || newStatus)}`;
        showToast(error.message || 'No se pudo actualizar la etapa', 'error');
    });
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
            applyOrderStatusToRow(orderId, 'paid');
            showToast('Pago confirmado. Se avisó al cliente por WhatsApp.');
            showOrderDetails(orderId);
        } else {
            showToast(data.message || 'No se pudo confirmar el pago', 'error');
        }
    })
    .catch(() => showToast('Error al confirmar el pago', 'error'));
}

/**
 * Borrado definitivo (no es lo mismo que cancelar): saca el pedido para
 * siempre e intenta devolver el stock reservado (ver
 * AdminController::destroyOrder). No hay forma de deshacerlo, por eso pide
 * escribir el número de pedido en vez de un simple confirm().
 */
function confirmDeleteOrder(orderId, orderNumber) {
    const typed = prompt(`Esto elimina el pedido ${orderNumber} para siempre (se devuelve el stock reservado si lo había). Esta acción no se puede deshacer.\n\nEscribí el número de pedido para confirmar:`);
    if (typed === null) return;
    if (typed.trim() !== orderNumber) {
        showToast('No coincide el número de pedido, no se eliminó nada.', 'error');
        return;
    }

    fetch(`/admin/orders/${orderId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    })
    .then(async response => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo eliminar el pedido');

        return data;
    })
    .then(() => {
        document.getElementById('order-row-' + orderId)?.remove();
        showToast(`Pedido ${orderNumber} eliminado.`);
    })
    .catch(error => showToast(error.message || 'No se pudo eliminar el pedido', 'error'));
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
document.getElementById('statusModal')?.addEventListener('click', e => {
    if (e.target.id === 'statusModal') closeStatusModal();
});
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (document.getElementById('statusModal')?.classList.contains('is-open')) closeStatusModal();
    else if (document.getElementById('orderModal')?.classList.contains('is-open')) closeOrderModal();
});

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
        const modalOpen = document.getElementById('orderModal')?.classList.contains('is-open')
            || document.getElementById('statusModal')?.classList.contains('is-open');
        if (!modalOpen) {
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
