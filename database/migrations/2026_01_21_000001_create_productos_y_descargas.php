<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Catalogo de productos de CLIMACO.
         *
         * Tres por ahora, pero en tabla y no en codigo: los textos
         * comerciales cambian a menudo y no deberia hacer falta un
         * despliegue para corregir una frase.
         */
        if (! Schema::hasTable('productos')) {
            Schema::create('productos', function (Blueprint $tabla) {
                $tabla->id();
                $tabla->string('slug', 60)->unique();
                $tabla->string('nombre', 120);
                $tabla->string('reclamo', 200);
                $tabla->text('descripcion')->nullable();

                /**
                 * INSTALABLE  se descarga y corre en el equipo del cliente
                 * SAAS        se usa desde internet, con su subdominio
                 *
                 * Cambia todo lo demas: uno lleva descargas y versiones,
                 * el otro lleva alta de cuenta y provision de base de datos.
                 */
                $tabla->enum('modalidad', ['INSTALABLE', 'SAAS'])->default('INSTALABLE');

                $tabla->string('sector', 60)->nullable();
                $tabla->string('color', 9)->default('#6366f1');
                $tabla->string('icono', 40)->nullable();

                // Lista de puntos fuertes, para la ficha
                $tabla->json('caracteristicas')->nullable();

                $tabla->decimal('precio_desde', 10, 2)->nullable();
                $tabla->string('precio_nota', 80)->nullable();

                $tabla->boolean('activo')->default(true);
                $tabla->boolean('descargable')->default(true);
                $tabla->unsignedSmallInteger('orden')->default(0);

                $tabla->timestamps();
            });
        }

        /**
         * Versiones publicadas de cada producto instalable.
         *
         * Se guarda la ruta del fichero, no el fichero: los instaladores
         * pesan cientos de megas y no tienen sitio en una base de datos.
         */
        if (! Schema::hasTable('producto_versiones')) {
            Schema::create('producto_versiones', function (Blueprint $tabla) {
                $tabla->id();
                $tabla->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();

                $tabla->string('version', 20);
                $tabla->text('novedades')->nullable();

                $tabla->string('fichero', 255);          // ruta en storage
                $tabla->string('nombre_fichero', 160);   // como se descarga
                $tabla->unsignedBigInteger('tamano')->default(0);
                $tabla->string('sha256', 64)->nullable();

                $tabla->date('publicada_el');

                /**
                 * La version actual. Solo una por producto: es la que se
                 * ofrece por defecto. Las anteriores siguen accesibles
                 * porque a veces hay que volver atras.
                 */
                $tabla->boolean('es_actual')->default(false);
                $tabla->boolean('publica')->default(true);

                $tabla->unsignedInteger('descargas')->default(0);

                $tabla->timestamps();

                $tabla->unique(['producto_id', 'version']);
            });
        }

        /**
         * Registro de descargas.
         *
         * Sirve para saber que version tiene cada cliente cuando llama
         * con un problema, que es la primera pregunta de cualquier
         * soporte.
         */
        if (! Schema::hasTable('descargas')) {
            Schema::create('descargas', function (Blueprint $tabla) {
                $tabla->id();
                $tabla->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
                $tabla->foreignId('version_id')->constrained('producto_versiones')->cascadeOnDelete();

                $tabla->string('ip', 45)->nullable();
                $tabla->string('dispositivo', 200)->nullable();
                $tabla->dateTime('fecha');

                $tabla->timestamps();

                $tabla->index(['cuenta_id', 'fecha']);
            });
        }

        // --- La cuenta se amplia para el area de clientes
        $columnas = [
            'telefono'   => fn (Blueprint $t) => $t->string('telefono', 30)->nullable(),
            'empresa'    => fn (Blueprint $t) => $t->string('empresa', 120)->nullable(),
            'nif'        => fn (Blueprint $t) => $t->string('nif', 20)->nullable(),
            'provincia'  => fn (Blueprint $t) => $t->string('provincia', 60)->nullable(),
            'sector'     => fn (Blueprint $t) => $t->string('sector', 60)->nullable(),
            'acepta_novedades' => fn (Blueprint $t) => $t->boolean('acepta_novedades')->default(false),

            /**
             * Verificacion del correo.
             *
             * Con registro abierto, sin verificar cualquiera crearia
             * cuentas con correos ajenos o inventados. Y para el SaaS es
             * mas serio: cada alta provisiona una base de datos.
             */
            'token_verificacion' => fn (Blueprint $t) => $t->string('token_verificacion', 64)->nullable(),
        ];

        foreach ($columnas as $nombre => $definicion) {
            if (! Schema::hasColumn('cuentas', $nombre)) {
                Schema::table('cuentas', $definicion);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('descargas');
        Schema::dropIfExists('producto_versiones');
        Schema::dropIfExists('productos');

        foreach (['telefono', 'empresa', 'nif', 'provincia', 'sector',
                  'acepta_novedades', 'token_verificacion'] as $columna) {
            if (Schema::hasColumn('cuentas', $columna)) {
                Schema::table('cuentas', fn (Blueprint $t) => $t->dropColumn($columna));
            }
        }
    }
};
