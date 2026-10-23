<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset your Whale Wallet password</title>
</head>
<body style="margin:0;padding:0;background:#eef4fb;font-family:'Instrument Sans',-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0b2545;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4fb;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 12px 32px rgba(11,37,69,0.10);">
    <tr>
        <td bgcolor="#0b2545" style="background:#0b2545;background-image:linear-gradient(135deg,#0b2545 0%,#1565c0 100%);padding:24px 28px;">
            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                <td><img src="{{ url('/mascot/happy.png') }}" width="56" height="56" alt="Whale" style="display:block;width:56px;height:56px;border-radius:28px;border:3px solid rgba(255,255,255,0.35);background:#eaf2ff;"></td>
                <td style="padding-left:12px;font-size:20px;font-weight:700;color:#ffffff;">Whale Wallet</td>
            </tr></table>
        </td>
    </tr>
    <tr>
        <td style="padding:28px;text-align:center;">
            <div style="font-size:22px;font-weight:700;">Reset your password</div>
            <div style="font-size:15px;line-height:1.6;color:#4a5f7a;margin-top:8px;">Hi {{ explode(' ', $name)[0] }}, we got a request to reset your password. The link works for {{ $minutes }} minutes.</div>
            <a href="{{ $url }}" style="display:inline-block;margin-top:22px;background:#1e88e5;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;padding:13px 28px;border-radius:10px;">Reset password</a>
            <div style="font-size:13px;line-height:1.6;color:#6b7f99;margin-top:22px;">If you did not ask for this, ignore this email. Your password stays the same.</div>
            <div style="font-size:12px;line-height:1.6;color:#8a9ab0;margin-top:14px;word-break:break-all;">Button not working? Paste this link into your browser:<br><a href="{{ $url }}" style="color:#1565c0;">{{ $url }}</a></div>
        </td>
    </tr>
</table>
@include('mail.partials.legal')
</td></tr>
</table>
</body>
</html>
