<?php

namespace App\Mail;

use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/** "What's new in Whale Wallet": the release note in the blue design, with a one-click unsubscribe. */
class ReleaseNoteMail extends Mailable
{
    public string $unsubscribe;

    public function __construct(public ReleaseNote $note, public User $user)
    {
        $this->unsubscribe = URL::signedRoute('release.unsubscribe', ['user' => $user->id]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "What's new: {$this->note->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.release', text: 'mail.release-text', with: ['blocks' => $this->note->blocks()]);
    }
}
