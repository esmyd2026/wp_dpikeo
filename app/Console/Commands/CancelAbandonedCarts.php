<?php

namespace App\Console\Commands;

use App\Services\AbandonedCartService;
use Illuminate\Console\Command;

class CancelAbandonedCarts extends Command
{
    protected $signature = 'carts:cancel-abandoned';

    protected $description = 'Cancela pedidos de WhatsApp abandonados a medias y avisa al cliente (ver Configuración del bot)';

    public function handle(AbandonedCartService $service): int
    {
        $closed = $service->cancelTimedOut();

        if ($closed > 0) {
            $this->info("Se cerraron {$closed} pedido(s) abandonado(s).");
        }

        return self::SUCCESS;
    }
}
