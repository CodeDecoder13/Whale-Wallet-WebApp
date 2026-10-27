<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The PIN + emailed-code check, good for 2 hours; sign-out clears the session and so the check. */
class RequireAdminGate
{
    public const HOURS = 2;

    public static function passed(Request $request): bool
    {
        [$id, $at] = $request->session()->get('admin_gate', [null, 0]);

        return $id === $request->user()?->id && $at > now()->subHours(self::HOURS)->timestamp;
    }

    public function handle(Request $request, Closure $next): Response
    {
        return self::passed($request) ? $next($request) : redirect()->guest(route('admin.gate'));
    }
}
