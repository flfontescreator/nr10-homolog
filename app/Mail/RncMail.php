<?php

namespace App\Mail;

use App\Models\Rnc;
use App\Models\RncRevision;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RncMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Rnc $rnc,
        public RncRevision $revision,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('%s — %s (%s)', $this->rnc->code, $this->rnc->titulo, $this->revision->label),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rnc',
            with: [
                'rnc' => $this->rnc,
                'revision' => $this->revision,
                'publicUrl' => $this->revision->publicUrl(),
                'validade' => $this->revision->public_expires_at?->format('d/m/Y'),
            ],
        );
    }
}
