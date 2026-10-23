<?php

namespace Tests\Feature;

use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Plataforma\Models\Plan;
use App\Modulos\Plataforma\Models\Suscripcion;
use App\Modulos\Plataforma\Models\Titular;
use App\Modulos\Plataforma\Services\SuscripcionService;
use App\Modulos\Plataforma\Support\Modulos;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** Suscripciones por producto (ERP y Página web), titular que paga y varias empresas por suscripción */
class SuscripcionProductoTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->seed(PlanSeeder::class);
        $this->empresa = $this->crearEmpresa(['ruc' => '20601234567', 'razon_social' => 'MASCOTAS DEL NORTE SAC']);
    }

    public function test_erp_y_web_son_suscripciones_independientes(): void
    {
        $servicio = $this->servicio();
        $servicio->asignar($this->empresa, $this->plan('pro'), ['periodicidad' => 'mensual', 'pagado' => true, 'comprobante' => 'ninguno']);

        // Sin suscripción web: el ERP tiene sus módulos, la web ninguno (aunque sin_plan_ilimitado)
        $this->assertTrue(Modulos::incluido('compras', $this->empresa->id));
        $this->assertFalse(Modulos::incluido('web_catalogo', $this->empresa->id));
        $this->assertFalse(Modulos::rutaIncluida('web.productos.index', $this->empresa->id));

        // Catálogo: la página, pero no la tienda
        $this->servicio()->asignar($this->empresa, $this->plan('catalogo'), ['periodicidad' => 'mensual', 'pagado' => true, 'comprobante' => 'ninguno']);
        $this->assertTrue(Modulos::rutaIncluida('web.productos.index', $this->empresa->id));
        $this->assertFalse(Modulos::rutaIncluida('web.pedidos.index', $this->empresa->id));

        // Pasar a Tienda no toca la suscripción del ERP
        $this->servicio()->asignar($this->empresa, $this->plan('tienda'), ['periodicidad' => 'anual', 'pagado' => true, 'comprobante' => 'ninguno']);
        $this->assertTrue(Modulos::rutaIncluida('web.pedidos.index', $this->empresa->id));
        $this->assertSame('pro', $this->servicio()->vigente($this->empresa->id)->plan->codigo);
        $this->assertSame('tienda', $this->servicio()->vigente($this->empresa->id, 'web')->plan->codigo);
        $this->assertSame(1, Suscripcion::where('estado', 'cancelada')->count());   // solo la de Catálogo
    }

    public function test_sin_suscripcion_al_erp_las_empresas_antiguas_siguen_sin_limites(): void
    {
        $this->assertTrue(Modulos::incluido('lotes', $this->empresa->id));
        config(['suscripciones.sin_plan_ilimitado' => false]);
        $this->app->forgetScopedInstances();
        $this->assertFalse(Modulos::incluido('lotes', $this->empresa->id));
    }

    public function test_el_titular_paga_y_la_suscripcion_cubre_varias_empresas_hasta_el_limite(): void
    {
        $titular = Titular::create(['tipo_documento' => '1', 'numero_documento' => '44556677', 'nombre' => 'ANA TORRES GIL']);
        $s = $this->servicio()->asignar($this->empresa, $this->plan('pro'), ['periodicidad' => 'mensual', 'titular_id' => $titular->id, 'pagado' => true, 'comprobante' => 'ninguno']);
        $this->assertSame($titular->id, $s->titular_id);

        // Pro cubre 2 empresas: la segunda entra, la tercera no
        $segunda = $this->crearEmpresa();
        $this->servicio()->agregarEmpresa($s, $segunda);
        $this->assertSame('pro', $this->servicio()->vigente($segunda->id)?->plan->codigo);
        $this->assertTrue(Modulos::incluido('compras', $segunda->id));

        try {
            $this->servicio()->agregarEmpresa($s, $this->crearEmpresa());
            $this->fail('Debió rechazar la tercera empresa');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('hasta 2 empresas', $e->errors()['empresa'][0]);
        }

        // Los límites (usuarios, productos…) siguen siendo por empresa; la principal no se quita
        $this->servicio()->quitarEmpresa($s, $segunda);
        $this->assertNull($this->servicio()->vigente($segunda->id));
        $this->expectException(ValidationException::class);
        $this->servicio()->quitarEmpresa($s, $this->empresa);
    }

    public function test_sin_titular_indicado_la_empresa_es_su_propio_titular(): void
    {
        $s = $this->servicio()->asignar($this->empresa, $this->plan('basico'), ['periodicidad' => 'mensual', 'prueba' => true]);

        $this->assertSame('20601234567', $s->titular->numero_documento);
        $this->assertTrue($s->titular->esRuc());
        $this->assertSame([$this->empresa->id], $s->empresas()->pluck('empresas.id')->all());
    }

    public function test_una_empresa_con_su_propio_erp_no_entra_a_otra_suscripcion_erp(): void
    {
        $otra = $this->crearEmpresa();
        $this->servicio()->asignar($otra, $this->plan('basico'), ['periodicidad' => 'mensual', 'prueba' => true]);
        $s = $this->servicio()->asignar($this->empresa, $this->plan('total'), ['periodicidad' => 'mensual', 'prueba' => true]);

        $this->expectException(ValidationException::class);
        $this->servicio()->agregarEmpresa($s, $otra);
    }

    private function plan(string $codigo): Plan
    {
        return Plan::where('codigo', $codigo)->firstOrFail();
    }

    private function servicio(): SuscripcionService
    {
        $this->app->forgetScopedInstances();

        return app(SuscripcionService::class);
    }
}
