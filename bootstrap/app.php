<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Tenancy\Http\Middleware\SetWorkspaceContext;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            // Must run AFTER auth: resolves the per-request workspace context.
            'workspace.context' => SetWorkspaceContext::class,
            // Users must confirm their email before using the panel.
            'verified' => EnsureEmailIsVerified::class,
        ]);

        // Framework route-name contract used by auth notifications:
        // verification.verify (signed link) and password.reset. Redirect
        // guests to the sign-in screen named 'login'.
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
