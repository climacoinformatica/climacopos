<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Support\Permisos;
use Database\Seeders\Tenant\PerfilesSeeder;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

class PermisosUsuarioTest extends TestCase
{
    protected ?Empresa $empresa = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'slug'             => 'test-perm-' . uniqid(),
            'nombre_comercial' => 'Salon Permisos',
            'email'            => 'p@test.local',
        ]);

        tenancy()->initialize($this->empresa);
        (new PerfilesSeeder())->run();
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

    protected function crearUsuario(string $perfilClave, array $extra = []): Usuario
    {
        return Usuario::create(array_merge([
            'nombre'    => 'Empleado ' . $perfilClave,
            'pin'       => '1234',
            'password'  => 'secreto123',
            'perfil_id' => Perfil::where('clave', $perfilClave)->value('id'),
            'estado'    => 'ACTIVO',
        ], $extra));
    }

    public function test_se_siembran_los_cinco_perfiles_de_fabrica(): void
    {
        $this->assertSame(5, Perfil::count());
        $this->assertTrue(Perfil::where('clave', 'propietario')->value('es_sistema'));
    }

    public function test_el_propietario_tiene_todos_los_permisos(): void
    {
        $usuario = $this->crearUsuario('propietario');

        foreach (Permisos::todos() as $permiso) {
            $this->assertTrue($usuario->tienePermiso($permiso), "Falta {$permiso}");
        }
    }

    public function test_el_profesional_no_puede_entrar_en_ajustes_ni_cerrar_caja(): void
    {
        $usuario = $this->crearUsuario('profesional');

        $this->assertTrue($usuario->tienePermiso(Permisos::TPV_VENDER));
        $this->assertFalse($usuario->tienePermiso(Permisos::AJUSTES_ACCESO));
        $this->assertFalse($usuario->tienePermiso(Permisos::CAJA_CIERRE));
        $this->assertFalse($usuario->tienePermiso(Permisos::INFORMES_VER));
    }

    public function test_un_usuario_inactivo_pierde_todos_los_permisos(): void
    {
        $usuario = $this->crearUsuario('propietario', ['estado' => 'INACTIVO']);

        $this->assertFalse($usuario->tienePermiso(Permisos::TPV_VENDER));
    }

    // ------------------------------------------------------------------
    // Formacion
    // ------------------------------------------------------------------

    public function test_el_empleado_en_formacion_solo_cobra_en_efectivo(): void
    {
        $usuario = $this->crearUsuario('formacion', ['en_formacion' => true]);

        $this->assertSame(['EFECTIVO'], $usuario->mediosPagoPermitidos());
        $this->assertTrue($usuario->puedeCobrarCon('EFECTIVO'));
        $this->assertFalse($usuario->puedeCobrarCon('TARJETA'));
        $this->assertFalse($usuario->puedeCobrarCon('BIZUM'));
    }

    public function test_un_empleado_normal_puede_usar_todos_los_medios(): void
    {
        $usuario = $this->crearUsuario('recepcion');

        $this->assertTrue($usuario->puedeCobrarCon('TARJETA'));
        $this->assertTrue($usuario->puedeCobrarCon('BIZUM'));
    }

    // ------------------------------------------------------------------
    // PIN
    // ------------------------------------------------------------------

    public function test_el_pin_correcto_entra_y_el_incorrecto_no(): void
    {
        $usuario = $this->crearUsuario('recepcion');

        $this->assertFalse($usuario->comprobarPin('0000'));
        $this->assertTrue($usuario->fresh()->comprobarPin('1234'));
    }

    public function test_el_pin_se_bloquea_tras_cinco_fallos(): void
    {
        $usuario = $this->crearUsuario('recepcion');

        for ($i = 0; $i < Usuario::MAX_INTENTOS_PIN; $i++) {
            $usuario->fresh()->comprobarPin('0000');
        }

        $usuario = $usuario->fresh();

        $this->assertTrue($usuario->pinBloqueado());
        $this->assertFalse($usuario->comprobarPin('1234'),
            'El PIN correcto no debe funcionar mientras esta bloqueado');
    }

    public function test_el_pin_no_se_guarda_en_claro(): void
    {
        $usuario = $this->crearUsuario('recepcion');

        $this->assertNotSame('1234', $usuario->getAttributes()['pin']);
        $this->assertStringStartsWith('$2y$', $usuario->getAttributes()['pin']);
    }

    // ------------------------------------------------------------------
    // Escalada a contrasena (opcion C)
    // ------------------------------------------------------------------

    public function test_los_permisos_sensibles_exigen_contrasena(): void
    {
        $this->assertTrue(Permisos::exigePassword(Permisos::CAJA_CIERRE));
        $this->assertTrue(Permisos::exigePassword(Permisos::AJUSTES_ACCESO));
        $this->assertTrue(Permisos::exigePassword(Permisos::TPV_ANULAR_TICKET));

        $this->assertFalse(Permisos::exigePassword(Permisos::TPV_VENDER));
        $this->assertFalse(Permisos::exigePassword(Permisos::CLIENTES_EDITAR));
    }

    public function test_la_contrasena_se_comprueba_correctamente(): void
    {
        $usuario = $this->crearUsuario('propietario');

        $this->assertTrue($usuario->comprobarPassword('secreto123'));
        $this->assertFalse($usuario->comprobarPassword('otra'));
    }

    public function test_no_se_admiten_permisos_inventados_en_un_perfil(): void
    {
        $perfil = Perfil::create([
            'clave'    => 'inventado',
            'nombre'   => 'Inventado',
            'permisos' => [Permisos::TPV_VENDER, 'permiso.que.no.existe'],
        ]);

        $this->assertSame([Permisos::TPV_VENDER], $perfil->fresh()->permisos);
    }
}
