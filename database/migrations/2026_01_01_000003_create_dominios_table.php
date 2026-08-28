<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dominios', function (Blueprint $tabla) {
            $tabla->id();

            // Dominio completo: 'jectan.climacopos.com' o 'reservas.supeluqueria.com'
            $tabla->string('domain', 255)->unique();

            // stancl/tenancy espera el nombre 'tenant_id' en el modelo Domain.
            // Lo mantenemos aunque el resto del esquema este en castellano.
            $tabla->foreignId('tenant_id')->constrained('empresas')->cascadeOnDelete();

            $tabla->boolean('es_principal')->default(true);
            $tabla->boolean('es_propio')->default(false);   // dominio del cliente via CNAME
            $tabla->timestamp('verificado_en')->nullable();
            $tabla->timestamps();

            $tabla->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dominios');
    }
};
