<?php

namespace App\Services;

use App\Models\WhatsappCampaign;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConsumptionReportService
{
    public function __construct(
        private readonly PricingService $pricing
    ) {}

    public function build(Carbon $from, Carbon $to, ?int $businessProfileId = null): array
    {
        $counts = $this->countByCategory($from, $to, $businessProfileId);
        $categories = [];
        $totalMin = 0.0;
        $totalMax = 0.0;

        foreach ($this->pricing->enabledCategories() as $category) {
            $count = $counts[$category] ?? 0;
            $rates = $this->pricing->rateWithMarkup($category);
            $meta = $this->pricing->categoryMeta($category);
            $costMin = round($count * $rates['min'], 2);
            $costMax = round($count * $rates['max'], 2);

            $categories[$category] = array_merge($meta, [
                'count' => $count,
                'rate_min' => $rates['min'],
                'rate_max' => $rates['max'],
                'cost_min' => $costMin,
                'cost_max' => $costMax,
            ]);

            $totalMin += $costMin;
            $totalMax += $costMax;
        }

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $monthCounts = $this->countByCategory($monthStart, $monthEnd, $businessProfileId);
        $monthMin = $this->pricing->estimateCost($monthCounts, 'min');
        $monthMax = $this->pricing->estimateCost($monthCounts, 'max');

        $daysInMonth = (int) $monthEnd->day;
        $daysElapsed = max(1, Carbon::now()->day);
        $projectedMin = round(($monthMin / $daysElapsed) * $daysInMonth, 2);
        $projectedMax = round(($monthMax / $daysElapsed) * $daysInMonth, 2);

        return [
            'categories' => $categories,
            'total_min' => round($totalMin, 2),
            'total_max' => round($totalMax, 2),
            'month_min' => $monthMin,
            'month_max' => $monthMax,
            'month_label' => Carbon::now()->translatedFormat('F Y'),
            'projected_min' => $projectedMin,
            'projected_max' => $projectedMax,
            'currency' => $this->pricing->settings()->currency,
            'daily_trend' => $this->dailyCostTrend($from, $to, $businessProfileId),
        ];
    }

    private function countByCategory(Carbon $from, Carbon $to, ?int $businessProfileId): array
    {
        $counts = [
            'service' => 0,
            'utility' => 0,
            'marketing' => 0,
            'authentication' => 0,
            'campaign_freeform' => 0,
        ];

        if ($this->pricing->isCategoryEnabled('service')) {
            $counts['service'] = $this->countServiceConversations($from, $to, $businessProfileId);
        }
        if ($this->pricing->isCategoryEnabled('utility')) {
            $counts['utility'] = $this->countUtilityMessages($from, $to, $businessProfileId);
        }
        if ($this->pricing->isCategoryEnabled('marketing')) {
            $counts['marketing'] = $this->countMarketingMessages($from, $to, $businessProfileId);
        }
        if ($this->pricing->isCategoryEnabled('authentication')) {
            $counts['authentication'] = $this->countAuthenticationMessages($from, $to, $businessProfileId);
        }
        if ($this->pricing->isCategoryEnabled('campaign_freeform')) {
            $counts['campaign_freeform'] = $this->countFreeformCampaignMessages($from, $to, $businessProfileId);
        }

        return $counts;
    }

    /**
     * Días con chat iniciado por el cliente y respuesta del negocio.
     */
    private function countServiceConversations(Carbon $from, Carbon $to, ?int $businessProfileId): int
    {
        $result = DB::selectOne("
            SELECT COUNT(DISTINCT CONCAT(client.contact_id, '-', DATE(client.created_at))) AS total
            FROM whatsapp_messages client
            WHERE client.sender_type = 'client'
              AND client.created_at BETWEEN ? AND ?
              AND (? IS NULL OR client.business_profile_id = ?)
              AND EXISTS (
                  SELECT 1 FROM whatsapp_messages reply
                  WHERE reply.contact_id = client.contact_id
                    AND reply.sender_type IN ('system', 'humano')
                    AND DATE(reply.created_at) = DATE(client.created_at)
                    AND (? IS NULL OR reply.business_profile_id = ?)
              )
        ", [$from, $to, $businessProfileId, $businessProfileId, $businessProfileId, $businessProfileId]);

        return (int) ($result->total ?? 0);
    }

    /**
     * Mensajes del bot fuera de la ventana de servicio (24 h desde el último mensaje del cliente).
     */
    private function countUtilityMessages(Carbon $from, Carbon $to, ?int $businessProfileId): int
    {
        // DATE_SUB(...) es sintaxis MySQL; en SQLite (tests) el equivalente
        // portable es restar 86400 segundos al timestamp unix de la fila.
        $windowStart = DB::getDriverName() === 'sqlite'
            ? "datetime(outbound.created_at, '-24 hours')"
            : 'DATE_SUB(outbound.created_at, INTERVAL 24 HOUR)';

        return (int) DB::selectOne("
            SELECT COUNT(*) AS total
            FROM whatsapp_messages outbound
            WHERE outbound.sender_type = 'system'
              AND outbound.type != 'template'
              AND outbound.created_at BETWEEN ? AND ?
              AND (? IS NULL OR outbound.business_profile_id = ?)
              AND NOT EXISTS (
                  SELECT 1 FROM whatsapp_messages recent_client
                  WHERE recent_client.contact_id = outbound.contact_id
                    AND recent_client.sender_type = 'client'
                    AND recent_client.created_at <= outbound.created_at
                    AND recent_client.created_at >= {$windowStart}
              )
        ", [$from, $to, $businessProfileId, $businessProfileId])->total ?? 0;
    }

    /**
     * Plantillas enviadas + envíos de campañas completadas con plantilla
     * aprobada por Meta. Las campañas de texto/imagen libre NO cuentan aquí:
     * van aparte en "campaign_freeform" (Plantillas útiles), porque no son
     * plantillas reales de Meta y tienen su propio costo configurable.
     */
    /**
     * JSON_UNQUOTE(JSON_EXTRACT(...)) es MySQL; json_extract() de SQLite
     * (tests) ya devuelve el escalar sin comillas, no necesita UNQUOTE.
     */
    private function templateCategoryExpr(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "UPPER(json_extract(metadata, '$.template_category'))"
            : "UPPER(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.template_category')))";
    }

    private function countMarketingMessages(Carbon $from, Carbon $to, ?int $businessProfileId): int
    {
        $categoryExpr = $this->templateCategoryExpr();

        $templates = WhatsappMessage::query()
            ->whereIn('sender_type', ['system', 'humano'])
            ->where('type', 'template')
            ->whereBetween('created_at', [$from, $to])
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->where(function ($query) use ($categoryExpr) {
                $query->whereNull('metadata->template_category')
                    ->orWhereRaw("{$categoryExpr} != 'AUTHENTICATION'");
            })
            ->count();

        $campaigns = (int) WhatsappCampaign::query()
            ->where('status', 'completed')
            ->where('message_type', 'template')
            ->whereBetween('sent_at', [$from, $to])
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->sum('sent_count');

        return $templates + $campaigns;
    }

    private function countAuthenticationMessages(Carbon $from, Carbon $to, ?int $businessProfileId): int
    {
        return WhatsappMessage::query()
            ->whereIn('sender_type', ['system', 'humano'])
            ->where('type', 'template')
            ->whereBetween('created_at', [$from, $to])
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->whereRaw("{$this->templateCategoryExpr()} = 'AUTHENTICATION'")
            ->count();
    }

    /**
     * Envíos completados de campañas de texto/imagen libre ("Plantillas
     * útiles"): no son plantillas aprobadas por Meta, solo le llegan a
     * contactos que escribieron en las últimas 24 h, y se cobran aparte de
     * las promociones (categoría "marketing").
     */
    private function countFreeformCampaignMessages(Carbon $from, Carbon $to, ?int $businessProfileId): int
    {
        return (int) WhatsappCampaign::query()
            ->where('status', 'completed')
            ->whereIn('message_type', ['text', 'image'])
            ->whereBetween('sent_at', [$from, $to])
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->sum('sent_count');
    }

    private function dailyCostTrend(Carbon $from, Carbon $to, ?int $businessProfileId): array
    {
        $days = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $dayEnd = $cursor->copy()->endOfDay();
            $counts = $this->countByCategory($cursor, $dayEnd, $businessProfileId);
            $days[] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('d/m'),
                'cost' => $this->pricing->estimateCost($counts, 'min'),
            ];
            $cursor->addDay();
        }

        return $days;
    }
}
