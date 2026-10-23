<?php

namespace Tests\Feature;

use App\Models\AlmacenProducto;
use App\Models\Cliente;
use App\Models\Correlativo;
use App\Models\Devolucion;
use App\Models\Empresa;
use App\Models\Kardex;
use App\Models\MovimientoCaja;
use App\Models\Nota;
use App\Models\Plan;
use App\Models\User;
use App\Models\Venta;
use App\Services\EmpresaService;
use App\Services\SuscripcionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** Notas de crédito y débito: stock, deuda, caja, series y límites */
class NotaTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa();
        app(EmpresaService::class)->series($this->empresa);   // B001, BC01, BD01, DV01…
        $this->crearCaja($this->empresa);
        $this->admin = $this->usuarioCon('Administrador', $this->empresa);
        $this->actingAs($this->admin)->post(route('caja.abrir'), ['caja_id' => \App\Models\Caja::where('empresa_id', $this->empresa->id)->value('id'), 'monto_apertura' => 100]);
    }

    public function test_devolucion_por_item_reingresa_stock_y_devuelve_efectivo(): void
    {
        [$venta, $producto] = $this->vender(3, 20);   // boleta de S/ 60 al contado, en efectivo
        $detalle = $venta->detalles->first();

        $this->actingAs($this->admin)->get(route('ventas.notas.create', [$venta, 'tipo' => '07']))->assertOk()->assertSee('Devolución por ítem');

        $this->actingAs($this->admin)->post(route('ventas.notas.store', $venta), [
            'tipo' => '07', 'motivo_codigo' => '07', 'items' => [$detalle->id => 1], 'metodo' => 'efectivo',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $nota = Nota::firstOrFail();
        $this->assertSame('BC01', $nota->serie);
        $this->assertEquals(20.0, $nota->total);
        $this->assertEquals(8.0, $this->stock($producto));                        // 10 − 3 + 1
        $this->assertTrue(Kardex::where('referencia_tipo', 'nota_credito')->where('referencia_id', $nota->id)->exists());
        $this->assertSame(1, Devolucion::where('nota_id', $nota->id)->count());
        $this->assertEquals(20.0, MovimientoCaja::where('concepto', "Nota de crédito {$nota->numero_completo}")->value('monto'));

        // No se puede devolver más de lo vendido; tampoco anular todo si ya hay notas
        $this->actingAs($this->admin)->post(route('ventas.notas.store', $venta), ['tipo' => '07', 'motivo_codigo' => '07', 'items' => [$detalle->id => 3], 'metodo' => 'ninguno'])
            ->assertSessionHasErrors('items');
        $this->actingAs($this->admin)->post(route('ventas.notas.store', $venta), ['tipo' => '07', 'motivo_codigo' => '01', 'metodo' => 'ninguno'])
            ->assertSessionHasErrors('motivo_codigo');

        $this->actingAs($this->admin)->get(route('notas.show', $nota))->assertOk()->assertSee('Devolución')->assertSee('S/ 20.00');
        $this->actingAs($this->admin)->get(route('notas.ticket', $nota))->assertOk()->assertSee('NOTA DE CRÉDITO ELECTRÓNICA');
        $this->actingAs($this->admin)->get(route('ventas.show', $venta))->assertOk()->assertSee($nota->numero_completo);
        $this->actingAs($this->admin)->get(route('notas.index'))->assertOk()->assertSee($nota->numero_completo);
    }

    public function test_anulacion_total_en_venta_al_credito_reduce_la_deuda_y_se_revierte(): void
    {
        $cliente = Cliente::factory()->create();
        $cliente->empresas()->attach($this->empresa->id);
        [$venta, $producto] = $this->vender(2, 25, ['condicion_pago' => 'credito', 'cliente_id' => $cliente->id, 'fecha_vencimiento' => now()->addDays(30)->toDateString()]);
        $this->assertEquals(50.0, $venta->saldo());

        $this->actingAs($this->admin)->post(route('ventas.notas.store', $venta), ['tipo' => '07', 'motivo_codigo' => '01', 'metodo' => 'efectivo'])
            ->assertSessionHasNoErrors();
        $nota = Nota::firstOrFail();

        $this->assertEquals(50.0, $nota->total);
        $this->assertEquals(0.0, $venta->refresh()->saldo());                     // la deuda se canceló con la nota
        $this->assertEquals(10.0, $this->stock($producto));                       // todo volvió
        $this->assertSame(0, MovimientoCaja::where('concepto', 'like', 'Nota de crédito%')->count());   // nada que devolver en efectivo

        // Anular: la deuda y el stock vuelven
        $this->actingAs($this->admin)->patch(route('notas.anular', $nota))->assertSessionHas('status');
        $this->assertSame(Nota::ANULADA, $nota->refresh()->estado);
        $this->assertEquals(50.0, $venta->refresh()->saldo());
        $this->assertEquals(8.0, $this->stock($producto));
        $this->assertTrue(Kardex::where('referencia_tipo', 'anulacion_nota')->exists());
    }

    public function test_nota_de_credito_por_monto_y_nota_de_debito(): void
    {
        [$venta] = $this->vender(1, 59);

        // Descuento de S/ 10 con IGV 18 %: base 8.47 + IGV 1.53; queda a favor del cliente
        $this->actingAs($this->admin)->post(route('ventas.notas.store', $venta), ['tipo' => '07', 'motivo_codigo' => '04', 'monto' => 10, 'metodo' => 'ninguno'])
            ->assertSessionHasNoErrors();
        $nc = Nota::where('tipo', '07')->firstOrFail();
        $this->assertEquals([8.47, 1.53, 10.0], [$nc->subtotal, $nc->total_impuestos, $nc->total]);
        $this->assertStringContainsString('Saldo a favor', $nc->observacion);

        // No puede superar lo que queda (59 − 10 = 49)
        $this->actingAs($this->admin)->post(route('ventas.notas.store', $venta), ['tipo' => '07', 'motivo_codigo' => '09', 'monto' => 50, 'metodo' => 'ninguno'])->assertSessionHasErrors('monto');

        // Débito: intereses de S/ 5 cobrados en efectivo (entra a la caja), serie BD01
        $this->actingAs($this->admin)->post(route('ventas.notas.store', $venta), ['tipo' => '08', 'motivo_codigo' => '01', 'monto' => 5, 'metodo' => 'efectivo'])
            ->assertSessionHasNoErrors();
        $nd = Nota::where('tipo', '08')->firstOrFail();
        $this->assertSame('BD01', $nd->serie);
        $this->assertSame('ingreso', MovimientoCaja::where('concepto', "Nota de débito {$nd->numero_completo}")->value('tipo'));
    }

    public function test_restricciones(): void
    {
        // Sobre un ticket (serie T001, creada con las series iniciales) no se emiten notas
        [$ticket] = $this->vender(1, 10, [], 'T001');
        $this->actingAs($this->admin)->get(route('ventas.notas.create', $ticket))->assertRedirect(route('ventas.show', $ticket));
        $this->actingAs($this->admin)->post(route('ventas.notas.store', $ticket), ['tipo' => '07', 'motivo_codigo' => '04', 'monto' => 1, 'metodo' => 'ninguno'])->assertSessionHasErrors('venta');

        [$venta] = $this->vender(1, 10);

        // Permisos: el vendedor no emite notas; el contador sí
        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))->get(route('ventas.notas.create', $venta))->assertForbidden();
        $this->actingAs($this->usuarioCon('Contador', $this->empresa))->get(route('ventas.notas.create', $venta))->assertOk();

        // La nota consume un comprobante del plan
        $this->seed(PlanSeeder::class);
        $plan = Plan::where('codigo', 'basico')->first();
        $plan->update(['max_comprobantes_mes' => 2]);   // ya hay 2 ventas emitidas este mes
        app(SuscripcionService::class)->asignar($this->empresa, $plan, ['periodicidad' => 'mensual', 'pagado' => true]);
        $this->app->forgetScopedInstances();
        $this->actingAs($this->admin)->post(route('ventas.notas.store', $venta), ['tipo' => '07', 'motivo_codigo' => '04', 'monto' => 1, 'metodo' => 'ninguno'])
            ->assertSessionHasErrors('suscripcion');
    }

    /* ───────────────────────── Auxiliares ───────────────────────── */

    /** @return array{0: Venta, 1: \App\Models\Producto} */
    private function vender(float $cantidad, float $precio, array $extra = [], string $serie = 'B001'): array
    {
        $this->app->forgetScopedInstances();
        $producto = $this->productoConStock($this->empresa, 10, ['precio_unitario' => $precio]);
        $this->actingAs($this->admin)->post(route('ventas.store'), $extra + [
            'accion' => 'registrar', 'correlativo_id' => Correlativo::where('empresa_id', $this->empresa->id)->where('serie', $serie)->value('id'),
            'almacen_id' => $this->almacen($this->empresa)->id, 'fecha_emision' => now()->toDateString(), 'metodo_pago' => 'efectivo',
            'productos' => [['id' => $producto->id, 'cantidad' => $cantidad]],
        ])->assertSessionHasNoErrors();
        $this->app->forgetScopedInstances();

        return [Venta::latest('id')->with('detalles')->firstOrFail(), $producto];
    }

    private function stock($producto): float
    {
        return (float) AlmacenProducto::where('producto_id', $producto->id)->value('stock');
    }
}
