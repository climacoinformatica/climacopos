<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->uuid('uuid')->unique();
            $tabla->string('codigo', 20)->nullable()->unique();

            $tabla->string('nombre', 80);
            $tabla->string('apellidos', 120)->nullable();

            // El telefono es el identificador real del cliente en un salon:
            // mucha gente no da email, pero todo el mundo da su movil.
            $tabla->string('telefono', 20)->nullable()->index();
            $tabla->string('email', 160)->nullable()->index();

            // Acceso al portal para ver y cancelar sus citas
            $tabla->string('password')->nullable();

            $tabla->date('fecha_nac')->nullable();
            $tabla->enum('sexo', ['M', 'F', 'O'])->nullable();
            $tabla->text('notas')->nullable();
            $tabla->text('alergias')->nullable();

            // RGPD: hay que poder demostrar cuando y desde donde se consintio
            $tabla->boolean('acepta_rgpd')->default(false);
            $tabla->boolean('acepta_marketing')->default(false);
            $tabla->timestamp('fecha_consentimiento')->nullable();
            $tabla->string('ip_consentimiento', 45)->nullable();

            $tabla->unsignedInteger('puntos')->default(0);
            $tabla->decimal('saldo_monedero', 10, 2)->default(0);

            $tabla->unsignedSmallInteger('no_shows')->default(0);
            $tabla->unsignedSmallInteger('citas_totales')->default(0);
            $tabla->boolean('bloqueado')->default(false);
            $tabla->string('motivo_bloqueo', 255)->nullable();

            $tabla->enum('origen', ['ONLINE', 'MANUAL', 'IMPORTADO'])->default('MANUAL');
            $tabla->timestamp('ultima_visita')->nullable();
            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['nombre', 'apellidos']);
        });

        // Fotos antes/despues y referencias de color
        Schema::create('cliente_fotos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $tabla->string('ruta', 255);
            $tabla->string('ruta_mini', 255)->nullable();
            $tabla->string('descripcion', 255)->nullable();
            $tabla->date('fecha');
            $tabla->timestamps();
        });

        // Formulas de color e historial tecnico
        Schema::create('cliente_ficha_tecnica', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->date('fecha');
            $tabla->text('texto');
            $tabla->timestamps();

            $tabla->index(['cliente_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_ficha_tecnica');
        Schema::dropIfExists('cliente_fotos');
        Schema::dropIfExists('clientes');
    }
};
