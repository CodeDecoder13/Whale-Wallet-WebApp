<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the owner: "Ana Cruz just signed in", with when, from what device and IP, and whether the device is new. */
class LoginAlertMail extends Mailable
{
    public function __construct(public User $user, public string $device, public ?string $ip, public bool $newDevice, public string $when, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Sign-in: {$this->user->name}".($this->newDevice ? ' (new device)' : ''));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.login-alert', text: 'mail.login-alert-text');
    }
}
