<?php

namespace App\Mail;

use App\Models\CotioInstancia;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MuestraSinResponsableMail extends Mailable
{
    use Queueable, SerializesModels;

    public $coordinador;
    public $muestra;
    public $url;

    /**
     * Create a new message instance.
     */
    public function __construct(User $coordinador, CotioInstancia $muestra, string $url)
    {
        $this->coordinador = $coordinador;
        $this->muestra = $muestra;
        $this->url = $url;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ Muestra sin responsables asignados - ' . ($this->muestra->cotio_descripcion ?? 'Sin descripción'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.muestra-sin-responsable',
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
