<?php

namespace App\Services;

use App\Models\PlatformPaymentReceipt;
use App\Models\PricingSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlatformBillingService
{
    public function billingSettings(): array
    {
        $raw = app(PlanLimitsService::class)->platformLimitsRaw();
        $defaults = config('platform_billing.defaults', []);

        return array_merge($defaults, is_array($raw['billing'] ?? null) ? $raw['billing'] : []);
    }

    public function suspensionSettings(): array
    {
        $raw = app(PlanLimitsService::class)->platformLimitsRaw();
        $stored = is_array($raw['suspensions'] ?? null) ? $raw['suspensions'] : [];

        return [
            'suspend_bot' => $this->toBool($stored['suspend_bot'] ?? false),
            'suspend_chat' => $this->toBool($stored['suspend_chat'] ?? false),
            'suspend_orders' => $this->toBool($stored['suspend_orders'] ?? false),
            'auto_suspend_on_overdue' => $this->toBool($stored['auto_suspend_on_overdue'] ?? true),
        ];
    }

    public function saveBillingAndSuspensions(array $billing, array $suspensions): void
    {
        $limits = app(PlanLimitsService::class)->platformLimitsRaw();
        $limits['billing'] = array_merge($this->billingSettings(), $billing);
        $limits['suspensions'] = [
            'suspend_bot' => $this->toBool($suspensions['suspend_bot'] ?? false),
            'suspend_chat' => $this->toBool($suspensions['suspend_chat'] ?? false),
            'suspend_orders' => $this->toBool($suspensions['suspend_orders'] ?? false),
            'auto_suspend_on_overdue' => $this->toBool($suspensions['auto_suspend_on_overdue'] ?? true),
        ];

        PricingSetting::current()->update(['platform_limits' => $limits]);
    }

    /** Motivo por el que el bot no responde, o null si puede responder. */
    public function botBlockReason(?object $contact = null): ?string
    {
        $s = $this->suspensionSettings();

        if ($s['suspend_bot']) {
            return 'platform_suspend_bot_manual';
        }

        if ($this->autoOverdueActive()) {
            $payment = $this->paymentStatusForCurrentMonth();
            if ($payment['plan_overdue'] && $payment['meta_overdue']) {
                return 'platform_auto_overdue_plan_and_meta';
            }
            if ($payment['plan_overdue']) {
                return 'platform_auto_overdue_plan';
            }
            if ($payment['meta_overdue']) {
                return 'platform_auto_overdue_meta';
            }

            return 'platform_auto_overdue';
        }

        if ($contact && ! ($contact->bot_enabled ?? true)) {
            return 'contact_bot_disabled';
        }

        return null;
    }

    public function dashboardSnapshot(?float $metaEstimate = null): array
    {
        $billing = $this->billingSettings();
        $suspensions = $this->suspensionSettings();
        $payment = $this->paymentStatusForCurrentMonth();
        $autoOverdue = $suspensions['auto_suspend_on_overdue']
            && ($payment['plan_overdue'] || $payment['meta_overdue']);

        return [
            'plan_amount' => (float) ($billing['plan_amount'] ?? 0),
            'plan_due_date' => $payment['plan_due'],
            'plan_due_label' => $payment['plan_due']->format('d/m/Y'),
            'plan_paid' => $payment['plan_paid'],
            'plan_overdue' => $payment['plan_overdue'],
            'meta_due_date' => $payment['meta_due'],
            'meta_due_label' => $payment['meta_due']->format('d/m/Y'),
            'meta_paid' => $payment['meta_paid'],
            'meta_overdue' => $payment['meta_overdue'],
            'meta_estimate' => $metaEstimate,
            'auto_overdue' => $autoOverdue,
            'manual_suspend_bot' => $suspensions['suspend_bot'],
            'manual_suspend_chat' => $suspensions['suspend_chat'],
            'manual_suspend_orders' => $suspensions['suspend_orders'],
            'auto_suspend_enabled' => $suspensions['auto_suspend_on_overdue'],
            'suspensions_raw' => $suspensions,
            'bot_suspended' => $this->isBotSuspended(),
            'chat_suspended' => $this->isChatSuspended(),
            'orders_suspended' => $this->isOrdersSuspended(),
            'any_suspended' => $this->isBotSuspended() || $this->isChatSuspended() || $this->isOrdersSuspended(),
        ];
    }

    public function isBotSuspended(?User $user = null): bool
    {
        $s = $this->suspensionSettings();

        if ($s['suspend_bot']) {
            return true;
        }

        return $this->autoOverdueActive() && ! $this->userBypassesAutoSuspension($user);
    }

    public function isChatSuspended(?User $user = null): bool
    {
        $s = $this->suspensionSettings();

        if ($s['suspend_chat']) {
            return true;
        }

        return $this->autoOverdueActive() && ! $this->userBypassesAutoSuspension($user);
    }

    public function isOrdersSuspended(?User $user = null): bool
    {
        $s = $this->suspensionSettings();

        if ($s['suspend_orders']) {
            return true;
        }

        return $this->autoOverdueActive() && ! $this->userBypassesAutoSuspension($user);
    }

    public function botMayRespondToContact(object $contact): bool
    {
        return $this->botBlockReason($contact) === null;
    }

    public function clearAllSuspensions(): void
    {
        $this->saveBillingAndSuspensions([], [
            'suspend_bot' => false,
            'suspend_chat' => false,
            'suspend_orders' => false,
            'auto_suspend_on_overdue' => false,
        ]);
    }

    public function receiptsForWallet(int $limit = 50): Collection
    {
        return PlatformPaymentReceipt::query()
            ->with(['user:id,name', 'reviewer:id,name'])
            ->latest('paid_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function pendingReceiptsCount(): int
    {
        return PlatformPaymentReceipt::query()->where('status', PlatformPaymentReceipt::STATUS_PENDING)->count();
    }

    public function reviewReceipt(PlatformPaymentReceipt $receipt, string $status, ?User $reviewer, ?string $notes = null): PlatformPaymentReceipt
    {
        $receipt->update([
            'status' => $status,
            'review_notes' => $notes,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
        ]);

        return $receipt->fresh(['user', 'reviewer']);
    }

    private function autoOverdueActive(): bool
    {
        if (! $this->suspensionSettings()['auto_suspend_on_overdue']) {
            return false;
        }

        $payment = $this->paymentStatusForCurrentMonth();

        return $payment['plan_overdue'] || $payment['meta_overdue'];
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array{plan_due: Carbon, meta_due: Carbon, plan_paid: bool, meta_paid: bool, plan_overdue: bool, meta_overdue: bool} */
    private function paymentStatusForCurrentMonth(): array
    {
        $billing = $this->billingSettings();
        $now = now();
        $planDue = $this->dueDateForCurrentMonth((int) ($billing['plan_due_day'] ?? 5));
        $metaDue = $this->dueDateForCurrentMonth((int) ($billing['meta_due_day'] ?? 10));
        $planPaid = $this->hasApprovedPaymentForPeriod(PlatformPaymentReceipt::FOR_PLAN, $now)
            || $this->hasApprovedPaymentForPeriod(PlatformPaymentReceipt::FOR_BOTH, $now);
        $metaPaid = $this->hasApprovedPaymentForPeriod(PlatformPaymentReceipt::FOR_META, $now)
            || $this->hasApprovedPaymentForPeriod(PlatformPaymentReceipt::FOR_BOTH, $now);

        return [
            'plan_due' => $planDue,
            'meta_due' => $metaDue,
            'plan_paid' => $planPaid,
            'meta_paid' => $metaPaid,
            'plan_overdue' => ! $planPaid && $now->gt($planDue->copy()->endOfDay()),
            'meta_overdue' => ! $metaPaid && $now->gt($metaDue->copy()->endOfDay()),
        ];
    }

    private function userBypassesAutoSuspension(?User $user): bool
    {
        $user ??= auth()->user();

        return $user?->isSuperAdmin() ?? false;
    }

    private function dueDateForCurrentMonth(int $day): Carbon
    {
        $day = max(1, min(28, $day));
        $now = now();

        return Carbon::create($now->year, $now->month, $day)->startOfDay();
    }

    private function hasApprovedPaymentForPeriod(string $paymentFor, Carbon $date): bool
    {
        return PlatformPaymentReceipt::query()
            ->where('status', PlatformPaymentReceipt::STATUS_APPROVED)
            ->where('payment_for', $paymentFor)
            ->whereYear('paid_at', $date->year)
            ->whereMonth('paid_at', $date->month)
            ->exists();
    }
}
