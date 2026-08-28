<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Cola de impresion.
         *
         * El navegador no puede hablar con una impresora ESC/POS, asi que
         * el servidor deja aqui el trabajo ya montado en bytes y el Agente
         * instalado en el salon lo recoge y lo envia por socket.
         *
         * Ventaja de la cola frente a una conexion directa: si el equipo
         * esta apagado o sin red, el trabajo espera en lugar de perderse.
         */
        Schema::create('cola_impresion', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('terminal_id')->constrained('terminales')->cascadeOnDelete();

            $tabla->enum('tipo', [
                'TICKET', 'COPIA', 'CIERRE', 'CAJON', 'VISOR', 'PRUEBA',
            ]);

            $tabla->enum('destino', ['TICKETS', 'ETIQUETAS', 'CAJON', 'VISOR'])->default('TICKETS');

            // Bytes ESC/POS ya montados, en base64
            $tabla->longText('payload')->nullable();
            $tabla->string('descripcion', 160)->nullable();

            $tabla->enum('estado', ['PENDIENTE', 'ENVIADO', 'HECHO', 'ERROR'])->default('PENDIENTE');
            $tabla->unsignedTinyInteger('intentos')->default(0);
            $tabla->text('error')->nullable();

            $tabla->unsignedBigInteger('referencia_id')->nullable();   // ticket, cierre...
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->dateTime('recogido_en')->nullable();
            $tabla->dateTime('procesado_en')->nullable();
            $tabla->timestamps();

            $tabla->index(['terminal_id', 'estado']);
            $tabla->index('created_at');
        });

        /**
         * Diseno del ticket: cabecera, pie, logotipo y opciones.
         * Cada empresa puede tener varios y uno activo.
         */
        Schema::create('ticket_disenos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 60);
            $tabla->boolean('activo')->default(false);

            $tabla->unsignedSmallInteger('ancho_mm')->default(80);
            $tabla->unsignedSmallInteger('columnas')->default(48);

            $tabla->string('logo', 255)->nullable();
            $tabla->enum('logo_alineacion', ['IZQUIERDA', 'CENTRO', 'DERECHA'])->default('CENTRO');
            $tabla->unsignedSmallInteger('logo_ancho_px')->default(384);

            // [{texto, alineacion, negrita, doble_alto, doble_ancho}]
            $tabla->json('cabecera')->nullable();
            $tabla->json('pie')->nullable();

            $tabla->boolean('mostrar_qr_verifactu')->default(false);
            $tabla->boolean('mostrar_qr_reserva')->default(false);
            $tabla->boolean('mostrar_cliente')->default(true);
            $tabla->boolean('mostrar_profesional')->default(true);
            $tabla->boolean('mostrar_desglose_impuesto')->default(true);

            $tabla->text('texto_legal')->nullable();
            $tabla->unsignedTinyInteger('lineas_finales')->default(4);
            $tabla->boolean('cortar_papel')->default(true);
            $tabla->boolean('abrir_cajon_efectivo')->default(true);

            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_disenos');
        Schema::dropIfExists('cola_impresion');
    }
};
