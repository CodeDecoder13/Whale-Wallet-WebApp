<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Your daily Whale tips</title>
</head>
<body style="margin:0;padding:0;background:#eef4fb;font-family:'Instrument Sans',-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0b2545;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4fb;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 12px 32px rgba(11,37,69,0.10);">

    <tr>
        <td bgcolor="#0b2545" style="background:#0b2545;background-image:linear-gradient(135deg,#0b2545 0%,#1565c0 100%);padding:28px 28px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                <td width="84" valign="middle">
                    <img src="{{ url('/mascot/happy.png') }}" width="72" height="72" alt="Whale" style="display:block;width:72px;height:72px;border-radius:36px;border:3px solid rgba(255,255,255,0.35);background:#eaf2ff;">
                </td>
                <td valign="middle" style="padding-left:6px;">
                    <div style="font-size:20px;font-weight:700;color:#ffffff;letter-spacing:-0.3px;">Whale Wallet</div>
                    <div style="font-size:13px;color:#bcd9fb;margin-top:2px;">Your daily Whale tips</div>
                </td>
            </tr></table>
        </td>
    </tr>

    <tr>
        <td style="padding:28px 28px 8px;">
            <div style="font-size:24px;font-weight:700;letter-spacing:-0.4px;color:#0b2545;">Good morning, {{ explode(' ', $user->name)[0] }}</div>
            <div style="font-size:15px;line-height:1.6;color:#4a5f7a;margin-top:6px;">Here is what Whale noticed about your money today.</div>
        </td>
    </tr>

    @foreach ($tips as $tip)
    <tr>
        <td style="padding:6px 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e3f2fd;border-radius:12px;">
                <tr>
                    <td width="4" style="background:#1e88e5;border-radius:12px 0 0 12px;font-size:0;line-height:0;">&nbsp;</td>
                    <td style="padding:14px 16px;font-size:15px;line-height:1.55;color:#0b2545;">{{ $tip->text }}</td>
                </tr>
            </table>
        </td>
    </tr>
    @endforeach

    <tr>
        <td align="center" style="padding:22px 28px 30px;">
            <a href="{{ url('/dashboard') }}" style="display:inline-block;background:#1e88e5;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;padding:13px 28px;border-radius:10px;">Open Whale Wallet</a>
        </td>
    </tr>

    <tr>
        <td style="background:#f6f9fd;border-top:1px solid #e1eaf5;padding:18px 28px;font-size:12px;line-height:1.6;color:#6b7f99;text-align:center;">
            You get this because the daily digest is on. Change the time or turn it off in
            <a href="{{ route('profile.edit') }}" style="color:#1565c0;">Settings</a>.<br>
            Budgeting guidance based on the numbers you entered, not licensed financial advice.
        </td>
    </tr>

</table>
@include('mail.partials.legal')
</td></tr>
</table>
</body>
</html>
