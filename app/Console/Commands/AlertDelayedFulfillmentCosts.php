<?php

namespace App\Console\Commands;

use App\Services\OrderDelayAlertService;
use Illuminate\Console\Command;

class AlertDelayedFulfillmentCosts extends Command
{
    protected $signature = 'orders:alert-delayed-fulfillment-costs';

    protected $description = 'Avisa al cliente y al staff cuando un pedido lleva demasiado tiempo esperando que caja confirme el costo de envío/empaque';

    public function handle(OrderDelayAlertService $service): int
    {
        $alerted = $service->alertOverdue();

        if ($alerted > 0) {
            $this->info("Se avisó sobre {$alerted} pedido(s) demorado(s).");
        }

        return self::SUCCESS;
    }
}
