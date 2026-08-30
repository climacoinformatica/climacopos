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

            /**
             * Limite de facturas del plan.
             *
             * Bloquea el panel al agotarse, pero NUNCA el TPV: dejar a un
             * salon sin poder cobrar a alguien que ya esta sentada en la
             * silla seria crearle un problema fiscal para forzarle a
             * pagar. Las rutas exentas estan en el propio middleware.
             */
            'facturas' => \App\Http\Middleware\ComprobarFacturas::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();