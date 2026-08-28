<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Registros de facturación VERI*FACTU.
         *
         * Cada factura emitida genera un registro de ALTA. Si se anula,
         * genera otro de ANULACION. Los registros están encadenados por
         * la huella del anterior: modificar uno rompe la cadena entera,
         * que es justamente el objetivo del reglamento.
         *
         * IMPORTANTE: esta tabla es INMUTABLE. No se edita ni se borra
         * nunca. Un error se corrige emitiendo un registro nuevo, igual
         * que en contabilidad no se borra un apunte: se contra-asienta.
         */
        Schema::create('verifactu_registros', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->enum('tipo', ['ALTA', 'ANULACION']);
            $tabla->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();

            // --- Identificación de la factura
            $tabla->string('nif_emisor', 20);
            $tabla->string('serie_numero', 60);        // A-000123
            $tabla->date('fecha_expedicion');
            $tabla->string('tipo_factura', 4)->default('F2');   // F2 = simplificada

            // --- Importes
            $tabla->decimal('base', 12, 2)->default(0);
            $tabla->decimal('cuota', 12, 2)->default(0);
            $tabla->decimal('total', 12, 2)->default(0);
            $tabla->decimal('tipo_impositivo', 5, 2)->default(0);

            $tabla->string('descripcion', 500)->nullable();

            // --- Encadenado
            $tabla->char('huella', 64);                          // SHA-256 en mayúsculas
            $tabla->char('huella_anterior', 64)->nullable();
            $tabla->unsignedBigInteger('registro_anterior_id')->nullable();

            /**
             * Momento exacto de generación, con huso horario.
             * Entra en el cálculo de la huella, así que se guarda tal cual
             * se usó: recalcularlo después daría otro resultado.
             */
            $tabla->string('fecha_hora_huso', 30);

            // --- Envío a la AEAT
            $tabla->enum('estado', [
                'PENDIENTE',
                'ENVIANDO',
                'ACEPTADO',
                'ACEPTADO_CON_ERRORES',
                'RECHAZADO',
                'ERROR_ENVIO',
            ])->default('PENDIENTE');

            $tabla->unsignedTinyInteger('intentos')->default(0);
            $tabla->dateTime('enviado_en')->nullable();
            $tabla->string('csv_aeat', 40)->nullable();          // justificante
            $tabla->string('codigo_error', 20)->nullable();
            $tabla->string('mensaje_error', 500)->nullable();

            $tabla->longText('xml')->nullable();
            $tabla->longText('respuesta')->nullable();

            $tabla->timestamps();

            $tabla->index(['estado', 'id']);
            $tabla->index('serie_numero');
            $tabla->unique(['tipo', 'ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifactu_registros');
    }
};
