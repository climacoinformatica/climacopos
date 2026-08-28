<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Reserva;
use App\Services\Correo\GestorCorreos;
use Illuminate\Console\Command;

/**
 * Recordatorios de la vispera.
 *
 * Al scheduler:
 *   Schedule::command('climacopos:recordatorios')->hourly();
 *
 * Se ejecuta cada hora y no una vez al dia para respetar la antelacion
 * que cada salon configure: con 24 horas, una cita de las 9:00 avisa a
 * las 9:00 del dia anterior, no a medianoche.
 */
class EnviarRecordatorios extends Command
{
    protected $signature = 'climacopos:recordatorios
                            {--simular : Muestra a quien se avisaria sin enviar nada}';

    protected $description = 'Envia los recordatorios de las citas proximas';

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');
        $total = 0;

        $empresas = Empresa::whereIn('estado', ['PRUEBA', 'ACTIVA', 'MOROSA'])->get();

        foreach ($empresas as $empresa) {
            tenancy()->initialize($empresa);

            try {
                if (! $empresa->avisar_recordatorio) {
                    continue;
                }

                $horas = (int) ($empresa->recordatorio_horas ?: 24);

                /**
                 * Ventana de una hora.
                 *
                 * El comando corre cada hora, asi que se buscan las citas
                 * que caen justo en la franja correspondiente. Sin ventana,
                 * una ejecucion tardia dejaria citas sin avisar.
                 */
                $desde = now()->addHours($horas);
                $hasta = $desde->copy()->addHour();

                $reservas = Reserva::with('lineas')
                    ->whereIn('estado', ['CONFIRMADA'])
                    ->whereNotNull('cliente_email')
                    ->whereRaw(
                        'CONCAT(fecha, " ", hora_ini) BETWEEN ? AND ?',
                        [$desde->format('Y-m-d H:i:s'), $hasta->format('Y-m-d H:i:s')],
                    )
                    ->get();

                if ($reservas->isEmpty()) {
                    continue;
                }

                $this->line("  {$empresa->nombre_comercial}: {$reservas->count()} cita(s)");

                if ($simular) {
                    foreach ($reservas as $reserva) {
                        $this->line("     {$reserva->cliente_nombre} <{$reserva->cliente_email}>"
                            . " · {$reserva->fecha->format('d/m/Y')} {$reserva->hora_ini}");
                    }

                    continue;
                }

                $correos = new GestorCorreos();

                foreach ($reservas as $reserva) {
                    if ($correos->recordatorio($reserva)) {
                        $total++;
                    }
                }
            } finally {
                tenancy()->end();
            }
        }

        $this->newLine();
        $this->info($simular ? 'Simulacion terminada.' : "{$total} recordatorio(s) enviado(s).");

        return self::SUCCESS;
    }
}
