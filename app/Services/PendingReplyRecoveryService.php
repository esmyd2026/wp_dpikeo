<?php

namespace App\Services;

use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageFailure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Si el servicio se cae (o el proceso muere) justo después de guardar un
 * mensaje entrante pero antes de que el bot alcance a responderlo, ese
 * mensaje queda huérfano para siempre: ya existe en whatsapp_messages (con
 * message_id único), así que un reintento de webhook de Meta lo encuentra
 * "ya procesado" y lo ignora (ver WhatsappService::processIncomingMessage).
 *
 * Este servicio detecta esos mensajes huérfanos (el último mensaje de la
 * conversación sigue siendo del cliente, sin ninguna respuesta después) y
 * fuerza un reintento real -- reconstruye un mensaje entrante "sintético"
 * con el mismo contenido y lo hace pasar por el mismo pipeline de siempre
 * (processIncomingMessage), así el cliente recibe la respuesta real que le
 * correspondía, no un mensaje genérico de disculpas.
 */
class PendingReplyRecoveryService
{
    private const MAX_ATTEMPTS = 3;
    private const MIN_AGE_MINUTES = 2;
    private const MAX_AGE_HOURS = 23;

    /** Tipos que se pueden reconstruir de forma fiable a partir de lo guardado. */
    private const RECOVERABLE_TYPES = ['text', 'interactive'];

    public function recover(WhatsappService $whatsapp): int
    {
        $recovered = 0;

        foreach ($this->findStuckMessages() as $stuck) {
            if ($this->attempt($stuck, $whatsapp)) {
                $recovered++;
            }
        }

        return $recovered;
    }

    /**
     * Un mensaje "atascado" es el más reciente de su conversación, sigue
     * siendo del cliente (nadie -- ni bot ni asesor -- respondió después),
     * tiene un tipo que sabemos reconstruir, y ya pasó suficiente tiempo
     * como para no confundirlo con uno que está siendo procesado ahora
     * mismo (ni tan viejo que ya se cerró la ventana de 24h de WhatsApp).
     */
    private function findStuckMessages()
    {
        $latestPerContact = WhatsappMessage::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('contact_id')
            ->pluck('id');

        return WhatsappMessage::query()
            ->whereIn('id', $latestPerContact)
            ->where('sender_type', 'client')
            ->whereIn('type', self::RECOVERABLE_TYPES)
            ->where('created_at', '<=', now()->subMinutes(self::MIN_AGE_MINUTES))
            ->where('created_at', '>=', now()->subHours(self::MAX_AGE_HOURS))
            ->with('contact')
            ->get();
    }

