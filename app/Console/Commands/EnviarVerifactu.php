<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Services\Verifactu\EnvioAeat;
use App\Services\Verifactu\HuellaVerifactu;
use Illuminate\Console\Command;

/**
 * Envia a la AEAT los registros pendientes de todas las empresas.
 *
 * Al scheduler:
 *   Schedule::command('climacopos:verifactu-enviar')->everyFiveMinutes();
 */
class EnviarVerifactu extends Command
{
    protected $signature = 'climacopos:verifactu-enviar
                            {--empresa=  : Limitar a una empresa}
                            {--verificar : Comprueba ademas la integridad de la cadena}';

    protected $description = 'Envia los registros VERI*FACTU pendientes';

    public function handle(): int
    {
        $empresas = $this->option('empresa')
            ? Empresa::where('slug', $this->option('empresa'))
                     ->orWhere('id', $this->option('empresa'))->get()
            : Empresa::whereIn('estado', ['PRUEBA', 'ACTIVA', 'MOROSA'])->get();

        $totalEnviados = 0;
        $totalFallidos = 0;

        foreach ($empresas as $empresa) {
            tenancy()->initialize($empresa);

            try {
                if (! $empresa->verifactu_activo) {
                    continue;
                }

                if ($this->option('verificar')) {
                    $cadena = HuellaVerifactu::verificarCadena();

                    if (! $cadena['integra']) {
                        $this->error("  {$empresa->nombre_comercial}: CADENA ROTA "
                            . "en el registro {$cadena['roto_en']}");
                    }
                }

                $resultado = (new EnvioAeat())->enviarPendientes();

                if ($resultado['enviados'] > 0 || $resultado['fallidos'] > 0) {
                    $this->line("  {$empresa->nombre_comercial}: "
                        . "{$resultado['enviados']} enviados, {$resultado['fallidos']} fallidos");
                }

                $totalEnviados += $resultado['enviados'];
                $totalFallidos += $resultado['fallidos'];
            } finally {
                tenancy()->end();
            }
        }

        $this->newLine();
        $this->info("Total enviados: {$totalEnviados}");

        if ($totalFallidos > 0) {
            $this->warn("Total fallidos: {$totalFallidos}");
        }

        return self::SUCCESS;
    }
}
