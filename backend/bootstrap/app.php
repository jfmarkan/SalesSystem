<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Illuminate\http\Middleware\HandleCors;


use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Middleware GLOBAL (corre en todos lados)
        $middleware->append(HandleCors::class);

        // Middleware del grupo API (ej: Sanctum)
        $middleware->group('api', [
            EnsureFrontendRequestsAreStateful::class, // 👈 Necesario para cookies en API
            SubstituteBindings::class
        ]);

        // Middleware del grupo WEB (para páginas tradicionales o rutas web.php)
        $middleware->group('web', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            EnsureFrontendRequestsAreStateful::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('deviations:detect')
            ->monthlyOn(4, '05:00')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('erp:auto-import-sales')
            ->twiceDaily(12, 20)
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('erp:auto-sync-clients')
            ->dailyAt('23:30')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();
        })
    ->create();
