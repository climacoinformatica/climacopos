<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_horarios', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $tabla->unsignedTinyInteger('dia_semana');   // 0 domingo ... 6 sabado
            $tabla->time('hora_ini');
            $tabla->time('hora_fin');
            $tabla->timestamps();

            $tabla->index(['usuario_id', 'dia_semana']);
        });

        Schema::create('usuario_excepciones', function (Blueprint $tabla) {
            $tabla->id();

            // NULL = afecta a toda la empresa (festivo, cierre por vacaciones)
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->cascadeOnDelete();

            $tabla->date('fecha_ini');
            $tabla->date('fecha_fin');
            $tabla->enum('tipo', [
                'VACACIONES', 'BAJA', 'FESTIVO', 'CIERRE', 'HORARIO_ESPECIAL',
            ]);

            // Solo para HORARIO_ESPECIAL
            $tabla->time('hora_ini')->nullable();
            $tabla->time('hora_fin')->nullable();

            $tabla->string('motivo', 160)->nullable();
            $tabla->timestamps();

            $tabla->index(['fecha_ini', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_excepciones');
        Schema::dropIfExists('usuario_horarios');
    }
};
