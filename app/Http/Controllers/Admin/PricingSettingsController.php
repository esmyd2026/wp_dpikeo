<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingSetting;
use App\Services\OrderPdfSettingsService;
use App\Services\PricingService;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingSettingsController extends Controller
{
    public function edit(PricingService $pricing, OrderPdfSettingsService $orderPdfSettings): View
    {
        $settings = $pricing->settings();
        $categories = config('pricing.meta_rates.per_conversation', []);
        $categoryKeys = PricingSetting::ALL_CATEGORIES;
        $enabledCategories = $settings->enabledCategories();
        $user = auth()->user();
        $canManageBulkOrder = $user && app(PermissionService::class)->userCan($user, 'bulk_orders.manage');

        return view('admin.pricing-settings.edit', [
            'settings' => $settings,
            'categories' => $categories,
            'categoryKeys' => $categoryKeys,
            'enabledCategories' => $enabledCategories,
            'canManageBulkOrder' => $canManageBulkOrder,
            'orderPdfSettings' => $orderPdfSettings->get(),
        ]);
    }

    public function update(Request $request, OrderPdfSettingsService $orderPdfSettings): RedirectResponse
    {
        $section = $request->input('_section', 'all');

        if ($section === 'order_pdf') {
            return $this->updateOrderPdf($request, $orderPdfSettings);
        }

        return $this->updateMeta($request);
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
}
