<?php

namespace Tests\Feature;

use App\Models\AlmacenProducto;
use App\Models\Compra;
use App\Models\Empresa;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\EmpresaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** Órdenes de compra: totales, recepción parcial/total con compras, reversa al anular, cierre y anulación */
class OrdenCompraTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa();
        app(EmpresaService::class)->series($this->empresa);   // OC01…
        $this->admin = $this->usuarioCon('Administrador', $this->empresa);
        $this->proveedor = Proveedor::factory()->create(['empresa_id' => $this->empresa->id]);
    }

    public function test_crea_la_orden_con_costos_sin_igv(): void
    {
        $a = $this->productoConStock($this->empresa, 0);
        $b = $this->productoConStock($this->empresa, 0);

        $this->actingAs($this->admin)->get(route('ordenes.create'))->assertOk()->assertSee($a->nombre);
        $orden = $this->ordenar([[$a, 10, 5], [$b, 4, 2.5]]);

        $this->assertSame('OC01', $orden->serie);
        $this->assertEquals(60.0, $orden->subtotal);
        $this->assertEquals(10.80, $orden->total_impuestos);
        $this->assertEquals(70.80, $orden->total);
        $this->assertSame('emitida', $orden->estado);
        $this->assertEquals(0.0, $this->stock($a));                                  // la orden no mueve stock

        $sinIgv = $this->ordenar([[$a, 2, 5]], ['afecto_igv' => 0]);
        $this->assertEquals(10.0, $sinIgv->total);

        $this->actingAs($this->admin)->get(route('ordenes.index'))->assertOk()->assertSee($orden->numero_completo);
        $this->actingAs($this->admin)->get(route('ordenes.show', $orden))->assertOk()->assertSee('Recibir mercadería')->assertSee('S/ 70.80');
        $this->actingAs($this->admin)->get(route('ordenes.imprimir', $orden))->assertOk()->assertSee('ORDEN DE COMPRA');
    }

    public function test_recepcion_parcial_total_y_reversa_al_anular_la_compra(): void
    {
        $a = $this->productoConStock($this->empresa, 0);
        $b = $this->productoConStock($this->empresa, 0);
        $orden = $this->ordenar([[$a, 10, 5], [$b, 4, 2.5]]);

        // «Recibir» abre la compra con lo pendiente
        $this->actingAs($this->admin)->post(route('ordenes.recibir', $orden))
            ->assertRedirect(route('compras.create'))
            ->assertSessionHasInput('orden_compra_id', $orden->id)
            ->assertSessionHasInput('proveedor_id', $this->proveedor->id);
        $this->actingAs($this->admin)->withSession(['_old_input' => ['orden_compra_id' => $orden->id, 'orden_compra_ref' => $orden->numero_completo]])
            ->get(route('compras.create'))->assertOk()->assertSee('Recepción de la orden')->assertSee('name="orden_compra_id"', false);

        // Llegó una parte
        $this->comprar($orden, [[$a, 6, 5]], 'F001-1');
        $orden->refresh()->load('detalles');
        $this->assertSame('parcial', $orden->estado);
        $this->assertEquals(6.0, $orden->detalles->firstWhere('producto_id', $a->id)->cantidad_recibida);
        $this->assertEquals(6.0, $this->stock($a));
        $this->actingAs($this->admin)->patch(route('ordenes.anular', $orden))->assertSessionHasErrors('orden');   // ya recibió: se cierra, no se anula
        $this->actingAs($this->admin)->get(route('ordenes.edit', $orden))->assertRedirect(route('ordenes.show', $orden));

        // Llegó el resto (lo que exceda lo pedido no cuenta)
        $segunda = $this->comprar($orden, [[$a, 5, 5], [$b, 4, 2.5]], 'F001-2');
        $this->assertSame('recibida', $orden->refresh()->estado);
        $this->assertSame(2, $orden->compras()->count());
        $this->actingAs($this->admin)->post(route('ordenes.recibir', $orden))->assertSessionHasErrors('orden');
        $this->actingAs($this->admin)->get(route('compras.show', $segunda))->assertOk()->assertSee($orden->numero_completo);

        // Anular la segunda compra devuelve lo recibido a pendiente
        $this->actingAs($this->admin)->patch(route('compras.anular', $segunda))->assertSessionHas('status');
        $orden->refresh()->load('detalles');
        $this->assertSame('parcial', $orden->estado);
        $this->assertEquals(6.0, $orden->detalles->firstWhere('producto_id', $a->id)->cantidad_recibida);
        $this->assertEquals(0.0, $orden->detalles->firstWhere('producto_id', $b->id)->cantidad_recibida);

        // No llegará el resto: se cierra
        $this->actingAs($this->admin)->patch(route('ordenes.cerrar', $orden))->assertSessionHasNoErrors();
        $this->assertSame('cerrada', $orden->refresh()->estado);
        $this->actingAs($this->admin)->post(route('ordenes.recibir', $orden))->assertSessionHasErrors('orden');
    }

    public function test_restricciones(): void
    {
        $a = $this->productoConStock($this->empresa, 0);
        $orden = $this->ordenar([[$a, 3, 4]]);

        // La compra debe ser del mismo proveedor: no se registra nada
        $otroProveedor = Proveedor::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->actingAs($this->admin)->post(route('compras.store'), $this->datosCompra($orden, [[$a, 3, 4]], 'F009-1', $otroProveedor->id))
            ->assertSessionHasErrors('proveedor_id');
        $this->assertSame(0, Compra::count());
        $this->assertEquals(0.0, $this->stock($a));

        // Cerrar solo si está en parte; editar y anular mientras no reciba nada
        $this->actingAs($this->admin)->patch(route('ordenes.cerrar', $orden))->assertSessionHasErrors('orden');
        $this->actingAs($this->admin)->put(route('ordenes.update', $orden), [
            'proveedor_id' => $this->proveedor->id, 'almacen_id' => $this->almacen($this->empresa)->id, 'fecha_emision' => now()->toDateString(),
            'condicion_pago' => 'credito', 'afecto_igv' => 1, 'productos' => [['id' => $a->id, 'cantidad' => 5, 'costo' => 4]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(23.6, $orden->refresh()->total);
        $this->actingAs($this->admin)->patch(route('ordenes.anular', $orden))->assertSessionHasNoErrors();
        $this->assertSame('anulada', $orden->refresh()->estado);

        // Producto repetido en la orden
        $this->actingAs($this->admin)->post(route('ordenes.store'), [
            'proveedor_id' => $this->proveedor->id, 'almacen_id' => $this->almacen($this->empresa)->id, 'fecha_emision' => now()->toDateString(),
            'condicion_pago' => 'contado', 'productos' => [['id' => $a->id, 'cantidad' => 1, 'costo' => 1], ['id' => $a->id, 'cantidad' => 1, 'costo' => 1]],
        ])->assertSessionHasErrors('productos.1.id');

        // Otra empresa no la ve; sin permiso de compras no entra
        $ajeno = $this->usuarioCon('Administrador', $this->crearEmpresa());
        $this->actingAs($ajeno)->get(route('ordenes.show', $orden))->assertNotFound();
        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))->get(route('ordenes.index'))->assertForbidden();
    }

    /** @param  array<int, array{0: Producto, 1: float, 2: float}>  $lineas */
    private function ordenar(array $lineas, array $extra = []): OrdenCompra
    {
        $this->actingAs($this->admin)->post(route('ordenes.store'), $extra + [
            'proveedor_id' => $this->proveedor->id, 'almacen_id' => $this->almacen($this->empresa)->id,
            'fecha_emision' => now()->toDateString(), 'fecha_entrega' => now()->addDays(3)->toDateString(),
            'condicion_pago' => 'credito', 'afecto_igv' => 1,
            'productos' => array_map(fn ($l) => ['id' => $l[0]->id, 'cantidad' => $l[1], 'costo' => $l[2]], $lineas),
        ])->assertSessionHasNoErrors();

        return OrdenCompra::latest('id')->firstOrFail();
    }

    private function comprar(OrdenCompra $orden, array $lineas, string $comprobante): Compra
    {
        $this->app->forgetScopedInstances();
        $this->actingAs($this->admin)->post(route('compras.store'), $this->datosCompra($orden, $lineas, $comprobante))->assertSessionHasNoErrors();

        return Compra::latest('id')->firstOrFail();
    }

    private function datosCompra(OrdenCompra $orden, array $lineas, string $comprobante, ?int $proveedorId = null): array
    {
        [$serie, $numero] = explode('-', $comprobante);

        return [
            'orden_compra_id' => $orden->id, 'proveedor_id' => $proveedorId ?? $this->proveedor->id, 'almacen_id' => $this->almacen($this->empresa)->id,
            'tipo_comprobante' => 'factura', 'serie' => $serie, 'numero' => $numero, 'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN', 'afecto_igv' => 1, 'precios_incluyen_igv' => 0, 'condicion_pago' => 'credito',
            'fecha_vencimiento' => now()->addDays(30)->toDateString(),
            'productos' => array_map(fn ($l) => ['id' => $l[0]->id, 'cantidad' => $l[1], 'costo' => $l[2]], $lineas),
        ];
    }

    private function stock(Producto $p): float
    {
        return (float) AlmacenProducto::where('producto_id', $p->id)->value('stock');
    }
}
