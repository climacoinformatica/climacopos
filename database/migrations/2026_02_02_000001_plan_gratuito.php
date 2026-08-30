<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Limite de documentos al mes.
         *
         * Cero significa SIN LIMITE, igual que los demas maximos de esta
         * tabla. Los planes de pago se quedan en cero; el gratuito lleva
         * cien.
         */
        if (! Schema::hasColumn('planes', 'max_facturas_mes')) {
            Schema::table('planes', function (Blueprint $tabla) {
                $tabla->unsignedInteger('max_facturas_mes')->default(0);
            });
        }

        /**
         * Un plan gratuito no se cobra.
         *
         * Sin esto, el ciclo de morosidad intentaria cobrar cero euros
         * cada mes y marcaria al salon como impagado.
         */
        if (! Schema::hasColumn('planes', 'es_gratuito')) {
            Schema::table('planes', function (Blueprint $tabla) {
                $tabla->boolean('es_gratuito')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['max_facturas_mes', 'es_gratuito'] as $columna) {
            if (Schema::hasColumn('planes', $columna)) {
                Schema::table('planes', fn (Blueprint $t) => $t->dropColumn($columna));
            }
        }
    }
};
