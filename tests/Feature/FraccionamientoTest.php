<?php

namespace Tests\Feature;

use App\Models\AlmacenProducto;
use App\Models\Caja;
use App\Models\Correlativo;
use App\Models\Empresa;
use App\Models\Fraccionamiento;
use App\Models\Kardex;
use App\Models\Producto;
use App\Models\TipoUnidad;
use App\Models\UnidadConversion;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

class FraccionamientoTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;
    private Producto $saco;
    private Producto $kilo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa();

        $this->saco = $this->productoConStock($this->empresa, 2, [
            'nombre' => 'Ricocan 15 kg', 'tipo_unidad_id' => TipoUnidad::where('codigo', 'SCO')->value('id'), 'precio_unitario' => 115, 'costo_unitario' => 90,
        ]);
        $this->kilo = $this->productoConStock($this->empresa, 0, [
            'nombre' => 'Ricocan por kilo', 'tipo_unidad_id' => TipoUnidad::where('codigo', 'KG')->value('id'), 'precio_unitario' => 9, 'costo_unitario' => 0,
        ]);
    }

    public function test_la_regla_se_crea_desde_la_ficha_del_producto(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);

        $this->actingAs($admin)->post(route('productos.fracciones.store', $this->saco), ['producto_destino_id' => $this->kilo->id, 'factor' => 15])
            ->assertSessionHas('status');

        $regla = UnidadConversion::firstOrFail();
        $this->assertTrue($regla->esFraccionamiento());
        $this->assertSame(0, $this->saco->conversiones()->count());   // no se mezcla con las presentaciones
        $this->actingAs($admin)->get(route('productos.show', $this->saco))->assertOk()->assertSee('Se parte en')->assertSee('Ricocan por kilo');
        $this->actingAs($admin)->get(route('productos.show', $this->kilo))->assertOk()->assertSee('Se obtiene de');
    }

    public function test_partir_manualmente_mueve_stock_y_reparte_el_costo(): void
    {
        $regla = $this->regla();
        $user = $this->usuarioCon('Almacenero', $this->empresa);

        $this->actingAs($user)->get(route('fraccionamientos.create'))->assertOk();
        $this->actingAs($user)->post(route('fraccionamientos.store'), [
            'unidad_conversion_id' => $regla->id, 'almacen_id' => $this->almacen($this->empresa)->id, 'cantidad' => 1,
        ])->assertRedirect();

        $this->assertEquals(1.0, $this->stock($this->saco));
        $this->assertEquals(15.0, $this->stock($this->kilo));
        $this->assertEquals(6.0, $this->kilo->refresh()->costo_unitario);   // 90 / 15

        $f = Fraccionamiento::firstOrFail();
        $this->assertSame(2, Kardex::where('referencia_tipo', 'fraccionamiento')->where('referencia_id', $f->id)->count());
        $this->actingAs($user)->get(route('fraccionamientos.show', $f))->assertOk()->assertSee('Ricocan por kilo');
        $this->actingAs($user)->get(route('fraccionamientos.index'))->assertOk()->assertSee($f->numero_completo);
    }

    public function test_al_vender_kilos_sin_stock_se_abre_un_saco_automaticamente(): void
    {
        $this->regla();
        Caja::create(['empresa_id' => $this->empresa->id, 'codigo' => 'C1', 'nombre' => 'Caja']);
        $this->crearCorrelativoVenta($this->empresa);
        $vendedor = $this->usuarioCon('Vendedor', $this->empresa);
        $this->actingAs($vendedor)->post(route('caja.abrir'), ['caja_id' => Caja::value('id'), 'monto_apertura' => 0]);

        // El formulario sabe que hay 30 kg disponibles al partir y que acepta decimales
        $this->actingAs($vendedor)->get(route('ventas.create'))->assertOk()->assertSee('"fraccionable"', false);

        $this->actingAs($vendedor)->post(route('ventas.store'), [
            'accion' => 'registrar', 'correlativo_id' => Correlativo::value('id'), 'almacen_id' => $this->almacen($this->empresa)->id,
            'fecha_emision' => now()->toDateString(), 'metodo_pago' => 'efectivo',
            'productos' => [['id' => $this->kilo->id, 'cantidad' => 7.5]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $venta = Venta::firstOrFail();
        $this->assertSame(Venta::COMPLETADA, $venta->estado);
        $this->assertEquals(7.5, $venta->detalles()->value('cantidad'));
        $this->assertEquals(67.5, $venta->total);

        $this->assertEquals(1.0, $this->stock($this->saco));   // se abrió 1 de los 2 sacos
        $this->assertEquals(7.5, $this->stock($this->kilo));   // 15 − 7.5

        $f = Fraccionamiento::firstOrFail();
        $this->assertSame('venta', $f->origen);
        $this->assertSame($venta->id, $f->venta_id);
    }

    public function test_si_ni_partiendo_alcanza_la_venta_se_rechaza(): void
    {
        $this->regla();
        $this->crearCorrelativoVenta($this->empresa);
        config(['caja.exigir_para_vender' => false]);

        $this->actingAs($this->usuarioCon('Administrador', $this->empresa))->post(route('ventas.store'), [
            'accion' => 'registrar', 'correlativo_id' => Correlativo::value('id'), 'almacen_id' => $this->almacen($this->empresa)->id,
            'fecha_emision' => now()->toDateString(), 'metodo_pago' => 'efectivo',
            'productos' => [['id' => $this->kilo->id, 'cantidad' => 40]],   // 2 sacos = 30 kg
        ])->assertSessionHasErrors('productos');

        $this->assertSame(0, Fraccionamiento::count());   // todo se revirtió
        $this->assertEquals(2.0, $this->stock($this->saco));
    }

    public function test_anular_devuelve_el_origen_salvo_que_ya_se_haya_vendido(): void
    {
        $regla = $this->regla();
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $datos = ['unidad_conversion_id' => $regla->id, 'almacen_id' => $this->almacen($this->empresa)->id, 'cantidad' => 1];

        $this->actingAs($admin)->post(route('fraccionamientos.store'), $datos);
        $f = Fraccionamiento::firstOrFail();
        $this->actingAs($admin)->patch(route('fraccionamientos.anular', $f))->assertSessionHas('status');
        $this->assertEquals(2.0, $this->stock($this->saco));
        $this->assertEquals(0.0, $this->stock($this->kilo));

        // Si parte de lo partido ya salió, no se puede revertir
        $this->actingAs($admin)->post(route('fraccionamientos.store'), $datos);
        AlmacenProducto::where('producto_id', $this->kilo->id)->update(['stock' => 3]);
        $this->actingAs($admin)->patch(route('fraccionamientos.anular', Fraccionamiento::latest('id')->first()))->assertSessionHasErrors('fraccionamiento');
    }

    private function regla(): UnidadConversion
    {
        return UnidadConversion::create([
            'empresa_id' => $this->empresa->id, 'producto_id' => $this->saco->id, 'producto_destino_id' => $this->kilo->id,
            'unidad_origen_id' => $this->saco->tipo_unidad_id, 'unidad_destino_id' => $this->kilo->tipo_unidad_id, 'factor' => 15, 'activo' => true,
        ]);
    }

    private function stock(Producto $p): float
    {
        return (float) AlmacenProducto::where('producto_id', $p->id)->where('almacen_id', $this->almacen($this->empresa)->id)->value('stock');
    }
}
