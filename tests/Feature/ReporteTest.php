<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Venta;
use App\Services\Reportes\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa();
        $this->crearCorrelativoVenta($this->empresa);
        config(['caja.exigir_para_vender' => false]);
    }

    public function test_todos_los_reportes_se_generan_y_exportan(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $p = $this->productoConStock($this->empresa, 20, ['nombre' => 'Producto reportado', 'precio_unitario' => 59, 'costo_unitario' => 30]);
        \App\Models\AlmacenProducto::where('producto_id', $p->id)->update(['stock_minimo' => 25]);

        $this->actingAs($admin)->post(route('ventas.store'), [
            'accion' => 'registrar', 'correlativo_id' => \App\Models\Correlativo::value('id'), 'almacen_id' => $this->almacen($this->empresa)->id,
            'fecha_emision' => now()->toDateString(), 'metodo_pago' => 'yape', 'productos' => [['id' => $p->id, 'cantidad' => 2]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Venta::count());

        $this->actingAs($admin)->get(route('reportes.index'))->assertOk()->assertSee('Productos más vendidos');

        foreach (array_keys(ReporteService::CATALOGO) as $clave) {
            $this->actingAs($admin)->get(route("reportes.{$clave}", ['agrupar' => 'mes']))->assertOk();
            $csv = $this->actingAs($admin)->get(route("reportes.{$clave}.exportar"));
            $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        }

        $this->actingAs($admin)->get(route('reportes.productos-vendidos'))->assertSee('Producto reportado')->assertSee('S/ 118.00');
        $this->actingAs($admin)->get(route('reportes.reposicion'))->assertSee('Producto reportado');
        $this->actingAs($admin)->get(route('reportes.metodos-pago'))->assertSee('Yape');
        $this->actingAs($admin)->get(route('reportes.registro-ventas'))->assertSee('B001-00000001');
    }

    public function test_el_vendedor_no_ve_reportes(): void
    {
        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))->get(route('reportes.ventas'))->assertForbidden();
    }
}
