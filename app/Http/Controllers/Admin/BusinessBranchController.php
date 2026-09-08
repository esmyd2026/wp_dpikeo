<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessBranch;
use App\Models\BusinessBranchDeliveryFeeTier;
use App\Models\BusinessBranchHour;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
                ->forUserAccess(auth()->user(), $context->businessProfileId())
                ->withCount('orders')
                ->with(['hours', 'deliveryFeeTiers'])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = CompanyContext::current()->businessProfile;
        abort_unless($profile, 422, 'Esta empresa todavía no tiene un número de WhatsApp conectado.');
        abort_unless(auth()->user()->hasAllBranchAccess($profile->id), 403, 'Solo un administrador con acceso a todas las sucursales puede crear locales.');
        $data = $this->validated($request);

        DB::transaction(function () use ($profile, $data, $request) {
            if ($data['is_default']) {
                BusinessBranch::query()->where('business_profile_id', $profile->id)->update(['is_default' => false]);
            }

            $branch = BusinessBranch::create(array_merge($data, ['business_profile_id' => $profile->id]));
            $this->syncHours($branch, $request);
            $this->syncDeliveryFeeTiers($branch, $request);
        });

        return back()->with('success', 'Sucursal creada correctamente.');
    }

    /** El id de la sucursal llega en la URL: sin este chequeo se podría editar/borrar la de otra empresa. */
    private function authorizeBranch(BusinessBranch $branch): void
    {
        $businessProfileId = CompanyContext::current()->businessProfileId();
        abort_unless($businessProfileId && (int) $branch->business_profile_id === (int) $businessProfileId, 404);
        abort_unless(auth()->user()->canAccessBranch($branch), 404);
    }

    public function update(Request $request, BusinessBranch $branch): RedirectResponse
    {
        $this->authorizeBranch($branch);
        $data = $this->validated($request, $branch);

        DB::transaction(function () use ($branch, $data, $request) {
            if ($data['is_default']) {
                BusinessBranch::query()->where('business_profile_id', $branch->business_profile_id)->whereKeyNot($branch->id)->update(['is_default' => false]);
            }
            $branch->update($data);
            $this->syncHours($branch, $request);
            $this->syncDeliveryFeeTiers($branch, $request);
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
            'reservations_info' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'orders_enabled' => ['nullable', 'boolean'],
            'dine_in_enabled' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_fee_minimum' => ['nullable', 'numeric', 'min:0'],
            'hours' => ['nullable', 'array'],
            'hours.*.is_closed' => ['nullable', 'boolean'],
            'hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'date_format:H:i'],
            'delivery_fee_tiers' => ['nullable', 'array'],
            'delivery_fee_tiers.*.from_km' => ['required_with:delivery_fee_tiers.*.price', 'numeric', 'min:0'],
            'delivery_fee_tiers.*.to_km' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee_tiers.*.price' => ['required_with:delivery_fee_tiers.*.from_km', 'numeric', 'min:0'],
        ]);

        return [
            'name' => trim($data['name']),
            'code' => strtoupper(trim($data['code'] ?: Str::slug($data['name'], '-'))),
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'address' => filled($data['address'] ?? null) ? trim($data['address']) : null,
            'reservations_info' => filled($data['reservations_info'] ?? null) ? trim($data['reservations_info']) : null,
            // Siempre existe una sucursal predeterminada por empresa: los
            // pedidos de WhatsApp se asignan allí cuando el cliente no
            // selecciona local. Se compara contra las sucursales de ESTA
            // empresa, nunca contra el total global (si no, la primera
            // sucursal de una empresa nueva no quedaría como predeterminada
            // solo porque otra empresa ya tenía las suyas).
            'is_default' => $request->boolean('is_default') || ($branch?->is_default ?? ! BusinessBranch::query()
                ->where('business_profile_id', $branch?->business_profile_id ?? CompanyContext::current()->businessProfileId())
                ->exists()),
            'is_active' => $request->boolean('is_active'),
            'orders_enabled' => $request->boolean('orders_enabled'),
            'dine_in_enabled' => $request->boolean('dine_in_enabled'),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'delivery_fee_minimum' => $data['delivery_fee_minimum'] ?? null,
        ];
    }

    /**
     * Guarda el horario de atención (7 filas, una por día, 0=domingo..6=sábado).
     * Un día marcado "cerrado" siempre queda sin horas, sin importar lo que
     * haya llegado en el form -- evita que quede una hora "fantasma" guardada
     * para un día que la sucursal no atiende.
     */
    private function syncHours(BusinessBranch $branch, Request $request): void
    {
        $hours = $request->input('hours', []);

        foreach (array_keys(BusinessBranchHour::DAYS) as $day) {
            $row = $hours[$day] ?? [];
            $isClosed = (bool) ($row['is_closed'] ?? false);
            $opensAt = $isClosed ? null : (($row['opens_at'] ?? null) ?: null);
            $closesAt = $isClosed ? null : (($row['closes_at'] ?? null) ?: null);

            if (! $isClosed && $opensAt && $closesAt && $closesAt <= $opensAt) {
                throw ValidationException::withMessages([
                    "hours.{$day}.closes_at" => 'La hora de cierre de '.BusinessBranchHour::DAYS[$day].' debe ser posterior a la de apertura.',
                ]);
            }

            BusinessBranchHour::updateOrCreate(
                ['business_branch_id' => $branch->id, 'day_of_week' => $day],
                ['is_closed' => $isClosed, 'opens_at' => $opensAt, 'closes_at' => $closesAt]
            );
        }
    }

    /**
     * Reemplaza por completo la tabla de tramos de delivery de la sucursal
     * ("desde X hasta Y km = $"), con la que se calcula el costo de envío
     * solo cuando el cliente comparte su ubicación (ver
     * DeliveryFeeTierService::feeForDistance). Filas vacías (sin "desde" ni
     * "precio") se ignoran -- así el form puede tener filas extra sin llenar.
     */
    private function syncDeliveryFeeTiers(BusinessBranch $branch, Request $request): void
    {
        $rows = collect($request->input('delivery_fee_tiers', []))
            ->filter(fn ($row) => filled($row['from_km'] ?? null) && filled($row['price'] ?? null))
            ->map(fn ($row) => [
                'from_km' => (float) $row['from_km'],
                'to_km' => filled($row['to_km'] ?? null) ? (float) $row['to_km'] : null,
                'price' => (float) $row['price'],
            ])
            ->sortBy('from_km')
            ->values();

        $previousTo = null;
        foreach ($rows as $index => $row) {
            if ($row['to_km'] !== null && $row['to_km'] <= $row['from_km']) {
                throw ValidationException::withMessages([
                    "delivery_fee_tiers.{$index}.to_km" => 'El "hasta" debe ser mayor que el "desde".',
                ]);
            }
            if ($previousTo !== null && $row['from_km'] < $previousTo) {
                throw ValidationException::withMessages([
                    "delivery_fee_tiers.{$index}.from_km" => 'Los tramos no pueden solaparse con el anterior.',
                ]);
            }
            $previousTo = $row['to_km'];
        }

        $branch->deliveryFeeTiers()->delete();
        foreach ($rows as $row) {
            $branch->deliveryFeeTiers()->create($row);
        }
    }
}
