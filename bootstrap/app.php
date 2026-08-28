<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'terminal' => \App\Http\Middleware\VerificarTerminal::class,
            'salon'    => \App\Http\Middleware\AutenticarSalon::class,
            'permiso'  => \App\Http\Middleware\VerificarPermiso::class,
	    'agente' => \App\Http\Middleware\AutenticarAgente::class,
	    'superadmin' => \App\Http\Middleware\AutenticarSuperadmin::class,
	    'suscripcion' => \App\Http\Middleware\ComprobarSuscripcion::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();