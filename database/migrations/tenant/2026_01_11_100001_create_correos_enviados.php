<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Registro de correos enviados.
         *
         * Sirve para dos cosas concretas: responder a «no me ha llegado
         * nada» sin adivinar, y evitar mandar dos veces el mismo
         * recordatorio.
         */
        Schema::create('correos_enviados', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->string('tipo', 40);
            $tabla->string('destinatario', 160);
            $tabla->unsignedBigInteger('referencia_id')->nullable();
            $tabla->string('asunto', 200);

            $tabla->enum('estado', ['ENVIADO', 'ERROR', 'SIN_CONFIGURAR'])->default('ENVIADO');
            $tabla->string('error', 400)->nullable();

            $tabla->dateTime('enviado_en');
            $tabla->timestamps();

            $tabla->index(['tipo', 'referencia_id']);
            $tabla->index('enviado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correos_enviados');
    }
};
