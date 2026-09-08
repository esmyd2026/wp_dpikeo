<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesReportPeriod;
use App\Http\Controllers\Controller;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\ConsumptionReportService;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsappReportsController extends Controller
{
    use ResolvesReportPeriod;

    public function index(Request $request, ConsumptionReportService $consumption)
    {
        // Se resuelve UNA vez acá y se pasa explícito a todo lo que sigue --
        // ninguna consulta de este reporte debe volver a resolver "la
        // empresa" por su cuenta.
        $businessProfileId = CompanyContext::current()->businessProfileId();

        [$from, $to, $periodPreset] = $this->resolveReportPeriod($request, $businessProfileId);
        [$prevFrom, $prevTo] = $this->previousPeriod($from, $to);

        $consumptionReport = $consumption->build($from, $to, $businessProfileId);
        $metrics = $this->buildWhatsappMetrics($from, $to, $prevFrom, $prevTo, $businessProfileId);

        return view('admin.reports.whatsapp', compact(
            'consumptionReport',
            'metrics',
            'from',
            'to',
            'periodPreset',
        ));
    }

    private function buildWhatsappMetrics(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo, ?int $businessProfileId): array
    {
        $periodMessages = $this->scopedMessages($businessProfileId)
            ->whereBetween('created_at', [$from, $to])->count();
        $prevPeriodMessages = $this->scopedMessages($businessProfileId)
            ->whereBetween('created_at', [$prevFrom, $prevTo])->count();

        $received = $this->scopedMessages($businessProfileId)
            ->where('sender_type', 'client')
            ->whereBetween('created_at', [$from, $to])
            ->count();
        $sent = $this->scopedMessages($businessProfileId)
            ->whereIn('sender_type', ['system', 'humano'])
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $responseRate = $this->clientResponseRate($from, $to, $businessProfileId);
        $prevResponseRate = $this->clientResponseRate($prevFrom, $prevTo, $businessProfileId);

        $avgResponseTime = $this->averageResponseTimeMinutes($from, $to, $businessProfileId);

        $activeClients = (int) $this->scopedMessages($businessProfileId)
            ->whereBetween('created_at', [$from, $to])
            ->distinct()
            ->count('contact_id');

        $newContacts = WhatsappContact::query()
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $humanMessages = $this->scopedMessages($businessProfileId)
            ->where('sender_type', 'humano')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $botMessages = $this->scopedMessages($businessProfileId)
            ->where('sender_type', 'system')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        // HOUR() es MySQL; strftime('%H', ...) es el equivalente en SQLite (tests).
        $hourExpr = DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', created_at) AS INTEGER)"
            : 'HOUR(created_at)';

        $peakHourData = $this->scopedMessages($businessProfileId)
            ->whereBetween('created_at', [$from, $to])
            ->select(DB::raw("{$hourExpr} as hour"), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        $peakHour = $peakHourData
            ? str_pad($peakHourData->hour, 2, '0', STR_PAD_LEFT).':00'
            : null;

        return [
            'period_messages' => $periodMessages,
            'message_growth' => $this->percentChange($prevPeriodMessages, $periodMessages),
            'received' => $received,
            'sent' => $sent,
            'response_rate' => $responseRate,
            'response_rate_growth' => $this->percentChange($prevResponseRate, $responseRate),
            'avg_response_time_formatted' => $this->formatMinutes($avgResponseTime),
            'active_clients' => $activeClients,
            'new_contacts' => $newContacts,
            'human_messages' => $humanMessages,
            'bot_messages' => $botMessages,
            'peak_hour' => $peakHour,
        ];
    }

    /** Punto único de partida para cualquier consulta de mensajes de este reporte. */
    private function scopedMessages(?int $businessProfileId)
    {
        return WhatsappMessage::query()
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId));
    }

    private function clientResponseRate(Carbon $from, Carbon $to, ?int $businessProfileId): float
    {
        $clientMessages = $this->scopedMessages($businessProfileId)
            ->where('sender_type', 'client')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('contact_id')
            ->orderBy('created_at')
            ->get(['id', 'contact_id', 'created_at']);

        if ($clientMessages->isEmpty()) {
            return 0;
        }

        // Antes se ejecutaba un EXISTS por cada mensaje recibido. Se cargan
        // únicamente las respuestas posibles en dos consultas fijas y se
        // recorren cronológicamente por contacto.
        $repliesByContact = $this->scopedMessages($businessProfileId)
            ->whereIn('contact_id', $clientMessages->pluck('contact_id')->unique())
            ->whereIn('sender_type', ['system', 'humano'])
            ->where('created_at', '>', $from)
            ->where('created_at', '<=', $to->copy()->addDay())
            ->orderBy('contact_id')
            ->orderBy('created_at')
            ->get(['contact_id', 'created_at'])
            ->groupBy('contact_id');

        $answered = 0;
        foreach ($clientMessages->groupBy('contact_id') as $contactId => $messages) {
            $replies = $repliesByContact->get($contactId, collect())->values();
            $replyIndex = 0;

            foreach ($messages as $message) {
                while ($replyIndex < $replies->count()
                    && $replies[$replyIndex]->created_at->lte($message->created_at)) {
                    $replyIndex++;
                }

                if ($replyIndex < $replies->count()
                    && $replies[$replyIndex]->created_at->lte($message->created_at->copy()->addDay())) {
                    $answered++;
                }
            }
        }

        return round(($answered / $clientMessages->count()) * 100, 1);
    }

    private function averageResponseTimeMinutes(Carbon $from, Carbon $to, ?int $businessProfileId): float
    {
        $outbound = $this->scopedMessages($businessProfileId)
            ->whereIn('sender_type', ['system', 'humano'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('contact_id')
            ->orderBy('created_at')
            ->get(['contact_id', 'created_at']);

        if ($outbound->isEmpty()) {
            return 0;
        }

        // Solo hacen falta mensajes del cliente dentro de la ventana máxima
        // aceptada (7 días). Así se evita una consulta por cada respuesta y
        // tampoco se trae el historial completo de contactos antiguos.
        $clientsByContact = $this->scopedMessages($businessProfileId)
            ->whereIn('contact_id', $outbound->pluck('contact_id')->unique())
            ->where('sender_type', 'client')
            ->where('created_at', '>=', $from->copy()->subDays(7))
            ->where('created_at', '<', $to)
            ->orderBy('contact_id')
            ->orderBy('created_at')
            ->get(['contact_id', 'created_at'])
            ->groupBy('contact_id');

        $diffs = [];
        foreach ($outbound->groupBy('contact_id') as $contactId => $replies) {
            $clientMessages = $clientsByContact->get($contactId, collect())->values();
            $clientIndex = 0;
            $previousClientAt = null;

            foreach ($replies as $reply) {
                while ($clientIndex < $clientMessages->count()
                    && $clientMessages[$clientIndex]->created_at->lt($reply->created_at)) {
                    $previousClientAt = $clientMessages[$clientIndex]->created_at;
                    $clientIndex++;
                }

                if ($previousClientAt) {
                    $minutes = $previousClientAt->diffInMinutes($reply->created_at);
                    if ($minutes >= 0 && $minutes <= 10080) {
                        $diffs[] = $minutes;
                    }
                }
            }
        }

        return count($diffs) > 0 ? round(array_sum($diffs) / count($diffs), 1) : 0;
    }

    private function formatMinutes(?float $minutes): string
    {
        if (! $minutes || $minutes <= 0) {
            return '—';
        }

        if ($minutes < 60) {
            return round($minutes).' min';
        }

        $hours = floor($minutes / 60);
        $mins = round($minutes % 60);

        return $mins > 0 ? "{$hours} h {$mins} m" : "{$hours} h";
    }
}
