<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OJO: esta migracion corre sobre la base de CADA SALON.
     *
     * Aqui solo van tablas del tenant. `empresas` vive en la base
     * central y no existe aqui: intentar tocarla desde una migracion de
     * tenant falla con «Table doesn't exist», que es justo lo que pasaba
     * en la primera version de este fichero.
     *
     * El ajuste `tras_cobrar` esta en la migracion central hermana.
     */
    public function up(): void
    {
        /**
         * Modo de comision de cada profesional.
         *
         * NINGUNA por defecto: mientras el salon no lo configure, la
         * columna de comisiones no aparece siquiera en pantalla.
         */
        if (! Schema::hasColumn('usuarios', 'comision_tipo')) {
            Schema::table('usuarios', function (Blueprint $tabla) {
                $tabla->enum('comision_tipo', ['NINGUNA', 'PORCENTAJE', 'POR_SERVICIO'])
                      ->default('NINGUNA');
            });
        }

        if (! Schema::hasColumn('usuarios', 'comision_fija')) {
            Schema::table('usuarios', function (Blueprint $tabla) {
                // Euros por cada servicio ejecutado
                $tabla->decimal('comision_fija', 8, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['comision_tipo', 'comision_fija'] as $columna) {
            if (Schema::hasColumn('usuarios', $columna)) {
                Schema::table('usuarios', fn (Blueprint $t) => $t->dropColumn($columna));
            }
        }
    }
};
