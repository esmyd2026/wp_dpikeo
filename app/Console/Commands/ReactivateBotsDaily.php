<?php

namespace App\Console\Commands;

use App\Models\WhatsappContact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pedido explícito: "que diariamente se active [el bot] para todos los
 * clientes excepto a los de la lista negra". Esto solo cambia el
 * interruptor (bot_enabled); nunca reprocesa ni reenvía ningún mensaje --
 * el cliente simplemente vuelve a tener respuesta automática la próxima vez
 * que escriba. Los contactos en bot_blacklisted=true (ver WhatsappContact)
 * quedan afuera a propósito: son los que un asesor decidió que nunca deben
 * volver a activarse solos.
 */
class ReactivateBotsDaily extends Command
{
    protected $signature = 'whatsapp:reactivate-bots-daily';

    protected $description = 'Reactiva el bot todos los días para los contactos pausados que no estén en la lista negra';

    public function handle(): int
    {
        $reactivated = WhatsappContact::query()
            ->where('bot_enabled', false)
            ->where('bot_blacklisted', false)
            ->update(['bot_enabled' => true]);

        if ($reactivated > 0) {
            Log::info('[ReactivateBotsDaily] Bot reactivado automáticamente', [
                'count' => $reactivated,
            ]);
            $this->info("Se reactivó el bot para {$reactivated} contacto(s).");
        }

        return self::SUCCESS;
    }
}
