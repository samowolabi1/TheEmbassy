<?php

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
        // Sellers sign in at /vendor, church staff at /admin, and members at /login.
        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->is('vendor*') => route('vendor.login'),
            $request->is('admin*') => route('admin.login'),
            default => route('login'),
        });
        $middleware->redirectUsersTo(fn (Request $request) => match (true) {
            $request->is('vendor*') => route('vendor.products.index'),
            $request->is('admin*') => route('admin.dashboard'),
            default => route('account'),
        });
        // Paystack signs its webhooks instead; see PaymentController::webhook.
        $middleware->validateCsrfTokens(except: ['webhooks/paystack']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
