<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comprobada antes de aplicar, como norma del proyecto: MariaDB no
     * tiene DDL transaccional, asi que una migracion que falle a mitad
     * deja las columnas creadas sin marcarse como aplicada.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('ticket_cobros', 'pago_online_id')) {
            Schema::table('ticket_cobros', function (Blueprint $tabla) {
                /**
                 * Pago online que respalda este cobro.
                 *
                 * Sin esta referencia no se puede devolver automaticamente:
                 * se sabria que se cobraron 20 € por internet, pero no a
                 * que cargo de Stripe hay que lanzar el refund.
                 */
                $tabla->foreignId('pago_online_id')->nullable()
                      ->constrained('pagos_online')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('ticket_cobros', 'devuelto_por_cobro_id')) {
            Schema::table('ticket_cobros', function (Blueprint $tabla) {
                /**
                 * Devolucion ya realizada.
                 * Evita devolver dos veces el mismo cargo.
                 */
                $tabla->foreignId('devuelto_por_cobro_id')->nullable()
                      ->constrained('ticket_cobros')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ticket_cobros', 'devuelto_por_cobro_id')) {
            Schema::table('ticket_cobros',
                fn (Blueprint $t) => $t->dropConstrainedForeignId('devuelto_por_cobro_id'));
        }

        if (Schema::hasColumn('ticket_cobros', 'pago_online_id')) {
            Schema::table('ticket_cobros',
                fn (Blueprint $t) => $t->dropConstrainedForeignId('pago_online_id'));
        }
    }
};
