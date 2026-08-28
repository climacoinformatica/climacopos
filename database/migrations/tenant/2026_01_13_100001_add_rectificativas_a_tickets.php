<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $tabla) {
            /**
             * Documento rectificativo.
             *
             * Cuando un ticket ya entró en un cierre de jornada no se puede
             * anular: el cierre dejaría de cuadrar retroactivamente y la
             * cadena de VERI*FACTU quedaría con una factura que declaró un
             * importe y ahora dice otro.
             *
             * La vía correcta es emitir una FACTURA RECTIFICATIVA, que es un
             * documento nuevo, con su propio número, que corrige al anterior
             * sin tocarlo. Igual que en contabilidad no se borra un apunte:
             * se contra-asienta.
             */
            $tabla->enum('tipo_documento', ['NORMAL', 'RECTIFICATIVA'])
                  ->default('NORMAL')->after('serie');

            $tabla->foreignId('rectifica_ticket_id')->nullable()
                  ->after('tipo_documento')
                  ->constrained('tickets')->nullOnDelete();

            /**
             * Tipo según la AEAT:
             *   R1  error fundado en derecho (lo habitual en devoluciones)
             *   R2  concurso de acreedores
             *   R3  créditos incobrables
             *   R4  resto
             *   R5  rectificación de facturas simplificadas
             *
             * Para tickets de peluquería lo que aplica casi siempre es R5,
             * porque el documento original es una factura simplificada.
             */
            $tabla->string('tipo_rectificativa', 2)->nullable()->after('rectifica_ticket_id');

            $tabla->string('motivo_rectificacion', 255)->nullable()->after('tipo_rectificativa');
        });

        Schema::table('ticket_lineas', function (Blueprint $tabla) {
            // Línea original que se está devolviendo
            $tabla->foreignId('rectifica_linea_id')->nullable()
                  ->constrained('ticket_lineas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_lineas', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('rectifica_linea_id');
        });

        Schema::table('tickets', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('rectifica_ticket_id');
            $tabla->dropColumn(['tipo_documento', 'tipo_rectificativa', 'motivo_rectificacion']);
        });
    }
};
