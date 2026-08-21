@php
    $snap = $platformBillingSnapshot ?? [];
@endphp

<section class="platform-section" id="billing">
    <div class="platform-section-head">
        <h2>💳 Facturación y billetera</h2>
        <p>Fechas de pago, montos y revisión de comprobantes del cliente.</p>
    </div>
    <div class="platform-section-body">
        {{-- Estado actual --}}
        <div class="billing-status-panel mb-4">
            <div class="billing-status-title">Estado del mes actual</div>
            <div class="billing-status-grid">
                <div class="billing-status-item {{ ($snap['plan_paid'] ?? false) ? 'is-ok' : (($snap['plan_overdue'] ?? false) ? 'is-danger' : 'is-warn') }}">
                    <span class="lbl">Plan plataforma</span>
                    <span class="val">${{ number_format($snap['plan_amount'] ?? 0, 0) }}</span>
                    <span class="sub">Vence {{ $snap['plan_due_label'] ?? '—' }}
                        · {{ ($snap['plan_paid'] ?? false) ? '✓ Pagado' : (($snap['plan_overdue'] ?? false) ? '✗ Vencido' : '○ Pendiente') }}
                    </span>
                </div>
                <div class="billing-status-item {{ ($snap['meta_paid'] ?? false) ? 'is-ok' : (($snap['meta_overdue'] ?? false) ? 'is-danger' : 'is-warn') }}">
                    <span class="lbl">Mensajería Meta</span>
                    <span class="val">Estimado</span>
                    <span class="sub">Vence {{ $snap['meta_due_label'] ?? '—' }}
                        · {{ ($snap['meta_paid'] ?? false) ? '✓ Pagado' : (($snap['meta_overdue'] ?? false) ? '✗ Vencido' : '○ Pendiente') }}
                    </span>
                </div>
            </div>
            @if($snap['any_suspended'] ?? false)
                <div class="billing-status-alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Servicio suspendido ahora:</strong>
                        @if($snap['bot_suspended'] ?? false) bot @endif
                        @if($snap['chat_suspended'] ?? false) · chat @endif
                        @if($snap['orders_suspended'] ?? false) · pedidos @endif
                        <br>
                        <span class="text-muted">
                            Config actual:
                            bot={{ ($snap['suspensions_raw']['suspend_bot'] ?? false) ? 'off' : 'on' }},
                            chat={{ ($snap['suspensions_raw']['suspend_chat'] ?? false) ? 'off' : 'on' }},
                            pedidos={{ ($snap['suspensions_raw']['suspend_orders'] ?? false) ? 'off' : 'on' }},
                            mora auto={{ ($snap['suspensions_raw']['auto_suspend_on_overdue'] ?? false) ? 'sí' : 'no' }}.
                            @if($snap['auto_overdue'] ?? false)
                                Motivo: mora automática (sin pago aprobado del mes).
                            @elseif(($snap['manual_suspend_bot'] ?? false) || ($snap['manual_suspend_chat'] ?? false) || ($snap['manual_suspend_orders'] ?? false))
                                Motivo: suspensiones manuales activas. Usa «Reactivar todo el servicio» o desmarca y guarda.
                            @endif
                        </span>
                    </div>
                </div>
            @else
                <div class="billing-status-ok"><i class="fas fa-check-circle"></i> Sin suspensiones activas — servicio operativo.</div>
            @endif
        </div>

        {{-- Formulario: fechas y montos --}}
        <form action="{{ route('admin.pricing-settings.billing.update') }}" method="POST" class="billing-subsection">
            @csrf
            @method('PUT')

            <h3 class="billing-subsection-title">Fechas y montos de pago</h3>
            <p class="billing-subsection-desc">El cliente ve estos datos en la billetera. La mora se calcula si no hay comprobante <strong>aprobado</strong> del mes tras la fecha de vencimiento.</p>

            <div class="platform-grid mb-3">
                <div class="platform-field">
                    <label for="plan_due_day">Día de pago del plan (c/mes)</label>
                    <input type="number" id="plan_due_day" name="plan_due_day" min="1" max="28" required
                        value="{{ old('plan_due_day', $billing['plan_due_day'] ?? 5) }}">
                </div>
                <div class="platform-field">
                    <label for="plan_amount">Monto plan (USD)</label>
                    <input type="number" id="plan_amount" name="plan_amount" min="0" step="0.01" required
                        value="{{ old('plan_amount', $billing['plan_amount'] ?? 90) }}">
                </div>
                <div class="platform-field">
                    <label for="meta_due_day">Día de pago Meta (c/mes)</label>
                    <input type="number" id="meta_due_day" name="meta_due_day" min="1" max="28" required
                        value="{{ old('meta_due_day', $billing['meta_due_day'] ?? 10) }}">
                </div>
            </div>

            <h3 class="billing-subsection-title mt-4">Suspensiones del servicio</h3>
            <p class="billing-subsection-desc">Las suspensiones <strong>manuales</strong> bloquean a <strong>todos los usuarios</strong> (incluido super admin). Desmarca todas para reactivar. La mora automática solo afecta a usuarios normales; el super admin puede seguir entrando a Parámetros y Billetera.</p>

            <div class="billing-suspension-list mb-3">
                <label class="billing-check-row">
                    <input type="checkbox" name="auto_suspend_on_overdue" value="1"
                        @checked(old('auto_suspend_on_overdue', $suspensions['auto_suspend_on_overdue'] ?? true))>
                    <span>
                        <strong>Suspender automáticamente por mora</strong>
                        <small>Si no hay pago aprobado del mes después del día de vencimiento (plan o Meta).</small>
                    </span>
                </label>
                <label class="billing-check-row">
                    <input type="checkbox" name="suspend_bot" value="1"
                        @checked(old('suspend_bot', $suspensions['suspend_bot'] ?? false))>
                    <span>
                        <strong>Bot sin responder (manual)</strong>
                        <small>El bot deja de contestar; el cliente sí puede escribir.</small>
                    </span>
                </label>
                <label class="billing-check-row">
                    <input type="checkbox" name="suspend_chat" value="1"
                        @checked(old('suspend_chat', $suspensions['suspend_chat'] ?? false))>
                    <span>
                        <strong>Bloquear interfaz de chat</strong>
                        <small>Los agentes no pueden abrir conversaciones en el panel.</small>
                    </span>
                </label>
                <label class="billing-check-row">
                    <input type="checkbox" name="suspend_orders" value="1"
                        @checked(old('suspend_orders', $suspensions['suspend_orders'] ?? false))>
                    <span>
                        <strong>Bloquear módulo de pedidos</strong>
                        <small>El listado y gestión de pedidos queda inaccesible.</small>
                    </span>
                </label>
            </div>

            <div class="platform-save-bar billing-save-bar">
                <p class="text-xs text-gray-500 mb-0">Los cambios aplican de inmediato al guardar.</p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg border-0">
                        <i class="fas fa-save"></i> Guardar facturación y suspensiones
                    </button>
                    @if(($snap['any_suspended'] ?? false))
                        <button type="submit" name="reactivate_all" value="1" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white text-emerald-700 text-sm font-semibold rounded-lg border border-emerald-300"
                            onclick="return confirm('¿Reactivar bot, chat y pedidos ahora? Se desactivarán todas las suspensiones.');">
                            <i class="fas fa-play-circle"></i> Reactivar todo el servicio
                        </button>
                    @endif
                </div>
            </div>
        </form>

        {{-- Comprobantes (sin formulario padre) --}}
        <div class="billing-subsection billing-receipts-block">
            <h3 class="billing-subsection-title">
                Comprobantes del cliente
                @if($pendingReceiptsCount > 0)
                    <span class="text-amber-600 fw-normal">({{ $pendingReceiptsCount }} pendientes)</span>
                @endif
            </h3>
            @if($paymentReceipts->isEmpty())
                <p class="text-muted small mb-0">Sin comprobantes enviados desde la billetera.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle billing-receipts-table">
                        <thead>
                            <tr>
                                <th>Fecha pago</th>
                                <th>Concepto</th>
                                <th>Método</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paymentReceipts as $receipt)
                                <tr>
                                    <td>{{ $receipt->paid_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $receipt->paymentForLabel() }}</td>
                                    <td>{{ $receipt->paymentMethodLabel() }}</td>
                                    <td>${{ number_format($receipt->amount, 2) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $receipt->status === 'approved' ? 'success' : ($receipt->status === 'rejected' ? 'danger' : 'warning') }}">
                                            {{ $receipt->statusLabel() }}
                                        </span>
                                    </td>
                                    <td class="billing-receipt-actions">
                                        @if($receipt->receiptUrl())
                                            <a href="{{ $receipt->receiptUrl() }}" target="_blank" class="btn btn-outline-secondary btn-sm">Ver</a>
                                        @endif
                                        @if($receipt->status === 'pending')
                                            <form method="POST" action="{{ route('admin.platform-receipts.review', $receipt) }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="btn btn-success btn-sm">Aprobar</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.platform-receipts.review', $receipt) }}" class="d-inline-flex align-items-center gap-1 flex-wrap mt-1">
                                                @csrf
                                                <input type="hidden" name="status" value="rejected">
                                                <input type="text" name="review_notes" class="form-control form-control-sm" style="width:120px;" placeholder="Motivo">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Rechazar</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</section>

