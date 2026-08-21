@extends('admin.layouts.app')

@section('header', 'Billetera')

@section('content')
@php $b = $billing; @endphp
<style>
    .wallet-page { max-width: 900px; margin: 0 auto; }
    .wallet-card {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
        padding: 1rem 1.15rem; margin-bottom: .85rem;
        box-shadow: 0 1px 3px rgba(15,23,42,.04);
    }
    .wallet-card h2 { margin: 0 0 .5rem; font-size: 1rem; font-weight: 700; color: #0f172a; }
    .receipt-item {
        padding: .75rem 0; border-bottom: 1px solid #f1f5f9; font-size: .84rem;
    }
    .receipt-item:last-child { border-bottom: none; }
    .receipt-status {
        font-size: .68rem; font-weight: 700; padding: .15rem .5rem; border-radius: 999px;
    }
    .receipt-status.pending { background: #fef3c7; color: #92400e; }
    .receipt-status.approved { background: #dcfce7; color: #166534; }
    .receipt-status.rejected { background: #fee2e2; color: #991b1b; }
</style>

<div class="wallet-page">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @include('admin.partials.plan-limits-widget', ['planLimits' => $planLimitsSnapshot])

    @include('admin.partials.platform-billing-reminder', [
        'platformBillingSnapshot' => $b,
        'showWalletLink' => false,
    ])

    @perm('wallet.submit')
    <div class="wallet-card">
        <h2><i class="fas fa-upload me-1 text-success"></i> Enviar comprobante de pago</h2>
        <p class="text-muted small">Adjunta transferencia, depósito o pago en efectivo. Quedará en revisión.</p>
        <form method="POST" action="{{ route('admin.wallet.receipts.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small">Concepto</label>
                    <select name="payment_for" class="form-select form-select-sm" required>
                        @foreach($paymentForOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Forma de pago</label>
                    <select name="payment_method" class="form-select form-select-sm" required>
                        @foreach($paymentMethods as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Monto (USD)</label>
                    <input type="number" name="amount" step="0.01" min="0.01" class="form-control form-control-sm" required value="{{ old('amount') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Fecha del pago</label>
                    <input type="datetime-local" name="paid_at" class="form-control form-control-sm" required value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="col-md-8">
                    <label class="form-label small">Comprobante (PDF o imagen)</label>
                    <input type="file" name="receipt" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf,.webp">
                </div>
                <div class="col-12">
                    <label class="form-label small">Notas (opcional)</label>
                    <textarea name="client_notes" class="form-control form-control-sm" rows="2" placeholder="Referencia bancaria, número de transacción...">{{ old('client_notes') }}</textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-success btn-sm mt-3"><i class="fas fa-paper-plane me-1"></i>Enviar comprobante</button>
        </form>
    </div>
    @endperm

    <div class="wallet-card">
        <h2><i class="fas fa-list me-1"></i> Historial de pagos</h2>
        @forelse($receipts as $receipt)
            <article class="receipt-item">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <strong>${{ number_format($receipt->amount, 2) }}</strong>
                        · {{ $receipt->paymentForLabel() }}
                        · {{ $receipt->paymentMethodLabel() }}
                        · {{ $receipt->paid_at->format('d/m/Y H:i') }}
                        <div class="text-muted small mt-1">
                            Enviado {{ $receipt->created_at->format('d/m/Y H:i') }}
                            @if($receipt->user) por {{ $receipt->user->name }} @endif
                        </div>
                        @if($receipt->client_notes)
                            <div class="small mt-1">{{ $receipt->client_notes }}</div>
                        @endif
                        @if($receipt->review_notes && $receipt->status === 'rejected')
                            <div class="small text-danger mt-1">Motivo: {{ $receipt->review_notes }}</div>
                        @endif
                    </div>
                    <div class="text-end">
                        <span class="receipt-status {{ $receipt->status }}">{{ $receipt->statusLabel() }}</span>
                        @if($receipt->receiptUrl())
                            <div class="mt-1"><a href="{{ $receipt->receiptUrl() }}" target="_blank" class="small">Ver comprobante</a></div>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <p class="text-muted small mb-0">Aún no has enviado comprobantes.</p>
        @endforelse
    </div>
</div>
@endsection
