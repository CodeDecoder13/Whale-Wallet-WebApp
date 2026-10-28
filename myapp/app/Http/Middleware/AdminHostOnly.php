<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When ADMIN_DOMAIN is set (admin.whale-wallet.cloud), that host serves the admin and the sign-in pages only.
 * Everything else the app does (dashboard, cards, sign-up...) is sent back to the admin home, so the admin
 * host is not a second copy of the user app. Its cookies are host-only (SESSION_DOMAIN is null), so a session
 * on the main site is not a session here: the admin signs in again on this host.
 */
class AdminHostOnly
{
    /** Paths the admin host may serve besides the admin routes themselves. */
    private const ALLOWED = ['login', 'logout', 'two-factor-challenge', 'forgot-password', 'reset-password', 'reset-password/*', 'confirm-password', 'user/confirm-password', 'up'];

    public static function isAdminHost(Request $request): bool
    {
        $domain = config('app.admin_domain');

        return $domain && strcasecmp($request->getHost(), $domain) === 0;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::isAdminHost($request)) {
            return $next($request);
        }

        if (! ($request->routeIs('admin.*') || $request->is(...self::ALLOWED))) {
            return redirect('/');
        }

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow'); // also covers the sign-in pages, before anyone is an admin

        return $response;
    }
}
