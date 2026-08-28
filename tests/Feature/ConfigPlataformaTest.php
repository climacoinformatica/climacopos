<?php

namespace Tests\Feature;

use App\Models\ConfigPlataforma;
use App\Models\Cuenta;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class ConfigPlataformaTest extends TestCase
{
    protected function tearDown(): void
    {
        ConfigPlataforma::query()->delete();
        Cuenta::where('email', 'like', 'test-admin%')->delete();

        parent::tearDown();
    }

    public function test_un_ajuste_normal_se_guarda_en_claro(): void
    {
        ConfigPlataforma::guardar('stripe_publica', 'pk_test_123');

        $fila = ConfigPlataforma::find('stripe_publica');

        $this->assertFalse((bool) $fila->cifrado);
        $this->assertSame('pk_test_123', $fila->valor);
    }

    public function test_las_claves_secretas_se_guardan_cifradas(): void
    {
        ConfigPlataforma::guardar('stripe_secreto', 'sk_test_supersecreto');

        $fila = ConfigPlataforma::find('stripe_secreto');

        $this->assertTrue((bool) $fila->cifrado);
        $this->assertNotSame('sk_test_supersecreto', $fila->valor,
            'Un volcado de la base no debe exponer la clave');
        $this->assertSame('sk_test_supersecreto', Crypt::decryptString($fila->valor));
    }

    public function test_el_valor_cifrado_se_lee_descifrado(): void
    {
        ConfigPlataforma::guardar('stripe_webhook', 'whsec_abc123');

        $this->assertSame('whsec_abc123', ConfigPlataforma::obtener('stripe_webhook'));
    }

    public function test_una_clave_inexistente_devuelve_el_valor_por_defecto(): void
    {
        $this->assertSame('nada', ConfigPlataforma::obtener('no_existe', 'nada'));
    }

    public function test_los_numeros_se_devuelven_como_numeros(): void
    {
        ConfigPlataforma::guardar('comision_plataforma_pct', '2.5');

        $this->assertSame(2.5, ConfigPlataforma::obtener('comision_plataforma_pct'));
    }

    public function test_los_booleanos_se_devuelven_como_booleanos(): void
    {
        ConfigPlataforma::guardar('modo_pruebas', true);

        $this->assertTrue(ConfigPlataforma::obtener('modo_pruebas'));
    }

    public function test_se_puede_saber_si_hay_secreto_sin_leerlo(): void
    {
        $this->assertFalse(ConfigPlataforma::tiene('stripe_secreto'));

        ConfigPlataforma::guardar('stripe_secreto', 'sk_test_x');

        $this->assertTrue(ConfigPlataforma::tiene('stripe_secreto'));
    }

    public function test_guardar_vacio_deja_la_clave_a_nulo(): void
    {
        ConfigPlataforma::guardar('stripe_secreto', 'sk_test_x');
        ConfigPlataforma::guardar('stripe_secreto', '');

        $this->assertFalse(ConfigPlataforma::tiene('stripe_secreto'));
    }

    public function test_el_enmascarado_no_revela_la_clave(): void
    {
        $enmascarada = ConfigPlataforma::enmascarar('sk_test_1234567890abcdef');

        $this->assertStringNotContainsString('1234567890', $enmascarada);
        $this->assertStringStartsWith('sk_test', $enmascarada);
    }

    // ------------------------------------------------------------------
    // Acceso
    // ------------------------------------------------------------------

    public function test_solo_un_superadmin_puede_entrar_al_panel(): void
    {
        $normal = Cuenta::create([
            'nombre'   => 'Dueño de salón',
            'email'    => 'test-admin-normal@ejemplo.com',
            'password' => 'secreto123',
        ]);

        $this->assertFalse((bool) $normal->es_superadmin,
            'Una cuenta de salón no puede ver las claves de la plataforma');
    }

    public function test_el_superadmin_se_marca_explicitamente(): void
    {
        $admin = Cuenta::create([
            'nombre'        => 'Admin',
            'email'         => 'test-admin@ejemplo.com',
            'password'      => 'secreto123',
            'es_superadmin' => true,
        ]);

        $this->assertTrue((bool) $admin->es_superadmin);
    }
}
