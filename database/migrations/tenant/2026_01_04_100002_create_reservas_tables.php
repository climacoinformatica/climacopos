<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->uuid('uuid')->unique();
            $tabla->string('codigo', 12)->unique();   // el que se da al cliente: RS-8F3K2

            $tabla->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();

            // Copia de los datos en el momento de reservar. Si el cliente
            // luego cambia de telefono, la cita conserva con que datos se hizo.
            $tabla->string('cliente_nombre', 120);
            $tabla->string('cliente_telefono', 20)->nullable();
            $tabla->string('cliente_email', 160)->nullable();

            $tabla->date('fecha');
            $tabla->time('hora_ini');
            $tabla->time('hora_fin');

            $tabla->enum('estado', [
                'PENDIENTE', 'CONFIRMADA', 'RECHAZADA', 'CANCELADA',
                'EN_CURSO', 'ATENDIDA', 'NO_SHOW',
            ])->default('CONFIRMADA');

            $tabla->enum('origen', ['ONLINE', 'LOCAL', 'TELEFONO', 'WHATSAPP'])->default('LOCAL');

            $tabla->enum('pago_tipo', ['NINGUNO', 'FIANZA', 'TOTAL'])->default('NINGUNO');
            $tabla->decimal('importe_total', 10, 2)->default(0);
            $tabla->decimal('importe_pagado', 10, 2)->default(0);
            $tabla->unsignedBigInteger('ticket_id')->nullable();   // Fase 5

            $tabla->text('notas_cliente')->nullable();
            $tabla->text('notas_internas')->nullable();

            $tabla->foreignId('creada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->foreignId('confirmada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamp('confirmada_en')->nullable();
            $tabla->string('motivo_rechazo', 255)->nullable();
            $tabla->enum('cancelada_por', ['CLIENTE', 'SALON'])->nullable();
            $tabla->timestamp('cancelada_en')->nullable();

            $tabla->boolean('recordatorio_enviado')->default(false);
            $tabla->timestamps();

            // El indice mas consultado de toda la aplicacion: la agenda del dia
            $tabla->index(['fecha', 'estado']);
            $tabla->index(['cliente_id', 'fecha']);
        });

        // Una cita puede encadenar varios servicios, cada uno con su
        // profesional. Corte con Marta y despues color con Ana.
        Schema::create('reserva_lineas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $tabla->foreignId('articulo_id')->constrained('articulos');
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->foreignId('recurso_id')->nullable()->constrained('recursos')->nullOnDelete();

            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->time('hora_ini');

            // Copia de los tiempos del articulo en el momento de reservar:
            // si manana cambian la duracion del servicio, las citas ya
            // hechas conservan la suya y la agenda no se descoloca.
            $tabla->unsignedSmallInteger('duracion_min');
            $tabla->unsignedSmallInteger('tiempo_pausa_min')->default(0);
            $tabla->unsignedSmallInteger('tiempo_final_min')->default(0);

            $tabla->decimal('precio', 10, 2)->default(0);
            $tabla->string('nombre_servicio', 120);   // por si el articulo se borra

            $tabla->index(['usuario_id', 'hora_ini']);
        });

        Schema::create('bloqueos_agenda', function (Blueprint $tabla) {
            $tabla->id();
            // NULL = bloquea a todo el salon
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->cascadeOnDelete();
            $tabla->date('fecha');
            $tabla->time('hora_ini');
            $tabla->time('hora_fin');
            $tabla->string('motivo', 160)->nullable();
            $tabla->string('color', 7)->default('#64748b');
            $tabla->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamps();

            $tabla->index(['fecha', 'usuario_id']);
        });

        // Retencion temporal del hueco mientras el cliente paga en el portal.
        // Sin esto, dos personas pagarian la misma hora.
        Schema::create('reservas_temporales', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('token', 64)->unique();
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->cascadeOnDelete();
            $tabla->date('fecha');
            $tabla->time('hora_ini');
            $tabla->time('hora_fin');
            $tabla->timestamp('caduca_en');
            $tabla->timestamps();

            $tabla->index(['fecha', 'caduca_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas_temporales');
        Schema::dropIfExists('bloqueos_agenda');
        Schema::dropIfExists('reserva_lineas');
        Schema::dropIfExists('reservas');
    }
};
