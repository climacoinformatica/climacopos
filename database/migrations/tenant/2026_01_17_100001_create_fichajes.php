<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * REGISTRO DE JORNADA
         *
         * Obligatorio en Espana desde mayo de 2019 (art. 34.9 del Estatuto
         * de los Trabajadores, RDL 8/2019). Hay que conservarlo CUATRO ANOS
         * y tenerlo a disposicion de los trabajadores, sus representantes y
         * la Inspeccion de Trabajo.
         *
         * Ademas hay un Real Decreto en tramitacion que exigira que el
         * registro sea exclusivamente digital, TRAZABLE e INMUTABLE, y
         * accesible en remoto para la Inspeccion. Esta tabla se disena ya
         * con esos requisitos: los fichajes no se editan ni se borran
         * nunca, y una correccion deja rastro de quien la hizo y por que.
         */
        if (! Schema::hasTable('fichajes')) {
            Schema::create('fichajes', function (Blueprint $tabla) {
                $tabla->id();
                $tabla->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();

                // Se guarda la fecha aparte para agrupar por jornada sin
                // tener que hacer DATE() sobre el datetime en cada consulta
                $tabla->date('fecha');
                $tabla->dateTime('fecha_hora');

                $tabla->enum('tipo', [
                    'ENTRADA',
                    'SALIDA',
                    'PAUSA_INICIO',
                    'PAUSA_FIN',
                ]);

                /**
                 * De donde vino el fichaje.
                 *
                 * MANUAL significa que lo introdujo un responsable, no la
                 * persona trabajadora. La Inspeccion mira esto: un registro
                 * lleno de fichajes manuales pierde credibilidad.
                 */
                $tabla->enum('origen', ['TERMINAL', 'PANEL', 'MOVIL', 'MANUAL', 'AUTOMATICO'])
                      ->default('TERMINAL');

                $tabla->foreignId('terminal_id')->nullable()->constrained('terminales')->nullOnDelete();
                $tabla->string('ip', 45)->nullable();
                $tabla->string('dispositivo', 200)->nullable();

                $tabla->string('observaciones', 300)->nullable();

                // --- Trazabilidad de las correcciones

                /**
                 * Un fichaje corregido NO se modifica: se marca como anulado
                 * y se crea otro que lo sustituye. Asi la Inspeccion puede
                 * ver el original, la correccion y el motivo.
                 */
                $tabla->foreignId('corrige_a_id')->nullable()
                      ->constrained('fichajes')->nullOnDelete();

                $tabla->boolean('anulado')->default(false);
                $tabla->foreignId('anulado_por')->nullable()->constrained('usuarios')->nullOnDelete();
                $tabla->dateTime('anulado_en')->nullable();
                $tabla->string('motivo_correccion', 300)->nullable();

                $tabla->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();

                $tabla->timestamps();

                $tabla->index(['usuario_id', 'fecha']);
                $tabla->index(['fecha', 'anulado']);
            });
        }

        // --- Datos laborales del usuario
        $columnas = [
            'horas_semana'   => fn (Blueprint $t) => $t->decimal('horas_semana', 5, 2)->nullable(),
            'ficha_jornada'  => fn (Blueprint $t) => $t->boolean('ficha_jornada')->default(true),
            'nif'            => fn (Blueprint $t) => $t->string('nif', 20)->nullable(),
            'fecha_alta_lab' => fn (Blueprint $t) => $t->date('fecha_alta_lab')->nullable(),
        ];

        foreach ($columnas as $nombre => $definicion) {
            if (! Schema::hasColumn('usuarios', $nombre)) {
                Schema::table('usuarios', $definicion);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fichajes');

        foreach (['horas_semana', 'ficha_jornada', 'nif', 'fecha_alta_lab'] as $columna) {
            if (Schema::hasColumn('usuarios', $columna)) {
                Schema::table('usuarios', fn (Blueprint $t) => $t->dropColumn($columna));
            }
        }
    }
};
