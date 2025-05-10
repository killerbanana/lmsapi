<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    // Add this method
    public function render($request, Throwable $exception)
    {
        // Force JSON response for API routes
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Server Error',
                'error' => config('app.debug') ? $exception->getMessage() : 'Something went wrong.',
                'trace' => config('app.debug') ? $exception->getTrace() : []
            ], 500);
        }

        // Default error handler for non-API requests (HTML)
        return parent::render($request, $exception);
    }
}
