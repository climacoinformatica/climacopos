<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfiles', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('clave', 40)->unique();      // propietario, encargado...
            $tabla->string('nombre', 60);
            $tabla->json('permisos');
            $tabla->boolean('es_sistema')->default(false);   // no se puede borrar
            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->timestamps();
        });

        Schema::create('usuarios', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->uuid('uuid')->unique();

            $tabla->string('nombre', 80);
            $tabla->string('alias', 30)->nullable();     // lo que se ve en la agenda
            $tabla->string('email', 160)->nullable()->unique();
            $tabla->string('telefono', 20)->nullable();

            // Credenciales: contrasena para acciones sensibles, PIN para el dia a dia
            $tabla->string('password')->nullable();
            $tabla->string('pin')->nullable();           // hasheado, nunca en claro
            $tabla->unsignedTinyInteger('intentos_pin')->default(0);
            $tabla->timestamp('pin_bloqueado_hasta')->nullable();

            $tabla->foreignId('perfil_id')->constrained('perfiles');

            $tabla->boolean('es_profesional')->default(false);  // aparece en la agenda
            $tabla->boolean('en_formacion')->default(false);    // solo efectivo, fuera del cierre

            $tabla->string('color_agenda', 7)->default('#6366f1');
            $tabla->string('foto', 255)->nullable();
            $tabla->decimal('comision_pct', 5, 2)->default(0);

            $tabla->enum('estado', ['INVITADO', 'ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $tabla->timestamp('ultimo_acceso')->nullable();
            $tabla->unsignedSmallInteger('orden')->default(0);

            $tabla->rememberToken();
            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['estado', 'es_profesional']);
        });

        Schema::create('invitaciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('email', 160);
            $tabla->string('nombre', 80);
            $tabla->foreignId('perfil_id')->constrained('perfiles');
            $tabla->boolean('es_profesional')->default(false);
            $tabla->boolean('en_formacion')->default(false);

            $tabla->string('token', 64)->unique();
            $tabla->timestamp('caduca_en');
            $tabla->timestamp('aceptada_en')->nullable();
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->foreignId('invitada_por')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitaciones');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('perfiles');
    }
};
