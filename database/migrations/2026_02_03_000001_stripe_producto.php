<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * El producto de Stripe al que cuelgan los precios.
         *
         * Se guarda para no crear uno nuevo cada vez que se toca un
         * precio. El producto es el concepto —«Plan Basico de CLIMACO POS
         * Beauty»— y los precios son las tarifas que cuelgan de el.
         */
        if (! Schema::hasColumn('planes', 'stripe_producto')) {
            Schema::table('planes', function (Blueprint $tabla) {
                $tabla->string('stripe_producto', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('planes', 'stripe_producto')) {
            Schema::table('planes', fn (Blueprint $t) => $t->dropColumn('stripe_producto'));
        }
    }
};
