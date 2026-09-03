<?php

namespace App\Services;

use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use App\Models\Franchise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class DemoClienteService
{
    public function __construct(
        private PlanLimitsService $planLimits
    ) {}

    /**
     * Este toggle es una única fila global (PlatformBillingService), no por
     * empresa. Si el negocio activo (webhook/sesión) no coincide con el
     * dueño real de esa franquicia, se ignora -- si no, activar este filtro
     * para dpikeo terminaría filtrando también el catálogo de Zapatos Demo
     * (u otra empresa) por una franquicia que no es la suya.
     */
    public function activeKey(): ?string
    {
        $raw = $this->planLimits->platformLimitsRaw()['active_demo_cliente'] ?? null;

        if (!is_string($raw)) {
            return null;
        }

        $key = trim($raw);
        if ($key === '') {
            return null;
        }

        $franchise = Franchise::where('slug', $key)->first();
        if (!$franchise) {
            return $key;
        }

        try {
            $businessProfileId = \App\Support\CompanyContext::current()->businessProfileId();
        } catch (\Throwable $e) {
            // Sin sesión (webhook/consola): no hay con qué comparar, se
            // confía en el valor global como ya se hacía antes.
            return $key;
        }

        if ($businessProfileId && (int) $franchise->business_profile_id !== (int) $businessProfileId) {
            return null;
        }

        return $key;
    }

    public function saveActiveKey(?string $key): void
    {
        $this->planLimits->savePlatformLimits([
            'active_demo_cliente' => $key !== null && trim($key) !== '' ? trim($key) : null,
        ]);
    }

    /**
     * @return array<string, string> slug => label
     *
     * Acotado a las franquicias de la empresa activa -- antes listaba
     * TODAS las franquicias de la plataforma sin importar qué empresa
     * estuviera seleccionada (una empresa nueva veía las franquicias de
     * otra en este selector, aunque su catálogo real ya estaba aislado).
     */
    public function options(): array
    {
        $businessProfileId = null;
        try {
            $businessProfileId = \App\Support\CompanyContext::current()->businessProfileId();
        } catch (\Throwable $e) {
            // Contexto sin sesión (comando/consola): sin filtro, igual que antes.
        }

        return Franchise::query()
            ->where('is_active', true)
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();
    }

    /**
     * @param  Builder|Relation  $query
     * @return Builder|Relation
     */
    public function applyCategoryScope(Builder|Relation $query): Builder|Relation
    {
        $active = $this->activeKey();

        if (!$active) {
            return $query;
        }

        return $query->where('demo_cliente', $active);
    }

    /**
     * @param  Builder|Relation  $query
     * @return Builder|Relation
     */
    public function scopeCategoriesWithVisibleProducts(Builder|Relation $query): Builder|Relation
    {
        $active = $this->activeKey();

        return $query->whereHas('prices', function ($priceQuery) use ($active) {
            $priceQuery->where('is_active', true);
            if ($active) {
                $priceQuery->where('demo_cliente', $active);
            }
        });
    }

    /**
     * Categorías del catálogo con al menos un producto visible para la demo activa.
     */
    public function categoryHasVisibleProducts(WhatsappMenuItem $category): bool
    {
        return $this->applyProductScope(
            $category->prices()->where('is_active', true)
        )->exists();
    }

    /**
     * @param  Builder|Relation  $query  Relación prices() o query de WhatsappPrice
     */
    public function applyProductScope(Builder|Relation $query): Builder|Relation
    {
        $active = $this->activeKey();

        if (!$active) {
            return $query;
        }

        return $query->where('demo_cliente', $active);
    }
}
