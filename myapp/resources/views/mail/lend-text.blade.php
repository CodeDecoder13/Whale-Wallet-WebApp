Hi {{ $lend->name }},

{{ $headline }}

{{ $intro }}

Amount to pay back: PHP {{ number_format($lend->amount / 100, 2) }}
Lent on: {{ $lend->lent_on->format('M j, Y') }}
Pay back by: {{ $lend->due_on->format('M j, Y') }}
@if ($lend->reference)
Reference: {{ $lend->reference }}
@endif

Pay {{ $lender->name }} directly, the way you agreed. Whale Wallet never takes payments and never asks for your card or password.
Questions? Reply to this email to reach {{ $lender->name }}.

{{ $lender->name }} asked Whale Wallet to send you this email. If you do not know about this loan, ignore it.
