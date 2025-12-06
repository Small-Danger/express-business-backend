<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
        
        // Configuration CORS pour permettre les requêtes depuis le frontend
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Envoyer les alertes quotidiennes à 10h26 tous les jours (test temporaire)
        $schedule->command('alerts:daily')
            ->dailyAt('10:26')
            ->timezone('Africa/Casablanca')
            ->withoutOverlapping();

        // Envoyer le résumé quotidien à 10h30 tous les jours (test temporaire)
        $schedule->command('alerts:daily-summary')
            ->dailyAt('10:30')
            ->timezone('Africa/Casablanca')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
