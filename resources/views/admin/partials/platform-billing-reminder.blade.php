@php $b = $platformBillingSnapshot ?? null; @endphp
@if($b)
<div class="billing-reminder {{ ($b['any_suspended'] ?? false) ? 'is-suspended' : '' }} {{ ($b['plan_overdue'] ?? false) || ($b['meta_overdue'] ?? false) ? 'is-overdue' : '' }}">
    <style>
        .billing-reminder {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
            padding: 1rem 1.15rem; margin-bottom: 1rem;
            display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: center;
            box-shadow: 0 1px 3px rgba(15,23,42,.04);
        }
        .billing-reminder.is-overdue { border-color: #fde68a; background: linear-gradient(90deg, #fffbeb, #fff); }
        .billing-reminder.is-suspended { border-color: #fecaca; background: linear-gradient(90deg, #fef2f2, #fff); }
        .billing-reminder h3 { margin: 0 0 .35rem; font-size: .95rem; font-weight: 700; color: #0f172a; }
        .billing-reminder-grid { display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; font-size: .82rem; color: #475569; }
        .billing-reminder-grid strong { color: #0f172a; }
        .billing-reminder .badge-ok { color: #047857; font-weight: 700; }
        .billing-reminder .badge-warn { color: #b45309; font-weight: 700; }
        .billing-reminder .badge-danger { color: #dc2626; font-weight: 700; }
        @media (max-width: 640px) { .billing-reminder { grid-template-columns: 1fr; } }
    </style>
    <div>
        <h3><i class="fas fa-wallet me-1 text-success"></i> Pagos de plataforma</h3>
        <div class="billing-reminder-grid">
            <span>Plan <strong>${{ number_format($b['plan_amount'], 0) }}</strong> · vence <strong>{{ $b['plan_due_label'] }}</strong>
                @if($b['plan_paid'])<span class="badge-ok"> · pagado</span>@elseif($b['plan_overdue'])<span class="badge-danger"> · vencido</span>@else<span class="badge-warn"> · pendiente</span>@endif
            </span>
            <span>Meta estimado
                @if($b['meta_estimate'])<strong>${{ number_format($b['meta_estimate'], 2) }}</strong>@else<strong>—</strong>@endif
                · vence <strong>{{ $b['meta_due_label'] }}</strong>
                @if($b['meta_paid'])<span class="badge-ok"> · pagado</span>@elseif($b['meta_overdue'])<span class="badge-danger"> · vencido</span>@else<span class="badge-warn"> · pendiente</span>@endif
            </span>
        </div>
        @if($b['any_suspended'])
            <p class="mb-0 mt-2 small text-danger fw-semibold">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Servicio suspendido:
                @if($b['bot_suspended']) bot @endif
                @if($b['chat_suspended']) · chat @endif
                @if($b['orders_suspended']) · pedidos @endif
                @if($b['auto_overdue'] ?? false)
                    — regulariza el pago del mes en Billetera.
                @elseif(($b['manual_suspend_bot'] ?? false) || ($b['manual_suspend_chat'] ?? false) || ($b['manual_suspend_orders'] ?? false))
                    — contacta al administrador para reactivar el servicio.
                @endif
            </p>
        @endif
    </div>
    @if($showWalletLink ?? true)
        @perm('wallet.view')
            <a href="{{ route('admin.wallet.index') }}" class="btn btn-success btn-sm">
                <i class="fas fa-file-invoice-dollar me-1"></i> Ir a billetera
            </a>
        @endperm
    @endif
</div>
@endif
