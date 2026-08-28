<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Ajustes de la PLATAFORMA, no de ninguna empresa.
         *
         * Vive en la base central y solo lo ve el superadministrador.
         * Las claves secretas se guardan cifradas con la APP_KEY: un
         * volcado de la base de datos no expone las credenciales de
         * cobro de todos los salones.
         */
        Schema::create('configuracion_plataforma', function (Blueprint $tabla) {
            $tabla->string('clave', 60)->primary();
            $tabla->longText('valor')->nullable();
            $tabla->boolean('cifrado')->default(false);
            $tabla->timestamps();
        });

        Schema::table('cuentas', function (Blueprint $tabla) {
            $tabla->timestamp('ultimo_acceso_admin')->nullable()->after('ultimo_acceso');
        });
    }

    public function down(): void
    {
        Schema::table('cuentas', function (Blueprint $tabla) {
            $tabla->dropColumn('ultimo_acceso_admin');
        });

        Schema::dropIfExists('configuracion_plataforma');
    }
};
