<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesReportPeriod;
use App\Http\Controllers\Controller;
use App\Models\WhatsappCart;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdersReportsController extends Controller
{
    use ResolvesReportPeriod;

    public function index(Request $request)
    {
        [$from, $to, $periodPreset] = $this->resolveReportPeriod($request);

        $statusBreakdown = $this->statusBreakdown($from, $to);
        $statusCards = $this->statusSummaryCards($statusBreakdown);
        $dailyTrend = $this->dailyOrderTrend($from, $to);
        $recentOrders = WhatsappCart::reportable()
            ->with(['contact'])
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->limit(8)
            ->get();

        $statusLabels = [
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmado',
            'preparing' => 'En preparación',
            'ready' => 'Listo para entregar',
            'completed' => 'Entregado',
            'cancelled' => 'Cancelado',
            'payment_pending' => 'Pago pendiente',
            'paid' => 'Pagado',
        ];

        return view('admin.reports.orders', compact(
            'statusCards',
            'statusBreakdown',
            'dailyTrend',
            'recentOrders',
            'statusLabels',
            'from',
            'to',
            'periodPreset',
        ));
    }

    /** @param  array<int, array{status: string, count: int, amount: float}>  $breakdown */
    private function statusSummaryCards(array $breakdown): array
    {
        $byStatus = collect($breakdown)->keyBy('status');

        $groups = [
            'pending' => [
                'label' => 'Pendientes',
                'statuses' => [
                    WhatsappCart::STATUS_PENDING,
                    WhatsappCart::STATUS_PAYMENT_PENDING,
                ],
            ],
            'confirmed' => [
                'label' => 'En operación',
                'statuses' => [
                    WhatsappCart::STATUS_CONFIRMED,
                    WhatsappCart::STATUS_PREPARING,
                    WhatsappCart::STATUS_READY,
                ],
            ],
            'paid' => [
                'label' => 'Pagados',
                'statuses' => [
                    WhatsappCart::STATUS_PAID,
                    WhatsappCart::STATUS_COMPLETED,
                ],
            ],
            'cancelled' => [
                'label' => 'Cancelados',
                'statuses' => [WhatsappCart::STATUS_CANCELLED],
            ],
        ];

        $cards = [];
        foreach ($groups as $key => $group) {
            $count = 0;
            $amount = 0.0;
            foreach ($group['statuses'] as $status) {
                $row = $byStatus->get($status);
                if ($row) {
                    $count += $row['count'];
                    $amount += $row['amount'];
                }
            }
            $cards[$key] = [
                'label' => $group['label'],
                'count' => $count,
                'amount' => $amount,
            ];
        }

        return $cards;
    }

    private function statusBreakdown(Carbon $from, Carbon $to): array
    {
        $rows = WhatsappCart::reportable()
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(total), 0) as amount'))
            ->groupBy('status')
            ->get();

        return $rows->map(fn ($row) => [
            'status' => $row->status,
            'count' => (int) $row->total,
            'amount' => (float) $row->amount,
        ])->all();
    }

    private function dailyOrderTrend(Carbon $from, Carbon $to): array
    {
        $rows = WhatsappCart::reportable()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', WhatsappCart::STATUS_CANCELLED)
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('COALESCE(SUM(total), 0) as revenue')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return $rows->map(fn ($row) => [
            'label' => Carbon::parse($row->day)->format('d/m'),
            'orders' => (int) $row->orders,
            'revenue' => (float) $row->revenue,
        ])->all();
    }
}
