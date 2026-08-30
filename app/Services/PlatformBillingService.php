<?php

namespace App\Services;

use App\Models\User;

class PlatformBillingService
{
    /** Motivo por el que el bot no responde, o null si puede responder. */
    public function botBlockReason(?object $contact = null): ?string
    {
        if ($contact && ! ($contact->bot_enabled ?? true)) {
            return 'contact_bot_disabled';
        }

        return null;
    }

    public function isBotSuspended(?User $user = null): bool
    {
        return false;
    }

    public function isChatSuspended(?User $user = null): bool
    {
        return false;
    }

    public function isOrdersSuspended(?User $user = null): bool
    {
        return false;
    }

    public function botMayRespondToContact(object $contact): bool
    {
        return $this->botBlockReason($contact) === null;
    }
}
