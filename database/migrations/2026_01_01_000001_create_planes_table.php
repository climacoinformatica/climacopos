<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 60);
            $tabla->string('slug', 40)->unique();
            $tabla->decimal('precio_mes', 8, 2)->default(0);
            $tabla->decimal('precio_ano', 8, 2)->default(0);
            $tabla->string('stripe_price_mes', 100)->nullable();
            $tabla->string('stripe_price_ano', 100)->nullable();

            // Limites del plan
            $tabla->unsignedSmallInteger('max_profesionales')->default(1);
            $tabla->unsignedSmallInteger('max_terminales')->default(1);
            $tabla->unsignedInteger('max_almacenamiento_mb')->default(500);
            $tabla->unsignedInteger('sms_incluidos')->default(0);

            // Funcionalidades activadas
            $tabla->boolean('reservas_online')->default(true);
            $tabla->boolean('pagos_online')->default(false);
            $tabla->boolean('verifactu')->default(false);
            $tabla->boolean('dominio_propio')->default(false);
            $tabla->boolean('informes_avanzados')->default(false);

            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
