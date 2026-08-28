<?php

namespace App\Console\Commands;

use App\Models\Cuenta;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CrearSuperadmin extends Command
{
    protected $signature = 'climacopos:crear-superadmin
                            {--email=    : Email de acceso}
                            {--nombre=   : Nombre}
                            {--password= : Contrasena (si no, se genera una)}';

    protected $description = 'Crea la cuenta de administracion de la plataforma';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email de acceso');
        $nombre = $this->option('nombre') ?: $this->ask('Nombre', 'Administrador');
        $password = $this->option('password') ?: Str::random(14);

        $cuenta = Cuenta::where('email', $email)->first();

        if ($cuenta) {
            if (! $this->confirm("Ya existe una cuenta con {$email}. Convertirla en superadministrador?", true)) {
                return self::SUCCESS;
            }

            $cuenta->forceFill([
                'es_superadmin' => true,
                'password'      => $password,
            ])->save();
        } else {
            $cuenta = Cuenta::create([
                'nombre'            => $nombre,
                'email'             => $email,
                'password'          => $password,
                'es_superadmin'     => true,
                'email_verified_at' => now(),
            ]);
        }

        $dominios = config('tenancy.central_domains', []);
        $admin = collect($dominios)->first(fn ($d) => str_starts_with($d, 'admin.'))
                 ?? ('admin.' . config('climacopos.dominio_base'));

        $this->newLine();
        $this->line('  Cuenta ........ ' . $cuenta->email);
        $this->line('  Contrasena .... ' . $password);
        $this->line('  Panel ......... http://' . $admin . '/acceso');
        $this->newLine();
        $this->warn('Anota la contrasena: no se vuelve a mostrar.');

        return self::SUCCESS;
    }
}
