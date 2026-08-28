<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columnas = [
            /**
             * Cuando termino el asistente de configuracion.
             *
             * Mientras sea null, el panel redirige al asistente: un salon
             * sin datos fiscales ni horarios no puede emitir facturas ni
             * dar citas, y dejar entrar al TPV en ese estado solo genera
             * errores que el cliente no sabe interpretar.
             */
            'configurada_en' => fn (Blueprint $t) => $t->dateTime('configurada_en')->nullable(),

            // Paso por el que va, para poder retomarlo
            'paso_configuracion' => fn (Blueprint $t) => $t
                ->unsignedTinyInteger('paso_configuracion')->default(1),
        ];

        foreach ($columnas as $nombre => $definicion) {
            if (! Schema::hasColumn('empresas', $nombre)) {
                Schema::table('empresas', $definicion);
            }
        }

        // La cuenta propietaria, si no venia de la Fase 8b
        if (! Schema::hasColumn('empresas', 'cuenta_id')) {
            Schema::table('empresas', function (Blueprint $tabla) {
                $tabla->foreignId('cuenta_id')->nullable()
                      ->constrained('cuentas')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['configurada_en', 'paso_configuracion'] as $columna) {
            if (Schema::hasColumn('empresas', $columna)) {
                Schema::table('empresas', fn (Blueprint $t) => $t->dropColumn($columna));
            }
        }
    }
};
