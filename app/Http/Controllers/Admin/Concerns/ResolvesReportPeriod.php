<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\WhatsappCart;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;

trait ResolvesReportPeriod
{
    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function resolveReportPeriod(Request $request): array
    {
        $preset = $request->input('period', 'month');
        $to = Carbon::now()->endOfDay();

        if ($request->filled('to')) {
            $to = Carbon::parse($request->input('to'))->endOfDay();
            $preset = 'custom';
        }

        if ($request->filled('from')) {
            $from = Carbon::parse($request->input('from'))->startOfDay();
            $preset = 'custom';

            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            return [$from, $to, $preset];
        }

        $from = match ($preset) {
            '7d' => $to->copy()->subDays(6)->startOfDay(),
            '30d' => $to->copy()->subDays(29)->startOfDay(),
            '90d' => $to->copy()->subDays(89)->startOfDay(),
            'month' => Carbon::now()->startOfMonth()->startOfDay(),
            'all' => $this->earliestReportDate() ?? $to->copy()->subDays(29)->startOfDay(),
            default => Carbon::now()->startOfMonth()->startOfDay(),
        };

        return [$from, $to, $preset];
    }

    protected function earliestReportDate(): ?Carbon
    {
        $dates = collect([
            WhatsappCart::reportable()->min('created_at'),
            WhatsappMessage::min('created_at'),
        ])->filter();

        if ($dates->isEmpty()) {
            return null;
        }

        return Carbon::parse($dates->min())->startOfDay();
    }

    protected function previousPeriod(Carbon $from, Carbon $to): array
    {
        $periodDays = max(1, $from->diffInDays($to) + 1);
        $prevTo = $from->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($periodDays - 1)->startOfDay();

        return [$prevFrom, $prevTo];
    }

    protected function percentChange(float $previous, float $current): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
