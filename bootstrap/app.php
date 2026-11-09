<?php

use App\Http\Controllers\RuntimeDiagnosticsController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::post('/_diagnostics', RuntimeDiagnosticsController::class)
                ->name('runtime.diagnostics');
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            'throttle:60,1',
        ]);

        $middleware->trustProxies(
            '*',
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $exception): void {
            $request = request();
            $applicationFrame = collect($exception->getTrace())
                ->first(fn (array $frame): bool => isset($frame['file'])
                    && str_starts_with($frame['file'], app_path().DIRECTORY_SEPARATOR));

            error_log('[QuickFolio] '.json_encode([
                'request_id' => $request->header('x-vercel-id') ?? $request->header('x-request-id'),
                'method' => $request->method(),
                'path' => $request->path(),
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
                'application_file' => $applicationFrame['file'] ?? null,
                'application_line' => $applicationFrame['line'] ?? null,
            ], JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES));
        });
    })->create();
