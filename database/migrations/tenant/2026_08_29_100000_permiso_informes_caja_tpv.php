<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Informes X y Z desde el TPV.
 *
 * Hace dos cosas:
 *
 *   1. Reparte el permiso nuevo `tpv.informes_caja` entre los perfiles
 *      que ya cuadraban caja. Sin esto, el permiso existiria en el
 *      catalogo pero nadie lo tendria, y los botones no saldrian en
 *      ningun salon ya creado.
 *
 *   2. Ensancha cola_impresion.tipo si se creo como ENUM. El informe X
 *      añade el tipo INFORME_X y un ENUM cerrado lo rechazaria.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->repartirPermiso();
        $this->ensancharTipo();
    }

    public function down(): void
    {
        if (! Schema::hasTable('perfiles')) {
            return;
        }

        foreach (DB::table('perfiles')->get() as $perfil) {
            $permisos = json_decode($perfil->permisos ?? '[]', true) ?: [];

            if (! in_array('tpv.informes_caja', $permisos, true)) {
                continue;
            }

            DB::table('perfiles')->where('id', $perfil->id)->update([
                'permisos' => json_encode(array_values(
                    array_diff($permisos, ['tpv.informes_caja'])
                )),
            ]);
        }
    }

    // ------------------------------------------------------------------

    /**
     * Se lo damos a quien ya tenia responsabilidad sobre la caja.
     *
     * El criterio es deliberadamente conservador: leer la X enseña lo
     * que ha entrado en el dia, y eso no tiene por que verlo todo el
     * mundo. Quien quiera darselo a mas gente lo hace desde la pantalla
     * de perfiles.
     */
    protected function repartirPermiso(): void
    {
        if (! Schema::hasTable('perfiles')) {
            return;
        }

        $heredan = ['caja.cierre', 'caja.entradas_salidas', 'tpv.abrir_cajon'];

        foreach (DB::table('perfiles')->get() as $perfil) {
            $permisos = json_decode($perfil->permisos ?? '[]', true) ?: [];

            if (in_array('tpv.informes_caja', $permisos, true)) {
                continue;
            }

            if (array_intersect($heredan, $permisos) === []) {
                continue;
            }

            $permisos[] = 'tpv.informes_caja';

            DB::table('perfiles')->where('id', $perfil->id)->update([
                'permisos' => json_encode(array_values($permisos)),
            ]);
        }
    }

    protected function ensancharTipo(): void
    {
        if (! Schema::hasTable('cola_impresion')) {
            return;
        }

        $columna = DB::selectOne(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            ['cola_impresion', 'tipo']
        );

        if ($columna && strtolower($columna->DATA_TYPE) === 'enum') {
            DB::statement('ALTER TABLE cola_impresion MODIFY tipo VARCHAR(20) NOT NULL');
        }
    }
};
