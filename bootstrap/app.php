<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Single-user on-device app: the embedded PHP server is reachable only
        // from the app's own webview, so CSRF adds no protection here. The
        // webview restores stale DOM when the app reopens; its expired token
        // would otherwise 419 the first Livewire call and pop Livewire's
        // "This page has expired" modal on every app start. The _native bridge
        // endpoint is exempted too: the JS auto-prompt for notification
        // permission posts there with the (possibly stale) meta-tag token.
        $middleware->validateCsrfTokens(except: [
            'livewire/*',
            'livewire-*',
            '_native/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
