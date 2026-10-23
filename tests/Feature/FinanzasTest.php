<?php

namespace Tests\Feature;

use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Erp\Models\Compra;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Erp\Models\Pago;
use App\Modulos\Erp\Models\Proveedor;
use App\Modulos\Erp\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

class FinanzasTest extends TestCase
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

    public function test_venta_al_credito_con_adelanto_queda_en_cuentas_por_cobrar(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $cliente = $this->cliente();
        $producto = $this->productoConStock($this->empresa, 10, ['precio_unitario' => 50]);

        $this->actingAs($admin)->post(route('ventas.store'), $this->venta($producto, 2, $cliente, ['adelanto' => 20]))->assertRedirect();

        $venta = Venta::firstOrFail();
        $this->assertSame(Venta::CREDITO, $venta->condicion_pago);
        $this->assertSame(now()->addDays(15)->toDateString(), $venta->fecha_vencimiento->toDateString());
        $this->assertEquals(20.0, $venta->montoPagado());
        $this->assertEquals(round($venta->total - 20, 2), $venta->saldo());

        $this->actingAs($admin)->get(route('cuentas-cobrar.index'))->assertOk()
            ->assertSee($venta->numero_completo)->assertSee($cliente->nombre_completo);
        $this->actingAs($admin)->get(route('cuentas-cobrar.index', ['vista' => 'terceros']))->assertOk()->assertSee($cliente->nombre_completo);
        $this->actingAs($admin)->get(route('ventas.show', $venta))->assertOk()->assertSee('Registrar cobro');
    }

    public function test_venta_al_credito_exige_cliente_y_adelanto_menor_al_total(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $producto = $this->productoConStock($this->empresa, 10, ['precio_unitario' => 50]);

        $this->actingAs($admin)->post(route('ventas.store'), $this->venta($producto, 1, null))->assertSessionHasErrors('cliente_id');
        $this->actingAs($admin)->post(route('ventas.store'), $this->venta($producto, 1, $this->cliente(), ['adelanto' => 500]))->assertSessionHasErrors('adelanto');

        // Un cliente de otra empresa no existe para esta
        $ajeno = Cliente::factory()->create(['empresa_id' => $this->crearEmpresa()->id]);
        $this->actingAs($admin)->post(route('ventas.store'), $this->venta($producto, 1, $ajeno))->assertSessionHasErrors('cliente_id');

        $this->assertSame(0, Venta::count());
    }

    public function test_cobro_parcial_total_y_anulacion(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $venta = $this->ventaCredito($admin, $this->cliente(), 3);
        $total = (float) $venta->total;

        // No se puede cobrar más del saldo
        $this->actingAs($admin)->post(route('cuentas-cobrar.registrar', $venta), $this->pago($total + 1))->assertSessionHasErrors('monto');

        $this->actingAs($admin)->post(route('cuentas-cobrar.registrar', $venta), $this->pago(10))->assertSessionHas('status');
        $this->assertEquals(round($total - 10, 2), $venta->refresh()->saldo());
        $this->assertSame('parcial', $venta->estadoPago());

        $this->actingAs($admin)->post(route('cuentas-cobrar.registrar', $venta), $this->pago($total - 10))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'quedó cancelado'));
        $this->assertEquals(0.0, $venta->refresh()->saldo());

        // Anular el último cobro devuelve el saldo
        $ultimo = Pago::where('venta_id', $venta->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->patch(route('finanzas.movimientos.anular', $ultimo))->assertSessionHas('status');
        $this->assertSame(Pago::ANULADO, $ultimo->refresh()->estado);
        $this->assertEquals(round($total - 10, 2), $venta->refresh()->saldo());
    }

    public function test_abono_global_se_aplica_del_documento_mas_antiguo(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $cliente = $this->cliente();
        $nueva = $this->ventaCredito($admin, $cliente, 1, now()->addDays(30));
        $antigua = $this->ventaCredito($admin, $cliente, 1, now()->addDays(5));
        $monto = round($antigua->total + 5, 2);

        $this->actingAs($admin)->get(route('cuentas-cobrar.cuenta', $cliente))->assertOk()
            ->assertSee($antigua->numero_completo)->assertSee('Registrar abono');

        $this->actingAs($admin)->post(route('cuentas-cobrar.abonar', $cliente), $this->pago($monto))
            ->assertSessionHas('status', fn ($m) => str_contains($m, '2 documentos'));

        $this->assertEquals(0.0, $antigua->refresh()->saldo());
        $this->assertEquals(round($nueva->total - 5, 2), $nueva->refresh()->saldo());

        // Un abono mayor a la deuda se rechaza
        $this->actingAs($admin)->post(route('cuentas-cobrar.abonar', $cliente), $this->pago(99999))->assertSessionHasErrors('monto');
    }

    public function test_cuentas_por_pagar_y_permisos(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $proveedor = Proveedor::factory()->create(['empresa_id' => $this->empresa->id]);
        $producto = $this->productoConStock($this->empresa, 0);

        $this->actingAs($admin)->post(route('compras.store'), [
            'proveedor_id' => $proveedor->id, 'almacen_id' => $this->almacen($this->empresa)->id,
            'tipo_comprobante' => 'factura', 'serie' => 'F001', 'numero' => '77', 'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN', 'afecto_igv' => 1, 'precios_incluyen_igv' => 1, 'condicion_pago' => 'credito',
            'fecha_vencimiento' => now()->addDays(20)->toDateString(),
            'productos' => [['id' => $producto->id, 'cantidad' => 10, 'costo' => 5]],
        ])->assertRedirect();

        $compra = Compra::firstOrFail();
        $this->assertSame(now()->addDays(20)->toDateString(), $compra->fecha_vencimiento->toDateString());
        $this->assertEquals(50.0, $compra->saldo());

        $this->actingAs($admin)->get(route('cuentas-pagar.index'))->assertOk()->assertSee($compra->numero_completo);
        $this->actingAs($admin)->get(route('cuentas-pagar.cuenta', $proveedor))->assertOk()->assertSee($proveedor->nombre);

        $this->actingAs($admin)->post(route('cuentas-pagar.registrar', $compra), $this->pago(30, 'transferencia'))->assertSessionHas('status');
        $this->assertEquals(20.0, $compra->refresh()->saldo());

        // El vendedor cobra, pero no ve ni paga a proveedores; el contador sí
        $vendedor = $this->usuarioCon('Vendedor', $this->empresa);
        $this->actingAs($vendedor)->get(route('cuentas-cobrar.index'))->assertOk();
        $this->actingAs($vendedor)->get(route('cuentas-pagar.index'))->assertForbidden();
        $this->actingAs($vendedor)->post(route('cuentas-pagar.registrar', $compra), $this->pago(5))->assertForbidden();
        $this->actingAs($vendedor)->patch(route('finanzas.movimientos.anular', Pago::where('compra_id', $compra->id)->firstOrFail()))->assertForbidden();

        $this->actingAs($this->usuarioCon('Contador', $this->empresa))->get(route('cuentas-pagar.index'))->assertOk();
        $this->actingAs($this->usuarioCon('Almacenero', $this->empresa))->get(route('cuentas-cobrar.index'))->assertForbidden();
    }

    public function test_movimientos_lista_exporta_y_aisla_empresas(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $venta = $this->ventaCredito($admin, $this->cliente(), 2);
        $this->actingAs($admin)->post(route('cuentas-cobrar.registrar', $venta), $this->pago(7, 'yape') + ['referencia' => 'OP-998877']);

        $this->actingAs($admin)->get(route('finanzas.movimientos.index'))->assertOk()->assertSee('OP-998877');
        $this->actingAs($admin)->get(route('finanzas.movimientos.index', ['q' => 'OP-998877', 'tipo' => 'cobro']))->assertOk()->assertSee($venta->numero_completo);
        $csv = $this->actingAs($admin)->get(route('finanzas.movimientos.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('OP-998877', $csv);

        // Otra empresa no puede cobrar ni ver la venta ajena
        $otra = $this->crearEmpresa();
        $ajeno = $this->usuarioCon('Administrador', $otra);
        $this->actingAs($ajeno)->post(route('cuentas-cobrar.registrar', $venta), $this->pago(1))->assertNotFound();
        $this->actingAs($ajeno)->get(route('cuentas-cobrar.index'))->assertOk()->assertDontSee($venta->numero_completo);
        $this->actingAs($ajeno)->get(route('finanzas.movimientos.index'))->assertOk()->assertDontSee('OP-998877');
    }

    /* ───────────────────────── Auxiliares ───────────────────────── */

    private function cliente(): Cliente
    {
        $c = Cliente::factory()->create(['empresa_id' => $this->empresa->id]);

        return $c;
    }

    private function ventaCredito($user, Cliente $cliente, int $cantidad, $vence = null): Venta
    {
        $producto = $this->productoConStock($this->empresa, 10, ['precio_unitario' => 40]);
        $this->actingAs($user)->post(route('ventas.store'), $this->venta($producto, $cantidad, $cliente, array_filter([
            'fecha_vencimiento' => $vence?->toDateString(),
        ])))->assertSessionHasNoErrors();

        return Venta::latest('id')->firstOrFail();
    }

    private function venta($producto, int $cantidad, ?Cliente $cliente, array $extra = []): array
    {
        return $extra + [
            'accion'            => 'registrar',
            'correlativo_id'    => Correlativo::where('empresa_id', $this->empresa->id)->value('id'),
            'almacen_id'        => $this->almacen($this->empresa)->id,
            'cliente_id'        => $cliente?->id,
            'fecha_emision'     => now()->toDateString(),
            'metodo_pago'       => 'efectivo',
            'condicion_pago'    => 'credito',
            'fecha_vencimiento' => now()->addDays(15)->toDateString(),
            'productos'         => [['id' => $producto->id, 'cantidad' => $cantidad]],
        ];
    }

    private function pago(float $monto, string $metodo = 'efectivo'): array
    {
        return ['monto' => round($monto, 2), 'metodo_pago' => $metodo, 'fecha_pago' => now()->toDateString()];
    }
}
