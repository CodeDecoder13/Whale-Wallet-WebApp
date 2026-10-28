<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $note->title }}</title>
</head>
<body style="margin:0;padding:0;background:#eef4fb;font-family:'Instrument Sans',-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0b2545;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4fb;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 12px 32px rgba(11,37,69,0.10);">
    <tr>
        <td bgcolor="#0b2545" style="background:#0b2545;background-image:linear-gradient(135deg,#0b2545 0%,#1565c0 100%);padding:24px 28px;">
            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                <td><img src="{{ rtrim(config('app.url'), '/') }}/mascot/excited.png" width="56" height="56" alt="Whale" style="display:block;width:56px;height:56px;border-radius:28px;border:3px solid rgba(255,255,255,0.35);background:#eaf2ff;"></td>
                <td style="padding-left:12px;font-size:20px;font-weight:700;color:#ffffff;">What's new</td>
            </tr></table>
        </td>
    </tr>
    <tr>
        <td style="padding:28px;">
            <div style="font-size:13px;color:#4a5f7a;">Hi {{ explode(' ', $user->name)[0] }},</div>
            <div style="margin-top:6px;font-size:22px;font-weight:700;line-height:1.3;">{{ $note->title }}</div>
            @if ($note->version)
                <div style="margin-top:8px;"><span style="display:inline-block;background:#e3f2fd;color:#1565c0;font-size:12px;font-weight:700;padding:3px 10px;border-radius:999px;">{{ $note->version }}</span></div>
            @endif

            <div style="margin-top:16px;font-size:15px;line-height:1.65;color:#23395b;">
                @foreach ($blocks as $b)
                    @if ($b['type'] === 'p')
                        <p style="margin:0 0 12px;">{{ $b['text'] }}</p>
                    @else
                        <ul style="margin:0 0 12px;padding-left:20px;">
                            @foreach ($b['items'] as $item)
                                <li style="margin:0 0 6px;">{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                @endforeach
            </div>

            <div style="margin-top:22px;text-align:center;">
                <a href="{{ rtrim(config('app.url'), '/') }}/dashboard" style="display:inline-block;background:#1565c0;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 28px;border-radius:10px;">Open Whale Wallet</a>
            </div>
        </td>
    </tr>
</table>
@include('mail.partials.legal')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;margin:0 auto;">
    <tr><td align="center" style="padding:0 16px 8px;font-size:12px;color:#8a9ab0;">
        You get product updates because you have a Whale Wallet account.
        <a href="{{ $unsubscribe }}" style="color:#1565c0;text-decoration:none;">Unsubscribe</a>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
