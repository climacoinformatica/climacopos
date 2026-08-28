<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migracion CENTRAL: la tabla `empresas` vive en climacopos_central.
     */
    public function up(): void
    {
        /**
         * Que hacer despues de cobrar un ticket.
         *
         *   NADA      se queda en el TPV. Recepcion centralizada.
         *   SELECTOR  vuelve a elegir usuario. Cada uno cobra lo suyo.
         *   INICIO    vuelve al menu principal.
         *
         * Por defecto NADA, que es como funciona hoy: cambiar el
         * comportamiento a todos los salones de golpe seria una sorpresa
         * desagradable para quien ya lo tiene rodado.
         */
        if (! Schema::hasColumn('empresas', 'tras_cobrar')) {
            Schema::table('empresas', function (Blueprint $tabla) {
                $tabla->enum('tras_cobrar', ['NADA', 'SELECTOR', 'INICIO'])->default('NADA');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresas', 'tras_cobrar')) {
            Schema::table('empresas', fn (Blueprint $t) => $t->dropColumn('tras_cobrar'));
        }
    }
};
