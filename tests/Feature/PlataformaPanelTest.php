<?php

namespace Tests\Feature;

use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Suscripciones\Models\PagoSuscripcion;
use App\Modulos\Suscripciones\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** Plataforma dentro de la aplicación: permiso global, pantallas del panel y API de administración interna */
class PlataformaPanelTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->seed(PlanSeeder::class);
        config(['services.admin.token' => null]);   // el panel no necesita el token de la API externa

        $this->admin = $this->usuarioCon('Vendedor', $this->crearEmpresa());
        $this->artisan('plataforma:asignar-admin', ['correo' => $this->admin->email])->assertSuccessful();
        $this->admin->refresh();
    }

    public function test_solo_entra_quien_administra_la_plataforma(): void
    {
        $this->get(route('plataforma.inicio'))->assertRedirect(route('login'));

        $otro = $this->usuarioCon('Administrador', $this->crearEmpresa());
        $this->actingAs($otro)->get(route('plataforma.inicio'))->assertForbidden();
        $this->actingAs($otro)->get(route('plataforma.empresas.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('plataforma.inicio'))->assertOk()->assertSee('Plataforma');
        $this->actingAs($this->admin)->get(route('inicio'))->assertSee(route('plataforma.inicio'));   // enlace en el menú del ERP
        $this->actingAs($otro)->get(route('inicio'))->assertDontSee(route('plataforma.inicio'));

        // El permiso no es asignable en masa y se quita por consola
        $otro->update(['administra_plataforma' => true]);
        $this->assertFalse((bool) $otro->refresh()->administra_plataforma);
        $this->artisan('plataforma:asignar-admin', ['correo' => $this->admin->email, '--quitar' => true])->assertSuccessful();
        $this->actingAs($this->admin->refresh())->get(route('plataforma.inicio'))->assertForbidden();
        $this->artisan('plataforma:asignar-admin', ['correo' => 'nadie@x.pe'])->assertFailed();
    }

    public function test_alta_de_empresa_plan_y_pago_desde_el_panel(): void
    {
        $this->actingAs($this->admin)->get(route('plataforma.empresas.create'))->assertOk()->assertSee('Básico');

        $this->actingAs($this->admin)->post(route('plataforma.empresas.store'), [
            'ruc' => '20601234567', 'razon_social' => 'Mascotas del Norte SAC', 'direccion' => 'Av. Grau 100',
            'admin' => ['name' => 'Lucía', 'email' => 'lucia@masnorte.pe', 'password' => 'clave1234'],
            'plan_id' => Plan::where('codigo', 'pro')->value('id'), 'periodicidad' => 'mensual', 'pagado' => '1', 'metodo' => 'yape', 'comprobante' => 'ninguno',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $empresa = Empresa::where('ruc', '20601234567')->firstOrFail();
        $this->actingAs($this->admin)->get(route('plataforma.empresas.show', $empresa->id))->assertOk()->assertSee('Mascotas del Norte SAC')->assertSee('Pro');
        $this->actingAs($this->admin)->get(route('plataforma.empresas.index', ['q' => 'Norte']))->assertOk()->assertSee('Mascotas del Norte SAC');

        $pago = PagoSuscripcion::where('empresa_id', $empresa->id)->firstOrFail();
        $this->assertSame($this->admin->name, $pago->registrado_por);   // la API interna registra quién lo hizo
        $this->actingAs($this->admin)->get(route('plataforma.pagos.index'))->assertOk()->assertSee('Mascotas del Norte SAC');

        // Un error de la API vuelve como error del formulario
        $this->actingAs($this->admin)->from(route('plataforma.empresas.create'))->post(route('plataforma.empresas.store'), [
            'ruc' => '20601234567', 'razon_social' => 'Repetida', 'admin' => ['name' => 'X', 'email' => 'x@x.pe', 'password' => 'clave1234'],
        ])->assertRedirect(route('plataforma.empresas.create'))->assertSessionHasErrors('ruc');
    }

    public function test_planes_y_ofertas_desde_el_panel(): void
    {
        $this->actingAs($this->admin)->get(route('plataforma.planes.index'))->assertOk()->assertSee('Catálogo')->assertSee('Tienda');
        $this->actingAs($this->admin)->get(route('plataforma.ofertas.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('plataforma.planes.edit', Plan::where('codigo', 'basico')->value('id')))->assertOk();
    }

    /** Como en Apache: localhost/proyectoERP-Laravel/public/plataforma (la API interna debe resolver /api/admin/…) */
    public function test_funciona_con_la_aplicacion_en_una_subcarpeta(): void
    {
        $this->actingAs($this->admin)
            ->withServerVariables(['SCRIPT_NAME' => '/proyectoERP-Laravel/public/index.php', 'SCRIPT_FILENAME' => base_path('public/index.php')])
            ->get('http://localhost/proyectoERP-Laravel/public/plataforma')
            ->assertOk()->assertSee('Plataforma');
    }

    public function test_la_api_externa_sigue_exigiendo_su_token(): void
    {
        $this->actingAs($this->admin)->getJson('/api/admin/resumen')->assertStatus(503);   // sin token configurado: cerrada
        config(['services.admin.token' => 'token-externo-0123456789']);
        $this->actingAs($this->admin)->getJson('/api/admin/resumen')->assertUnauthorized();   // la sesión no basta
        $this->getJson('/api/admin/resumen', ['Authorization' => 'Bearer token-externo-0123456789'])->assertOk();
    }
}
