<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Plan;
use App\Models\User;
use App\Services\SuscripcionService;
use App\Support\Menu;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** Cada plan habilita sus módulos: lo que no incluye no aparece en el menú ni se abre por enlace */
class ModulosPlanTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->seed(PlanSeeder::class);
        $this->empresa = $this->crearEmpresa();
        $this->admin = $this->usuarioCon('Administrador', $this->empresa);
    }

    public function test_basico_oculta_y_bloquea_los_modulos_que_no_incluye(): void
    {
        $this->plan('basico');

        $menu = $this->claves();
        foreach (['nueva-venta', 'ventas', 'notas', 'clientes', 'caja', 'productos', 'cuentas-cobrar', 'sunat', 'reporte-ventas'] as $clave) {
            $this->assertContains($clave, $menu, "Básico debería mostrar {$clave}");
        }
        foreach (['cotizaciones', 'compras', 'proveedores', 'cuentas-pagar', 'kardex', 'ajustes', 'ordenes-compra', 'transferencias', 'lotes', 'fraccionamientos', 'auditoria', 'reporte-utilidad'] as $clave) {
            $this->assertNotContains($clave, $menu, "Básico no debería mostrar {$clave}");
        }

        // Ni por enlace directo (GET), ni por formulario (POST), ni por la API (JSON)
        $this->actingAs($this->admin)->get(route('compras.index'))->assertRedirect(route('config.suscripcion'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, '«Compras y proveedores» no está incluido en tu plan Básico'));
        $this->actingAs($this->admin)->get(route('lotes.index'))->assertRedirect(route('config.suscripcion'));
        $this->actingAs($this->admin)->post(route('cotizaciones.store'), [])->assertSessionHas('error');
        $this->actingAs($this->admin)->getJson(route('kardex.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('reportes.utilidad'))->assertRedirect(route('config.suscripcion'));

        // Lo incluido sí abre; el catálogo de reportes solo muestra los del plan
        $this->actingAs($this->admin)->get(route('notas.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('reportes.index'))->assertOk()
            ->assertSee('Ventas por período')->assertDontSee('Utilidad por categoría');

        // Mi suscripción muestra qué incluye cada plan
        $this->actingAs($this->admin)->get(route('config.suscripcion'))->assertOk()->assertSee('Pro')->assertSee('Lotes y vencimientos');
    }

    public function test_al_subir_de_plan_aparecen_los_modulos(): void
    {
        $this->plan('pro');
        $this->assertContains('compras', $this->claves());
        $this->assertContains('cotizaciones', $this->claves());
        $this->assertNotContains('lotes', $this->claves());
        $this->actingAs($this->admin)->get(route('compras.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('lotes.index'))->assertRedirect();

        $this->plan('total');
        $this->assertContains('lotes', $this->claves());
        $this->assertContains('auditoria', $this->claves());
        $this->actingAs($this->admin)->get(route('lotes.index'))->assertOk();
    }

    public function test_sin_plan_tiene_todo_y_el_admin_puede_cambiar_los_modulos(): void
    {
        // Empresas anteriores al sistema de planes: sin límites ni recortes
        $this->assertContains('lotes', $this->claves());

        // Quitar "Cotizaciones" al plan Pro desde el panel admin
        config(['services.admin.token' => 'token-admin']);
        $pro = Plan::where('codigo', 'pro')->first();
        $this->putJson("/api/admin/planes/{$pro->id}", ['codigo' => 'pro', 'nombre' => 'Pro', 'activo' => true,
            'modulos' => ['compras', 'notas', 'cotizacion_inexistente']], ['Authorization' => 'Bearer token-admin'])->assertJsonValidationErrors('modulos.2');
        $this->putJson("/api/admin/planes/{$pro->id}", ['codigo' => 'pro', 'nombre' => 'Pro', 'activo' => true,
            'modulos' => ['compras', 'notas', 'compras']], ['Authorization' => 'Bearer token-admin'])->assertOk()->assertJsonPath('data.modulos', ['notas', 'compras']);

        $this->plan('pro');
        $this->assertContains('compras', $this->claves());
        $this->assertNotContains('cotizaciones', $this->claves());
    }

    public function test_suspendida_oculta_del_menu_lo_que_quedo_desactivado(): void
    {
        $s = $this->plan('total');
        $this->assertContains('nueva-venta', $this->claves());

        app(SuscripcionService::class)->suspender($s, 'Falta de pago');
        $this->app->forgetScopedInstances();
        $menu = $this->claves();
        $this->assertNotContains('nueva-venta', $menu);                       // su formulario está bloqueado
        $this->assertContains('ventas', $menu);                               // consultar sí
        $this->actingAs($this->admin)->get(route('ventas.create'))->assertRedirect(route('config.suscripcion'));

        app(SuscripcionService::class)->reactivar($s->refresh());
        $this->app->forgetScopedInstances();
        $this->assertContains('nueva-venta', $this->claves());
    }

    private function plan(string $codigo)
    {
        $s = app(SuscripcionService::class)->asignar($this->empresa, Plan::where('codigo', $codigo)->firstOrFail(), ['periodicidad' => 'mensual', 'pagado' => true]);
        $this->app->forgetScopedInstances();

        return $s;
    }

    /** Claves de los ítems del menú lateral para el administrador */
    private function claves(): array
    {
        $this->app->forgetScopedInstances();
        $this->actingAs($this->admin);
        $user = $this->comoUsuario($this->admin, $this->empresa);   // permisos de Spatie en el equipo de la empresa

        return Menu::para($user)->flatMap(fn ($s) => $s->items)->pluck('clave')->all();
    }
}
