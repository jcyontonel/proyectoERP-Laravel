<?php

namespace Tests\Feature;

use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Plataforma\Models\Plan;
use App\Modulos\Plataforma\Services\SuscripcionService;
use App\Modulos\Web\Models\Tienda;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** La puerta de entrada de cada módulo responde (también con la app en una subcarpeta, como en Apache) */
class EntradasModulosTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private const BASE = 'http://localhost/proyectoERP-Laravel/public';

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->seed(PlanSeeder::class);
        $this->empresa = $this->crearEmpresa(['nombre_comercial' => 'MasNorte']);
        $this->admin = $this->usuarioCon('Administrador', $this->empresa);
        $this->withServerVariables(['SCRIPT_NAME' => '/proyectoERP-Laravel/public/index.php', 'SCRIPT_FILENAME' => base_path('public/index.php')]);
    }

    public function test_portal_de_suscripciones_con_todas_las_empresas_que_administra(): void
    {
        $otra = $this->crearEmpresa(['nombre_comercial' => 'Botica Sur']);
        $this->admin->empresas()->attach($otra->id);
        $this->comoUsuario($this->admin, $otra)->assignRole('Administrador');
        setPermissionsTeamId(null);
        \App\Modulos\Nucleo\Support\EmpresaActual::olvidar();
        $ajena = $this->crearEmpresa(['nombre_comercial' => 'Empresa Ajena']);
        $s = app(SuscripcionService::class);
        $s->asignar($this->empresa, Plan::where('codigo', 'pro')->firstOrFail(), ['periodicidad' => 'mensual', 'prueba' => true]);
        $s->asignar($this->empresa, Plan::where('codigo', 'tienda')->firstOrFail(), ['periodicidad' => 'mensual', 'prueba' => true]);

        $this->actingAs($this->admin)->get(self::BASE . '/suscripciones')->assertOk()
            ->assertSee('MasNorte')->assertSee('Botica Sur')->assertDontSee('Empresa Ajena')
            ->assertSee('Pro')->assertSee('Tienda')->assertSee('No contratada');

        // Cambiar de empresa y llegar a su «Mi suscripción»
        $this->actingAs($this->admin)->post(self::BASE . '/empresa-actual', ['empresa_id' => $otra->id, 'ir' => 'config.suscripcion'])
            ->assertRedirect(route('config.suscripcion'));
        $this->actingAs($this->admin)->post(self::BASE . '/empresa-actual', ['empresa_id' => $otra->id, 'ir' => 'https://otro.sitio'])
            ->assertSessionHasErrors('ir');

        // Un vendedor no administra: no ve empresas
        $vendedor = $this->usuarioCon('Vendedor', $this->empresa);
        $this->actingAs($vendedor)->get(self::BASE . '/suscripciones')->assertOk()->assertSee('No administras ninguna empresa');
    }

    public function test_sin_sesion_el_portal_pide_iniciar_sesion(): void
    {
        $this->get(self::BASE . '/suscripciones')->assertRedirect(route('login'));
    }

    public function test_atajo_web_y_directorio_de_tiendas(): void
    {
        // Sin plan web: el aviso de que no lo incluye; con plan: al bloque Página web
        $this->actingAs($this->admin)->get(self::BASE . '/web')->assertRedirect(route('config.suscripcion'));

        $this->get(self::BASE . '/tienda')->assertOk()->assertSee('Aún no hay tiendas publicadas');
        app(SuscripcionService::class)->asignar($this->empresa, Plan::where('codigo', 'catalogo')->firstOrFail(), ['periodicidad' => 'mensual', 'prueba' => true]);
        $this->app->forgetScopedInstances();
        $this->actingAs($this->admin)->get(self::BASE . '/web')->assertRedirect(route('web.tienda.edit'));
        Tienda::deEmpresa($this->empresa)->update(['publicada' => true, 'slug' => 'masnorte']);
        $this->get(self::BASE . '/tienda')->assertOk()->assertSee('MasNorte')->assertSee(self::BASE . '/tienda/masnorte');
        $this->get(self::BASE . '/tienda/masnorte')->assertOk();
    }

    public function test_indices_de_las_apis(): void
    {
        $this->getJson(self::BASE . '/api')->assertOk()->assertJsonCount(3, 'apis');
        config(['services.admin.token' => 'token-0123456789']);
        $this->getJson(self::BASE . '/api/admin')->assertUnauthorized();
        $this->getJson(self::BASE . '/api/admin', ['Authorization' => 'Bearer token-0123456789'])->assertOk()->assertJsonPath('api', 'Administración de la plataforma');
    }
}
