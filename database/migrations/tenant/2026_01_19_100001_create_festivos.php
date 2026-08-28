<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Festivos y cierres del salon.
         *
         * Igual que las ausencias, se apoya en usuario_excepciones: el
         * motor de huecos ya trata `usuario_id = NULL` como «afecta a todo
         * el salon», asi que al guardar un festivo se crea esa excepcion y
         * la agenda deja de ofrecer huecos sin tocar el motor.
         *
         * La tabla propia existe para lo que la excepcion no guarda: el
         * nombre del festivo, su ambito y de que ano es, que es lo que
         * permite importarlos de golpe cada enero.
         */
        if (! Schema::hasTable('festivos')) {
            Schema::create('festivos', function (Blueprint $tabla) {
                $tabla->id();

                $tabla->date('fecha');
                $tabla->string('nombre', 120);

                $tabla->enum('ambito', [
                    'NACIONAL',
                    'AUTONOMICO',
                    'LOCAL',
                    'CIERRE',      // vacaciones del salon, reforma, lo que sea
                ])->default('LOCAL');

                /**
                 * Media jornada: hay salones que abren solo la manana en
                 * Nochebuena o Nochevieja.
                 */
                $tabla->enum('media_jornada', ['MANANA', 'TARDE'])->nullable();

                $tabla->string('observaciones', 300)->nullable();

                $tabla->foreignId('excepcion_id')->nullable()
                      ->constrained('usuario_excepciones')->nullOnDelete();

                $tabla->timestamps();

                $tabla->unique('fecha');
                $tabla->index('fecha');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('festivos');
    }
};
