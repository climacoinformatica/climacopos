<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un vinculo = un equipo autorizado a usar el panel de este salon.
        // El navegador guarda el token en cookie de larga duracion; asi el
        // empleado solo teclea su PIN en el uso diario (opcion C).
        Schema::create('terminal_vinculos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('terminal_id')->constrained('terminales')->cascadeOnDelete();

            $tabla->string('token', 64)->unique();       // hash, nunca el valor en claro
            $tabla->string('dispositivo', 120)->nullable();
            $tabla->string('ultima_ip', 45)->nullable();
            $tabla->timestamp('ultima_conexion')->nullable();

            $tabla->foreignId('vinculado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamp('revocado_en')->nullable();
            $tabla->timestamps();
        });

        Schema::create('log_auditoria', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->timestamp('fecha')->useCurrent();
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->foreignId('terminal_id')->nullable()->constrained('terminales')->nullOnDelete();

            $tabla->string('accion', 60);          // login, login_fallido, permiso_denegado...
            $tabla->string('tabla', 40)->nullable();
            $tabla->unsignedBigInteger('registro_id')->nullable();
            $tabla->json('detalle')->nullable();
            $tabla->string('ip', 45)->nullable();

            $tabla->index(['fecha', 'accion']);
            $tabla->index(['usuario_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_auditoria');
        Schema::dropIfExists('terminal_vinculos');
    }
};
