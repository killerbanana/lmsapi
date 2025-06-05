<?php

use App\Http\Middleware\CheckAbility;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'check.ability' => CheckAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $exception, $request) {
            // Log full error
            Log::error('Exception occurred', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if ($request->expectsJson() || str_starts_with($request->getPathInfo(), '/api')) {
                return response()->json([
                    'message' => 'An error occurred.',
                    'error' => config('app.debug') ? $exception->getMessage() : 'Server Error',
                    'trace' => config('app.debug') ? $exception->getTrace() : null,
                ], method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500);
            }

            return response()->view('errors.500', [], 500);
        });
    })
    ->create();




