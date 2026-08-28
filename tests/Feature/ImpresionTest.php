<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\ColaImpresion;
use App\Models\DisenoTicket;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\Perfil;
use App\Models\Terminal;
use App\Models\Usuario;
use App\Services\ConstructorTicket;
use App\Services\EscPos;
use App\Services\GestorImpresion;
use App\Services\GestorTickets;
use Database\Seeders\Tenant\ConfigSeeder;
use Database\Seeders\Tenant\PerfilesSeeder;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class ImpresionTest extends TestCase
{
    protected ?Empresa $empresa = null;
    protected Usuario $marta;
    protected Usuario $aprendiz;
    protected Terminal $terminal;
    protected Articulo $corte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-imp-' . uniqid(),
            'nombre_comercial' => 'Salón Impresión',
            'razon_social'     => 'Salón Impresión SL',
            'nif'              => 'B12345678',
            'email'            => 'i@test.local',
        ]);

        tenancy()->initialize($this->empresa);
        (new ConfigSeeder())->run();
        (new PerfilesSeeder())->run();

        $this->terminal = Terminal::create(['nombre' => 'Mostrador', 'codigo' => 'T001']);

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

        $familia = Familia::create(['nombre' => 'Pruebas', 'tipo' => 'SERVICIO']);

        $this->corte = Articulo::create([
            'familia_id'   => $familia->id,
            'tipo'         => 'SERVICIO',
            'nombre'       => 'Corte de señora',
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

    // ------------------------------------------------------------------
    // ESC/POS
    // ------------------------------------------------------------------

    public function test_el_flujo_empieza_inicializando_la_impresora(): void
    {
        $bytes = (new EscPos())->inicializar()->linea('Hola')->salida();

        $this->assertStringStartsWith("\x1B@", $bytes,
            'Sin ESC @ el trabajo hereda los estilos del anterior');
    }

    public function test_la_alineacion_se_emite_antes_del_texto(): void
    {
        $bytes = (new EscPos())->centrar()->linea('CENTRADO')->salida();

        $posicionComando = strpos($bytes, "\x1Ba\x01");
        $posicionTexto   = strpos($bytes, 'CENTRADO');

        $this->assertNotFalse($posicionComando);
        $this->assertLessThan($posicionTexto, $posicionComando,
            'Emitir la alineación después del texto no afecta a la línea ya impresa');
    }

    public function test_las_filas_de_dos_columnas_ocupan_el_ancho_exacto(): void
    {
        $esc = new EscPos(48);
        $bytes = $esc->fila('Base imponible', '20,56 E')->salida();

        $linea = rtrim($bytes, "\n");

        $this->assertSame(48, strlen($linea));
        $this->assertStringEndsWith('20,56 E', $linea);
    }

    public function test_los_textos_largos_se_recortan_sin_desbordar(): void
    {
        $esc = new EscPos(32);
        $linea = rtrim($esc->fila(str_repeat('A', 60), '9,99')->salida(), "\n");

        $this->assertSame(32, strlen($linea));
    }

    public function test_los_acentos_se_convierten_a_la_pagina_de_codigos(): void
    {
        $bytes = (new EscPos())->texto('Peluquería')->salida();

        $this->assertStringNotContainsString('Ã', $bytes,
            'Sin conversión, «í» sale como dos bytes basura');
        $this->assertSame(10, strlen($bytes), 'Debe ocupar 10 bytes, uno por carácter');
    }

    public function test_el_cajon_usa_el_pin_indicado(): void
    {
        $pin2 = (new EscPos())->abrirCajon(2)->salida();
        $pin5 = (new EscPos())->abrirCajon(5)->salida();

        $this->assertSame("\x1Bp\x00", substr($pin2, 0, 3));
        $this->assertSame("\x1Bp\x01", substr($pin5, 0, 3));
    }

    public function test_el_qr_lleva_la_longitud_correcta(): void
    {
        $datos = 'https://ejemplo.com';
        $bytes = (new EscPos())->qr($datos)->salida();

        $this->assertStringContainsString($datos, $bytes);
        $this->assertStringContainsString("\x1D(k", $bytes);
    }

    public function test_el_ancho_de_papel_determina_las_columnas(): void
    {
        $this->assertSame(48, EscPos::columnasPara(80));
        $this->assertSame(32, EscPos::columnasPara(58));
    }

    // ------------------------------------------------------------------
    // Ticket completo
    // ------------------------------------------------------------------

    public function test_el_ticket_incluye_los_datos_fiscales_y_el_total(): void
    {
        $gestor = new GestorTickets();
        $ticket = $gestor->abrir($this->marta);
        $gestor->anadirLinea($ticket, $this->corte);
        $gestor->cobrar($ticket, 'EFECTIVO', 22.00, entregado: 50.00);

        $bytes = (new ConstructorTicket())->ticket($ticket->fresh(['lineas', 'cobros']));

        $this->assertStringContainsString('B12345678', $bytes);
        $this->assertStringContainsString('TOTAL', $bytes);
        $this->assertStringContainsString('22,00', $bytes);
        $this->assertStringContainsString('28,00', $bytes, 'Debe imprimir el cambio');
    }

    public function test_el_ticket_de_formacion_avisa_de_que_no_tiene_valor_fiscal(): void
    {
        $gestor = new GestorTickets();
        $ticket = $gestor->abrir($this->aprendiz);
        $gestor->anadirLinea($ticket, $this->corte);
        $gestor->cobrar($ticket, 'EFECTIVO', 22.00);

        $ticket = \App\Models\Ticket::soloFormacion()->with(['lineas', 'cobros'])->find($ticket->id);

        $bytes = (new ConstructorTicket())->ticket($ticket);

        $this->assertStringContainsString('SIN VALOR FISCAL', $bytes);
        $this->assertStringContainsString('FORMACION', $bytes);
    }

    public function test_el_ticket_de_formacion_no_abre_el_cajon(): void
    {
        $gestor = new GestorTickets();
        $ticket = $gestor->abrir($this->aprendiz);
        $gestor->anadirLinea($ticket, $this->corte);
        $gestor->cobrar($ticket, 'EFECTIVO', 22.00);

        $ticket = \App\Models\Ticket::soloFormacion()->with(['lineas', 'cobros'])->find($ticket->id);

        $bytes = (new ConstructorTicket())->ticket($ticket);

        $this->assertStringNotContainsString("\x1Bp", $bytes,
            'Un cobro de prácticas no debe abrir el cajón del dinero real');
    }

    public function test_el_cobro_con_tarjeta_no_abre_el_cajon(): void
    {
        $gestor = new GestorTickets();
        $ticket = $gestor->abrir($this->marta);
        $gestor->anadirLinea($ticket, $this->corte);
        $gestor->cobrar($ticket, 'TARJETA', 22.00);

        $bytes = (new ConstructorTicket())->ticket($ticket->fresh(['lineas', 'cobros']));

        $this->assertStringNotContainsString("\x1Bp", $bytes);
    }

    // ------------------------------------------------------------------
    // Cola
    // ------------------------------------------------------------------

    public function test_el_trabajo_se_encola_para_el_terminal_correcto(): void
    {
        $trabajo = (new GestorImpresion())->prueba($this->terminal);

        $this->assertSame($this->terminal->id, $trabajo->terminal_id);
        $this->assertSame('PENDIENTE', $trabajo->estado);
        $this->assertNotEmpty($trabajo->payload);
    }

    public function test_el_agente_solo_ve_los_trabajos_de_su_terminal(): void
    {
        $otro = Terminal::create(['nombre' => 'Cabina', 'codigo' => 'T002']);

        $gestor = new GestorImpresion();
        $gestor->prueba($this->terminal);
        $gestor->prueba($otro);

        $this->assertCount(1, ColaImpresion::paraAgente($this->terminal->id)->get());
        $this->assertCount(1, ColaImpresion::paraAgente($otro->id)->get());
    }

    public function test_un_trabajo_recogido_y_sin_confirmar_se_reintenta(): void
    {
        $trabajo = (new GestorImpresion())->prueba($this->terminal);
        $trabajo->marcarRecogido();

        // Recién recogido, no se vuelve a ofrecer
        $this->assertCount(0, ColaImpresion::paraAgente($this->terminal->id)->get());

        // Si el PC se apagó justo después, hay que reintentarlo
        $trabajo->forceFill(['recogido_en' => now()->subMinutes(5)])->save();

        $this->assertCount(1, ColaImpresion::paraAgente($this->terminal->id)->get(),
            'Un trabajo atascado debe volver a la cola: el ticket no llegó a imprimirse');
    }

    public function test_tras_varios_fallos_el_trabajo_deja_de_reintentarse(): void
    {
        $trabajo = (new GestorImpresion())->prueba($this->terminal);

        for ($i = 0; $i < ColaImpresion::MAX_INTENTOS; $i++) {
            $trabajo->marcarRecogido();
            $trabajo->marcarError('Impresora apagada');
        }

        $this->assertSame('ERROR', $trabajo->fresh()->estado);
        $this->assertCount(0, ColaImpresion::paraAgente($this->terminal->id)->get(),
            'No tiene sentido reintentar indefinidamente contra una impresora rota');
    }

    public function test_los_trabajos_completados_se_pueden_purgar(): void
    {
        $trabajo = (new GestorImpresion())->prueba($this->terminal);
        $trabajo->marcarRecogido();
        $trabajo->marcarHecho();

        $this->assertSame(1, ColaImpresion::purgar(0));
        $this->assertSame(0, ColaImpresion::count());
    }

    // ------------------------------------------------------------------
    // Diseño
    // ------------------------------------------------------------------

    public function test_hay_un_diseno_por_defecto_si_la_empresa_no_configura_ninguno(): void
    {
        $diseno = DisenoTicket::activo();

        $this->assertNotNull($diseno);
        $this->assertTrue($diseno->activo);
        $this->assertSame(48, $diseno->columnas);
    }

    public function test_activar_un_diseno_desactiva_los_demas(): void
    {
        $primero = DisenoTicket::activo();
        $segundo = DisenoTicket::create(['nombre' => 'Navidad', 'columnas' => 48]);

        $segundo->marcarActivo();

        $this->assertFalse($primero->fresh()->activo);
        $this->assertTrue($segundo->fresh()->activo);
        $this->assertSame($segundo->id, DisenoTicket::activo()->id);
    }

    public function test_la_cabecera_configurada_aparece_en_el_ticket(): void
    {
        $diseno = DisenoTicket::activo();
        $diseno->update([
            'cabecera' => [
                ['texto' => 'MI SALON DE PRUEBA', 'alineacion' => 'CENTRO', 'negrita' => true],
            ],
        ]);

        $gestor = new GestorTickets();
        $ticket = $gestor->abrir($this->marta);
        $gestor->anadirLinea($ticket, $this->corte);

        $bytes = (new ConstructorTicket($diseno))->ticket($ticket->fresh(['lineas', 'cobros']));

        $this->assertStringContainsString('MI SALON DE PRUEBA', $bytes);
    }

    public function test_el_ancho_de_58mm_reduce_las_columnas(): void
    {
        $diseno = DisenoTicket::activo();
        $diseno->update(['ancho_mm' => 58, 'columnas' => 32]);

        $gestor = new GestorTickets();
        $ticket = $gestor->abrir($this->marta);
        $gestor->anadirLinea($ticket, $this->corte);

        $bytes = (new ConstructorTicket($diseno))->ticket($ticket->fresh(['lineas', 'cobros']));

        // Los separadores deben medir 32, no 48
        $this->assertStringContainsString(str_repeat('-', 32), $bytes);
        $this->assertStringNotContainsString(str_repeat('-', 48), $bytes);
    }
}
