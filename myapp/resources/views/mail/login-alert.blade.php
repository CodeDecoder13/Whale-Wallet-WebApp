<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign-in: {{ $user->name }}</title>
</head>
<body style="margin:0;padding:0;background:#eef4fb;font-family:'Instrument Sans',-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0b2545;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4fb;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 12px 32px rgba(11,37,69,0.10);">
    <tr>
        <td bgcolor="#0b2545" style="background:#0b2545;background-image:linear-gradient(135deg,#0b2545 0%,#1565c0 100%);padding:24px 28px;">
            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                <td><img src="{{ rtrim(config('app.url'), '/') }}/mascot/checking.png" width="56" height="56" alt="Whale" style="display:block;width:56px;height:56px;border-radius:28px;border:3px solid rgba(255,255,255,0.35);background:#eaf2ff;"></td>
                <td style="padding-left:12px;font-size:20px;font-weight:700;color:#ffffff;">Whale Wallet admin</td>
            </tr></table>
        </td>
    </tr>
    <tr>
        <td style="padding:28px;">
            <div style="font-size:22px;font-weight:700;line-height:1.3;">{{ $user->name }} just signed in</div>
            @if ($newDevice)
                <div style="margin-top:10px;"><span style="display:inline-block;background:#fff3cd;color:#8a6100;font-size:12px;font-weight:700;padding:3px 10px;border-radius:999px;">New device</span></div>
            @endif

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;font-size:14px;line-height:1.5;">
                <tr><td style="padding:6px 0;color:#6b7f99;width:90px;">Email</td><td style="padding:6px 0;">{{ $user->email }}</td></tr>
                <tr><td style="padding:6px 0;color:#6b7f99;">When</td><td style="padding:6px 0;">{{ $when }}</td></tr>
                <tr><td style="padding:6px 0;color:#6b7f99;">Device</td><td style="padding:6px 0;">{{ $device }}</td></tr>
                <tr><td style="padding:6px 0;color:#6b7f99;">IP address</td><td style="padding:6px 0;">{{ $ip ?? 'unknown' }}</td></tr>
            </table>

            <div style="margin-top:22px;text-align:center;">
                <a href="{{ $url }}" style="display:inline-block;background:#1565c0;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 28px;border-radius:10px;">Open {{ explode(' ', $user->name)[0] }} in admin</a>
            </div>
            <div style="margin-top:14px;text-align:center;font-size:12px;color:#8a9ab0;">Change how often you get these in the admin overview: Login alerts.</div>
        </td>
    </tr>
</table>
@include('mail.partials.legal')
</td></tr>
</table>
</body>
</html>
