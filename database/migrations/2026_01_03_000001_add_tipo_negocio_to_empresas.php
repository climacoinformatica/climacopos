<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $tabla) {
            // Determina que catalogo plantilla se precarga en el alta
            $tabla->enum('tipo_negocio', [
                'PELUQUERIA', 'BARBERIA', 'ESTETICA', 'UNAS', 'SPA', 'MIXTO',
            ])->default('PELUQUERIA')->after('nombre_comercial');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $tabla) {
            $tabla->dropColumn('tipo_negocio');
        });
    }
};
