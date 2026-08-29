<?php

namespace App\Support;

/**
 * Catalogo unico de permisos de la aplicacion.
 *
 * Regla: si un permiso no esta aqui, no existe. Nunca comprobar cadenas
 * sueltas por el codigo; usar siempre las constantes de esta clase para
 * que el buscador del IDE encuentre todos los usos.
 */
class Permisos
{
    // --- Empresa y ajustes
    public const EMPRESA_FACTURACION   = 'empresa.facturacion';
    public const AJUSTES_ACCESO        = 'ajustes.acceso';
    public const AJUSTES_HARDWARE      = 'ajustes.hardware';
    public const AJUSTES_TICKET_DISENO = 'ajustes.ticket_diseno';

    // --- Usuarios
    public const USUARIOS_GESTIONAR = 'usuarios.gestionar';
    public const USUARIOS_INVITAR   = 'usuarios.invitar';

    // --- Terminales
    public const TERMINALES_VINCULAR = 'terminales.vincular';

    // --- Catalogo
    public const CATALOGO_EDITAR = 'catalogo.editar';

    // --- TPV
    public const TPV_VENDER        = 'tpv.vender';
    public const TPV_DESCUENTO     = 'tpv.descuento';
    public const TPV_INVITACION    = 'tpv.invitacion';   // servir a coste cero
    public const TPV_ANULAR_LINEA  = 'tpv.anular_linea';
    public const TPV_ANULAR_TICKET = 'tpv.anular_ticket';
    public const TPV_DEVOLUCION    = 'tpv.devolucion';
    public const TPV_ABRIR_CAJON   = 'tpv.abrir_cajon';

    /**
     * Consultar e imprimir los informes X y Z desde el propio TPV.
     *
     * Va aparte de CAJA_CIERRE a proposito: leer el arqueo no cierra
     * nada y no puede exigir contrasena, o quien esta en el mostrador
     * dejaria de mirarlo. Cerrar la jornada sigue pidiendo CAJA_CIERRE.
     */
    public const TPV_INFORMES_CAJA = 'tpv.informes_caja';

    // --- Caja
    public const CAJA_ENTRADAS_SALIDAS = 'caja.entradas_salidas';
    public const CAJA_CIERRE           = 'caja.cierre';

    // --- Agenda y reservas
    public const AGENDA_VER_TODAS     = 'agenda.ver_todas';
    public const AGENDA_EDITAR_OTROS  = 'agenda.editar_otros';
    public const RESERVAS_CONFIRMAR   = 'reservas.confirmar';

    // --- Clientes
    public const CLIENTES_EDITAR             = 'clientes.editar';
    public const CLIENTES_VER_DATOS_CONTACTO = 'clientes.ver_datos_contacto';

    // --- Informes
    public const INFORMES_VER         = 'informes.ver';
    public const INFORMES_VER_PROPIOS = 'informes.ver_propios';

    // --- Formacion
    public const FORMACION_CONSULTAR = 'formacion.consultar';
    public const FORMACION_BORRAR    = 'formacion.borrar';

    /**
     * Permisos que exigen reautenticacion con contrasena (opcion C).
     * El PIN no basta para estos: son irreversibles o dan acceso a dinero
     * y datos sensibles.
     */
    public const EXIGEN_PASSWORD = [
        self::EMPRESA_FACTURACION,
        self::AJUSTES_ACCESO,
        self::AJUSTES_HARDWARE,
        self::USUARIOS_GESTIONAR,
        self::TERMINALES_VINCULAR,
        self::TPV_ANULAR_TICKET,
        self::TPV_DEVOLUCION,
        self::CAJA_CIERRE,
        self::INFORMES_VER,
        self::FORMACION_BORRAR,
    ];

