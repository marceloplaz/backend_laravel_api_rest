<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Console\Commands\SincronizarPersonal; // <-- IMPORTA TU CLASE

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        SincronizarPersonal::class, // <-- REGISTRA LA CLASE AQUÍ
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jugadordeunbit' => \App\Http\Middleware\PermissionMiddleware::class,
            'role' => \App\Http\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();