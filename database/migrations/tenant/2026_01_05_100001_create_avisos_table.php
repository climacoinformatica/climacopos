<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->enum('tipo', [
                'RESERVA_NUEVA',
                'RESERVA_CANCELADA',
                'STOCK_MINIMO',
                'ERROR_VERIFACTU',
                'ERROR_AGENTE',
            ]);

            $tabla->unsignedBigInteger('referencia_id')->nullable();
            $tabla->string('titulo', 120);
            $tabla->string('mensaje', 255)->nullable();

            /**
             * Los avisos que EXIGEN accion (una reserva sin confirmar) no se
             * apagan al leerlos: siguen destellando hasta que se resuelven.
             * Los informativos se apagan con un clic.
             */
            $tabla->boolean('requiere_accion')->default(false);
            $tabla->boolean('resuelto')->default(false);

            $tabla->boolean('leido')->default(false);
            $tabla->foreignId('leido_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamp('leido_en')->nullable();

            $tabla->timestamps();

            $tabla->index(['resuelto', 'tipo']);
            $tabla->index(['tipo', 'referencia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos');
    }
};
