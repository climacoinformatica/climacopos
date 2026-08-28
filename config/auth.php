<?php

use App\Models\User;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Guardias
    |--------------------------------------------------------------------------
    |
    | web      no se usa: el proyecto no tiene el User de Laravel por medio
    | cuenta   clientes del area de descargas y propietarios de salon
    |
    | El acceso al panel de cada salon NO usa guardias: va por sesion propia
    | con PIN, gestionada por SesionSalon. Y el superadministrador tampoco:
    | lo resuelve el middleware AutenticarSuperadmin.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'cuenta' => [
            'driver'   => 'session',
            'provider' => 'cuentas',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Proveedores
    |--------------------------------------------------------------------------
    |
    | Cuenta vive en la base CENTRAL, no en la de ningun salon: una misma
    | cuenta sirve para los tres productos.
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'cuentas' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Cuenta::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        'cuentas' => [
            'provider' => 'cuentas',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
