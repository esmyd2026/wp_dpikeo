<?php

namespace App\Services;

use App\Models\PricingSetting;

class PlanLimitsService
{
    public function platformLimitsRaw(): array
    {
        $settings = PricingSetting::current();
        $stored = $settings->platform_limits;

        return is_array($stored) ? $stored : [];
    }

    public function savePlatformLimits(array $data): void
    {
        $limits = $this->platformLimitsRaw();

        foreach ($data as $key => $value) {
            $limits[$key] = $value;
        }

        PricingSetting::current()->update(['platform_limits' => $limits]);
    }
}
