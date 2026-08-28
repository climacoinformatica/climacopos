<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisenoTicket extends Model
{
    protected $table = 'ticket_disenos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'activo'                    => 'boolean',
            'cabecera'                  => 'array',
            'pie'                       => 'array',
            'mostrar_qr_verifactu'      => 'boolean',
            'mostrar_qr_reserva'        => 'boolean',
            'mostrar_cliente'           => 'boolean',
            'mostrar_profesional'       => 'boolean',
            'mostrar_desglose_impuesto' => 'boolean',
            'cortar_papel'              => 'boolean',
            'abrir_cajon_efectivo'      => 'boolean',
        ];
    }

    /** Diseño activo, o uno por defecto si la empresa no ha configurado ninguno. */
    public static function activo(): self
    {
        return static::where('activo', true)->first()
            ?? static::first()
            ?? static::porDefecto();
    }

    public static function porDefecto(): self
    {
        return static::create([
            'nombre'   => 'Estándar',
            'activo'   => true,
            'ancho_mm' => 80,
            'columnas' => 48,
            'cabecera' => [
                ['texto' => tenant('nombre_comercial') ?? 'Mi salón',
                 'alineacion' => 'CENTRO', 'negrita' => true, 'doble_alto' => true, 'doble_ancho' => false],
            ],
            'pie' => [
                ['texto' => '¡Gracias por tu visita!',
                 'alineacion' => 'CENTRO', 'negrita' => true, 'doble_alto' => false, 'doble_ancho' => false],
            ],
            'texto_legal' => 'Conserva este ticket para cualquier reclamación.',
        ]);
    }

    public function marcarActivo(): void
    {
        static::where('id', '!=', $this->id)->update(['activo' => false]);

        $this->forceFill(['activo' => true])->save();
    }

    /** Vista previa en texto, para el editor del panel. */
    public function previsualizar(): array
    {
        $lineas = [];

        foreach ($this->cabecera ?? [] as $fila) {
            $lineas[] = $this->alinearTexto($fila['texto'] ?? '', $fila['alineacion'] ?? 'CENTRO');
        }

        return $lineas;
    }

    protected function alinearTexto(string $texto, string $alineacion): string
    {
        $ancho = $this->columnas;

        return match (strtoupper($alineacion)) {
            'CENTRO'  => str_pad($texto, $ancho, ' ', STR_PAD_BOTH),
            'DERECHA' => str_pad($texto, $ancho, ' ', STR_PAD_LEFT),
            default   => $texto,
        };
    }
}
