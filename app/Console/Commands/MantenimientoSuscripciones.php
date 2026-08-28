<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Services\GestorSuscripciones;
use Illuminate\Console\Command;

/**
 * Tareas diarias del ciclo de vida de las suscripciones.
 *
 * En produccion va al scheduler:
 *   Schedule::command('climacopos:mantenimiento-suscripciones')->dailyAt('04:15');
 */
class MantenimientoSuscripciones extends Command
{
    protected $signature = 'climacopos:mantenimiento-suscripciones
                            {--simular : Muestra lo que haria sin tocar nada}';

    protected $description = 'Caduca pruebas, avisa de borrados y purga empresas canceladas';

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');

        $this->avisosDePrueba($simular);
        $this->pruebasCaducadas($simular);
        $this->avisosDeBorrado($simular);
        $this->borradosPendientes($simular);

        return self::SUCCESS;
    }

    /** Prueba terminada sin contratar: pasa a suspendida. */
    protected function pruebasCaducadas(bool $simular): void
    {
        $empresas = Empresa::where('estado', 'PRUEBA')
            ->whereNotNull('prueba_hasta')
            ->whereDate('prueba_hasta', '<', now()->toDateString())
            ->get();

        foreach ($empresas as $empresa) {
            $this->line("  Prueba terminada: {$empresa->nombre_comercial}");

            if ($simular) {
                continue;
            }

            $empresa->forceFill([
                'estado'                 => 'SUSPENDIDA',
                'suspendida_en'          => now(),
                // Nunca en mitad de la jornada
                'suspension_efectiva_en' => now()->addDay()->setTime(4, 0),
                'borrar_a_partir_de'     => now()->addDays(GestorSuscripciones::DIAS_HASTA_BORRADO),
            ])->save();
        }

        $this->info($empresas->count() . ' prueba(s) caducada(s).');
    }

    /**
     * Aviso de que la prueba termina.
     *
     * Se manda a 7 y a 1 dia. Dos avisos bastan: mas resulta insistente y
     * menos se pierde entre el resto del correo.
     */
    protected function avisosDePrueba(bool $simular): void
    {
        $correos = new \App\Services\Correo\CorreosPlataforma();
        $enviados = 0;

        foreach ([7, 1] as $dias) {
            $empresas = Empresa::where('estado', 'PRUEBA')
                ->whereDate('prueba_hasta', now()->addDays($dias)->toDateString())
                ->get();

            foreach ($empresas as $empresa) {
                $this->line("  Prueba termina en {$dias} dia(s): {$empresa->nombre_comercial}");

                if (! $simular) {
                    $correos->pruebaTermina($empresa, $dias);
                    $enviados++;
                }
            }
        }

        $this->info($enviados . ' aviso(s) de fin de prueba.');
    }

    /** Aviso 15 dias antes del borrado definitivo. */
    protected function avisosDeBorrado(bool $simular): void
    {
        $empresas = Empresa::whereNotNull('borrar_a_partir_de')
            ->whereNull('aviso_borrado_en')
            ->where('borrar_a_partir_de', '<=', now()->addDays(15))
            ->get();

        foreach ($empresas as $empresa) {
            $this->warn("  Aviso de borrado: {$empresa->nombre_comercial}"
                . ' (el ' . $empresa->borrar_a_partir_de->format('d/m/Y') . ')');

            if ($simular) {
                continue;
            }

            (new \App\Services\Correo\CorreosPlataforma())->avisoBorrado($empresa);

            $empresa->forceFill(['aviso_borrado_en' => now()])->save();
        }

        $this->info($empresas->count() . ' aviso(s) de borrado.');
    }

    /**
     * Borrado definitivo.
     *
     * NO se borra automaticamente: se listan las empresas que ya cumplen
     * el plazo para que alguien lo confirme con climacopos:purgar-empresa.
     * Destruir los datos de un cliente sin intervencion humana es un
     * riesgo que no compensa automatizar.
     */
    protected function borradosPendientes(bool $simular): void
    {
        $empresas = Empresa::whereNotNull('borrar_a_partir_de')
            ->where('borrar_a_partir_de', '<=', now())
            ->get();

        if ($empresas->isEmpty()) {
            $this->info('No hay empresas pendientes de borrado.');

            return;
        }

        $this->newLine();
        $this->error('Empresas que ya cumplen el plazo de 90 dias:');

        foreach ($empresas as $empresa) {
            $this->line("  [{$empresa->id}] {$empresa->nombre_comercial}"
                . ' · avisada el ' . ($empresa->aviso_borrado_en?->format('d/m/Y') ?? 'nunca'));
        }

        $this->newLine();
        $this->warn('Revisalas y borra con: php artisan climacopos:purgar-empresa {id}');
    }
}
