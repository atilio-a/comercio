<?php

use App\Http\Middleware\EstablecerSucursalActiva;
use App\Http\Middleware\IdentificarDispositivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            IdentificarDispositivo::class,
            EstablecerSucursalActiva::class,
        ]);

        // spatie/laravel-permission teams: the branch must be set before route model binding.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: EstablecerSucursalActiva::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
