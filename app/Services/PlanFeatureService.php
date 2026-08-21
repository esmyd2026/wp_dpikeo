<?php

namespace App\Services;

class PlanFeatureService
{
    public function __construct(
        private PlanLimitsService $planLimits
    ) {}

    public function planKey(): string
    {
        return $this->planLimits->planKey();
    }

    public function hasPlanFeature(string $feature): bool
    {
        $planKey = $this->planKey();
        $features = config("pricing.plans.{$planKey}.features", []);

        return (bool) ($features[$feature] ?? false);
    }

    public function isPlatformBulkWebOrderEnabled(): bool
    {
        $raw = $this->planLimits->platformLimitsRaw();

        return (bool) ($raw['bulk_web_order_enabled'] ?? false);
    }

    public function isBulkWebOrderAvailable(): bool
    {
        return $this->isPlatformBulkWebOrderEnabled();
    }
}
