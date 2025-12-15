<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\Cors;
use App\Http\Middleware\AuthToken;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\BarberMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global middleware
        $middleware->append(Cors::class);

        // Route-specific middleware
        $middleware->alias([
            'auth.token' => AuthToken::class,
            'admin' => AdminMiddleware::class,
            'barber' => BarberMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Ensure API routes always return JSON, even for validation errors
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'Validation failed',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors()
                ], 422);
            }
        });

        // Handle database and other exceptions for API routes (but not ValidationException)
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') && !($e instanceof \Illuminate\Validation\ValidationException)) {
                \Log::error('API Error: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'error' => 'Server error',
                    'message' => config('app.debug') ? $e->getMessage() : 'An error occurred. Please check the server logs.'
                ], 500);
            }
        });
    })->create();
