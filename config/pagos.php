<?php

return [

    /*
    | Pasarela activa: 'stripe' o 'redsys'.
    | Redsys queda pendiente; la interfaz Pasarela ya está preparada.
    */
    'pasarela' => env('PASARELA_PAGO', 'stripe'),

    'stripe' => [
        // Claves de la PLATAFORMA, no del salón.
        // Cada salón conecta su propia cuenta con Connect.
        'publica' => env('STRIPE_PUBLICA'),
        'secreto' => env('STRIPE_SECRETO'),

        // Secreto de firma del webhook (whsec_...)
        'webhook' => env('STRIPE_WEBHOOK'),
    ],

    /*
    | Comisión por defecto de la plataforma sobre cada pago del cliente
    | final, en porcentaje. Se puede ajustar por empresa.
    | 0 = no cobramos nada por las reservas.
    */
    'comision_pct' => env('COMISION_PLATAFORMA_PCT', 0),

];
