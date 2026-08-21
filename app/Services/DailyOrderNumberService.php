<?php

namespace App\Services;

use App\Models\OrderDailyCounter;
use App\Models\WhatsappCart;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Asigna turnos diarios visibles de tres dígitos para operación y TV. */
class DailyOrderNumberService
{
    public function assign(WhatsappCart $cart): string
    {
        $current = $cart->metadata['order_details']['turn_number'] ?? null;
        if (is_string($current) && preg_match('/^\d{3}$/', $current)) {
            return $current;
        }

        return DB::transaction(function () use ($cart): string {
            $date = ($cart->created_at ?? now())->toDateString();
            OrderDailyCounter::query()->firstOrCreate(['business_date' => $date], ['last_number' => 0]);
            $counter = OrderDailyCounter::query()->where('business_date', $date)->lockForUpdate()->firstOrFail();

            if ($counter->last_number >= 999) {
                throw new InvalidArgumentException('Se alcanzó el límite de 999 turnos para hoy. Reinicia el contador al cerrar la jornada.');
            }

            $counter->increment('last_number');

            return str_pad((string) $counter->fresh()->last_number, 3, '0', STR_PAD_LEFT);
        });
    }

    public function resetToday(?int $userId = null): void
    {
        DB::transaction(function () use ($userId): void {
            $date = now()->toDateString();
            OrderDailyCounter::query()->firstOrCreate(['business_date' => $date], ['last_number' => 0]);
            $counter = OrderDailyCounter::query()->where('business_date', $date)->lockForUpdate()->firstOrFail();
            $counter->update(['last_number' => 0, 'reset_at' => now(), 'reset_by_user_id' => $userId]);
        });
    }
}
