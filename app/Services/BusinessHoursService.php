<?php

namespace App\Services;

use App\Models\BusinessBranch;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BusinessHoursService
{
    /**
     * Una sucursal sin horarios configurados conserva el comportamiento
     * anterior y se considera disponible. Así activar esta regla no cierra
     * accidentalmente empresas que todavía están completando sus horarios.
     */
    public function branchIsOpen(BusinessBranch $branch, ?CarbonInterface $at = null): bool
    {
        $at = $this->localTime($at);
        $hours = $branch->relationLoaded('hours') ? $branch->hours : $branch->hours()->get();
        if ($hours->isEmpty()) {
            return true;
        }

        $today = $hours->firstWhere('day_of_week', $at->dayOfWeek);
        if (! $today) {
            // Si la sucursal ya empezó a configurar su calendario, un día
            // ausente no puede interpretarse como abierto todo el día.
            return false;
        }
        if ($today->is_closed) {
            return false;
        }
        if (blank($today->opens_at) || blank($today->closes_at)) {
            return true;
        }

        $current = $at->format('H:i:s');

        return $current >= $this->timeValue($today->opens_at)
            && $current < $this->timeValue($today->closes_at);
    }

    /** @return Collection<int, BusinessBranch> */
    public function openOrderBranches(int $businessProfileId, ?CarbonInterface $at = null): Collection
    {
        return BusinessBranch::query()
            ->where('business_profile_id', $businessProfileId)
            ->availableForOrders()
            ->with('hours')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->filter(fn (BusinessBranch $branch) => $this->branchIsOpen($branch, $at))
            ->values();
    }

    public function closedMessage(int $businessProfileId, ?CarbonInterface $at = null): ?string
    {
        $at = $this->localTime($at);
        $branches = BusinessBranch::query()
            ->where('business_profile_id', $businessProfileId)
            ->availableForOrders()
            ->with('hours')
            ->get();

        if ($branches->isEmpty() || $branches->contains(fn (BusinessBranch $branch) => $this->branchIsOpen($branch, $at))) {
            return null;
        }

        $next = $this->nextOpening($branches, $at);

        return $next
            ? 'En este momento estamos fuera de horario. Puedes revisar el menú y volver a pedir '.$next.'.'
            : 'En este momento estamos fuera de horario. Puedes revisar el menú y volver a intentar durante nuestro horario de atención.';
    }

    /** @param Collection<int, BusinessBranch> $branches */
    private function nextOpening(Collection $branches, CarbonInterface $at): ?string
    {
        $candidates = collect();
        foreach ($branches as $branch) {
            $hours = $branch->relationLoaded('hours') ? $branch->hours : $branch->hours()->get();
            for ($offset = 0; $offset <= 7; $offset++) {
                $day = $at->copy()->startOfDay()->addDays($offset);
                $row = $hours->firstWhere('day_of_week', $day->dayOfWeek);
                if (! $row || $row->is_closed || blank($row->opens_at) || blank($row->closes_at)) {
                    continue;
                }
                $opening = $day->copy()->setTimeFromTimeString($this->timeValue($row->opens_at));
                if ($opening->isAfter($at)) {
                    $candidates->push($opening);
                    break;
                }
            }
        }

        $next = $candidates->sortBy(fn (CarbonInterface $date) => $date->getTimestamp())->first();
        if (! $next) {
            return null;
        }

        $day = $next->isSameDay($at) ? 'hoy' : ($next->isSameDay($at->copy()->addDay()) ? 'mañana' : $next->locale('es')->isoFormat('dddd D [de] MMMM'));

        return $day.' a las '.$next->format('H:i');
    }

    private function localTime(?CarbonInterface $at): CarbonInterface
    {
        return $at ? $at->copy()->timezone(config('app.timezone')) : Carbon::now(config('app.timezone'));
    }

    private function timeValue(mixed $value): string
    {
        return substr((string) $value, 0, 8);
    }
}
