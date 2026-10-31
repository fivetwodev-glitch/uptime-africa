<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Résumé périodique d'une agence : disponibilité par site et incidents de la période. */
class DigestMail extends Mailable
{
    public function __construct(public readonly array $digest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Résumé '.$this->digest['period_label'].' · '.$this->digest['agency']);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.digest', with: ['digest' => $this->digest]);
    }
}