<style>
    .billing-status-panel {
        background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1rem;
    }
    .billing-status-title { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: .65rem; }
    .billing-status-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .65rem; }
    @media (max-width: 640px) { .billing-status-grid { grid-template-columns: 1fr; } }
    .billing-status-item {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: .75rem .85rem;
    }
    .billing-status-item .lbl { display: block; font-size: .72rem; font-weight: 700; color: #64748b; text-transform: uppercase; }
    .billing-status-item .val { display: block; font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: .1rem 0; }
    .billing-status-item .sub { display: block; font-size: .75rem; color: #475569; }
    .billing-status-item.is-ok { border-color: #a7f3d0; background: #ecfdf5; }
    .billing-status-item.is-warn { border-color: #fde68a; background: #fffbeb; }
    .billing-status-item.is-danger { border-color: #fecaca; background: #fef2f2; }
    .billing-status-alert {
        display: flex; gap: .65rem; margin-top: .75rem; padding: .75rem .85rem;
        background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; font-size: .82rem; color: #991b1b;
    }
    .billing-status-ok {
        margin-top: .75rem; padding: .65rem .85rem; background: #ecfdf5; border: 1px solid #a7f3d0;
        border-radius: 10px; font-size: .82rem; color: #047857; font-weight: 600;
    }
    .billing-subsection { margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; }
    .billing-subsection:first-of-type { margin-top: 0; padding-top: 0; border-top: none; }
    .billing-receipts-block { border-top: 1px solid #e5e7eb; margin-top: 1.5rem; padding-top: 1.25rem; }
    .billing-subsection-title { font-size: .92rem; font-weight: 700; color: #0f172a; margin: 0 0 .35rem; }
    .billing-subsection-desc { font-size: .8rem; color: #64748b; margin: 0 0 .85rem; max-width: 640px; }
    .billing-suspension-list { display: flex; flex-direction: column; gap: .5rem; }
    .billing-check-row {
        display: flex; align-items: flex-start; gap: .65rem; padding: .65rem .75rem;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; cursor: pointer; margin: 0;
    }
    .billing-check-row:hover { border-color: #cbd5e1; background: #fafbfc; }
    .billing-check-row input[type="checkbox"] {
        width: 18px; height: 18px; margin-top: .15rem; flex-shrink: 0; accent-color: #059669;
    }
    .billing-check-row span { display: flex; flex-direction: column; gap: .15rem; font-size: .84rem; color: #334155; }
    .billing-check-row span strong { color: #0f172a; font-size: .875rem; }
    .billing-check-row span small { font-size: .75rem; color: #64748b; line-height: 1.35; }
    .billing-save-bar { margin-top: 1rem; border: none; padding: 0; background: transparent; }
    .billing-receipts-table { font-size: .84rem; margin-bottom: 0; }
    .billing-receipt-actions { white-space: nowrap; }
</style>
