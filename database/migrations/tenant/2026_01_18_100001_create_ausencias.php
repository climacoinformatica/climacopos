<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Ausencias: vacaciones, bajas y permisos.
         *
         * NO sustituye a usuario_excepciones, que es lo que el motor de
         * huecos ya consulta desde la Fase 3. Esta tabla anade encima el
         * flujo que faltaba —solicitar, aprobar, rechazar— y el computo de
         * dias. Al aprobar una ausencia se crea su excepcion, y asi la
         * agenda la respeta sin tocar el motor.
         */
        if (! Schema::hasTable('ausencias')) {
            Schema::create('ausencias', function (Blueprint $tabla) {
                $tabla->id();
                $tabla->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();

                $tabla->enum('tipo', [
                    'VACACIONES',
                    'BAJA',              // incapacidad temporal
                    'PERMISO',           // retribuido: mudanza, boda, examen
                    'ASUNTOS_PROPIOS',
                    'MATERNIDAD',
                    'FORMACION',
                    'OTRO',
                ])->default('VACACIONES');

                $tabla->date('desde');
                $tabla->date('hasta');

                /**
                 * Medio dia: manana o tarde.
                 * Null significa jornada completa.
                 */
                $tabla->enum('medio_dia', ['MANANA', 'TARDE'])->nullable();

                $tabla->enum('estado', ['SOLICITADA', 'APROBADA', 'RECHAZADA', 'CANCELADA'])
                      ->default('SOLICITADA');

                $tabla->string('motivo', 300)->nullable();
                $tabla->string('respuesta', 300)->nullable();

                $tabla->foreignId('solicitada_por')->nullable()->constrained('usuarios')->nullOnDelete();
                $tabla->foreignId('resuelta_por')->nullable()->constrained('usuarios')->nullOnDelete();
                $tabla->dateTime('resuelta_en')->nullable();

                /**
                 * Excepcion de horario generada al aprobar.
                 * Guardarla permite retirarla si despues se cancela.
                 */
                $tabla->foreignId('excepcion_id')->nullable()
                      ->constrained('usuario_excepciones')->nullOnDelete();

                // Dias que consume del cupo. Las bajas no consumen.
                $tabla->decimal('dias_computados', 5, 1)->default(0);

                $tabla->timestamps();

                $tabla->index(['usuario_id', 'estado']);
                $tabla->index(['desde', 'hasta']);
            });
        }

        if (! Schema::hasColumn('usuarios', 'dias_vacaciones')) {
            Schema::table('usuarios', function (Blueprint $tabla) {
                // Cupo anual. 22 dias laborables es lo habitual por convenio.
                $tabla->decimal('dias_vacaciones', 5, 1)->default(22);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencias');

        if (Schema::hasColumn('usuarios', 'dias_vacaciones')) {
            Schema::table('usuarios', fn (Blueprint $t) => $t->dropColumn('dias_vacaciones'));
        }
    }
};
