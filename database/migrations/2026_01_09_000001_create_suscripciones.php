<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $tabla) {
            // --- Ciclo de morosidad
            $tabla->unsignedTinyInteger('impagos')->default(0)->after('estado');
            $tabla->dateTime('primer_impago_en')->nullable()->after('impagos');

            /**
             * Cuándo empieza a aplicarse la suspensión.
             *
             * NUNCA es inmediata: se fija para la madrugada siguiente. Si el
             * TPV se bloqueara con clientas esperando en el salón, se pierde
             * al cliente aunque pague al día siguiente.
             */
            $tabla->dateTime('suspension_efectiva_en')->nullable()->after('primer_impago_en');

            $tabla->dateTime('aviso_borrado_en')->nullable()->after('suspension_efectiva_en');
            $tabla->dateTime('borrar_a_partir_de')->nullable()->after('aviso_borrado_en');

            // --- Suscripción
            $tabla->enum('ciclo', ['MENSUAL', 'ANUAL'])->default('MENSUAL')->after('plan_id');
            $tabla->dateTime('suscripcion_hasta')->nullable()->after('ciclo');
            $tabla->boolean('cancela_al_terminar')->default(false)->after('suscripcion_hasta');
        });

        /**
         * Facturas que la plataforma emite a los salones.
         * Espejo de lo que hay en Stripe, para que el salón pueda
         * consultarlas sin salir del panel.
         */
        Schema::create('facturas_plataforma', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $tabla->string('numero', 60)->nullable();
            $tabla->string('stripe_factura_id', 120)->nullable()->unique();

            $tabla->decimal('importe', 10, 2);
            $tabla->decimal('impuesto', 10, 2)->default(0);
            $tabla->char('moneda', 3)->default('EUR');

            $tabla->enum('estado', ['BORRADOR', 'PENDIENTE', 'PAGADA', 'IMPAGADA', 'ANULADA'])
                  ->default('PENDIENTE');

            $tabla->date('periodo_desde')->nullable();
            $tabla->date('periodo_hasta')->nullable();
            $tabla->dateTime('pagada_en')->nullable();
            $tabla->unsignedTinyInteger('intentos_cobro')->default(0);

            $tabla->text('url_factura')->nullable();
            $tabla->text('url_pago')->nullable();

            $tabla->timestamps();

            $tabla->index(['empresa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas_plataforma');

        Schema::table('empresas', function (Blueprint $tabla) {
            $tabla->dropColumn([
                'impagos', 'primer_impago_en', 'suspension_efectiva_en',
                'aviso_borrado_en', 'borrar_a_partir_de',
                'ciclo', 'suscripcion_hasta', 'cancela_al_terminar',
            ]);
        });
    }
};
