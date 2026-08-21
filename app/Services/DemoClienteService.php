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

    public function activeKey(): ?string
    {
        $raw = $this->planLimits->platformLimitsRaw()['active_demo_cliente'] ?? null;

        if (!is_string($raw)) {
            return null;
        }

        $key = trim($raw);

        return $key !== '' ? $key : null;
    }

    public function saveActiveKey(?string $key): void
    {
        $this->planLimits->savePlatformLimits([
            'active_demo_cliente' => $key !== null && trim($key) !== '' ? trim($key) : null,
        ]);
    }

    /**
     * @return array<string, string> slug => label
     */
    public function options(): array
    {
        return Franchise::query()
            ->where('is_active', true)
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
