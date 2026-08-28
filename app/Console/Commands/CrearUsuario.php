<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Usuario;
use Illuminate\Console\Command;

class CrearUsuario extends Command
{
    protected $signature = 'climacopos:crear-usuario
                            {empresa           : Id o slug de la empresa}
                            {nombre            : Nombre del empleado}
                            {--email=          : Email (necesario para vincular equipos y reautenticar)}
                            {--password=       : Contrasena para acciones sensibles}
                            {--pin=            : PIN de 4 a 8 digitos}
                            {--perfil=propietario : Clave del perfil}
                            {--profesional     : Aparece en la agenda}
                            {--formacion       : Empleado en formacion (solo efectivo)}';

    protected $description = 'Crea un usuario dentro de la base de datos de una empresa';

    public function handle(): int
    {
        $empresa = is_numeric($this->argument('empresa'))
            ? Empresa::find($this->argument('empresa'))
            : Empresa::where('slug', $this->argument('empresa'))->first();

        if (! $empresa) {
            $this->error('No existe esa empresa.');

            return self::FAILURE;
        }

        $pin = $this->option('pin') ?: (string) random_int(1000, 9999);
        $password = $this->option('password') ?: \Illuminate\Support\Str::random(12);

        tenancy()->initialize($empresa);

        try {
            $perfil = Perfil::where('clave', $this->option('perfil'))->first();

            if (! $perfil) {
                $this->error('No existe el perfil «' . $this->option('perfil') . '». '
                             . 'Ejecuta primero: php artisan tenants:seed');

                return self::FAILURE;
            }

            $usuario = Usuario::create([
                'nombre'         => $this->argument('nombre'),
                'email'          => $this->option('email'),
                'password'       => $password,
                'pin'            => $pin,
                'perfil_id'      => $perfil->id,
                'es_profesional' => (bool) $this->option('profesional'),
                'en_formacion'   => (bool) $this->option('formacion'),
                'estado'         => 'ACTIVO',
            ]);

            $this->newLine();
            $this->line('  Empresa ....... ' . $empresa->nombre_comercial);
            $this->line('  Usuario ....... ' . $usuario->nombre . ' (id ' . $usuario->id . ')');
            $this->line('  Perfil ........ ' . $perfil->nombre);
            $this->line('  Email ......... ' . ($usuario->email ?: '(sin email)'));
            $this->line('  PIN ........... ' . $pin);
            $this->line('  Contrasena .... ' . $password);

            if ($usuario->en_formacion) {
                $this->warn('  EN FORMACION: solo podra cobrar en efectivo.');
            }

            $this->newLine();
            $this->warn('Anota el PIN y la contrasena: no se vuelven a mostrar.');
        } finally {
            tenancy()->end();
        }

        return self::SUCCESS;
    }
}
