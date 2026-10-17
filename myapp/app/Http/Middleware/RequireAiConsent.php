<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Free-tier Gemini inputs may be used by Google, so nothing is sent until the user has agreed. */
class RequireAiConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->ai_consent_at) {
            return $next($request);
        }

        $message = 'Accept the AI notice first. It is on the Receipts, Whale Chat and Tips pages.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'consent_url' => route('tips.index')], 422);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
