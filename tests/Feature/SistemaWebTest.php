<?php

namespace Tests\Feature;

use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Erp\Models\Kardex;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Nucleo\Support\Menu;
use App\Modulos\Plataforma\Models\Plan;
use App\Modulos\Plataforma\Services\SuscripcionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/**
 * La suscripción WEB es un sistema propio: página web, ventas, comprobantes, clientes, productos y facturación, sin
 * caja ni control de inventario (el stock se edita en el producto). Con ERP + WEB la empresa tiene todo junto.
 */
class SistemaWebTest extends TestCase
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

    public function test_solo_web_muestra_lo_suyo_y_oculta_el_erp(): void
    {
        $this->plan('tienda');

        $menu = $this->claves();
        foreach (['nueva-venta', 'ventas', 'clientes', 'productos', 'categorias', 'sunat', 'web-tienda', 'web-pedidos', 'web-zonas', 'reporte-ventas', 'notas'] as $clave) {
            $this->assertContains($clave, $menu, "WEB Tienda debería mostrar {$clave}");
        }
        foreach (['caja', 'cajas', 'compras', 'proveedores', 'kardex', 'ajustes', 'lotes', 'transferencias', 'almacenes', 'cotizaciones', 'cuentas-pagar'] as $clave) {
            $this->assertNotContains($clave, $menu, "WEB no debería mostrar {$clave}");
        }

        $this->actingAs($this->admin)->get(route('compras.index'))->assertRedirect(route('config.suscripcion'));
        $this->actingAs($this->admin)->get(route('caja.index'))->assertRedirect(route('config.suscripcion'));
        $this->actingAs($this->admin)->get(route('kardex.index'))->assertRedirect(route('config.suscripcion'));
        $this->actingAs($this->admin)->get(route('web.pedidos.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('ventas.create'))->assertOk();

        // Catálogo: sin pedidos ni zonas (son de Tienda)
        $this->plan('catalogo');
        $this->assertNotContains('web-pedidos', $this->claves());
        $this->actingAs($this->admin)->get(route('web.pedidos.index'))->assertRedirect(route('config.suscripcion'));
    }

    public function test_solo_web_vende_sin_turno_de_caja(): void
    {
        config(['caja.exigir_para_vender' => true]);
        $this->plan('tienda');

        $this->vender()->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Venta::where('empresa_id', $this->empresa->id)->where('estado', '!=', 'borrador')->count());
    }

    public function test_solo_web_edita_el_stock_en_el_producto_y_queda_en_el_kardex(): void
    {
        $this->plan('tienda');
        $almacen = $this->almacen($this->empresa);
        $p = $this->productoConStock($this->empresa, 5);

        $this->actingAs($this->admin)->get(route('productos.edit', $p))->assertOk()->assertSee('name="almacenes[0][stock]"', false);

        $this->actingAs($this->admin)->put(route('productos.update', $p), $this->datosProducto($p, $almacen->id, 12))->assertSessionHasNoErrors();
        $this->assertEquals(12, AlmacenProducto::where('producto_id', $p->id)->value('stock'));
        $this->assertTrue(Kardex::where('producto_id', $p->id)->where('referencia_tipo', 'ajuste_producto')->where('cantidad', 7)->exists());

        $this->actingAs($this->admin)->put(route('productos.update', $p), $this->datosProducto($p, $almacen->id, 3))->assertSessionHasNoErrors();
        $this->assertEquals(3, AlmacenProducto::where('producto_id', $p->id)->value('stock'));
    }

    public function test_con_erp_y_web_tiene_todo_junto_y_el_stock_va_por_inventario(): void
    {
        config(['caja.exigir_para_vender' => true]);
        $this->plan('pro');
        $this->plan('tienda');

        $menu = $this->claves();
        foreach (['caja', 'compras', 'kardex', 'web-tienda', 'web-pedidos', 'cotizaciones'] as $clave) {
            $this->assertContains($clave, $menu, "ERP + WEB debería mostrar {$clave}");
        }

        // Con el ERP la caja vuelve a ser obligatoria y el stock no se edita en el producto
        $this->vender()->assertSessionHas('error');
        $almacen = $this->almacen($this->empresa);
        $p = $this->productoConStock($this->empresa, 5);
        $this->actingAs($this->admin)->get(route('productos.edit', $p))->assertOk()->assertDontSee('name="almacenes[0][stock]"', false);
        $this->actingAs($this->admin)->put(route('productos.update', $p), $this->datosProducto($p, $almacen->id, 12));
        $this->assertEquals(5, AlmacenProducto::where('producto_id', $p->id)->value('stock'));

        // Límites: el mayor de los dos planes (y ninguno ilimitado por tener WEB)
        $this->assertSame(5000, app(SuscripcionService::class)->limite($this->empresa->id, 'productos'));
    }

    private function plan(string $codigo)
    {
        $s = app(SuscripcionService::class)->asignar($this->empresa, Plan::where('codigo', $codigo)->firstOrFail(), ['periodicidad' => 'mensual', 'pagado' => true]);
        $this->app->forgetScopedInstances();

        return $s;
    }

    private function claves(): array
    {
        $this->app->forgetScopedInstances();
        $this->actingAs($this->admin);
        $user = $this->comoUsuario($this->admin, $this->empresa);

        return Menu::para($user)->flatMap(fn ($s) => $s->items)->pluck('clave')->all();
    }

    private function vender()
    {
        $this->app->forgetScopedInstances();
        $producto = $this->productoConStock($this->empresa, 5, ['precio_unitario' => 10]);

        return $this->actingAs($this->admin)->post(route('ventas.store'), [
            'accion' => 'registrar', 'correlativo_id' => Correlativo::where('empresa_id', $this->empresa->id)->where('modulo', Correlativo::MODULO_VENTA)->value('id'),
            'almacen_id' => $this->almacen($this->empresa)->id, 'fecha_emision' => now()->toDateString(), 'metodo_pago' => 'efectivo',
            'productos' => [['id' => $producto->id, 'cantidad' => 1]],
        ]);
    }

    private function datosProducto(Producto $p, int $almacenId, float $stock): array
    {
        $this->app->forgetScopedInstances();

        return [
            'codigo' => $p->codigo, 'nombre' => $p->nombre, 'tipo_unidad_id' => $p->tipo_unidad_id,
            'precio_unitario' => $p->precio_unitario, 'costo_unitario' => $p->costo_unitario, 'activo' => 1,
            'almacenes' => [['id' => $almacenId, 'stock_minimo' => 0, 'stock' => $stock]],
        ];
    }
}
