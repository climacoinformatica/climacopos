<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Esta migracion se ejecuta DENTRO de la base de datos de cada empresa.
     * Fijate en que no hay ninguna columna empresa_id: no hace falta,
     * porque la base de datos entera pertenece a una sola empresa.
     */
    public function up(): void
    {
        Schema::create('config', function (Blueprint $tabla) {
            $tabla->string('clave', 60)->primary();
            $tabla->text('valor')->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config');
    }
};
