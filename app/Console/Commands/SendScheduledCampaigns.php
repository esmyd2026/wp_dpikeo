<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\MarketingCampaignController;
use App\Models\WhatsappCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:send-scheduled';

    protected $description = 'Envía campañas de marketing programadas cuya fecha ya pasó';

    public function handle(): int
    {
        $campaigns = WhatsappCampaign::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get();

        if ($campaigns->isEmpty()) {
            $this->info('No hay campañas programadas pendientes.');
            return self::SUCCESS;
        }

        $controller = app(MarketingCampaignController::class);

        foreach ($campaigns as $campaign) {
            $this->info("Enviando campaña #{$campaign->id}: {$campaign->name}");
            try {
                $result = $controller->executeCampaignSend($campaign->fresh());
                if ($result['ok']) {
                    $this->info($result['message']);
                } else {
                    $this->warn($result['message']);
                }
            } catch (\Throwable $e) {
                Log::error('[campaigns:send-scheduled] Error', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Error en campaña #{$campaign->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
