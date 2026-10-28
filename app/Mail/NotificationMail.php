<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->title.' · '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
        );
    }
}
