<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Plan;
use App\Support\Slug;
use Illuminate\Console\Command;

class CrearEmpresa extends Command
{
    protected $signature = 'climacopos:crear-empresa
                            {slug             : Subdominio de la empresa, p.ej. jectan}
                            {--nombre=        : Nombre comercial}
                            {--email=         : Email de contacto}
                            {--plan=          : Slug del plan a asignar}
                            {--regimen=IGIC   : IGIC o IVA}';

    protected $description = 'Crea una empresa, su base de datos, su dominio y ejecuta sus migraciones';

    public function handle(): int
    {
        $slug = Slug::normalizar($this->argument('slug'));

        if ($motivo = Slug::motivoRechazo($slug)) {
            $this->error("Slug no valido: {$motivo}");

            return self::FAILURE;
        }

        $nombre = $this->option('nombre') ?: ucfirst($slug);
        $email  = $this->option('email') ?: "info@{$slug}.test";

        $plan = $this->option('plan')
            ? Plan::where('slug', $this->option('plan'))->first()
            : Plan::where('activo', true)->orderBy('orden')->first();

        $this->info("Creando empresa '{$nombre}' ({$slug})...");

        $empresa = Empresa::create([
            'slug'             => $slug,
            'nombre_comercial' => $nombre,
            'email'            => $email,
            'plan_id'          => $plan?->id,
            'regimen_fiscal'   => strtoupper($this->option('regimen')),
        ]);

        // La creacion de la base de datos y sus migraciones las dispara
        // stancl/tenancy mediante los eventos TenantCreated -> CreateDatabase
        // -> MigrateDatabase, configurados en app/Providers/TenancyServiceProvider.php

        $dominio = $slug . '.' . config('climacopos.dominio_base');
        $empresa->domains()->create([
            'domain'       => $dominio,
            'es_principal' => true,
        ]);

        $this->newLine();
        $this->line('  Empresa ....... ' . $empresa->id . ' / ' . $empresa->uuid);
        $this->line('  Base datos .... ' . $empresa->tenancy_db_name);
        $this->line('  Portal ........ http://' . $dominio);
        $this->line('  Panel ......... http://' . $dominio . '/panel');
        $this->line('  Prueba hasta .. ' . $empresa->prueba_hasta->format('d/m/Y'));
        $this->newLine();

        if (app()->environment('local')) {
            $this->warn('Recuerda anadir esta linea a C:\\Windows\\System32\\drivers\\etc\\hosts:');
            $this->line('  127.0.0.1   ' . $dominio);
        }

        $this->info('Empresa creada correctamente.');

        return self::SUCCESS;
    }
}
