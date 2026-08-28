<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NOTA SOBRE LA IDEMPOTENCIA
     *
     * MariaDB no tiene DDL transaccional: si una migracion falla a mitad,
     * las tablas y columnas que ya se crearon SE QUEDAN, pero la migracion
     * no se marca como aplicada. Al reintentarla, revienta con «Duplicate
     * column name».
     *
     * Por eso todo va comprobado. Es mas verboso, pero permite reintentar
     * sin tener que deshacer nada a mano en phpMyAdmin.
     */
    public function up(): void
    {
        /**
         * Plantillas de bono: lo que el salon pone a la venta.
         *
         *   SESIONES  «5 manicuras por 60 €». Se descuenta una sesion por
         *             uso, sin mirar el precio del dia.
         *
         *   SALDO     «Recarga 100 € y te damos 120». Se descuenta el
         *             importe real de lo consumido.
         */
        if (! Schema::hasTable('bonos_plantillas')) {
            Schema::create('bonos_plantillas', function (Blueprint $tabla) {
                $tabla->id();
                $tabla->string('nombre', 120);
                $tabla->text('descripcion')->nullable();

                $tabla->enum('modalidad', ['SESIONES', 'SALDO'])->default('SESIONES');

                $tabla->decimal('precio', 10, 2);
                $tabla->decimal('impuesto_pct', 5, 2)->default(0);

                $tabla->unsignedSmallInteger('num_sesiones')->nullable();
                $tabla->decimal('saldo_otorgado', 10, 2)->nullable();

                $tabla->foreignId('articulo_id')->nullable()->constrained('articulos')->nullOnDelete();
                $tabla->foreignId('familia_id')->nullable()->constrained('familias')->nullOnDelete();

                $tabla->unsignedSmallInteger('caducidad_meses')->nullable();

                $tabla->boolean('activo')->default(true);
                $tabla->boolean('vender_online')->default(false);
                $tabla->string('color', 9)->default('#8b5cf6');
                $tabla->unsignedSmallInteger('orden')->default(0);

                $tabla->timestamps();
                $tabla->softDeletes();
            });
        }

        if (! Schema::hasTable('bonos')) {
            Schema::create('bonos', function (Blueprint $tabla) {
                $tabla->id();
                $tabla->string('codigo', 20)->unique();

                $tabla->foreignId('plantilla_id')->constrained('bonos_plantillas');
                $tabla->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
                $tabla->foreignId('ticket_compra_id')->nullable()->constrained('tickets')->nullOnDelete();

                $tabla->enum('modalidad', ['SESIONES', 'SALDO']);

                $tabla->unsignedSmallInteger('sesiones_totales')->default(0);
                $tabla->unsignedSmallInteger('sesiones_usadas')->default(0);

                $tabla->decimal('saldo_inicial', 10, 2)->default(0);
                $tabla->decimal('saldo_actual', 10, 2)->default(0);

                $tabla->decimal('precio_pagado', 10, 2)->default(0);

                $tabla->date('comprado_el');
                $tabla->date('caduca_el')->nullable();

                $tabla->enum('estado', ['ACTIVO', 'AGOTADO', 'CADUCADO', 'ANULADO'])->default('ACTIVO');

                $tabla->text('observaciones')->nullable();
                $tabla->timestamps();

                $tabla->index(['cliente_id', 'estado']);
            });
        }

        /**
         * Movimientos del bono. El saldo siempre se puede reconstruir
         * sumandolos, lo que permite detectar descuadres.
         */
        if (! Schema::hasTable('bono_movimientos')) {
            Schema::create('bono_movimientos', function (Blueprint $tabla) {
                $tabla->id();
                $tabla->foreignId('bono_id')->constrained('bonos')->cascadeOnDelete();

                $tabla->enum('tipo', ['COMPRA', 'CONSUMO', 'DEVOLUCION', 'AJUSTE', 'CADUCIDAD']);

                $tabla->decimal('sesiones', 6, 2)->default(0);
                $tabla->decimal('importe', 10, 2)->default(0);

                $tabla->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
                $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();

                $tabla->string('concepto', 200)->nullable();
                $tabla->dateTime('fecha');
                $tabla->timestamps();

                $tabla->index(['bono_id', 'fecha']);
            });
        }

        /**
         * Monedero: saldo a cuenta, sin vincular a servicios concretos.
         */
        if (! Schema::hasTable('monedero_movimientos')) {
            Schema::create('monedero_movimientos', function (Blueprint $tabla) {
                $tabla->id();
                $tabla->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();

                $tabla->enum('tipo', ['RECARGA', 'GASTO', 'DEVOLUCION', 'AJUSTE', 'REGALO']);

                $tabla->decimal('importe', 10, 2);
                $tabla->decimal('saldo_despues', 10, 2);

                $tabla->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
                $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();

                $tabla->string('concepto', 200)->nullable();
                $tabla->dateTime('fecha');
                $tabla->timestamps();

                $tabla->index(['cliente_id', 'fecha']);
            });
        }

        /**
         * Vales. Llevan codigo porque quien lo canjea no tiene por que
         * ser quien lo compro.
         */
        if (! Schema::hasTable('vales')) {
            Schema::create('vales', function (Blueprint $tabla) {
                $tabla->id();
                $tabla->string('codigo', 20)->unique();

                $tabla->enum('origen', ['DEVOLUCION', 'REGALO', 'PROMOCION', 'MANUAL'])->default('MANUAL');

                $tabla->decimal('importe_inicial', 10, 2);
                $tabla->decimal('importe_restante', 10, 2);

                $tabla->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
                $tabla->foreignId('ticket_origen_id')->nullable()->constrained('tickets')->nullOnDelete();

                $tabla->date('emitido_el');
                $tabla->date('caduca_el')->nullable();

                $tabla->enum('estado', ['ACTIVO', 'CANJEADO', 'CADUCADO', 'ANULADO'])->default('ACTIVO');

                $tabla->string('concepto', 200)->nullable();
                $tabla->timestamps();

                $tabla->index(['estado', 'caduca_el']);
            });
        }

        // --- Columnas anadidas a tablas existentes

        if (! Schema::hasColumn('clientes', 'saldo_monedero')) {
            Schema::table('clientes', function (Blueprint $tabla) {
                // Espejo del saldo, para no sumar los movimientos cada vez
                $tabla->decimal('saldo_monedero', 10, 2)->default(0);
            });
        }

        if (! Schema::hasColumn('articulos', 'bono_plantilla_id')) {
            Schema::table('articulos', function (Blueprint $tabla) {
                // Un articulo puede ser la venta de un bono
                $tabla->foreignId('bono_plantilla_id')->nullable()
                      ->constrained('bonos_plantillas')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('ticket_lineas', 'bono_id')) {
            Schema::table('ticket_lineas', function (Blueprint $tabla) {
                // Bono del que se consumio esta linea
                $tabla->foreignId('bono_id')->nullable()->constrained('bonos')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ticket_lineas', 'bono_id')) {
            Schema::table('ticket_lineas', fn (Blueprint $t) => $t->dropConstrainedForeignId('bono_id'));
        }

        if (Schema::hasColumn('articulos', 'bono_plantilla_id')) {
            Schema::table('articulos', fn (Blueprint $t) => $t->dropConstrainedForeignId('bono_plantilla_id'));
        }

        if (Schema::hasColumn('clientes', 'saldo_monedero')) {
            Schema::table('clientes', fn (Blueprint $t) => $t->dropColumn('saldo_monedero'));
        }

        Schema::dropIfExists('vales');
        Schema::dropIfExists('monedero_movimientos');
        Schema::dropIfExists('bono_movimientos');
        Schema::dropIfExists('bonos');
        Schema::dropIfExists('bonos_plantillas');
    }
};
