<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformPaymentReceipt;
use App\Models\PricingSetting;
use App\Services\DemoClienteService;
use App\Services\OrderPdfSettingsService;
use App\Services\PlanFeatureService;
use App\Services\PlanLimitsService;
use App\Services\PlatformBillingService;
use App\Services\PricingService;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingSettingsController extends Controller
{
    public function edit(PricingService $pricing, PlanLimitsService $planLimits, PlatformBillingService $billing, PlanFeatureService $planFeatures, OrderPdfSettingsService $orderPdfSettings, DemoClienteService $demoCliente): View
    {
        $settings = $pricing->settings();
        $categories = config('pricing.meta_rates.per_conversation', []);
        $categoryKeys = PricingSetting::ALL_CATEGORIES;
        $enabledCategories = $settings->enabledCategories();
        $plans = $planLimits->allPlans();
        $planLimitsSnapshot = $planLimits->snapshot();
        $platformLimits = $planLimits->platformLimitsRaw();
        $user = auth()->user();
        $canManageBulkOrder = $user && app(PermissionService::class)->userCan($user, 'bulk_orders.manage');

        return view('admin.pricing-settings.edit', [
            'settings' => $settings,
            'categories' => $categories,
            'categoryKeys' => $categoryKeys,
            'enabledCategories' => $enabledCategories,
            'plans' => $plans,
            'planLimitsSnapshot' => $planLimitsSnapshot,
            'platformLimits' => $platformLimits,
            'billing' => $billing->billingSettings(),
            'suspensions' => $billing->suspensionSettings(),
            'platformBillingSnapshot' => $billing->dashboardSnapshot(),
            'paymentReceipts' => $billing->receiptsForWallet(100),
            'pendingReceiptsCount' => $billing->pendingReceiptsCount(),
            'bulkWebOrderEnabled' => $planFeatures->isPlatformBulkWebOrderEnabled(),
            'canManageBulkOrder' => $canManageBulkOrder,
            'orderPdfSettings' => $orderPdfSettings->get(),
            'demoClienteOptions' => $demoCliente->options(),
            'activeDemoCliente' => $demoCliente->activeKey(),
        ]);
    }

    public function update(Request $request, PlanLimitsService $planLimits, OrderPdfSettingsService $orderPdfSettings): RedirectResponse
    {
        $section = $request->input('_section', 'all');

        if ($section === 'capacidades') {
            return $this->updateCapacidades($request, $planLimits);
        }

        if ($section === 'order_pdf') {
            return $this->updateOrderPdf($request, $orderPdfSettings);
        }

        if ($section === 'demo_cliente') {
            return $this->updateDemoCliente($request);
        }

        if ($section === 'meta') {
            return $this->updateMeta($request);
        }

        return $this->updateAll($request, $planLimits);
    }

    private function updateCapacidades(Request $request, PlanLimitsService $planLimits): RedirectResponse
    {
        $validated = $request->validate([
            'subscription_plan' => ['required', 'string', 'in:starter,pro,enterprise'],
            'max_products_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'max_categories_limit' => ['required', 'integer', 'min:0', 'max:10000'],
            'storage_gb_limit' => ['required', 'numeric', 'min:0', 'max:10000'],
            'storage_gb_used' => ['required', 'numeric', 'min:0', 'max:10000'],
        ]);

        $planLimits->savePlatformLimits([
            'subscription_plan' => $validated['subscription_plan'],
            'max_products_limit' => $validated['max_products_limit'],
            'max_categories_limit' => $validated['max_categories_limit'],
            'storage_gb_limit' => $validated['storage_gb_limit'],
            'storage_gb_used' => $validated['storage_gb_used'],
        ]);

        if ($request->user() && app(PermissionService::class)->userCan($request->user(), 'bulk_orders.manage')) {
            $planLimits->savePlatformLimits([
                'bulk_web_order_enabled' => $request->boolean('bulk_web_order_enabled'),
            ]);
        }

        return redirect()
            ->to(route('admin.pricing-settings.edit') . '#capacidades')
            ->with('success', 'Plan y límites de capacidad guardados.');
    }

    private function updateOrderPdf(Request $request, OrderPdfSettingsService $orderPdfSettings): RedirectResponse
    {
        $validated = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'ruc' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'document_title' => ['required', 'string', 'max:120'],
            'document_subtitle' => ['nullable', 'string', 'max:255'],
            'legal_footer' => ['nullable', 'string', 'max:2000'],
            'iva_rate_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $orderPdfSettings->save(array_merge($validated, [
            'prices_include_iva' => $request->boolean('prices_include_iva'),
        ]));

        return redirect()
            ->to(route('admin.pricing-settings.edit') . '#order-pdf')
            ->with('success', 'Datos del PDF de orden guardados.');
    }

    private function updateDemoCliente(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'active_demo_cliente' => ['nullable', 'string', 'max:64'],
        ]);

        app(DemoClienteService::class)->saveActiveKey($validated['active_demo_cliente'] ?? null);

        return redirect()
            ->to(route('admin.pricing-settings.edit') . '#demo-cliente')
            ->with('success', 'Demo de catálogo activa actualizada.');
    }

    private function updateMeta(Request $request): RedirectResponse
    {
        $enabled = array_values(array_intersect(
            PricingSetting::ALL_CATEGORIES,
            $request->input('enabled_categories', [])
        ));

        if ($enabled === []) {
            return back()
                ->withInput()
                ->with('error', 'Debe habilitar al menos un tipo de conversación.');
        }

        $rules = [
            'meta_markup' => ['required', 'numeric', 'min:1', 'max:3'],
            'region' => ['required', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3'],
            'rates' => ['required', 'array'],
            'enabled_categories' => ['nullable', 'array'],
            'enabled_categories.*' => ['in:' . implode(',', PricingSetting::ALL_CATEGORIES)],
        ];

        foreach (PricingSetting::ALL_CATEGORIES as $key) {
            $rules["rates.{$key}.min"] = ['required', 'numeric', 'min:0'];
            $rules["rates.{$key}.max"] = ['required', 'numeric', 'min:0', "gte:rates.{$key}.min"];
        }

        $validated = $request->validate($rules);

        PricingSetting::current()->update([
            'meta_markup' => $validated['meta_markup'],
            'region' => $validated['region'],
            'currency' => strtoupper($validated['currency']),
            'rates' => PricingSetting::normalizeRates($validated['rates']),
            'enabled_categories' => $enabled,
        ]);

        return redirect()
            ->to(route('admin.pricing-settings.edit') . '#costos-meta')
            ->with('success', 'Costos Meta guardados correctamente.');
    }

    private function updateAll(Request $request, PlanLimitsService $planLimits): RedirectResponse
    {
        $enabled = array_values(array_intersect(
            PricingSetting::ALL_CATEGORIES,
            $request->input('enabled_categories', [])
        ));

        if ($enabled === []) {
            return back()
                ->withInput()
                ->with('error', 'Debe habilitar al menos un tipo de conversación.');
        }

        $rules = [
            'meta_markup' => ['required', 'numeric', 'min:1', 'max:3'],
            'region' => ['required', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3'],
            'rates' => ['required', 'array'],
            'enabled_categories' => ['nullable', 'array'],
            'enabled_categories.*' => ['in:' . implode(',', PricingSetting::ALL_CATEGORIES)],
            'subscription_plan' => ['required', 'string', 'in:starter,pro,enterprise'],
            'max_products_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'max_categories_limit' => ['required', 'integer', 'min:0', 'max:10000'],
            'storage_gb_limit' => ['required', 'numeric', 'min:0', 'max:10000'],
            'storage_gb_used' => ['required', 'numeric', 'min:0', 'max:10000'],
        ];

        foreach (PricingSetting::ALL_CATEGORIES as $key) {
            $rules["rates.{$key}.min"] = ['required', 'numeric', 'min:0'];
            $rules["rates.{$key}.max"] = ['required', 'numeric', 'min:0', "gte:rates.{$key}.min"];
        }

        $validated = $request->validate($rules);

        $settings = PricingSetting::current();
        $settings->update([
            'meta_markup' => $validated['meta_markup'],
            'region' => $validated['region'],
            'currency' => strtoupper($validated['currency']),
            'rates' => PricingSetting::normalizeRates($validated['rates']),
            'enabled_categories' => $enabled,
        ]);

        $planLimits->savePlatformLimits([
            'subscription_plan' => $validated['subscription_plan'],
            'max_products_limit' => $validated['max_products_limit'],
            'max_categories_limit' => $validated['max_categories_limit'],
            'storage_gb_limit' => $validated['storage_gb_limit'],
            'storage_gb_used' => $validated['storage_gb_used'],
        ]);

        return redirect()
            ->route('admin.pricing-settings.edit')
            ->with('success', 'Parámetros de plataforma guardados correctamente.');
    }

    public function updateBilling(Request $request, PlatformBillingService $billing): RedirectResponse
    {
        if ($request->boolean('reactivate_all')) {
            $billing->clearAllSuspensions();

            return redirect()
                ->to(route('admin.pricing-settings.edit') . '#billing')
                ->with('success', 'Servicio reactivado: todas las suspensiones fueron desactivadas.');
        }

        $validated = $request->validate([
            'plan_due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'plan_amount' => ['required', 'numeric', 'min:0'],
            'meta_due_day' => ['required', 'integer', 'min:1', 'max:28'],
        ]);

        $billing->saveBillingAndSuspensions(
            [
                'plan_due_day' => (int) $validated['plan_due_day'],
                'plan_amount' => (float) $validated['plan_amount'],
                'meta_due_day' => (int) $validated['meta_due_day'],
            ],
            [
                'suspend_bot' => $request->has('suspend_bot'),
                'suspend_chat' => $request->has('suspend_chat'),
                'suspend_orders' => $request->has('suspend_orders'),
                'auto_suspend_on_overdue' => $request->has('auto_suspend_on_overdue'),
            ]
        );

        return redirect()
            ->to(route('admin.pricing-settings.edit') . '#billing')
            ->with('success', 'Facturación y suspensiones actualizadas.');
    }

    public function reviewReceipt(Request $request, PlatformPaymentReceipt $receipt, PlatformBillingService $billing): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $billing->reviewReceipt(
            $receipt,
            $validated['status'],
            auth()->user(),
            $validated['review_notes'] ?? null
        );

        return redirect()
            ->to(route('admin.pricing-settings.edit') . '#billing')
            ->with('success', 'Comprobante marcado como ' . ($validated['status'] === 'approved' ? 'aprobado' : 'rechazado') . '.');
    }
}
