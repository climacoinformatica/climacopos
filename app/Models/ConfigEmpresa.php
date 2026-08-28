<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ajustes de la empresa, clave/valor. Se lee con el helper config_empresa().
 */
class ConfigEmpresa extends Model
{
    protected $table = 'config';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** Ajustes de agenda con sus valores por defecto. */
    public const POR_DEFECTO = [
        'antelacion_min_horas'      => 2,      // no reservar con menos de X horas
        'antelacion_max_dias'       => 60,     // ni más allá de X días
        'cancelacion_horas_min'     => 24,     // el cliente puede cancelar hasta X horas antes
        'confirmacion_automatica'   => 'false',
        'caducidad_pendiente_horas' => 48,     // auto-rechazo si nadie decide
        'agenda_hora_ini'           => '08:00',
        'agenda_hora_fin'           => '21:00',
        'no_shows_para_exigir_pago' => 2,
    ];
}
