<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->uuid('uuid')->unique();
            $tabla->string('slug', 40)->unique();

            // Identidad
            $tabla->string('nombre_comercial', 120);
            $tabla->string('razon_social', 160)->nullable();
            $tabla->string('nif', 20)->nullable();
            $tabla->string('email', 160);
            $tabla->string('telefono', 20)->nullable();

            // Direccion fiscal
            $tabla->string('direccion', 160)->nullable();
            $tabla->string('cp', 10)->nullable();
            $tabla->string('municipio', 80)->nullable();
            $tabla->string('provincia', 80)->nullable();
            $tabla->char('pais', 2)->default('ES');

            // Localizacion y fiscalidad
            $tabla->string('zona_horaria', 40)->default('Atlantic/Canary');
            $tabla->char('moneda', 3)->default('EUR');
            $tabla->enum('regimen_fiscal', ['IGIC', 'IVA'])->default('IGIC');

            // Marca
            $tabla->string('logo', 255)->nullable();
            $tabla->string('color_marca', 7)->default('#111827');

            // Suscripcion
            $tabla->foreignId('plan_id')->nullable()->constrained('planes')->nullOnDelete();
            $tabla->enum('estado', ['PRUEBA', 'ACTIVA', 'MOROSA', 'SUSPENDIDA', 'CANCELADA'])
                  ->default('PRUEBA');
            $tabla->date('prueba_hasta')->nullable();
            $tabla->string('stripe_customer_id', 100)->nullable();
            $tabla->string('stripe_subscription_id', 100)->nullable();
            $tabla->string('stripe_connect_id', 100)->nullable();

            $tabla->boolean('onboarding_completado')->default(false);

            // Nombre real de la base de datos de esta empresa.
            // Lo lee stancl/tenancy para conectarse. Ver Empresa::asignarNombreBaseDatos()
            $tabla->string('tenancy_db_name', 64)->nullable();

            // Columna obligatoria de stancl/tenancy: cualquier atributo no declarado
            // en Empresa::getCustomColumns() se guarda aqui automaticamente.
            $tabla->json('data')->nullable();

            $tabla->timestamp('suspendida_en')->nullable();
            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
