<?php

namespace App\Mail;

use App\Models\Factura;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FacturaEnviadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Factura $factura,
        public ?string $rutaPdf = null
    ) {}

    public function envelope(): Envelope
    {
        $numero = trim((string) ($this->factura->numero_factura ?? ''));

        return new Envelope(
            subject: 'Factura ' . ($numero !== '' ? $numero : '#' . $this->factura->id),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.factura-enviada',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->rutaPdf === null || ! is_file($this->rutaPdf)) {
            return [];
        }

        return [
            Attachment::fromPath($this->rutaPdf)
                ->as(basename($this->rutaPdf))
                ->withMime('application/pdf'),
        ];
    }
}
