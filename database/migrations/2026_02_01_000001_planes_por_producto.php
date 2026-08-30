<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Cada plan pertenece a un producto.
         *
         * Hoy los tres productos tienen los mismos nombres y precios, asi
         * que compartir planes seria mas simple. Pero el dia que se suba
         * el precio del de restaurantes sin tocar peluquerias, habria que
         * duplicarlos con datos ya en produccion, y eso es mucho peor que
         * tenerlos separados desde el principio.
         */
        if (! Schema::hasColumn('planes', 'producto_id')) {
            Schema::table('planes', function (Blueprint $tabla) {
                $tabla->foreignId('producto_id')->nullable()
                      ->constrained('productos')->cascadeOnDelete();
            });
        }

        /**
         * Nivel de soporte: es lo UNICO que diferencia los planes.
         *
         * Las funcionalidades van completas en los tres. Se cobra por el
         * soporte, que es lo que de verdad cuesta tiempo.
         */
        if (! Schema::hasColumn('planes', 'soporte')) {
            Schema::table('planes', function (Blueprint $tabla) {
                $tabla->enum('soporte', ['NINGUNO', 'EMAIL', 'COMPLETO'])
                      ->default('NINGUNO');
            });
        }

        // Texto que se enseña al cliente sobre el soporte incluido
        if (! Schema::hasColumn('planes', 'soporte_texto')) {
            Schema::table('planes', function (Blueprint $tabla) {
                $tabla->string('soporte_texto', 200)->nullable();
            });
        }

        if (! Schema::hasColumn('planes', 'descripcion')) {
            Schema::table('planes', function (Blueprint $tabla) {
                $tabla->string('descripcion', 200)->nullable();
            });
        }

        /**
         * El slug deja de ser unico globalmente.
         *
         * Con planes por producto, «basico» existe tres veces: uno por
         * cada uno. Lo que tiene que ser unico es la pareja
         * producto + slug.
         */
        if (Schema::hasColumn('planes', 'slug')) {
            Schema::table('planes', function (Blueprint $tabla) {
                try {
                    $tabla->dropUnique('planes_slug_unique');
                } catch (\Throwable) {
                    // Si el indice no existe o tiene otro nombre, seguimos
                }
            });

            try {
                Schema::table('planes', function (Blueprint $tabla) {
                    $tabla->unique(['producto_id', 'slug'], 'planes_producto_slug_unique');
                });
            } catch (\Throwable) {
            }
        }
    }

    public function down(): void
    {
        foreach (['producto_id', 'soporte', 'soporte_texto', 'descripcion'] as $columna) {
            if (Schema::hasColumn('planes', $columna)) {
                Schema::table('planes', fn (Blueprint $t) => $t->dropColumn($columna));
            }
        }
    }
};
