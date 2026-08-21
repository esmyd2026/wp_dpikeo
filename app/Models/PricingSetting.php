<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingSetting extends Model
{
    public const ALL_CATEGORIES = ['service', 'utility', 'marketing', 'authentication', 'campaign_freeform'];

    protected $fillable = [
        'meta_markup',
        'region',
        'currency',
        'rates',
        'enabled_categories',
        'platform_limits',
    ];

    protected $casts = [
        'meta_markup' => 'float',
        'rates' => 'array',
        'enabled_categories' => 'array',
        'platform_limits' => 'array',
    ];

    public static function current(): self
    {
        $defaults = config('pricing.meta_rates.per_conversation', []);
        $markup = (float) config('pricing.meta_markup', 1.30);

        return static::query()->firstOrCreate([], [
            'meta_markup' => $markup,
            'region' => config('pricing.meta_rates.region', 'Ecuador / Latam'),
            'currency' => config('pricing.meta_rates.currency', 'USD'),
            'rates' => static::normalizeRates($defaults),
            'enabled_categories' => static::defaultEnabledCategories(),
        ]);
    }

    public static function defaultEnabledCategories(): array
    {
        $configured = config('pricing.enabled_conversation_categories', []);

        if ($configured !== []) {
            return array_values(array_filter(
                static::ALL_CATEGORIES,
                fn (string $key) => (bool) ($configured[$key] ?? false)
            ));
        }

        return ['service', 'utility'];
    }

    public static function normalizeRates(array $rates): array
    {
        $normalized = [];

        foreach (static::ALL_CATEGORIES as $key) {
            $normalized[$key] = [
                'min' => (float) ($rates[$key]['min'] ?? 0),
                'max' => (float) ($rates[$key]['max'] ?? 0),
            ];
        }

        return $normalized;
    }

    public function enabledCategories(): array
    {
        $enabled = $this->enabled_categories ?? static::defaultEnabledCategories();

        return array_values(array_intersect(static::ALL_CATEGORIES, $enabled));
    }

    public function isCategoryEnabled(string $category): bool
    {
        return in_array($category, $this->enabledCategories(), true);
    }
}
