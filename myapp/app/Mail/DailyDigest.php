<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/** Today's Whale tips, already checked against the user's own numbers by GenerateRecommendations. */
class DailyDigest extends Mailable
{
    /** @param  Collection<int, \App\Models\Recommendation>  $tips */
    public function __construct(public User $user, public Collection $tips) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your daily Whale tips');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.digest');
    }
}
