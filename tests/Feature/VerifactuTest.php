<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\Perfil;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Models\VerifactuRegistro;
use App\Services\GestorTickets;
use App\Services\Verifactu\GeneradorXml;
use App\Services\Verifactu\GestorVerifactu;
use App\Services\Verifactu\HuellaVerifactu;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class VerifactuTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected GestorTickets $tpv;
    protected GestorVerifactu $verifactu;
    protected Usuario $marta;
    protected Usuario $aprendiz;
    protected Articulo $corte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-vf-' . uniqid(),
            'nombre_comercial' => 'Salón Fiscal',
            'razon_social'     => 'Salón Fiscal SL',
            'nif'              => 'B76543210',
            'email'            => 'vf@test.local',
            'regimen_fiscal'   => 'IGIC',
            'verifactu_activo' => true,
        ]);

        tenancy()->initialize($this->empresa);
        (new ConfigSeeder())->run();
        (new PerfilesSeeder())->run();

        $this->tpv = new GestorTickets();
        $this->verifactu = new GestorVerifactu();

        $this->marta = Usuario::create([
            'nombre'    => 'Marta',
            'perfil_id' => Perfil::where('clave', 'encargado')->value('id'),
            'pin'       => '1234',
        ]);

        $this->aprendiz = Usuario::create([
            'nombre'       => 'Aprendiz',
            'perfil_id'    => Perfil::where('clave', 'formacion')->value('id'),
            'en_formacion' => true,
            'pin'          => '5678',
        ]);

        $familia = Familia::create(['nombre' => 'Corte', 'tipo' => 'SERVICIO']);

        $this->corte = Articulo::create([
            'familia_id'   => $familia->id,
            'tipo'         => 'SERVICIO',
            'nombre'       => 'Corte',
            'precio'       => 22.00,
            'impuesto_pct' => 7.00,
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if ($this->empresa) {
            try {
                (new DeleteDatabase($this->empresa))->handle();
            } catch (\Throwable) {
            }

            $this->empresa->domains()->delete();
            $this->empresa->forceDelete();
        }

        parent::tearDown();
    }

    protected function vender(?Usuario $usuario = null): Ticket
    {
        $usuario ??= $this->marta;

        $ticket = $this->tpv->abrir($usuario);
        $this->tpv->anadirLinea($ticket, $this->corte);
        $this->tpv->cobrar($ticket, 'EFECTIVO', 22.00);

        return $ticket->fresh();
    }

    // ------------------------------------------------------------------
    // La huella
    // ------------------------------------------------------------------

    public function test_la_huella_es_sha256_en_mayusculas(): void
    {
        $huella = HuellaVerifactu::alta([
            'nif_emisor'       => 'B76543210',
            'serie_numero'     => 'A-000001',
            'fecha_expedicion' => '23-08-2026',
            'tipo_factura'     => 'F2',
            'cuota'            => 1.44,
            'total'            => 22.00,
            'huella_anterior'  => '',
            'fecha_hora_huso'  => '2026-08-23T14:30:15+01:00',
        ]);

        $this->assertSame(64, strlen($huella));
        $this->assertSame(strtoupper($huella), $huella,
            'En minúsculas la AEAT rechaza el registro');
        $this->assertMatchesRegularExpression('/^[0-9A-F]{64}$/', $huella);
    }

    public function test_la_misma_entrada_da_siempre_la_misma_huella(): void
    {
        $datos = [
            'nif_emisor'       => 'B76543210',
            'serie_numero'     => 'A-000001',
            'fecha_expedicion' => '23-08-2026',
            'tipo_factura'     => 'F2',
            'cuota'            => 1.44,
            'total'            => 22.00,
            'huella_anterior'  => '',
            'fecha_hora_huso'  => '2026-08-23T14:30:15+01:00',
        ];

        $this->assertSame(
            HuellaVerifactu::alta($datos),
            HuellaVerifactu::alta($datos),
        );
    }

    public function test_cambiar_un_solo_centimo_cambia_la_huella(): void
    {
        $base = [
            'nif_emisor'       => 'B76543210',
            'serie_numero'     => 'A-000001',
            'fecha_expedicion' => '23-08-2026',
            'tipo_factura'     => 'F2',
            'cuota'            => 1.44,
            'total'            => 22.00,
            'huella_anterior'  => '',
            'fecha_hora_huso'  => '2026-08-23T14:30:15+01:00',
        ];

        $manipulado = array_merge($base, ['total' => 22.01]);

        $this->assertNotSame(
            HuellaVerifactu::alta($base),
            HuellaVerifactu::alta($manipulado),
            'Si un céntimo no cambiara la huella, el sistema no serviría de nada'
        );
    }

    public function test_los_importes_llevan_dos_decimales_y_punto(): void
    {
        // 22 y 22.00 deben producir la misma huella
        $a = HuellaVerifactu::alta([
            'nif_emisor' => 'B1', 'serie_numero' => 'A-1', 'fecha_expedicion' => '01-01-2026',
            'tipo_factura' => 'F2', 'cuota' => 0, 'total' => 22,
            'huella_anterior' => '', 'fecha_hora_huso' => '2026-01-01T10:00:00+01:00',
        ]);

        $b = HuellaVerifactu::alta([
            'nif_emisor' => 'B1', 'serie_numero' => 'A-1', 'fecha_expedicion' => '01-01-2026',
            'tipo_factura' => 'F2', 'cuota' => 0.00, 'total' => 22.00,
            'huella_anterior' => '', 'fecha_hora_huso' => '2026-01-01T10:00:00+01:00',
        ]);

        $this->assertSame($a, $b);
    }

    public function test_la_marca_temporal_lleva_huso_horario(): void
    {
        $marca = HuellaVerifactu::marcaTemporal();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $marca,
        );
    }

    // ------------------------------------------------------------------
    // La cadena
    // ------------------------------------------------------------------

    public function test_el_primer_registro_no_tiene_huella_anterior(): void
    {
        $ticket = $this->vender();
        $registro = $this->verifactu->alta($ticket);

        $this->assertNull($registro->huella_anterior);
        $this->assertNotEmpty($registro->huella);
    }

    public function test_cada_registro_encadena_con_el_anterior(): void
    {
        $primero = $this->verifactu->alta($this->vender());
        $segundo = $this->verifactu->alta($this->vender());
        $tercero = $this->verifactu->alta($this->vender());

        $this->assertNull($primero->huella_anterior);
        $this->assertSame($primero->huella, $segundo->huella_anterior);
        $this->assertSame($segundo->huella, $tercero->huella_anterior);
    }

    public function test_la_cadena_completa_se_verifica_correcta(): void
    {
        foreach (range(1, 5) as $i) {
            $this->verifactu->alta($this->vender());
        }

        $resultado = HuellaVerifactu::verificarCadena();

        $this->assertTrue($resultado['integra']);
        $this->assertSame(5, $resultado['revisados']);
        $this->assertNull($resultado['roto_en']);
    }

    public function test_manipular_la_base_de_datos_rompe_la_cadena(): void
    {
        $this->verifactu->alta($this->vender());
        $segundo = $this->verifactu->alta($this->vender());
        $this->verifactu->alta($this->vender());

        // Alguien cambia un importe directamente en la base de datos,
        // saltándose el modelo. Es el escenario que el reglamento persigue.
        \Illuminate\Support\Facades\DB::table('verifactu_registros')
            ->where('id', $segundo->id)
            ->update(['total' => 10.00]);

        $resultado = HuellaVerifactu::verificarCadena();

        $this->assertFalse($resultado['integra'],
            'Modificar un importe tiene que ser detectable');
        $this->assertSame($segundo->id, $resultado['roto_en']);
    }

    // ------------------------------------------------------------------
    // Inmutabilidad
    // ------------------------------------------------------------------

    public function test_no_se_puede_modificar_un_importe_del_registro(): void
    {
        $registro = $this->verifactu->alta($this->vender());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no se puede modificar');

        $registro->update(['total' => 99.00]);
    }

    public function test_no_se_puede_modificar_la_huella(): void
    {
        $registro = $this->verifactu->alta($this->vender());

        $this->expectException(\RuntimeException::class);

        $registro->update(['huella' => str_repeat('A', 64)]);
    }

    public function test_no_se_puede_borrar_un_registro(): void
    {
        $registro = $this->verifactu->alta($this->vender());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no se pueden borrar');

        $registro->delete();
    }

    public function test_si_se_puede_actualizar_el_estado_de_envio(): void
    {
        $registro = $this->verifactu->alta($this->vender());

        $registro->update(['estado' => 'ACEPTADO', 'csv_aeat' => 'ABC123']);

        $this->assertSame('ACEPTADO', $registro->fresh()->estado);
    }

    // ------------------------------------------------------------------
    // Formación: nunca entra
    // ------------------------------------------------------------------

    public function test_la_formacion_no_genera_registro_fiscal(): void
    {
        $practica = $this->tpv->abrir($this->aprendiz);
        $this->tpv->anadirLinea($practica, $this->corte);
        $this->tpv->cobrar($practica, 'EFECTIVO', 22.00);

        $ticket = Ticket::soloFormacion()->find($practica->id);
        $registro = $this->verifactu->alta($ticket);

        $this->assertNull($registro,
            'Declarar prácticas sería comunicar a la AEAT ventas que no existen');
        $this->assertSame(0, VerifactuRegistro::count());
    }

    public function test_la_formacion_no_interrumpe_la_cadena(): void
    {
        $primero = $this->verifactu->alta($this->vender());

        // Prácticas por medio
        $practica = $this->tpv->abrir($this->aprendiz);
        $this->tpv->anadirLinea($practica, $this->corte);
        $this->tpv->cobrar($practica, 'EFECTIVO', 22.00);
        $this->verifactu->alta(Ticket::soloFormacion()->find($practica->id));

        $segundo = $this->verifactu->alta($this->vender());

        $this->assertSame($primero->huella, $segundo->huella_anterior,
            'La cadena solo la forman facturas reales');
        $this->assertSame(2, VerifactuRegistro::count());
    }

    // ------------------------------------------------------------------
    // Anulaciones
    // ------------------------------------------------------------------

    public function test_anular_genera_un_registro_de_anulacion(): void
    {
        $ticket = $this->vender();
        $alta = $this->verifactu->alta($ticket);

        $anulacion = $this->verifactu->anulacion($ticket->fresh());

        $this->assertNotNull($anulacion);
        $this->assertSame('ANULACION', $anulacion->tipo);
        $this->assertSame($alta->serie_numero, $anulacion->serie_numero);
        $this->assertSame($alta->huella, $anulacion->huella_anterior,
            'La anulación encadena igual que cualquier otro registro');
    }

    public function test_no_se_anula_dos_veces(): void
    {
        $ticket = $this->vender();
        $this->verifactu->alta($ticket);

        $this->verifactu->anulacion($ticket->fresh());
        $segunda = $this->verifactu->anulacion($ticket->fresh());

        $this->assertNull($segunda);
        $this->assertSame(1, VerifactuRegistro::where('tipo', 'ANULACION')->count());
    }

    public function test_no_se_duplica_el_alta_del_mismo_ticket(): void
    {
        $ticket = $this->vender();

        $primero = $this->verifactu->alta($ticket);
        $segundo = $this->verifactu->alta($ticket->fresh());

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, VerifactuRegistro::count());
    }

    // ------------------------------------------------------------------
    // XML y QR
    // ------------------------------------------------------------------

    public function test_el_xml_es_valido_y_lleva_los_datos_clave(): void
    {
        $registro = $this->verifactu->alta($this->vender());

        $xml = (new GeneradorXml())->registro($registro);

        $documento = new \DOMDocument();
        $this->assertTrue($documento->loadXML($xml), 'El XML tiene que estar bien formado');

        $this->assertStringContainsString('B76543210', $xml);
        $this->assertStringContainsString($registro->huella, $xml);
        $this->assertStringContainsString('CLIMACO POS', $xml);
        $this->assertStringContainsString('F2', $xml);
    }

    public function test_el_primer_registro_se_marca_como_tal_en_el_xml(): void
    {
        $registro = $this->verifactu->alta($this->vender());

        $xml = (new GeneradorXml())->registro($registro);

        $this->assertStringContainsString('PrimerRegistro', $xml);
    }

    public function test_el_segundo_registro_referencia_al_anterior(): void
    {
        $this->verifactu->alta($this->vender());
        $segundo = $this->verifactu->alta($this->vender());

        $xml = (new GeneradorXml())->registro($segundo);

        $this->assertStringContainsString('RegistroAnterior', $xml);
        $this->assertStringContainsString($segundo->huella_anterior, $xml);
    }

    public function test_el_qr_lleva_nif_numero_fecha_e_importe(): void
    {
        $registro = $this->verifactu->alta($this->vender());

        $url = $registro->urlQr();

        $this->assertStringContainsString('B76543210', $url);
        $this->assertStringContainsString(urlencode($registro->serie_numero), $url);
        $this->assertStringContainsString('22.00', $url);
    }

    // ------------------------------------------------------------------
    // Desactivado
    // ------------------------------------------------------------------

    public function test_si_verifactu_esta_desactivado_no_se_genera_nada(): void
    {
        $this->empresa->forceFill(['verifactu_activo' => false])->save();
        tenancy()->end();
        tenancy()->initialize($this->empresa->fresh());

        $registro = (new GestorVerifactu())->alta($this->vender());

        $this->assertNull($registro);
    }
}
