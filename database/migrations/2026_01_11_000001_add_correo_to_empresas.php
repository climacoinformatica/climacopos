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
             * SMTP propio del salon, opcional.
             *
             * Un salon con dominio y correo propios prefiere que los avisos
             * salgan desde su direccion: llegan menos a spam y dan mejor
             * imagen que un remitente de la plataforma.
             */
            $tabla->boolean('correo_propio')->default(false);
            $tabla->string('correo_host', 120)->nullable();
            $tabla->unsignedSmallInteger('correo_puerto')->nullable();
            $tabla->string('correo_usuario', 160)->nullable();
            $tabla->text('correo_password')->nullable();      // cifrada
            $tabla->enum('correo_cifrado', ['tls', 'ssl', 'ninguno'])->default('tls');
            $tabla->string('correo_remitente', 160)->nullable();

            // Que avisos manda este salon
            $tabla->boolean('avisar_reserva')->default(true);
            $tabla->boolean('avisar_recordatorio')->default(true);
            $tabla->boolean('avisar_cancelacion')->default(true);
            $tabla->unsignedTinyInteger('recordatorio_horas')->default(24);
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $tabla) {
            $tabla->dropColumn([
                'correo_propio', 'correo_host', 'correo_puerto', 'correo_usuario',
                'correo_password', 'correo_cifrado', 'correo_remitente',
                'avisar_reserva', 'avisar_recordatorio', 'avisar_cancelacion',
                'recordatorio_horas',
            ]);
        });
    }
};
