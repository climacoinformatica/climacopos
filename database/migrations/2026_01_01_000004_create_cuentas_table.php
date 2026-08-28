<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cuentas del dominio central: quien contrata y paga.
        // OJO: no confundir con la tabla 'usuarios' de cada empresa,
        // que son los empleados que trabajan en el salon (agenda, TPV, PIN).
        Schema::create('cuentas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 120);
            $tabla->string('email', 160)->unique();
            $tabla->string('password');
            $tabla->string('telefono', 20)->nullable();
            $tabla->boolean('es_superadmin')->default(false);
            $tabla->timestamp('email_verified_at')->nullable();
            $tabla->rememberToken();
            $tabla->timestamp('ultimo_acceso')->nullable();
            $tabla->timestamps();
        });

        Schema::create('cuenta_empresa', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $tabla->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $tabla->enum('rol', ['PROPIETARIO', 'FACTURACION'])->default('PROPIETARIO');
            $tabla->timestamps();

            $tabla->unique(['cuenta_id', 'empresa_id']);
        });

        Schema::create('cuentas_password_reset_tokens', function (Blueprint $tabla) {
            $tabla->string('email')->primary();
            $tabla->string('token');
            $tabla->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_password_reset_tokens');
        Schema::dropIfExists('cuenta_empresa');
        Schema::dropIfExists('cuentas');
    }
};
