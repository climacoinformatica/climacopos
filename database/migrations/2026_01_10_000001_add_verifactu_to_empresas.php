<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $tabla) {
            $tabla->boolean('verifactu_activo')->default(false)->after('regimen_fiscal');

            /**
             * Certificado del salon para firmar ante la AEAT.
             * Se guarda fuera del directorio publico y la contrasena
             * cifrada con la APP_KEY.
             */
            $tabla->string('certificado_ruta', 255)->nullable()->after('verifactu_activo');
            $tabla->text('certificado_clave')->nullable()->after('certificado_ruta');
            $tabla->date('certificado_caduca')->nullable()->after('certificado_clave');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $tabla) {
            $tabla->dropColumn([
                'verifactu_activo', 'certificado_ruta',
                'certificado_clave', 'certificado_caduca',
            ]);
        });
    }
};
