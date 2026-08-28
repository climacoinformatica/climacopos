<?php

return [

    /*
    | Modo pruebas: se envía al entorno de preproducción de la AEAT.
    | Se cambia desde Administración → VERI*FACTU, no aquí.
    */
    'pruebas' => env('VERIFACTU_PRUEBAS', true),

    /*
    | Endpoints de la AEAT.
    | Conviene contrastarlos con la documentación vigente antes de
    | pasar a producción: la Agencia los ha ido moviendo.
    */
    'endpoints' => [
        'pruebas'    => 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion.wsdl',
        'produccion' => 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion.wsdl',
    ],

    /*
    | Datos del sistema informático de facturación (SIF).
    | Van en cada registro: la AEAT quiere saber qué programa lo generó.
    */
    'sistema' => [
        'nombre_razon' => env('SIF_RAZON', 'Climaco Informatica'),
        'nif'          => env('SIF_NIF', '42190349T'),
        'nombre'       => 'CLIMACO POS',
        'id'           => '01',
        'version'      => '1.0',
        'numero_instalacion' => env('SIF_INSTALACION', '001'),

        // Solo se ofrece modalidad VERI*FACTU (envío inmediato)
        'solo_verifactu'   => 'S',
        'multi_ot'         => 'S',   // multi obligado tributario
        'indicador_multi_ot' => 'S',
    ],

    /*
    | Reintentos de envío. La AEAT pide esperar entre intentos.
    */
    'reintentos' => [
        'maximo'        => 10,
        'espera_minutos'=> 5,
    ],

];
