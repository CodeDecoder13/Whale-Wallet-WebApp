<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A message sent through the public Contact form, forwarded to the support inbox. Replying goes to the sender. */
class ContactReceived extends Mailable
{
    public function __construct(public ContactMessage $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->message->email, $this->message->name)],
            subject: "[{$this->message->topic}] Message from {$this->message->name}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact');
    }
}
