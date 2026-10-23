<x-mail::message>
# New message: {{ $message->topic }}

**From:** {{ $message->name }} ({{ $message->email }})

<x-mail::panel>
{{ $message->message }}
</x-mail::panel>

Reply to this email to answer {{ $message->name }} directly.
</x-mail::message>
