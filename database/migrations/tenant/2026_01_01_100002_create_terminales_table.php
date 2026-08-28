<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminales', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 60);
            $tabla->string('codigo', 20)->unique();

            // Token del Agente local (impresora ESC/POS, cajon, visor).
            // Se genera al vincular el equipo y se guarda hasheado.
            $tabla->string('agente_token', 100)->nullable();
            $tabla->timestamp('agente_ultima_conexion')->nullable();

            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });

        Schema::create('terminal_config', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('terminal_id')->constrained('terminales')->cascadeOnDelete();
            $tabla->string('clave', 60);
            $tabla->text('valor')->nullable();

            $tabla->unique(['terminal_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_config');
        Schema::dropIfExists('terminales');
    }
};
