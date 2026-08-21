<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MonitoringNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $contactName;
    public $phoneNumber;
    public $messageContent;
    public $messageType;
    public $timestamp;

    /**
     * Create a new message instance.
     */
    public function __construct($contactName, $phoneNumber, $messageContent, $messageType, $timestamp)
    {
        $this->contactName = $contactName;
        $this->phoneNumber = $phoneNumber;
        $this->messageContent = $messageContent;
        $this->messageType = $messageType;
        $this->timestamp = $timestamp;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuevo mensaje recibido en el bot de WhatsApp',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.monitoring',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
