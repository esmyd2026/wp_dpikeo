<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformPaymentReceipt;
use App\Services\PlanLimitsService;
use App\Services\PlatformBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function index(PlatformBillingService $billing, PlanLimitsService $planLimits): View
    {
        return view('admin.wallet.index', [
            'receipts' => $billing->receiptsForWallet(),
            'billing' => $billing->dashboardSnapshot(),
            'planLimitsSnapshot' => $planLimits->snapshot(),
            'paymentMethods' => $this->paymentMethods(),
            'paymentForOptions' => $this->paymentForOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_for' => ['required', 'in:plan,meta,both'],
            'payment_method' => ['required', 'in:transferencia,deposito,efectivo'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'paid_at' => ['required', 'date'],
            'client_notes' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,webp'],
        ]);

        $path = null;
        if ($request->hasFile('receipt')) {
            $path = $request->file('receipt')->store('payment-receipts', 'public');
        }

        PlatformPaymentReceipt::create([
            'user_id' => auth()->id(),
            'payment_for' => $validated['payment_for'],
            'payment_method' => $validated['payment_method'],
            'amount' => $validated['amount'],
            'paid_at' => $validated['paid_at'],
            'receipt_path' => $path,
            'status' => PlatformPaymentReceipt::STATUS_PENDING,
            'client_notes' => $validated['client_notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.wallet.index')
            ->with('success', 'Comprobante enviado. Quedará pendiente hasta que el administrador lo revise.');
    }

    /** @return array<string, string> */
    private function paymentMethods(): array
    {
        return [
            PlatformPaymentReceipt::METHOD_TRANSFER => 'Transferencia',
            PlatformPaymentReceipt::METHOD_DEPOSIT => 'Depósito',
            PlatformPaymentReceipt::METHOD_CASH => 'Efectivo',
        ];
    }

    /** @return array<string, string> */
    private function paymentForOptions(): array
    {
        return [
            PlatformPaymentReceipt::FOR_PLAN => 'Plan de plataforma',
            PlatformPaymentReceipt::FOR_META => 'Mensajería Meta',
            PlatformPaymentReceipt::FOR_BOTH => 'Plan + Meta',
        ];
    }
}
