{{ $user->name }} just signed in{{ $newDevice ? ' (new device)' : '' }}.

Email: {{ $user->email }}
When: {{ $when }}
Device: {{ $device }}
IP address: {{ $ip ?? 'unknown' }}

Open in admin: {{ $url }}
