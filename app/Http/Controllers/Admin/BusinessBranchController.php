<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessBranch;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BusinessBranchController extends Controller
{
    public function index(): View
    {
        $context = CompanyContext::current();

        return view('admin.branches.index', [
            'profile' => $context->businessProfile,
            'activeCompany' => $context->company,
            'branches' => BusinessBranch::query()
                ->when($context->businessProfileId(), fn ($q) => $q->where('business_profile_id', $context->businessProfileId()))
                ->withCount('orders')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = CompanyContext::current()->businessProfile;
        abort_unless($profile, 422, 'Esta empresa todavía no tiene un número de WhatsApp conectado.');
        $data = $this->validated($request);

        DB::transaction(function () use ($profile, $data) {
            if ($data['is_default']) {
                BusinessBranch::query()->where('business_profile_id', $profile->id)->update(['is_default' => false]);
            }

            BusinessBranch::create(array_merge($data, ['business_profile_id' => $profile->id]));
        });

        return back()->with('success', 'Sucursal creada correctamente.');
    }

    /** El id de la sucursal llega en la URL: sin este chequeo se podría editar/borrar la de otra empresa. */
    private function authorizeBranch(BusinessBranch $branch): void
    {
        $businessProfileId = CompanyContext::current()->businessProfileId();
        abort_unless(!$businessProfileId || (int) $branch->business_profile_id === (int) $businessProfileId, 404);
    }

    public function update(Request $request, BusinessBranch $branch): RedirectResponse
    {
        $this->authorizeBranch($branch);
        $data = $this->validated($request, $branch);

        DB::transaction(function () use ($branch, $data) {
            if ($data['is_default']) {
                BusinessBranch::query()->where('business_profile_id', $branch->business_profile_id)->whereKeyNot($branch->id)->update(['is_default' => false]);
            }
            $branch->update($data);
        });

        return back()->with('success', 'Sucursal actualizada.');
    }

    public function destroy(BusinessBranch $branch): RedirectResponse
    {
        $this->authorizeBranch($branch);

        if ($branch->is_default) {
            return back()->with('error', 'No puedes eliminar la sucursal predeterminada. Marca otra como predeterminada primero.');
        }

        if (BusinessBranch::query()->where('business_profile_id', $branch->business_profile_id)->count() <= 1) {
            return back()->with('error', 'Debe existir al menos una sucursal.');
        }

        if ($branch->orders()->exists()) {
            return back()->with('error', 'No se puede eliminar: la sucursal tiene pedidos históricos. Desactívala en vez de eliminarla.');
        }

        $branch->delete();

        return back()->with('success', 'Sucursal eliminada.');
    }

    private function validated(Request $request, ?BusinessBranch $branch = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:24', 'alpha_dash'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'dine_in_enabled' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_fee_per_unit' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee_km_unit' => ['nullable', 'numeric', 'min:0.1'],
            'delivery_fee_minimum' => ['nullable', 'numeric', 'min:0'],
        ]);

        return [
            'name' => trim($data['name']),
            'code' => strtoupper(trim($data['code'] ?: Str::slug($data['name'], '-'))),
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'address' => filled($data['address'] ?? null) ? trim($data['address']) : null,
            // Siempre existe una sucursal predeterminada por empresa: los
            // pedidos de WhatsApp se asignan allí cuando el cliente no
            // selecciona local. Se compara contra las sucursales de ESTA
            // empresa, nunca contra el total global (si no, la primera
            // sucursal de una empresa nueva no quedaría como predeterminada
            // solo porque otra empresa ya tenía las suyas).
            'is_default' => $request->boolean('is_default') || ($branch?->is_default ?? !BusinessBranch::query()
                ->where('business_profile_id', $branch?->business_profile_id ?? CompanyContext::current()->businessProfileId())
                ->exists()),
            'is_active' => $request->boolean('is_active'),
            'dine_in_enabled' => $request->boolean('dine_in_enabled'),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'delivery_fee_per_unit' => $data['delivery_fee_per_unit'] ?? null,
            'delivery_fee_km_unit' => $data['delivery_fee_km_unit'] ?? null,
            'delivery_fee_minimum' => $data['delivery_fee_minimum'] ?? null,
        ];
    }
}
