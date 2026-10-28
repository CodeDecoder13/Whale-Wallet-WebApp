<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A suspended user is signed out on their next request (suspending also deletes their sessions, so this is the backstop). */
class EnsureNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->suspended_at) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors(['email' => __('This account is suspended. Contact support if you think this is a mistake.')]);
        }

        return $next($request);
    }
}
