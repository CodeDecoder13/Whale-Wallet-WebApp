<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>New contact message</title>
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
        <td style="padding:28px;">
            <div style="font-size:22px;font-weight:700;">New message: {{ $message->topic }}</div>
            <div style="font-size:14px;color:#4a5f7a;margin-top:6px;">From {{ $message->name }} ({{ $message->email }})</div>
            <div style="margin-top:16px;background:#e3f2fd;border-radius:12px;padding:16px;font-size:15px;line-height:1.6;white-space:pre-wrap;">{{ $message->message }}</div>
            <div style="font-size:13px;color:#6b7f99;margin-top:16px;">Reply to this email to answer {{ $message->name }} directly.</div>
        </td>
    </tr>
</table>
@include('mail.partials.legal')
</td></tr>
</table>
</body>
</html>
