<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recursos fisicos limitados: cabinas, lavacabezas, aparatos.
        // Un servicio que los requiere no puede solaparse consigo mismo
        // mas veces que unidades haya. Lo consumira el motor de huecos (Fase 3).
        Schema::create('recursos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 60);
            $tabla->unsignedSmallInteger('cantidad')->default(1);
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });

        Schema::create('familias', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 80);
            $tabla->enum('tipo', ['SERVICIO', 'PRODUCTO', 'AMBOS'])->default('SERVICIO');
            $tabla->foreignId('familia_padre_id')->nullable()
                  ->constrained('familias')->nullOnDelete();

            $tabla->string('color', 7)->default('#6366f1');
            $tabla->string('imagen', 255)->nullable();
            $tabla->text('descripcion')->nullable();

            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->boolean('visible_online')->default(true);
            $tabla->boolean('activa')->default(true);
            $tabla->timestamps();

            $tabla->index(['activa', 'orden']);
        });

        Schema::create('articulos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->uuid('uuid')->unique();
            $tabla->foreignId('familia_id')->constrained('familias');

            $tabla->enum('tipo', ['SERVICIO', 'PRODUCTO', 'BONO', 'PACK'])->default('SERVICIO');
            $tabla->string('codigo', 30)->nullable()->unique();
            $tabla->string('nombre', 120);
            $tabla->text('descripcion')->nullable();
            $tabla->text('descripcion_online')->nullable();

            // Precio SIEMPRE con impuesto incluido: es como se muestra al
            // cliente final y como se teclea en el TPV. La base se calcula.
            $tabla->decimal('precio', 10, 2)->default(0);
            $tabla->decimal('impuesto_pct', 5, 2)->default(7.00);   // IGIC general
            $tabla->decimal('coste', 10, 2)->nullable();

            // --- Solo servicios
            $tabla->unsignedSmallInteger('duracion_min')->default(30);
            // Hueco libre intermedio: tinte = 20' activo + 30' espera + 15' activo.
            // Durante la pausa el profesional puede atender a otro cliente.
            $tabla->unsignedSmallInteger('tiempo_pausa_min')->default(0);
            $tabla->unsignedSmallInteger('tiempo_final_min')->default(0);
            $tabla->foreignId('recurso_id')->nullable()->constrained('recursos')->nullOnDelete();

            // --- Solo productos
            $tabla->boolean('control_stock')->default(false);
            $tabla->decimal('stock', 10, 3)->default(0);
            $tabla->decimal('stock_min', 10, 3)->default(0);
            $tabla->string('codigo_barras', 40)->nullable()->index();

            // --- Reserva online
            $tabla->boolean('permite_reserva_online')->default(true);
            $tabla->boolean('requiere_confirmacion')->default(true);
            $tabla->enum('politica_pago', ['NINGUNO', 'FIANZA', 'TOTAL'])->default('NINGUNO');
            $tabla->decimal('fianza_importe', 10, 2)->nullable();
            $tabla->decimal('fianza_pct', 5, 2)->nullable();

            // --- Bonos y packs
            $tabla->unsignedSmallInteger('sesiones')->nullable();
            $tabla->unsignedSmallInteger('caducidad_dias')->nullable();

            $tabla->string('color', 7)->nullable();
            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['activo', 'tipo']);
            $tabla->index(['familia_id', 'orden']);
        });

        Schema::create('articulo_fotos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $tabla->string('ruta', 255);
            $tabla->string('ruta_mini', 255)->nullable();
            $tabla->string('alt', 160)->nullable();
            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->boolean('principal')->default(false);
            $tabla->timestamps();

            $tabla->index(['articulo_id', 'orden']);
        });

        // Caracteristicas libres: marca, formato, tono, tipo de cabello...
        Schema::create('articulo_atributos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $tabla->string('clave', 60);
            $tabla->string('valor', 255);
            $tabla->boolean('visible_online')->default(true);
            $tabla->unsignedSmallInteger('orden')->default(0);

            $tabla->index(['articulo_id', 'orden']);
        });

        // Que profesional hace que servicio, con precio y duracion propios.
        // Sin filas para un servicio = lo hace cualquiera.
        Schema::create('articulo_profesional', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $tabla->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $tabla->decimal('precio', 10, 2)->nullable();
            $tabla->unsignedSmallInteger('duracion_min')->nullable();

            $tabla->unique(['articulo_id', 'usuario_id']);
        });

        // Composicion de packs: que articulos incluye y en que cantidad
        Schema::create('articulo_componentes', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('pack_id')->constrained('articulos')->cascadeOnDelete();
            $tabla->foreignId('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $tabla->decimal('cantidad', 10, 3)->default(1);
            $tabla->unsignedSmallInteger('orden')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articulo_componentes');
        Schema::dropIfExists('articulo_profesional');
        Schema::dropIfExists('articulo_atributos');
        Schema::dropIfExists('articulo_fotos');
        Schema::dropIfExists('articulos');
        Schema::dropIfExists('familias');
        Schema::dropIfExists('recursos');
    }
};
