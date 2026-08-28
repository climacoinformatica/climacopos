<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_online', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->uuid('uuid')->unique();

            $tabla->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $tabla->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();

            $tabla->enum('pasarela', ['STRIPE', 'REDSYS', 'MANUAL'])->default('STRIPE');
            $tabla->enum('tipo', ['FIANZA', 'TOTAL'])->default('FIANZA');

            $tabla->decimal('importe', 10, 2);
            $tabla->char('moneda', 3)->default('EUR');

            /**
             * Comision que se lleva la plataforma. En Stripe Connect el
             * dinero va a la cuenta del salon; esta parte se descuenta
             * como application_fee.
             */
            $tabla->decimal('comision_plataforma', 10, 2)->default(0);

            $tabla->enum('estado', [
                'INICIADO',    // se creo la sesion de pago
                'PAGADO',
                'FALLIDO',
                'CADUCADO',    // el cliente no termino
                'DEVUELTO',
                'DEVUELTO_PARCIAL',
            ])->default('INICIADO');

            // Referencias de la pasarela
            $tabla->string('referencia', 100)->unique();          // la nuestra
            $tabla->string('sesion_id', 120)->nullable()->index(); // checkout session
            $tabla->string('cargo_id', 120)->nullable();           // payment_intent
            $tabla->string('devolucion_id', 120)->nullable();

            $tabla->decimal('devuelto_importe', 10, 2)->default(0);
            $tabla->dateTime('devuelto_en')->nullable();
            $tabla->string('motivo_devolucion', 255)->nullable();

            $tabla->text('url_pago')->nullable();
            $tabla->dateTime('caduca_en')->nullable();
            $tabla->dateTime('pagado_en')->nullable();

            // Respuesta cruda de la pasarela, por si hay que reclamar
            $tabla->longText('respuesta')->nullable();
            $tabla->string('error', 255)->nullable();

            $tabla->timestamps();

            $tabla->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_online');
    }
};
