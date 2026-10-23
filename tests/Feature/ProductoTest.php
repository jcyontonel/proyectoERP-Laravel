<?php

namespace Tests\Feature;

use App\Modulos\Erp\Models\Almacen;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Erp\Models\Kardex;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Catalogo\Models\TipoUnidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

class ProductoTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa();
    }

    public function test_el_listado_solo_muestra_productos_de_la_empresa(): void
    {
        Producto::factory()->create(['empresa_id' => $this->empresa->id, 'nombre' => 'Ricocan Adulto 15 kg']);
        Producto::factory()->create(['empresa_id' => $this->crearEmpresa()->id, 'nombre' => 'Producto de otra empresa']);

        $this->actingAs($this->usuarioCon('Administrador', $this->empresa))
            ->get(route('productos.index'))
            ->assertOk()
            ->assertSee('Ricocan Adulto 15 kg')
            ->assertDontSee('Producto de otra empresa');
    }

    public function test_no_se_puede_ver_un_producto_de_otra_empresa(): void
    {
        $ajeno = Producto::factory()->create(['empresa_id' => $this->crearEmpresa()->id]);

        $this->actingAs($this->usuarioCon('Administrador', $this->empresa))
            ->get(route('productos.show', $ajeno))
            ->assertNotFound();   // el binding de rutas se limita a la empresa actual
    }

    public function test_crear_producto_con_presentacion_y_stock_inicial(): void
    {
        $kg    = TipoUnidad::where('codigo', 'KG')->value('id');
        $saco  = TipoUnidad::where('codigo', 'SCO')->value('id');
        $almacen = Almacen::where('empresa_id', $this->empresa->id)->first();

        $this->actingAs($this->usuarioCon('Administrador', $this->empresa))
            ->post(route('productos.store'), [
                'nombre'          => 'Ricocan Adulto a granel',
                'tipo_unidad_id'  => $kg,
                'precio_unitario' => 9.50,
                'costo_unitario'  => 6.20,
                'activo'          => 1,
                'conversiones'    => [['unidad_id' => $saco, 'factor' => 15, 'precio_venta' => 125, 'activo' => 1]],
                'almacenes'       => [['id' => $almacen->id, 'stock_minimo' => 10, 'stock_inicial' => 37.5]],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $p = Producto::where('nombre', 'Ricocan Adulto a granel')->firstOrFail();
        $this->assertSame(1, $p->conversiones()->count());
        $this->assertEquals(37.5, $p->stocks()->value('stock'));
        $this->assertSame(1, Kardex::where('producto_id', $p->id)->where('referencia_tipo', 'stock_inicial')->count());

        $this->get(route('productos.show', $p))
            ->assertOk()
            ->assertSee('37.5')
            ->assertSee('Saco')
            ->assertSee('S/ 125.00');
    }

    public function test_el_vendedor_consulta_pero_no_crea_ni_ve_costos(): void
    {
        $p = Producto::factory()->create(['empresa_id' => $this->empresa->id, 'costo_unitario' => 4.321]);
        $vendedor = $this->usuarioCon('Vendedor', $this->empresa);

        $this->actingAs($vendedor)->get(route('productos.index'))->assertOk()->assertDontSee('Inventario valorizado');
        $this->actingAs($vendedor)->get(route('productos.show', $p))->assertOk()->assertDontSee('Costo y margen');
        $this->actingAs($vendedor)->get(route('productos.create'))->assertForbidden();
        $this->actingAs($vendedor)->post(route('productos.store'), ['nombre' => 'X'])->assertForbidden();
    }

    public function test_no_se_elimina_un_producto_con_movimientos(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $p = Producto::factory()->create(['empresa_id' => $this->empresa->id]);
        Kardex::create([
            'empresa_id' => $this->empresa->id, 'almacen_id' => Almacen::where('empresa_id', $this->empresa->id)->value('id'),
            'producto_id' => $p->id, 'user_id' => $admin->id, 'referencia_tipo' => 'stock_inicial',
            'tipo_movimiento' => 'ingreso', 'cantidad' => 1, 'stock_anterior' => 0, 'stock_nuevo' => 1, 'fecha_movimiento' => now(),
        ]);

        $this->actingAs($admin)->delete(route('productos.destroy', $p))->assertSessionHas('error');
        $this->assertNotSoftDeleted($p);

        $libre = Producto::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->actingAs($admin)->delete(route('productos.destroy', $libre))->assertRedirect(route('productos.index'));
        $this->assertSoftDeleted($libre);
    }

    public function test_el_formulario_de_edicion_carga(): void
    {
        $p = Producto::factory()->create(['empresa_id' => $this->empresa->id]);

        $this->actingAs($this->usuarioCon('Almacenero', $this->empresa))
            ->get(route('productos.edit', $p))
            ->assertOk()
            ->assertSee('Guardar cambios');
    }
}
