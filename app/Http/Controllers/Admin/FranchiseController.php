<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
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
        return view('admin.franchises.index', [
            'franchises' => Franchise::query()->withCount(['products', 'categories'])->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            if ($data['is_default']) Franchise::query()->update(['is_default' => false]);
            Franchise::create($data);
        });

        return back()->with('success', 'Franquicia creada. Ya puedes asignar sus categorías y productos.');
    }

    public function update(Request $request, Franchise $franchise): RedirectResponse
    {
        $data = $this->validated($request, $franchise);
        DB::transaction(function () use ($data, $franchise) {
            if ($data['is_default']) Franchise::query()->whereKeyNot($franchise->id)->update(['is_default' => false]);
            $franchise->update($data);
        });

        return back()->with('success', 'Franquicia actualizada.');
    }

    public function destroy(Franchise $franchise): RedirectResponse
    {
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:64', 'alpha_dash', Rule::unique('franchises', 'slug')->ignore($franchise?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim($data['name']),
            'slug' => Str::lower(trim($data['slug'] ?: Str::slug($data['name']))),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'is_default' => $request->boolean('is_default') || ($franchise?->is_default ?? !Franchise::query()->exists()),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
