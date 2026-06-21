<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LembreteDiario extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly int $flashcardsPendentes,
        public readonly int $streakAtual,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'StudyWiki — lembrete diário de estudos',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lembrete_diario',
        );
    }
}
