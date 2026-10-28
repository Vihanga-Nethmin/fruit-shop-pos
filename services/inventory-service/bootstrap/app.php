<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
        then: function () {
            Route::prefix('internal/v1')->group(base_path('routes/internal.php'));
        },
    )
   ->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'tenant' => \App\Http\Middleware\ResolveTenant::class,
    ]);
})
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('reservations:expire')->everyMinute()->withoutOverlapping();
        $schedule->command('outbox:publish')->everyMinute()->withoutOverlapping();
    })
    
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
