<?php

namespace Tests\Feature;

use App\Models\Cuenta;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Usuario;
use App\Services\GestorAltas;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class AltaSalonTest extends TestCase
{
    protected GestorAltas $gestor;
    protected Cuenta $cuenta;
    protected array $creadas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestor = new GestorAltas();

        $this->cuenta = Cuenta::create([
            'nombre'   => 'Marta López',
            'email'    => 'marta-' . uniqid() . '@ejemplo.test',
            'password' => Hash::make('secreto123'),
            'empresa'  => 'Peluquería Marta',
            'email_verified_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // Se limpia lo que haya creado cada prueba
        foreach ($this->creadas as $empresa) {
            try {
                (new DeleteDatabase($empresa))->handle();
            } catch (\Throwable) {
            }

            try {
                $empresa->domains()->delete();
                $empresa->forceDelete();
            } catch (\Throwable) {
            }
        }

        $this->cuenta?->delete();

        parent::tearDown();
    }

    protected function crear(string $slug, string $nombre = 'Peluquería Marta'): array
    {
        $resultado = $this->gestor->crear($this->cuenta, $slug, $nombre);

        $this->creadas[] = $resultado['empresa'];

        return $resultado;
    }

    // ------------------------------------------------------------------
    // Comprobación del subdominio
    // ------------------------------------------------------------------

    public function test_un_subdominio_normal_vale(): void
    {
        $this->assertTrue($this->gestor->comprobarSlug('peluqueria-marta')['ok']);
    }

    public function test_hacen_falta_tres_letras(): void
    {
        $this->assertFalse($this->gestor->comprobarSlug('ab')['ok']);
    }

    public function test_no_se_admiten_tildes_ni_enes(): void
    {
        $this->assertFalse($this->gestor->comprobarSlug('peluquería')['ok']);
        $this->assertFalse($this->gestor->comprobarSlug('la-niña')['ok']);

        // Sin tilde sí vale: es lo que se propone al normalizar
        $this->assertTrue($this->gestor->comprobarSlug('la-nina')['ok']);
    }

    public function test_no_puede_empezar_ni_acabar_en_guion(): void
    {
        $this->assertFalse($this->gestor->comprobarSlug('-marta')['ok']);
        $this->assertFalse($this->gestor->comprobarSlug('marta-')['ok']);
    }

    public function test_los_subdominios_reservados_se_rechazan(): void
    {
        foreach (['www', 'admin', 'api', 'mail'] as $reservado) {
            $resultado = $this->gestor->comprobarSlug($reservado);

            $this->assertFalse($resultado['ok'], "«{$reservado}» no debería admitirse");
        }
    }

    public function test_se_sugiere_una_alternativa_cuando_esta_ocupado(): void
    {
        $resultado = $this->gestor->comprobarSlug('admin');

        $this->assertNotNull($resultado['sugerencia'],
            'Dejar al cliente sin alternativa es dejarlo en un callejón');
    }

    public function test_el_nombre_del_salon_se_convierte_en_subdominio(): void
    {
        $this->assertSame('peluqueria-marta',
            $this->gestor->proponerSlug('Peluquería Marta'));
    }

    // ------------------------------------------------------------------
    // Alta completa
    // ------------------------------------------------------------------

    public function test_el_alta_crea_la_empresa_y_su_dominio(): void
    {
        $slug = 'test-alta-' . substr(uniqid(), -6);

        $resultado = $this->crear($slug);

        $empresa = $resultado['empresa'];

        $this->assertSame($slug, $empresa->slug);
        $this->assertSame($this->cuenta->id, $empresa->cuenta_id);
        $this->assertSame(1, $empresa->domains()->count());
    }

    public function test_el_salon_nace_sin_configurar(): void
    {
        $resultado = $this->crear('test-conf-' . substr(uniqid(), -6));

        $this->assertNull($resultado['empresa']->configurada_en,
            'Un salón sin datos fiscales no puede facturar: el asistente es obligatorio');
    }

    public function test_se_crea_el_usuario_propietario_dentro_del_salon(): void
    {
        $resultado = $this->crear('test-prop-' . substr(uniqid(), -6));

        $resultado['empresa']->run(function () {
            $this->assertSame(1, Usuario::count());

            $propietario = Usuario::first();

            $this->assertSame('Marta López', $propietario->nombre);
            $this->assertSame('propietario', $propietario->perfil->clave);
        });
    }

    public function test_las_credenciales_se_devuelven_una_vez(): void
    {
        $resultado = $this->crear('test-cred-' . substr(uniqid(), -6));

        $this->assertMatchesRegularExpression('/^\d{4}$/', $resultado['pin']);
        $this->assertSame(12, strlen($resultado['password']));

        // Y en la base están cifradas
        $resultado['empresa']->run(function () use ($resultado) {
            $propietario = Usuario::first();

            $this->assertNotSame($resultado['pin'], $propietario->pin);
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check(
                $resultado['pin'], $propietario->pin
            ));
        });
    }

    public function test_el_salon_arranca_con_su_catalogo_sembrado(): void
    {
        $resultado = $this->crear('test-seed-' . substr(uniqid(), -6));

        $resultado['empresa']->run(function () {
            $this->assertGreaterThan(0, \App\Models\Perfil::count(),
                'Sin perfiles no se puede crear ningún usuario');
        });
    }

    // ------------------------------------------------------------------
    // Rechazos
    // ------------------------------------------------------------------

    public function test_no_se_crea_con_un_subdominio_ocupado(): void
    {
        $slug = 'test-dup-' . substr(uniqid(), -6);

        $this->crear($slug);

        $this->expectException(\RuntimeException::class);

        $this->gestor->crear($this->cuenta, $slug, 'Otro salón');
    }

    public function test_hace_falta_tener_el_correo_verificado(): void
    {
        $this->cuenta->update(['email_verified_at' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Confirma tu correo');

        $this->gestor->crear($this->cuenta->fresh(),
            'test-noverif-' . substr(uniqid(), -6), 'Salón');
    }

    public function test_un_subdominio_reservado_no_llega_a_crear_nada(): void
    {
        $antes = Empresa::count();

        try {
            $this->gestor->crear($this->cuenta, 'admin', 'Salón');
        } catch (\RuntimeException) {
        }

        $this->assertSame($antes, Empresa::count());
    }

    // ------------------------------------------------------------------
    // Configuración
    // ------------------------------------------------------------------

    public function test_marcar_configurada_deja_el_salon_listo(): void
    {
        $resultado = $this->crear('test-listo-' . substr(uniqid(), -6));

        $this->gestor->marcarConfigurada($resultado['empresa']);

        $this->assertNotNull($resultado['empresa']->fresh()->configurada_en);
    }
}