    private function attempt(WhatsappMessage $stuck, WhatsappService $whatsapp): bool
    {
        $contact = $stuck->contact;
        if (!$contact || !$contact->phone_number || str_starts_with($contact->phone_number, 'POS-')) {
            return false;
        }

        // Si el bot está apagado para este contacto (lo tomó un asesor) o la
        // cuenta está suspendida, el silencio es intencional -- no forzar
        // una respuesta automática por encima de eso.
        if (!app(PlatformBillingService::class)->botMayRespondToContact($contact)) {
            return false;
        }

        $attemptsKey = "wa-recovery-attempts:{$contact->id}";
        $attempts = (int) Cache::get($attemptsKey, 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->reportPersistentFailure($contact, $stuck);
            return false;
        }

        // Revalida en vivo justo antes de actuar: si en el instante entre la
        // consulta y este punto ya se mandó una respuesta (el servicio se
        // recuperó solo, o un asesor contestó), no lo dupliques.
        $stillLatestId = WhatsappMessage::where('contact_id', $contact->id)->max('id');
        if ($stillLatestId !== $stuck->id) {
            return false;
        }

        $messageData = $this->buildSyntheticMessage($contact, $stuck);
        if (!$messageData) {
            return false;
        }

        Cache::put($attemptsKey, $attempts + 1, now()->addHour());

        Log::warning('[PendingReplyRecoveryService] El bot no respondió a tiempo, reintentando', [
            'contact_id' => $contact->id,
            'business_profile_id' => $contact->business_profile_id,
            'original_message_id' => $stuck->message_id,
            'stuck_message_created_at' => $stuck->created_at?->toIso8601String(),
            'attempt' => $attempts + 1,
        ]);

        try {
            // La misma instancia de WhatsappService se reutiliza para todos
            // los mensajes atascados del comando (de cualquier empresa); hay
            // que reapuntarla al negocio del contacto en cada intento.
            $whatsapp->useBusinessProfile($contact->businessProfile);
            $whatsapp->processIncomingMessage($messageData);

            return true;
        } catch (\Throwable $e) {
            Log::error('[PendingReplyRecoveryService] Error reintentando responder', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Pedido explícito desde el panel: en vez de esperar el reintento
     * automático (que además espera al menos 2 minutos y limita a 3
     * intentos), un asesor puede forzarlo de una vez para UN contacto
     * puntual desde la conversación. No aplica el límite de intentos ni la
     * espera mínima -- es una acción manual, deliberada, sobre un caso a la
     * vista, no el barrido automático de cada minuto.
     *
     * @return array{ok: bool, message: string}
     */
    public function retryContact(WhatsappContact $contact, WhatsappService $whatsapp): array
    {
        if (! app(PlatformBillingService::class)->botMayRespondToContact($contact)) {
            return [
                'ok' => false,
                'message' => 'El bot está apagado para este cliente (o la cuenta está suspendida) -- actívalo primero.',
            ];
        }

        $stuck = WhatsappMessage::where('contact_id', $contact->id)->latest('id')->first();
        if (! $stuck || $stuck->sender_type !== 'client') {
            return [
                'ok' => false,
                'message' => 'No hay ningún mensaje del cliente esperando respuesta ahora mismo.',
            ];
        }

        if (! in_array($stuck->type, self::RECOVERABLE_TYPES, true)) {
            return [
                'ok' => false,
                'message' => 'Ese último mensaje no se puede reenviar automáticamente (tipo: '.$stuck->type.').',
            ];
        }

        $messageData = $this->buildSyntheticMessage($contact, $stuck);
        if (! $messageData) {
            return [
                'ok' => false,
                'message' => 'No se pudo reconstruir el mensaje original del cliente.',
            ];
        }

        Log::warning('[PendingReplyRecoveryService] Reintento manual disparado desde el panel', [
            'contact_id' => $contact->id,
            'business_profile_id' => $contact->business_profile_id,
            'original_message_id' => $stuck->message_id,
        ]);

        try {
            $whatsapp->useBusinessProfile($contact->businessProfile);
            $whatsapp->processIncomingMessage($messageData);

            return ['ok' => true, 'message' => 'Se reenvió su último mensaje al bot; debería responder en unos segundos.'];
        } catch (\Throwable $e) {
            Log::error('[PendingReplyRecoveryService] Error en el reintento manual', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Ocurrió un error al reintentar: '.$e->getMessage()];
        }
    }

    /** @return array<string, mixed>|null */
    private function buildSyntheticMessage(WhatsappContact $contact, WhatsappMessage $stuck): ?array
    {
        $base = [
            'from' => $contact->phone_number,
            // ID sintético (no reutiliza el original) para que el pipeline
            // normal lo trate como un mensaje nuevo y no lo descarte por el
            // índice único de message_id ni por el lock de deduplicación.
            'id' => 'recovery-' . $stuck->id . '-' . now()->timestamp,
            'timestamp' => (string) now()->timestamp,
            'contacts' => [],
        ];

        if ($stuck->type === 'text') {
            return $base + ['type' => 'text', 'text' => (string) $stuck->content];
        }

        if ($stuck->type === 'interactive') {
            $interactive = $stuck->metadata['interactive'] ?? null;
            if (empty($interactive)) {
                return null;
            }

            return $base + ['type' => 'interactive', 'text' => null, 'interactive' => $interactive];
        }

        return null;
    }

    /**
     * Después de agotar los reintentos automáticos, lo deja registrado en el
     * módulo de Fallos de envío para que el equipo lo vea y lo resuelva a
     * mano -- solo una vez por mensaje atascado, no cada minuto.
     */
    private function reportPersistentFailure(WhatsappContact $contact, WhatsappMessage $stuck): void
    {
        $reportedKey = "wa-recovery-reported:{$stuck->id}";
        if (Cache::has($reportedKey)) {
            return;
        }
        Cache::put($reportedKey, true, now()->addDay());

        WhatsappMessageFailure::create([
            'business_profile_id' => $stuck->business_profile_id,
            'contact_id' => $contact->id,
            'phone_number' => $contact->phone_number,
            'message_type' => $stuck->type,
            'source' => 'pending_reply_recovery',
            'error_message' => 'El bot nunca respondió este mensaje y ' . self::MAX_ATTEMPTS . ' reintentos automáticos tampoco lo lograron. Revísalo manualmente.',
            'context' => [
                'original_message_id' => $stuck->message_id,
                'content' => $stuck->content,
            ],
        ]);
    }
}
