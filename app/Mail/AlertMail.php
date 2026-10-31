<?php

namespace App\Mail;

use App\Services\Notifications\MessageContent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Alerte (panne, retour en ligne, panne prolongée, test). Envoyée depuis la file, pas de ShouldQueue ici. */
class AlertMail extends Mailable
{
    public function __construct(public readonly MessageContent $content) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->content->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.alert', text: 'mail.alert-text', with: ['alert' => $this->content]);
    }
}
