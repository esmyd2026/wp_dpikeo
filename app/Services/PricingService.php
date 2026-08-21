<?php

namespace App\Services;

use App\Models\PricingSetting;

class PricingService
{
    private ?PricingSetting $settings = null;

    public function settings(): PricingSetting
    {
        return $this->settings ??= PricingSetting::current();
    }

    public function markup(): float
    {
        return (float) $this->settings()->meta_markup;
    }

    public function enabledCategories(): array
    {
        return $this->settings()->enabledCategories();
    }

    public function isCategoryEnabled(string $category): bool
    {
        return $this->settings()->isCategoryEnabled($category);
    }

    public function baseRates(): array
    {
        return $this->settings()->rates ?? [];
    }

    public function categoryMeta(string $category): array
    {
        $config = config("pricing.meta_rates.per_conversation.{$category}", []);

        return [
            'icon' => $config['icon'] ?? '💬',
            'label' => $config['label'] ?? ucfirst($category),
            'description' => $config['description'] ?? '',
        ];
    }

    public function rateWithMarkup(string $category): array
    {
        $base = $this->baseRates()[$category] ?? ['min' => 0, 'max' => 0];
        $markup = $this->markup();

        return [
            'min' => round($base['min'] * $markup, 4),
            'max' => round($base['max'] * $markup, 4),
        ];
    }

    public function allRatesWithMarkup(): array
    {
        $result = [
            'region' => $this->settings()->region,
            'currency' => $this->settings()->currency,
            'per_conversation' => [],
        ];

        foreach ($this->enabledCategories() as $category) {
            $meta = $this->categoryMeta($category);
            $withMarkup = $this->rateWithMarkup($category);

            $result['per_conversation'][$category] = array_merge($meta, $withMarkup, [
                'base_min' => $this->baseRates()[$category]['min'] ?? 0,
                'base_max' => $this->baseRates()[$category]['max'] ?? 0,
            ]);
        }

        return $result;
    }

    /**
     * @param array<string, int> $counts Cantidad de conversaciones/mensajes por categoria (claves de PricingSetting::ALL_CATEGORIES).
     */
    public function estimateCost(array $counts, string $bound = 'min'): float
    {
        $total = 0.0;

        foreach ($counts as $category => $count) {
            if (!$this->isCategoryEnabled($category)) {
                continue;
            }

            $rate = $this->rateWithMarkup($category);
            $total += $count * ($rate[$bound] ?? 0);
        }

        return round($total, 2);
    }
}
