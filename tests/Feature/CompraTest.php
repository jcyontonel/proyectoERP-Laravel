<?php

namespace Tests\Feature;

use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Erp\Models\Compra;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Erp\Models\Proveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

class CompraTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa();
    }

    public function test_la_compra_actualiza_el_precio_de_venta_indicado(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $proveedor = Proveedor::factory()->create(['empresa_id' => $this->empresa->id]);
        $sube = $this->productoConStock($this->empresa, 2, ['precio_unitario' => 10, 'costo_unitario' => 6]);
        $igual = $this->productoConStock($this->empresa, 2, ['precio_unitario' => 20, 'costo_unitario' => 12]);

        $this->actingAs($admin)->get(route('compras.create'))->assertOk()->assertSee('Mantener margen');

        $this->actingAs($admin)->post(route('compras.store'), [
            'proveedor_id' => $proveedor->id, 'almacen_id' => $this->almacen($this->empresa)->id,
            'tipo_comprobante' => 'factura', 'serie' => 'F001', 'numero' => '123', 'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN', 'afecto_igv' => 1, 'precios_incluyen_igv' => 0, 'condicion_pago' => 'contado', 'metodo_pago' => 'efectivo',
            'productos' => [
                ['id' => $sube->id, 'cantidad' => 5, 'costo' => 7, 'precio_venta' => 12.5],
                ['id' => $igual->id, 'cantidad' => 1, 'costo' => 12],
            ],
        ])->assertRedirect()->assertSessionHas('status', fn ($m) => str_contains($m, 'precio de venta de 1 producto'));

        $this->assertEquals(12.5, $sube->refresh()->precio_unitario);
        $this->assertEquals(7.0, $sube->costo_unitario);
        $this->assertEquals(20.0, $igual->refresh()->precio_unitario);
        $this->assertEquals(7.0, (float) AlmacenProducto::where('producto_id', $sube->id)->value('stock'));
        $this->assertStringContainsString('Precios de venta actualizados', Compra::firstOrFail()->observacion);
    }

    public function test_producto_con_lotes_exige_lote(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $proveedor = Proveedor::factory()->create(['empresa_id' => $this->empresa->id]);
        $p = $this->productoConStock($this->empresa, 0, ['maneja_lotes' => true]);

        $this->actingAs($admin)->post(route('compras.store'), [
            'proveedor_id' => $proveedor->id, 'almacen_id' => $this->almacen($this->empresa)->id,
            'tipo_comprobante' => 'otro', 'fecha_emision' => now()->toDateString(), 'moneda' => 'PEN',
            'condicion_pago' => 'credito', 'productos' => [['id' => $p->id, 'cantidad' => 5, 'costo' => 7]],
        ])->assertSessionHasErrors('productos');
    }
}
