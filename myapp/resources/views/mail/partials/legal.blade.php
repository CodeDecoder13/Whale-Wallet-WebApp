<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;margin:0 auto;">
    <tr>
        <td align="center" style="padding:22px 16px 6px;font-size:12px;line-height:1.6;color:#6b7f99;">
            We will never ask for your password in an email. If you do not trust a link in an email, sign in directly at
            <a href="{{ url('/') }}" style="color:#1565c0;text-decoration:none;">{{ parse_url(url('/'), PHP_URL_HOST) }}</a>.
        </td>
    </tr>
    <tr>
        <td align="center" style="padding:10px 16px;font-size:13px;color:#8a9ab0;">
            <a href="{{ route('terms') }}" style="color:#1565c0;text-decoration:none;">Terms and Conditions</a>
            &nbsp;|&nbsp;
            <a href="{{ route('privacy.policy') }}" style="color:#1565c0;text-decoration:none;">Privacy Policy</a>
            &nbsp;|&nbsp;
            <a href="{{ route('contact') }}" style="color:#1565c0;text-decoration:none;">Help</a>
        </td>
    </tr>
    <tr>
        <td align="center" style="padding:6px 16px 8px;font-size:12px;line-height:1.6;color:#8a9ab0;">
            Operated by Rhuzzel Paramio, Philippines<br>
            &copy; {{ date('Y') }} Whale Wallet. All rights reserved.
        </td>
    </tr>
</table>
