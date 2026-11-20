<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccessRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{name: string, email: string, company: string, fleet: ?string, message: ?string}  $request
     */
    public function __construct(public array $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Solicitud de acceso · '.$this->request['company'],
            replyTo: [$this->request['email']],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.access-request');
    }
}
