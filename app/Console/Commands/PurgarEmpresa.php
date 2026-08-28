<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use Illuminate\Console\Command;
use Stancl\Tenancy\Jobs\DeleteDatabase;

/**
 * Borrado DEFINITIVO de una empresa: elimina su base de datos y su fila.
 *
 * Es un comando explicito y no un evento automatico a proposito. El modelo
 * Empresa usa SoftDeletes; dar de baja a un salon NO debe destruir sus datos,
 * porque la plataforma promete 90 dias de retencion para que pueda volver
 * o exportar su informacion.
 */
class PurgarEmpresa extends Command
{
    protected $signature = 'climacopos:purgar-empresa
                            {id?          : Id de la empresa a purgar}
                            {--caducadas  : Purga todas las que llevan de baja mas dias de los configurados}
                            {--sin-copia  : Omite la comprobacion de copia de seguridad previa}';

    protected $description = 'Elimina definitivamente una empresa y su base de datos';

    public function handle(): int
    {
        if ($this->option('caducadas')) {
            return $this->purgarCaducadas();
        }

        $id = $this->argument('id');

        if (! $id) {
            $this->error('Indica el id de la empresa o usa --caducadas.');

            return self::FAILURE;
        }

        $empresa = Empresa::withTrashed()->find($id);

        if (! $empresa) {
            $this->error("No existe la empresa {$id}.");

            return self::FAILURE;
        }

        return $this->purgar($empresa) ? self::SUCCESS : self::FAILURE;
    }

    protected function purgarCaducadas(): int
    {
        $dias = (int) config('climacopos.dias_hasta_borrado', 90);

        $empresas = Empresa::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays($dias))
            ->get();

        if ($empresas->isEmpty()) {
            $this->info("No hay empresas de baja desde hace mas de {$dias} dias.");

            return self::SUCCESS;
        }

        $this->warn("Se van a purgar {$empresas->count()} empresa(s):");
        foreach ($empresas as $empresa) {
            $this->line("  [{$empresa->id}] {$empresa->nombre_comercial} - baja el "
                        . $empresa->deleted_at->format('d/m/Y'));
        }

        if (! $this->confirm('Confirmas el borrado DEFINITIVO?', false)) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        foreach ($empresas as $empresa) {
            $this->purgar($empresa, false);
        }

        return self::SUCCESS;
    }

    protected function purgar(Empresa $empresa, bool $confirmar = true): bool
    {
        $this->newLine();
        $this->line("  Empresa ....... [{$empresa->id}] {$empresa->nombre_comercial}");
        $this->line('  Base datos .... ' . $empresa->tenancy_db_name);
        $this->line('  Estado ........ ' . $empresa->estado
                    . ($empresa->trashed() ? ' (de baja desde ' . $empresa->deleted_at->format('d/m/Y') . ')' : ' (ACTIVA)'));
        $this->newLine();

        if (! $empresa->trashed()) {
            $this->error('Esta empresa NO esta de baja. Da de baja primero y espera el periodo de retencion.');

            return false;
        }

        if (! $this->option('sin-copia')) {
            $this->warn('Asegurate de tener una copia de seguridad de ' . $empresa->tenancy_db_name);
            $this->line('  mysqldump -u root ' . $empresa->tenancy_db_name . ' > ' . $empresa->tenancy_db_name . '.sql');
            $this->newLine();
        }

        if ($confirmar && ! $this->confirm('Escribe si para borrar DEFINITIVAMENTE esta empresa', false)) {
            $this->info('Cancelado.');

            return false;
        }

        try {
            // 1. Base de datos de la empresa
            (new DeleteDatabase($empresa))->handle();
            $this->info('  Base de datos eliminada.');
        } catch (\Throwable $e) {
            $this->error('  No se pudo eliminar la base de datos: ' . $e->getMessage());
            $this->warn('  Se continua con el borrado de la fila. Revisa el servidor MySQL a mano.');
        }

        // 2. Dominios y fila de la empresa
        $empresa->domains()->delete();
        $empresa->forceDelete();

        $this->info('  Empresa purgada.');

        return true;
    }
}
