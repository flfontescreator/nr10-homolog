<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoStepCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;

    public string $name;

    public function __construct(string $code, string $name)
    {
        $this->code = $code;
        $this->name = $name;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [],
            subject: 'Seu código de verificação — Gestão de Conformidades NR-10',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.two-step-code',
            with: [
                'code' => $this->code,
                'name' => $this->name,
            ],
        );
    }
}
