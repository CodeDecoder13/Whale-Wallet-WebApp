<?php

namespace App\Mail;

use App\Models\Lend;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the person who borrowed: the loan, the agreed date, and a reminder as it gets close. Replies go to the lender. */
class LendNotice extends Mailable
{
    public string $headline;

    public string $intro;

    /** @param 'new'|'soon'|'today'|'overdue' $kind */
    public function __construct(public Lend $lend, public User $lender, public string $kind)
    {
        $due = $lend->due_on->format('F j, Y');
        $who = $lender->name;

        [$this->headline, $this->intro] = match ($kind) {
            'new' => ["{$who} recorded a loan to you", "{$who} lent you this amount and keeps a record of it in Whale Wallet. Please pay it back by {$due}."],
            'soon' => ['A reminder: your loan is due soon', "Your loan from {$who} is due on {$due}."],
            'today' => ['Your loan is due today', "Your loan from {$who} is due today."],
            default => ['Your loan is overdue', "Your loan from {$who} was due on {$due} and is still unpaid."],
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline, replyTo: [new Address($this->lender->email, $this->lender->name)]);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.lend', text: 'mail.lend-text');
    }
}
