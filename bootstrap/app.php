<?php

use App\Http\Middleware\CheckAbility;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register your middleware alias here
        $middleware->alias([
            'check.ability' => CheckAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Force JSON error response for API routes
        $exceptions->render(function (Throwable $exception, $request) {
            if ($request->expectsJson() || str_starts_with($request->getPathInfo(), '/api')) {
                // Return a simple JSON error response without stack trace
                return response()->json([
                    'message' => 'An error occurred.',
                    'error' => config('app.debug') ? $exception->getMessage() : 'Server Error'
                ], method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500);
            }

            // Default HTML error for non-API routes
            return response()->view('errors.500', [], 500);
        });
    })
    ->create();




