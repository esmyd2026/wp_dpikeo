<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Pedido explícito en vivo: "el cliente no dijo nada y le volvió a pasar el
 * menú -- eso no puede pasar." Causa raíz: el scheduler reprocesaba el
 * último mensaje del cliente como si acabara de llegar (whatsapp:retry-
 * pending-replies) para "recuperar" respuestas perdidas, lo que podía
 * disparar el menú principal sin ningún mensaje real del cliente -- se
 * desactivó. Esta prueba evita que alguien lo reactive sin darse cuenta.
 */
class ScheduledCommandsSafetyTest extends TestCase
{
    public function test_the_stuck_reply_recovery_command_is_never_scheduled_automatically(): void
    {
        $schedule = app(Schedule::class);

        $signatures = collect($schedule->events())->map(fn ($event) => $event->command)->implode(' | ');

        $this->assertStringNotContainsString('whatsapp:retry-pending-replies', $signatures);
    }
}
