<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesReportPeriod;
use App\Http\Controllers\Controller;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\ConsumptionReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsappReportsController extends Controller
{
    use ResolvesReportPeriod;

    public function index(Request $request, ConsumptionReportService $consumption)
    {
        [$from, $to, $periodPreset] = $this->resolveReportPeriod($request);
        [$prevFrom, $prevTo] = $this->previousPeriod($from, $to);

        $consumptionReport = $consumption->build($from, $to);
        $metrics = $this->buildWhatsappMetrics($from, $to, $prevFrom, $prevTo);

        return view('admin.reports.whatsapp', compact(
            'consumptionReport',
            'metrics',
            'from',
            'to',
            'periodPreset',
        ));
    }

    private function buildWhatsappMetrics(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $periodMessages = WhatsappMessage::whereBetween('created_at', [$from, $to])->count();
        $prevPeriodMessages = WhatsappMessage::whereBetween('created_at', [$prevFrom, $prevTo])->count();

        $received = WhatsappMessage::where('sender_type', 'client')
            ->whereBetween('created_at', [$from, $to])
            ->count();
        $sent = WhatsappMessage::whereIn('sender_type', ['system', 'humano'])
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $responseRate = $this->clientResponseRate($from, $to);
        $prevResponseRate = $this->clientResponseRate($prevFrom, $prevTo);

        $avgResponseTime = $this->averageResponseTimeMinutes($from, $to);

        $activeClients = (int) WhatsappMessage::whereBetween('created_at', [$from, $to])
            ->distinct()
            ->count('contact_id');

        $newContacts = WhatsappContact::whereBetween('created_at', [$from, $to])->count();

        $humanMessages = WhatsappMessage::where('sender_type', 'humano')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $botMessages = WhatsappMessage::where('sender_type', 'system')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $peakHourData = WhatsappMessage::whereBetween('created_at', [$from, $to])
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        $peakHour = $peakHourData
            ? str_pad($peakHourData->hour, 2, '0', STR_PAD_LEFT) . ':00'
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

    private function clientResponseRate(Carbon $from, Carbon $to): float
    {
        $clientMessages = WhatsappMessage::where('sender_type', 'client')
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'contact_id', 'created_at']);

        if ($clientMessages->isEmpty()) {
            return 0;
        }

        $answered = 0;
        foreach ($clientMessages as $msg) {
            $hasReply = WhatsappMessage::where('contact_id', $msg->contact_id)
                ->whereIn('sender_type', ['system', 'humano'])
                ->where('created_at', '>', $msg->created_at)
                ->where('created_at', '<=', $msg->created_at->copy()->addDay())
                ->exists();

            if ($hasReply) {
                $answered++;
            }
        }

        return round(($answered / $clientMessages->count()) * 100, 1);
    }

    private function averageResponseTimeMinutes(Carbon $from, Carbon $to): float
    {
        $outbound = WhatsappMessage::whereIn('sender_type', ['system', 'humano'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('contact_id')
            ->orderBy('created_at')
            ->get(['contact_id', 'created_at']);

        if ($outbound->isEmpty()) {
            return 0;
        }

        $diffs = [];
        foreach ($outbound as $reply) {
            $prevClientAt = WhatsappMessage::where('contact_id', $reply->contact_id)
                ->where('sender_type', 'client')
                ->where('created_at', '<', $reply->created_at)
                ->orderByDesc('created_at')
                ->value('created_at');

            if ($prevClientAt) {
                $minutes = Carbon::parse($prevClientAt)->diffInMinutes($reply->created_at);
                if ($minutes >= 0 && $minutes <= 10080) {
                    $diffs[] = $minutes;
                }
            }
        }

        return count($diffs) > 0 ? round(array_sum($diffs) / count($diffs), 1) : 0;
    }

    private function formatMinutes(?float $minutes): string
    {
        if (!$minutes || $minutes <= 0) {
            return '—';
        }

        if ($minutes < 60) {
            return round($minutes) . ' min';
        }

        $hours = floor($minutes / 60);
        $mins = round($minutes % 60);

        return $mins > 0 ? "{$hours} h {$mins} m" : "{$hours} h";
    }
}