    /** Catalogo completo agrupado, para pintar la pantalla de perfiles. */
    public static function catalogo(): array
    {
        return [
            'Empresa y ajustes' => [
                self::EMPRESA_FACTURACION   => 'Gestionar plan y forma de pago',
                self::AJUSTES_ACCESO        => 'Entrar en los ajustes',
                self::AJUSTES_HARDWARE      => 'Configurar impresoras, cajon y visor',
                self::AJUSTES_TICKET_DISENO => 'Disenar el ticket de venta',
            ],
            'Usuarios' => [
                self::USUARIOS_GESTIONAR => 'Crear, editar y desactivar usuarios',
                self::USUARIOS_INVITAR   => 'Invitar companeros por email',
            ],
            'Terminales' => [
                self::TERMINALES_VINCULAR => 'Vincular un equipo nuevo al salon',
            ],
            'Catalogo' => [
                self::CATALOGO_EDITAR => 'Editar familias, servicios y productos',
            ],
            'Punto de venta' => [
                self::TPV_VENDER        => 'Vender y cobrar',
                self::TPV_DESCUENTO     => 'Aplicar descuentos',
                self::TPV_INVITACION    => 'Invitar (servir a coste cero)',
                self::TPV_ANULAR_LINEA  => 'Anular lineas del ticket',
                self::TPV_ANULAR_TICKET => 'Anular tickets completos',
                self::TPV_DEVOLUCION    => 'Hacer devoluciones',
                self::TPV_ABRIR_CAJON   => 'Abrir el cajon sin venta',
                self::TPV_INFORMES_CAJA => 'Ver e imprimir los informes X y Z desde el TPV',
            ],
            'Caja' => [
                self::CAJA_ENTRADAS_SALIDAS => 'Registrar entradas y salidas de efectivo',
                self::CAJA_CIERRE           => 'Hacer el cierre de jornada',
            ],
            'Agenda y reservas' => [
                self::AGENDA_VER_TODAS    => 'Ver la agenda de todos los profesionales',
                self::AGENDA_EDITAR_OTROS => 'Editar citas de otros profesionales',
                self::RESERVAS_CONFIRMAR  => 'Confirmar o rechazar reservas online',
            ],
            'Clientes' => [
                self::CLIENTES_EDITAR             => 'Crear y editar fichas de cliente',
                self::CLIENTES_VER_DATOS_CONTACTO => 'Ver telefono y email de clientes',
            ],
            'Informes' => [
                self::INFORMES_VER         => 'Ver todos los informes',
                self::INFORMES_VER_PROPIOS => 'Ver solo sus propias ventas',
            ],
            'Formacion' => [
                self::FORMACION_CONSULTAR => 'Consultar documentos de formacion',
                self::FORMACION_BORRAR    => 'Borrar documentos de formacion',
            ],
        ];
    }

    /** Lista plana de todas las claves validas. */
    public static function todos(): array
    {
        $claves = [];

        foreach (self::catalogo() as $permisos) {
            $claves = array_merge($claves, array_keys($permisos));
        }

        return $claves;
    }

    public static function existe(string $clave): bool
    {
        return in_array($clave, self::todos(), true);
    }

    public static function exigePassword(string $clave): bool
    {
        return in_array($clave, self::EXIGEN_PASSWORD, true);
    }

    /**
     * Perfiles de fabrica. Se siembran en la base de cada empresa nueva.
     * El propietario puede duplicarlos y crear los suyos, pero estos
     * no se pueden borrar (es_sistema = 1).
     */
    public static function perfilesDeFabrica(): array
    {
        return [
            'propietario' => [
                'nombre'   => 'Propietario',
                'permisos' => self::todos(),   // todo
            ],

            'encargado' => [
                'nombre'   => 'Encargado',
                'permisos' => [
                    self::AJUSTES_TICKET_DISENO,
                    self::USUARIOS_INVITAR,
                    self::CATALOGO_EDITAR,
                    self::TPV_VENDER,
                    self::TPV_DESCUENTO,
                    self::TPV_INVITACION,
                    self::TPV_ANULAR_LINEA,
                    self::TPV_ANULAR_TICKET,
                    self::TPV_DEVOLUCION,
                    self::TPV_ABRIR_CAJON,
                    self::TPV_INFORMES_CAJA,
                    self::CAJA_ENTRADAS_SALIDAS,
                    self::CAJA_CIERRE,
                    self::AGENDA_VER_TODAS,
                    self::AGENDA_EDITAR_OTROS,
                    self::RESERVAS_CONFIRMAR,
                    self::CLIENTES_EDITAR,
                    self::CLIENTES_VER_DATOS_CONTACTO,
                    self::INFORMES_VER,
                    self::INFORMES_VER_PROPIOS,
                    self::FORMACION_CONSULTAR,
                ],
            ],

            'profesional' => [
                'nombre'   => 'Profesional',
                'permisos' => [
                    self::TPV_VENDER,
                    self::CLIENTES_EDITAR,
                    self::INFORMES_VER_PROPIOS,
                ],
            ],

            'recepcion' => [
                'nombre'   => 'Recepcion',
                'permisos' => [
                    self::TPV_VENDER,
                    self::TPV_DESCUENTO,
                    self::TPV_ANULAR_LINEA,
                    self::TPV_ABRIR_CAJON,
                    self::TPV_INFORMES_CAJA,
                    self::AGENDA_VER_TODAS,
                    self::AGENDA_EDITAR_OTROS,
                    self::RESERVAS_CONFIRMAR,
                    self::CLIENTES_EDITAR,
                    self::CLIENTES_VER_DATOS_CONTACTO,
                ],
            ],

            'formacion' => [
                'nombre'   => 'Formacion',
                'permisos' => [
                    self::TPV_VENDER,
                ],
            ],
        ];
    }
}
