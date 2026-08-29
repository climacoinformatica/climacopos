<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Perfil;
use App\Support\Permisos;
use Illuminate\Console\Command;

/**
 * Revisa y pone al dia los permisos de los perfiles de cada salon.
 *
 * POR QUE HACE FALTA
 *
 * Los perfiles se siembran al dar de alta el salon con la lista de
 * permisos que existia ESE dia, y se quedan congelados. Cada permiso
 * nuevo que se añade al catalogo nace sin que nadie lo tenga, asi que
 * la funcion correspondiente no aparece en ningun sitio y parece que el
 * despliegue no ha surtido efecto.
 *
 * Una migracion no sirve para esto: corre una sola vez y hay que
 * acordarse de escribirla. Este comando se puede pasar tantas veces
 * como haga falta y siempre deja los perfiles de sistema igual que el
 * catalogo actual.
 *
 * Uso:
 *   php artisan permisos:sincronizar                 (solo informa)
 *   php artisan permisos:sincronizar --aplicar       (corrige)
 *   php artisan permisos:sincronizar --aplicar --permiso=tpv.informes_caja --todos
 */
class SincronizarPermisos extends Command
{
    protected $signature = 'permisos:sincronizar
                            {--aplicar : Guarda los cambios. Sin esta opcion solo informa}
                            {--salon= : Id de un salon concreto. Por defecto, todos}
                            {--permiso=* : Permisos sueltos que añadir}
                            {--todos : Los permisos de --permiso van a TODOS los perfiles}';

    protected $description = 'Revisa los permisos de los perfiles y los pone al dia con el catalogo';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');

        $empresas = Empresa::query()
            ->when($this->option('salon'), fn ($q) => $q->where('id', $this->option('salon')))
            ->get();

        if ($empresas->isEmpty()) {
            $this->error('No hay ningun salon que revisar.');

            return self::FAILURE;
        }

        if (! $aplicar) {
            $this->warn('Modo informe: no se va a guardar nada. Añade --aplicar para corregir.');
            $this->newLine();
        }

        foreach ($empresas as $empresa) {
            $this->line("<fg=cyan>Salon {$empresa->id}: {$empresa->nombre_comercial}</>");

            $empresa->run(function () use ($aplicar) {
                $this->revisarSalon($aplicar);
            });

            $this->newLine();
        }

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------

    protected function revisarSalon(bool $aplicar): void
    {
        $deFabrica = Permisos::perfilesDeFabrica();
        $sueltos   = array_filter((array) $this->option('permiso'));
        $aTodos    = (bool) $this->option('todos');

        foreach (Perfil::all() as $perfil) {
            $actuales = $perfil->permisos ?? [];
            $nuevos   = $actuales;

            /**
             * Perfiles de sistema: se realinean con el catalogo.
             *
             * Solo se AÑADE lo que falta. Quitar permisos aqui seria
             * peligroso: quien haya ajustado un perfil a mano tiene sus
             * motivos, y este comando no puede saberlos.
             */
            $clave = $this->claveDeFabrica($perfil, $deFabrica);

            if ($clave !== null) {
                $nuevos = array_merge($nuevos, $deFabrica[$clave]['permisos']);
            }

            // Permisos pedidos a mano por linea de ordenes
            if ($sueltos !== [] && ($aTodos || $clave !== null)) {
                $nuevos = array_merge($nuevos, $sueltos);
            }

            $nuevos = array_values(array_unique(array_filter(
                $nuevos,
                fn ($permiso) => Permisos::existe($permiso)
            )));

            $faltan = array_values(array_diff($nuevos, $actuales));

            if ($faltan === []) {
                $this->line("  <fg=gray>{$perfil->nombre}: al dia (" . count($actuales) . ' permisos)</>');

                continue;
            }

            $this->line("  <fg=yellow>{$perfil->nombre}: faltan " . count($faltan) . '</> · '
                . implode(', ', $faltan));

            if (! $aplicar) {
                continue;
            }

            $perfil->permisos = $nuevos;
            $perfil->save();

            $this->line("    <fg=green>añadidos</>");
        }
    }

    /**
     * Empareja un perfil con el de fabrica del que salio.
     *
     * Se mira el nombre porque es lo unico que tenemos: al sembrar no se
     * guarda de que plantilla vino. Solo cuenta si es_sistema, para no
     * tocar un perfil a medida que alguien haya llamado «Recepcion».
     */
    protected function claveDeFabrica(Perfil $perfil, array $deFabrica): ?string
    {
        if (! $perfil->es_sistema) {
            return null;
        }

        $nombre = $this->normalizar($perfil->nombre);

        foreach ($deFabrica as $clave => $datos) {
            if ($this->normalizar($datos['nombre']) === $nombre
                || $this->normalizar($clave) === $nombre) {
                return $clave;
            }
        }

        return null;
    }

    /** Sin tildes, en minusculas: «Recepción» y «Recepcion» son lo mismo. */
    protected function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));

        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);
    }
}
