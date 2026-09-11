<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\SendWhatsAppTemplate::class,
        Commands\TestChatGPTConnection::class,
        Commands\GenerateWhatsappFlowKeys::class,
        Commands\RegisterWhatsappFlowPublicKey::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('campaigns:send-scheduled')->everyMinute();
        $schedule->command('carts:cancel-abandoned')->everyFiveMinutes();
        $schedule->command('orders:alert-delayed-fulfillment-costs')->everyFiveMinutes();
        // Pedido explícito en vivo: "el cliente no dijo nada y le volvió a
        // pasar el menú -- eso no puede pasar." Este comando reprocesaba el
        // ÚLTIMO mensaje del cliente como si acabara de llegar cuando nadie
        // le había respondido todavía, para "recuperar" respuestas perdidas
        // por fallas de envío a Meta. Pero si ADEMÁS el pedido ya se había
        // cerrado por abandono (AbandonedCartService::close(), que resetea
        // la posición del flujo antes de confirmar que el aviso se envió),
        // ese mismo mensaje viejo caía en el menú principal por defecto y se
        // le mandaba al cliente sin que escribiera nada. Se desactiva por
        // completo: nunca se le debe enviar nada a un cliente sin un mensaje
        // suyo real que lo dispare. El comando y el servicio (
        // RetryPendingWhatsappReplies / PendingReplyRecoveryService) quedan
        // intactos por si se decide reactivar con una lógica más segura.
        // $schedule->command('whatsapp:retry-pending-replies')->everyMinute();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
