{{ ($reset ?? false) ? 'Hi' : 'Welcome to Whale Wallet,' }} {{ explode(' ', $name)[0] }}.

Your code: {{ $code }}

It works for 10 minutes. {{ ($reset ?? false) ? 'This code resets your account to new. If you did not ask for it, ignore this email.' : 'If you did not sign up, ignore this email.' }}
