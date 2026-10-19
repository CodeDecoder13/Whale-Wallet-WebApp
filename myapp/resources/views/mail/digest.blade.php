<x-mail::message>
# Good morning, {{ explode(' ', $user->name)[0] }}

Here is what Whale noticed about your money today.

@foreach ($tips as $tip)
- {{ $tip->text }}
@endforeach

<x-mail::button :url="url('/dashboard')">
Open Whale Wallet
</x-mail::button>

You get this because the daily digest is on. Change the time or turn it off in [Settings]({{ route('profile.edit') }}).
</x-mail::message>
