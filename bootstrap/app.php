<?php

use App\Http\Middleware\CheckAbility;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Your middleware alias for checking abilities.
        // This part remains the same.
        $middleware->alias([
            'check.ability' => CheckAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /**
         * This is the custom exception handler for the application.
         * It's configured to return structured JSON responses for API requests,
         * ensuring the correct HTTP status codes are always returned.
         */
        $exceptions->render(function (Throwable $e, $request) {
            // This logic only applies to API routes or any request that expects a JSON response.
            if ($request->expectsJson() || str_starts_with($request->getPathInfo(), '/api')) {
                
                // Log the full, detailed error to the log file for easier debugging.
                // This is crucial for finding the root cause of production errors.
                Log::error(
                    'API Exception Occurred: ' . $e->getMessage(),
                    // We log the entire exception object for a full stack trace.
                    ['exception' => $e] 
                );

                // Start with a default "500 Internal Server Error" status code.
                $statusCode = 500;

                // If the exception is a specific HttpException (like 404, 403, 422),
                // use its intended status code. This is the key to returning correct codes.
                if ($e instanceof HttpException) {
                    $statusCode = $e->getStatusCode();
                }

                // Determine the error message to show.
                // In production (APP_DEBUG=false), we show a generic message for server errors (500).
                // Otherwise, we show the actual, specific exception message for easier debugging.
                $errorMessage = ($statusCode === 500 && !config('app.debug')) 
                    ? 'A server error occurred.' 
                    : $e->getMessage();
                
                // Return a standardized JSON error response.
                return response()->json([
                    'message' => $errorMessage,
                    'status_code' => $statusCode,
                ], $statusCode);
            }

            // For all other non-API requests, let Laravel's default handler do its job.
            // This will show the normal error pages for web routes.
            return null;
        });
    })
    ->create();