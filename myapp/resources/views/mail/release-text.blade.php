What's new in Whale Wallet: {{ $note->title }}{{ $note->version ? " ({$note->version})" : '' }}

@foreach ($blocks as $b)
@if ($b['type'] === 'p')
{{ $b['text'] }}

@else
@foreach ($b['items'] as $item)
- {{ $item }}
@endforeach

@endif
@endforeach
Open Whale Wallet: {{ url('/dashboard') }}

Unsubscribe from product updates: {{ $unsubscribe }}
