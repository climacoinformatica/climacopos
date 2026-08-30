<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Columna `fecha_alta` en clientes.
 *
 * Faltaba, y el codigo la daba por hecha en tres sitios:
 *
 *   - TpvController al crear una clienta desde el punto de venta
 *   - ClienteController al darla de alta desde su pantalla
 *   - La ficha del cliente, que muestra «cliente desde ...»
 *
 * Los dos primeros son inserciones, asi que crear un cliente fallaba con
 * «Unknown column 'fecha_alta'». El informe de Clientes reventaba por lo
 * mismo al contar altas del periodo.
 *
 * Se rellena con created_at para los que ya existen: es la fecha en que
 * entraron en el sistema, que es justo lo que la columna quiere decir.
 *
 * Va aparte de created_at a proposito: al importar clientes de otro
 * programa, el alta real no es el dia en que se creo la fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clientes')) {
            return;
        }

        if (! Schema::hasColumn('clientes', 'fecha_alta')) {
            Schema::table('clientes', function (Blueprint $tabla) {
                $tabla->timestamp('fecha_alta')->nullable()->after('codigo');
            });
        }

        // Los que ya estaban: su alta es cuando entraron en el sistema
        DB::table('clientes')
            ->whereNull('fecha_alta')
            ->update(['fecha_alta' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        if (Schema::hasTable('clientes') && Schema::hasColumn('clientes', 'fecha_alta')) {
            Schema::table('clientes', fn (Blueprint $t) => $t->dropColumn('fecha_alta'));
        }
    }
};
