<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de tokens de recuperacion, en la base CENTRAL.
     *
     * Laravel la crea de serie en proyectos nuevos, pero este arranco con
     * un esquema propio y puede que no exista. La migracion comprueba
     * antes de crear, asi que se puede ejecutar sin miedo.
     */
    public function up(): void
    {
        if (Schema::hasTable('password_reset_tokens')) {
            return;
        }

        Schema::create('password_reset_tokens', function (Blueprint $tabla) {
            $tabla->string('email')->primary();

            // Hasheado: es una llave que abre la cuenta durante una hora
            $tabla->string('token');

            $tabla->dateTime('created_at')->nullable();
        });
    }

    public function down(): void
    {
        // No se borra: puede estar en uso por el guardia por defecto
    }
};
