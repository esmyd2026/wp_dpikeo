<?php

namespace App\Services;

use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\DB;

class DemoResetService
{
    /**
     * Borra mensajes y pedidos de demo y restablece el estado operativo de los contactos.
     * No modifica catálogo, flujo del bot, usuarios ni tarifas.
     */
    public function reset(): array
    {
        return DB::transaction(function () {
            $messagesDeleted = WhatsappMessage::count();
            $cartsDeleted = WhatsappCart::count();
            $conversationsDeleted = WhatsappConversation::count();

            WhatsappCart::query()->delete();
            WhatsappConversation::query()->delete();
            WhatsappMessage::query()->delete();

            $contactsReset = $this->resetContacts();

            return [
                'messages_deleted' => $messagesDeleted,
                'carts_deleted' => $cartsDeleted,
                'conversations_deleted' => $conversationsDeleted,
                'contacts_reset' => $contactsReset,
            ];
        });
    }

    private function resetContacts(): int
    {
        $count = 0;

        WhatsappContact::query()->chunkById(100, function ($contacts) use (&$count) {
            foreach ($contacts as $contact) {
                $metadata = $contact->metadata ?? [];
                unset(
                    $metadata['needs_agent'],
                    $metadata['agent_requested_at'],
                    $metadata['agent_request_source'],
                    $metadata['human_sent'],
                    $metadata['human_sent_at'],
                );

                $contact->update([
                    'bot_enabled' => true,
                    'last_inbound_message_id' => null,
                    'last_inbound_at' => null,
                    'metadata' => $metadata === [] ? null : $metadata,
                ]);

                $count++;
            }
        });

        return $count;
    }
}
