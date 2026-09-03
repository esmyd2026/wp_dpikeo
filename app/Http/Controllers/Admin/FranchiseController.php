<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FranchiseController extends Controller
{
    public function index(): View
    {
        $businessProfileId = CompanyContext::current()->businessProfileId();

        return view('admin.franchises.index', [
            'activeCompany' => CompanyContext::current()->company,
            'franchises' => Franchise::query()
                ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
                ->withCount(['products', 'categories'])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /** El id de la franquicia llega en la URL: sin este chequeo se podría editar/borrar la de otra empresa. */
    private function authorizeFranchise(Franchise $franchise): void
    {
        $businessProfileId = CompanyContext::current()->businessProfileId();
        abort_unless(!$businessProfileId || (int) $franchise->business_profile_id === (int) $businessProfileId, 404);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessProfileId = CompanyContext::current()->businessProfileId();
        abort_unless($businessProfileId, 422, 'Esta empresa todavía no tiene un número de WhatsApp conectado.');

        $data = $this->validated($request);
        $data['business_profile_id'] = $businessProfileId;

        DB::transaction(function () use ($data, $businessProfileId) {
            if ($data['is_default']) {
                Franchise::query()->where('business_profile_id', $businessProfileId)->update(['is_default' => false]);
            }
            Franchise::create($data);
        });

        return back()->with('success', 'Franquicia creada. Ya puedes asignar sus categorías y productos.');
    }

    public function update(Request $request, Franchise $franchise): RedirectResponse
    {
        $this->authorizeFranchise($franchise);
        $data = $this->validated($request, $franchise);
        DB::transaction(function () use ($data, $franchise) {
            if ($data['is_default']) {
                Franchise::query()->where('business_profile_id', $franchise->business_profile_id)->whereKeyNot($franchise->id)->update(['is_default' => false]);
            }
            $franchise->update($data);
        });

        return back()->with('success', 'Franquicia actualizada.');
    }

    public function destroy(Franchise $franchise): RedirectResponse
    {
        $this->authorizeFranchise($franchise);

        if ($franchise->is_default) {
            return back()->with('error', 'No puedes eliminar la franquicia predeterminada. Marca otra como predeterminada primero.');
        }

        if ($franchise->products()->exists() || $franchise->categories()->exists()) {
            return back()->with('error', 'No se puede eliminar: la franquicia tiene categorías o productos asociados. Muévelos o elimínalos primero.');
        }

        $franchise->delete();

        return back()->with('success', 'Franquicia eliminada.');
    }

    private function validated(Request $request, ?Franchise $franchise = null): array
    {
        $businessProfileId = $franchise?->business_profile_id ?? CompanyContext::current()->businessProfileId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable', 'string', 'max:64', 'alpha_dash',
                Rule::unique('franchises', 'slug')->where('business_profile_id', $businessProfileId)->ignore($franchise?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim($data['name']),
            'slug' => Str::lower(trim($data['slug'] ?: Str::slug($data['name']))),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'is_default' => $request->boolean('is_default') || ($franchise?->is_default ?? !Franchise::query()->where('business_profile_id', $businessProfileId)->exists()),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
