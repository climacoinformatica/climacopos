<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $tabla) {
            /**
             * Estado de la cuenta conectada de Stripe.
             *
             * El dinero de las reservas va a la cuenta del SALON, no a la
             * nuestra. Si pasara por nosotros estariamos actuando como
             * entidad de pago, que es una figura regulada.
             */
            $tabla->enum('stripe_connect_estado', [
                'SIN_CONECTAR',
                'PENDIENTE',      // creada, faltan datos por verificar
                'ACTIVA',
                'RESTRINGIDA',    // Stripe pide documentacion
            ])->default('SIN_CONECTAR')->after('stripe_connect_id');

            $tabla->boolean('stripe_cobros_activos')->default(false)->after('stripe_connect_estado');
            $tabla->timestamp('stripe_verificado_en')->nullable()->after('stripe_cobros_activos');

            // Comision de la plataforma sobre cada pago del cliente final.
            // 0 = no cobramos nada por las reservas.
            $tabla->decimal('comision_plataforma_pct', 5, 2)->default(0)->after('stripe_verificado_en');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $tabla) {
            $tabla->dropColumn([
                'stripe_connect_estado',
                'stripe_cobros_activos',
                'stripe_verificado_en',
                'comision_plataforma_pct',
            ]);
        });
    }
};
