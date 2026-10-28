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

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
