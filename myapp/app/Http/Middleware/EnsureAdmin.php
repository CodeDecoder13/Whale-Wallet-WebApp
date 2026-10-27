<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only admins get past; everyone else sees a plain 404 so the page does not confirm it exists.
 * `admin:owner` also requires that role (owners can do everything, support only the support pages).
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = $request->user();

        abort_unless($user?->admin_role && ! $user->suspended_at && (! $role || $user->admin_role === $role), 404);

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
