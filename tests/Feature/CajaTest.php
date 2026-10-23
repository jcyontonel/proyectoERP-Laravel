<?php

namespace Tests\Feature;

use App\Modulos\Erp\Models\CajaSesion;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Erp\Models\Pago;
use App\Modulos\Erp\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

class CajaTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa();
        $this->crearCaja($this->empresa);
        $this->crearCorrelativoVenta($this->empresa);
    }

    public function test_flujo_completo_apertura_venta_egreso_y_cierre(): void
    {
        $vendedor = $this->usuarioCon('Vendedor', $this->empresa);
        $producto = $this->productoConStock($this->empresa, 10, ['precio_unitario' => 25]);

        // Sin caja abierta: la pantalla ofrece abrirla
        $this->actingAs($vendedor)->get(route('caja.index'))->assertOk()->assertSee('Abrir turno');

        $this->actingAs($vendedor)->post(route('caja.abrir'), ['caja_id' => $this->cajaId(), 'monto_apertura' => 100])
            ->assertRedirect(route('caja.index'));

        // Venta en efectivo: se cobra en el turno
        $this->actingAs($vendedor)->post(route('ventas.store'), $this->venta($producto, 2))->assertRedirect();
        $venta = Venta::latest('id')->first();
        $this->assertSame(Venta::COMPLETADA, $venta->estado);
        $this->assertNotNull($venta->caja_sesion_id);
        $this->assertSame(1, Pago::where('venta_id', $venta->id)->where('tipo', 'cobro')->count());

        // Egreso manual
        $this->actingAs($vendedor)->post(route('caja.movimientos.store'), [
            'tipo' => 'egreso', 'concepto' => 'Movilidad', 'metodo_pago' => 'efectivo', 'monto' => 15,
        ])->assertSessionHas('status');

        $sesion = CajaSesion::firstOrFail();
        $this->assertEquals(135.0, $sesion->efectivoEsperado());   // 100 + 50 − 15

        $this->actingAs($vendedor)->get(route('caja.index'))->assertOk()->assertSee('S/ 135.00')->assertSee('Movilidad');

        // Diferencia sin observación → error; con observación → cierra
        $this->actingAs($vendedor)->post(route('caja.cerrar'), ['denominaciones' => ['100' => 1, '20' => 1]])
            ->assertSessionHasErrors('observacion');

        $this->actingAs($vendedor)->post(route('caja.cerrar'), ['denominaciones' => ['100' => 1, '20' => 1, '10' => 1, '5' => 1]])
            ->assertRedirect(route('caja.turnos.show', $sesion));

        $sesion->refresh();
        $this->assertSame(CajaSesion::CERRADA, $sesion->estado);
        $this->assertEquals(135.0, $sesion->monto_contado);
        $this->assertEquals(0.0, $sesion->diferencia);

        $this->actingAs($vendedor)->get(route('caja.turnos.show', $sesion))->assertOk()->assertSee('Cuadra');
        $this->actingAs($vendedor)->get(route('caja.turnos.imprimir', $sesion))->assertOk()->assertSee('ARQUEO DE CAJA');
    }

    public function test_sin_caja_abierta_solo_se_guardan_borradores(): void
    {
        $vendedor = $this->usuarioCon('Vendedor', $this->empresa);
        $producto = $this->productoConStock($this->empresa, 10);

        $this->actingAs($vendedor)->post(route('ventas.store'), $this->venta($producto, 1))->assertSessionHasErrors('caja');
        $this->actingAs($vendedor)->post(route('ventas.store'), $this->venta($producto, 1, 'borrador'))->assertRedirect();

        $this->assertSame(Venta::BORRADOR, Venta::firstOrFail()->estado);
    }

    public function test_una_caja_solo_admite_un_turno_abierto(): void
    {
        $uno = $this->usuarioCon('Vendedor', $this->empresa);
        $otro = $this->usuarioCon('Vendedor', $this->empresa);

        $this->actingAs($uno)->post(route('caja.abrir'), ['caja_id' => $this->cajaId(), 'monto_apertura' => 0]);
        $this->actingAs($otro)->post(route('caja.abrir'), ['caja_id' => $this->cajaId(), 'monto_apertura' => 0])
            ->assertSessionHasErrors('caja_id');
    }

    public function test_anular_una_venta_anula_su_cobro(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $producto = $this->productoConStock($this->empresa, 10, ['precio_unitario' => 30]);

        $this->actingAs($admin)->post(route('caja.abrir'), ['caja_id' => $this->cajaId(), 'monto_apertura' => 0]);
        $this->actingAs($admin)->post(route('ventas.store'), $this->venta($producto, 1));
        $venta = Venta::firstOrFail();

        $this->actingAs($admin)->patch(route('ventas.anular', $venta))->assertSessionHas('status');

        $this->assertSame(Pago::ANULADO, Pago::where('venta_id', $venta->id)->value('estado'));
        $this->assertEquals(0.0, CajaSesion::firstOrFail()->efectivoEsperado());
    }

    public function test_el_cajero_no_ve_turnos_ajenos_ni_de_otra_empresa(): void
    {
        $uno = $this->usuarioCon('Vendedor', $this->empresa);
        $otro = $this->usuarioCon('Vendedor', $this->empresa);
        $this->actingAs($uno)->post(route('caja.abrir'), ['caja_id' => $this->cajaId(), 'monto_apertura' => 0]);
        $sesion = CajaSesion::firstOrFail();

        $this->actingAs($otro)->get(route('caja.turnos.show', $sesion))->assertForbidden();
        $this->actingAs($this->usuarioCon('Administrador', $this->empresa))->get(route('caja.turnos.show', $sesion))->assertOk();

        $ajena = $this->crearEmpresa();
        $this->actingAs($this->usuarioCon('Administrador', $ajena))->get(route('caja.turnos.show', $sesion))->assertNotFound();
    }

    public function test_el_almacenero_no_tiene_acceso_a_caja(): void
    {
        $this->actingAs($this->usuarioCon('Almacenero', $this->empresa))->get(route('caja.index'))->assertForbidden();
    }

    private function cajaId(): int
    {
        return \App\Modulos\Erp\Models\Caja::where('empresa_id', $this->empresa->id)->value('id');
    }

    private function venta($producto, int $cantidad, string $accion = 'registrar'): array
    {
        return [
            'accion'         => $accion,
            'correlativo_id' => \App\Modulos\Erp\Models\Correlativo::where('empresa_id', $this->empresa->id)->value('id'),
            'almacen_id'     => $this->almacen($this->empresa)->id,
            'fecha_emision'  => now()->toDateString(),
            'metodo_pago'    => 'efectivo',
            'productos'      => [['id' => $producto->id, 'cantidad' => $cantidad]],
        ];
    }
}
