<?php

use App\Http\Middleware\AdminHostOnly;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureNotSuspended;
use App\Http\Middleware\ForceJson;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireAdminGate;
use App\Http\Middleware\RequireAiConsent;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway terminates TLS at its proxy; trust X-Forwarded-Proto so asset/route URLs are https
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'ai.consent' => RequireAiConsent::class,
            'admin' => EnsureAdmin::class,
            'admin.gate' => RequireAdminGate::class,
        ]);
        $middleware->api(prepend: [ForceJson::class]);

        // a general ceiling on every route (the stricter per-route limits still apply on top): per user, or per IP when signed out
        $middleware->web(append: ['throttle:global']);
        $middleware->api(append: ['throttle:global']);

        $middleware->web(prepend: [AdminHostOnly::class]);
        $middleware->web(append: [EnsureNotSuspended::class]);

        $middleware->web(append: [
            HandleAppearance::class,
            SecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // AI endpoints are called with fetch: say what happened and where the manual form is
        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $request->expectsJson()
            ? response()->json(['message' => 'Too many requests. Wait a minute and try again, or add the expense manually.', 'manual_url' => route('transactions.index')], 429, $e->getHeaders())
            : null);

        // pages: a branded Vue error page instead of Laravel's plain one. 500/503 keep the debug screen while APP_DEBUG is on.
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || ! in_array($status, [403, 404, 419, 429, 500, 503], true) || ($status >= 500 && config('app.debug'))) {
                return $response;
            }

            try {
                return Inertia::render('Error', ['status' => $status])->toResponse($request)->setStatusCode($status)->withHeaders($response->headers->all());
            } catch (Throwable) {
                return $response; // if even the error page cannot render (database down), fall back to the plain one
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
