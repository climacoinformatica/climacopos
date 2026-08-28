<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * NOTA SOBRE MariaDB
         *
         * Se usa dateTime() y no timestamp() en las fechas de negocio.
         * MariaDB no admite dos columnas TIMESTAMP NOT NULL sin valor por
         * defecto en la misma tabla: a la primera le pone CURRENT_TIMESTAMP
         * automaticamente y a la segunda le asigna '0000-00-00', que con
         * sql_mode estricto revienta con «Invalid default value».
         *
         * Ademas, dateTime es lo correcto aqui: son fechas de negocio, no
         * marcas de auditoria, y no deben convertirse por zona horaria.
         */

        Schema::create('cierres_jornada', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->dateTime('fecha_ini');
            $tabla->dateTime('fecha_fin');
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->foreignId('terminal_id')->nullable()->constrained('terminales')->nullOnDelete();

            $tabla->decimal('efectivo_inicial', 10, 2)->default(0);
            $tabla->decimal('efectivo_teorico', 10, 2)->default(0);
            $tabla->decimal('efectivo_contado', 10, 2)->default(0);
            $tabla->decimal('descuadre', 10, 2)->default(0);

            $tabla->decimal('total_ventas', 10, 2)->default(0);
            $tabla->decimal('total_base', 10, 2)->default(0);
            $tabla->decimal('total_impuesto', 10, 2)->default(0);
            $tabla->unsignedInteger('num_tickets')->default(0);

            $tabla->json('totales_por_medio')->nullable();
            $tabla->json('totales_por_familia')->nullable();
            $tabla->json('totales_por_profesional')->nullable();

            $tabla->text('observaciones')->nullable();
            $tabla->string('backup_ruta', 255)->nullable();
            $tabla->timestamps();

            $tabla->index('fecha_fin');
        });

        Schema::create('tickets', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->uuid('uuid')->unique();

            // La numeracion es por serie. 'A' para ventas reales,
            // 'FOR' para formacion, que NO consume numeracion fiscal.
            $tabla->string('serie', 4)->default('A');
            $tabla->unsignedInteger('numero');

            $tabla->dateTime('fecha');

            $tabla->foreignId('usuario_id')->constrained('usuarios');
            $tabla->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $tabla->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $tabla->foreignId('terminal_id')->nullable()->constrained('terminales')->nullOnDelete();

            $tabla->decimal('base', 10, 2)->default(0);
            $tabla->decimal('impuesto', 10, 2)->default(0);
            $tabla->decimal('descuento', 10, 2)->default(0);
            $tabla->decimal('total', 10, 2)->default(0);

            $tabla->enum('estado', ['ABIERTO', 'COBRADO', 'ANULADO'])->default('ABIERTO');

            /**
             * FORMACION
             * Un ticket de formacion no entra en el cierre de jornada,
             * no suma en informes ni comisiones, y no consume numeracion
             * fiscal. Se guarda aparte para consulta y borrado posterior.
             */
            $tabla->boolean('es_formacion')->default(false);
            $tabla->boolean('es_invitacion')->default(false);

            // VERI*FACTU (Fase 10): la cadena de hashes nunca incluye formacion
            $tabla->char('verifactu_hash', 64)->nullable();
            $tabla->char('verifactu_hash_anterior', 64)->nullable();
            $tabla->enum('verifactu_estado', ['PENDIENTE', 'ENVIADO', 'ACEPTADO', 'RECHAZADO'])->nullable();
            $tabla->longText('verifactu_xml')->nullable();

            $tabla->foreignId('cierre_id')->nullable()->constrained('cierres_jornada')->nullOnDelete();
            $tabla->text('observaciones')->nullable();
            $tabla->foreignId('anulado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->dateTime('anulado_en')->nullable();
            $tabla->string('motivo_anulacion', 255)->nullable();

            $tabla->timestamps();

            $tabla->unique(['serie', 'numero']);
            $tabla->index(['fecha', 'estado']);
            $tabla->index(['es_formacion', 'cierre_id']);
        });

        Schema::create('ticket_lineas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $tabla->foreignId('articulo_id')->nullable()->constrained('articulos')->nullOnDelete();

            // Copia del nombre y del precio: si el articulo cambia o se
            // borra, el ticket antiguo sigue siendo legible y cuadrando.
            $tabla->string('descripcion', 160);
            $tabla->decimal('cantidad', 10, 3)->default(1);
            $tabla->decimal('precio', 10, 2)->default(0);
            $tabla->decimal('dto_pct', 5, 2)->default(0);
            $tabla->decimal('impuesto_pct', 5, 2)->default(0);
            $tabla->decimal('base', 10, 2)->default(0);
            $tabla->decimal('impuesto', 10, 2)->default(0);
            $tabla->decimal('importe', 10, 2)->default(0);

            // Quien ejecuta el servicio: base de las comisiones
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->boolean('es_invitacion')->default(false);
            $tabla->string('motivo_invitacion', 160)->nullable();

            $tabla->unsignedSmallInteger('orden')->default(0);

            $tabla->index(['ticket_id', 'orden']);
        });

        Schema::create('ticket_cobros', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();

            $tabla->enum('medio', [
                'EFECTIVO', 'TARJETA', 'BIZUM', 'TRANSFERENCIA',
                'ANTICIPO', 'MONEDERO', 'BONO', 'VALE',
            ]);

            $tabla->decimal('importe', 10, 2);
            $tabla->decimal('entregado', 10, 2)->nullable();
            $tabla->decimal('cambio', 10, 2)->default(0);
            $tabla->string('referencia', 100)->nullable();
            $tabla->timestamps();

            $tabla->index(['ticket_id', 'medio']);
        });

        Schema::create('caja_movimientos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->dateTime('fecha');
            $tabla->enum('tipo', ['APERTURA', 'ENTRADA', 'SALIDA']);
            $tabla->decimal('importe', 10, 2);
            $tabla->string('motivo', 160)->nullable();
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->foreignId('terminal_id')->nullable()->constrained('terminales')->nullOnDelete();
            $tabla->foreignId('cierre_id')->nullable()->constrained('cierres_jornada')->nullOnDelete();
            $tabla->timestamps();

            $tabla->index(['fecha', 'cierre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_movimientos');
        Schema::dropIfExists('ticket_cobros');
        Schema::dropIfExists('ticket_lineas');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('cierres_jornada');
    }
};
