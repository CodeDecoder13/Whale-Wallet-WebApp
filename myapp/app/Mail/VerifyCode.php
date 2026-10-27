<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A 6-digit code by email. $purpose picks the words: 'verify' (new account), 'reset' (reset to new account) or 'admin' (admin gate). */
class VerifyCode extends Mailable
{
    /** @var array{subject: string, hello: string, intro: string, foot: string} */
    public array $copy;

    public function __construct(public string $name, public string $code, public string $purpose = 'verify')
    {
        $this->copy = match ($purpose) {
            'reset' => [
                'subject' => 'reset your Whale Wallet account',
                'hello' => 'Hi',
                'intro' => 'Enter this code, with your PIN, to reset your account to new. Everything in it will be deleted.',
                'foot' => 'If you did not ask for this, ignore this email and change your password.',
            ],
            'admin' => [
                'subject' => 'open the Whale Wallet admin',
                'hello' => 'Hi',
                'intro' => 'Enter this code, with your PIN, to open the admin area. It stays open for 2 hours.',
                'foot' => 'If this was not you, someone may know your password. Change it now.',
            ],
            default => [
                'subject' => 'verify your Whale Wallet account',
                'hello' => 'Welcome',
                'intro' => 'Enter this code to confirm your email and open your dashboard.',
                'foot' => 'If you did not sign up, ignore this email.',
            ],
        };
    }

    public function envelope(): Envelope
    {
        // "Use 123456 to ..." is the subject pattern Gmail recognises as a one-time code
        return new Envelope(subject: "Use {$this->code} to {$this->copy['subject']}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.verify-code', text: 'mail.verify-code-text');
    }
}
