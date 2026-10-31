<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $headline }}</title>
</head>
<body style="margin:0;padding:0;background:#eef4fb;font-family:'Instrument Sans',-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0b2545;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4fb;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 12px 32px rgba(11,37,69,0.10);">
    <tr>
        <td bgcolor="#0b2545" style="background:#0b2545;background-image:linear-gradient(135deg,#0b2545 0%,#1565c0 100%);padding:24px 28px;">
            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                <td><img src="{{ rtrim(config('app.url'), '/') }}/mascot/{{ $kind === 'overdue' ? 'checking' : 'happy' }}.png" width="56" height="56" alt="Whale" style="display:block;width:56px;height:56px;border-radius:28px;border:3px solid rgba(255,255,255,0.35);background:#eaf2ff;"></td>
                <td style="padding-left:12px;font-size:20px;font-weight:700;color:#ffffff;">Whale Wallet</td>
            </tr></table>
        </td>
    </tr>
    <tr>
        <td style="padding:28px;">
            <div style="font-size:15px;">Hi {{ $lend->name }},</div>
            <div style="margin-top:8px;font-size:20px;font-weight:700;">{{ $headline }}</div>
            <div style="margin-top:12px;font-size:15px;line-height:1.6;">{{ $intro }}</div>
            <div style="margin-top:18px;padding:18px;background:#e3f2fd;border-radius:14px;text-align:center;">
                <div style="font-size:12px;color:#6b7f99;">Amount to pay back</div>
                <div style="font-size:32px;font-weight:700;color:{{ $kind === 'overdue' ? '#c62828' : '#0b2545' }};">&#8369;{{ number_format($lend->amount / 100, 2) }}</div>
            </div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;font-size:14px;">
                <tr><td style="padding:6px 0;color:#6b7f99;">Lent on</td><td align="right" style="padding:6px 0;font-weight:600;">{{ $lend->lent_on->format('M j, Y') }}</td></tr>
                <tr><td style="padding:6px 0;color:#6b7f99;border-top:1px solid #e3ebf5;">Pay back by</td><td align="right" style="padding:6px 0;font-weight:600;border-top:1px solid #e3ebf5;">{{ $lend->due_on->format('M j, Y') }}</td></tr>
                @if ($lend->reference)
                    <tr><td style="padding:6px 0;color:#6b7f99;border-top:1px solid #e3ebf5;">Reference</td><td align="right" style="padding:6px 0;font-weight:600;border-top:1px solid #e3ebf5;">{{ $lend->reference }}</td></tr>
                @endif
            </table>
            <div style="margin-top:20px;font-size:13px;line-height:1.6;color:#6b7f99;">
                Pay {{ $lender->name }} directly, the way you agreed. Whale Wallet never takes payments and never asks for your card or password.
                Questions? Reply to this email to reach {{ $lender->name }}.
            </div>
        </td>
    </tr>
</table>
<div style="max-width:480px;margin:14px auto 0;font-size:12px;line-height:1.6;color:#8a9ab0;text-align:center;">
    {{ $lender->name }} asked Whale Wallet to send you this email. If you do not know about this loan, ignore it or reply to tell {{ $lender->name }}.
</div>
@include('mail.partials.legal')
</td></tr>
</table>
</body>
</html>
