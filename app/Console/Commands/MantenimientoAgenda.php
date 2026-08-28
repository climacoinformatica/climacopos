<?php

namespace App\Console\Commands;

use App\Models\Aviso;
use App\Models\Empresa;
use App\Models\Reserva;
use App\Models\ReservaTemporal;
use Illuminate\Console\Command;

/**
 * Tareas periodicas de la agenda, para TODAS las empresas.
 *
 * Va al scheduler:
 *   Schedule::command('climacopos:mantenimiento-agenda')->hourly();
 */
class MantenimientoAgenda extends Command
{
    protected $signature = 'climacopos:mantenimiento-agenda
                            {--empresa= : Limitar a una empresa concreta}';

    protected $description = 'Caduca reservas pendientes sin atender y limpia retenciones';

    public function handle(): int
    {
        $empresas = $this->option('empresa')
            ? Empresa::where('id', $this->option('empresa'))->orWhere('slug', $this->option('empresa'))->get()
            : Empresa::whereIn('estado', ['PRUEBA', 'ACTIVA', 'MOROSA'])->get();

        $totalRechazadas = 0;
        $totalPurgadas   = 0;

        foreach ($empresas as $empresa) {
            tenancy()->initialize($empresa);

            try {
                // 1. Retenciones caducadas: huecos de gente que empezo a
                //    reservar y no termino
                $totalPurgadas += ReservaTemporal::purgarCaducadas();

                // 2. Reservas pendientes que nadie ha atendido
                $horas = (int) config_empresa('caducidad_pendiente_horas', 48);

                $caducadas = Reserva::pendientes()
                    ->where('created_at', '<=', now()->subHours($horas))
                    ->get();

                foreach ($caducadas as $reserva) {
                    $reserva->rechazar('No se pudo atender la solicitud a tiempo.');
                    Aviso::resolverDeReserva($reserva->id);
                    $totalRechazadas++;
                }

                if ($caducadas->isNotEmpty()) {
                    $this->warn("  {$empresa->nombre_comercial}: {$caducadas->count()} reserva(s) caducada(s).");
                }
            } finally {
                tenancy()->end();
            }
        }

        $this->info("Retenciones purgadas: {$totalPurgadas}");
        $this->info("Reservas caducadas: {$totalRechazadas}");

        return self::SUCCESS;
    }
}
