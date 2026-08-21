<?php

namespace App\Console\Commands;

use App\Services\PendingReplyRecoveryService;
use App\Services\WhatsappService;
use Illuminate\Console\Command;

class RetryPendingWhatsappReplies extends Command
{
    protected $signature = 'whatsapp:retry-pending-replies';

    protected $description = 'Reintenta responder mensajes de clientes que se quedaron sin respuesta (p. ej. porque el servicio se cayó a mitad de procesar)';

    public function handle(PendingReplyRecoveryService $service, WhatsappService $whatsapp): int
    {
        $recovered = $service->recover($whatsapp);

        if ($recovered > 0) {
            $this->info("Se reintentaron {$recovered} mensaje(s) sin respuesta.");
        }

        return self::SUCCESS;
    }
}
