<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alternativa al código de recuperación por WhatsApp (ver
 * StorefrontAccountController::requestPasswordReset) para cuando el mensaje
 * no le llega al cliente.
 */
class StorefrontPasswordResetCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public string $businessName)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Código para recuperar tu cuenta',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.storefront-password-reset',
        );
    }
}
