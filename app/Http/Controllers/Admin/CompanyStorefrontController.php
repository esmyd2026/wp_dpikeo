<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CompanyStorefrontController extends Controller
{
    public function edit(Company $company)
    {
        $this->authorizeCompany($company);
        $settings = $company->storefrontSetting()->firstOrCreate([], [
            'primary_color' => '#E85D04', 'secondary_color' => '#7C2D12',
            'accent_color' => '#FFD166', 'storefront_enabled' => true,
        ]);

        return view('admin.empresas.storefront', compact('company', 'settings'));
    }

    public function update(Request $request, Company $company)
    {
        $this->authorizeCompany($company);
        $settings = $company->storefrontSetting()->firstOrCreate();
        $validated = $request->validate([
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'custom_domain' => ['nullable', 'string', 'max:190', Rule::unique('company_storefront_settings')->ignore($settings->id)],
            'google_maps_api_key' => ['nullable', 'string', 'max:500'],
            'google_maps_map_id' => ['nullable', 'string', 'max:120'],
            'google_oauth_client_id' => ['nullable', 'string', 'max:500'],
            'google_oauth_client_secret' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png,jpg,jpeg,webp', 'max:1024'],
            'remove_favicon' => ['nullable', 'boolean'],
            'hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'storefront_enabled' => ['nullable', 'boolean'],
        ]);

        foreach (['logo' => 'logo_path', 'hero_image' => 'hero_image_path'] as $input => $column) {
            if ($request->hasFile($input)) {
                $path = $request->file($input)->store("company-branding/{$company->uuid}", 'public');
                $validated[$column] = 'storage/'.$path;
            }
            unset($validated[$input]);
        }

        $faviconToDelete = null;
        if ($request->boolean('remove_favicon') || $request->hasFile('favicon')) {
            $faviconToDelete = $settings->favicon_path;
            $validated['favicon_path'] = null;
        }
        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store("company-branding/{$company->uuid}", 'public');
            $validated['favicon_path'] = 'storage/'.$path;
        }
        unset($validated['favicon'], $validated['remove_favicon']);

        $validated['custom_domain'] = filled($validated['custom_domain'] ?? null)
            ? strtolower(preg_replace('#^https?://#', '', trim($validated['custom_domain'], ' /'))) : null;
        $validated['storefront_enabled'] = $request->boolean('storefront_enabled');
        foreach (['google_maps_api_key', 'google_oauth_client_id', 'google_oauth_client_secret'] as $secretField) {
            if (! filled($validated[$secretField] ?? null)) {
                unset($validated[$secretField]);
            }
        }
        $settings->update($validated);
        $this->deletePublicAsset($faviconToDelete);

        return back()->with('success', 'Diseño e integraciones de la tienda actualizados.');
    }

    private function authorizeCompany(Company $company): void
    {
        abort_unless(auth()->user()?->canAccessCompany($company), 403, 'No tienes acceso a esta empresa.');
    }

    private function deletePublicAsset(?string $path): void
    {
        if (! filled($path) || preg_match('#^https?://#i', $path)) {
            return;
        }

        $storagePath = preg_replace('#^/?storage/#', '', $path);
        if (filled($storagePath)) {
            Storage::disk('public')->delete($storagePath);
        }
    }
}
