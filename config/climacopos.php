<?php

return [

    /*
    | Dominio base sobre el que cuelgan los subdominios de empresa.
    | Local:      climacopos.test
    | Produccion: climacopos.com
    */
    'dominio_base' => env('DOMINIO_BASE', 'climacopos.test'),

    /*
    | Dominios que NO son de ninguna empresa: web comercial y panel de
    | superadministracion. Debe coincidir con tenancy.central_domains.
    */
    'dominios_centrales' => explode(',', env('CENTRAL_DOMAINS', 'climacopos.test,www.climacopos.test,admin.climacopos.test')),

    /*
    | Prueba gratuita y ciclo de morosidad, en dias.
    */
    'prueba_dias'               => env('PRUEBA_DIAS', 14),
    'dias_hasta_suspension'     => env('DIAS_HASTA_SUSPENSION', 7),
    'dias_hasta_borrado'        => env('DIAS_HASTA_BORRADO', 90),

    /*
    | Copias de seguridad por empresa.
    */
    'backup_disco' => env('BACKUP_DISCO', 'b2'),

];
