<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class CsrfExemptionTest extends TestCase
{
    /**
     * The Livewire endpoints must stay CSRF-exempt: the on-device webview
     * restores stale DOM on app reopen, and an expired token would 419 the
     * first Livewire call (e.g. the banner's wire:init auto-prompt), popping
     * Livewire's "This page has expired" modal on app start.
     *
     * CSRF verification is skipped entirely when running unit tests, so this
     * asserts the exemption at the middleware-matching level instead of over
     * HTTP.
     */
    public function test_livewire_update_endpoint_is_exempt_from_csrf(): void
    {
        $middleware = $this->app->make(ValidateCsrfToken::class);

        $request = Request::create(route('default-livewire.update'), 'POST');

        $inExceptArray = new ReflectionMethod($middleware, 'inExceptArray');

        $this->assertTrue(
            $inExceptArray->invoke($middleware, $request),
            'The Livewire update endpoint is no longer CSRF-exempt; Livewire calls from restored DOM will 419.'
        );
    }

    /**
     * The NativePHP bridge endpoint must stay CSRF-exempt: the JS auto-prompt
     * for notification permission posts there with the meta-tag token, which
     * is stale when the webview restores an old DOM.
     */
    public function test_native_bridge_endpoint_is_exempt_from_csrf(): void
    {
        $middleware = $this->app->make(ValidateCsrfToken::class);

        $request = Request::create('/_native/api/call', 'POST');

        $inExceptArray = new ReflectionMethod($middleware, 'inExceptArray');

        $this->assertTrue(
            $inExceptArray->invoke($middleware, $request),
            'The _native bridge endpoint is no longer CSRF-exempt; the JS permission auto-prompt will silently 419.'
        );
    }
}
