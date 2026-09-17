<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\GeneratePerformanceReports;
use App\Console\Commands\CleanupDailyExcelImports;
use App\Http\Middleware\DailyExcelCleanup;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        GeneratePerformanceReports::class,
        CleanupDailyExcelImports::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(null);
        $middleware->api(prepend: [
            DailyExcelCleanup::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        // TEMP-DEBUG: mostrar trace real en API (revertir luego)
        $exceptions->respond(function ($response, $e, $request) {
            if ($request->is('api/*') && !($e instanceof \Illuminate\Validation\ValidationException)) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'class' => get_class($e),
                    'at' => basename($e->getFile()) . ':' . $e->getLine(),
                    'trace' => collect($e->getTrace())->take(6)->map(fn ($t) => ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '') . ' @' . basename($t['file'] ?? '?') . ':' . ($t['line'] ?? '?'))->values(),
                ], 500);
            }
            return $response;
        });
    })->create();
