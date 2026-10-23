<?php

namespace Tests\Feature;

use App\Modulos\Erp\Models\Caja;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Erp\Models\Cotizacion;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Erp\Services\EmpresaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** Cotizaciones: totales iguales a la venta, estados y conversión en venta */
class CotizacionTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa();
        app(EmpresaService::class)->series($this->empresa);   // B001, CT01…
        $this->crearCaja($this->empresa);
        $this->admin = $this->usuarioCon('Administrador', $this->empresa);
    }

    public function test_crea_con_precios_del_catalogo_descuento_e_igv(): void
    {
        $a = $this->productoConStock($this->empresa, 0, ['precio_unitario' => 11.80]);
        $b = $this->productoConStock($this->empresa, 0, ['precio_unitario' => 5.90]);

        $this->actingAs($this->admin)->get(route('cotizaciones.create'))->assertOk()->assertSee($a->nombre);

        $this->actingAs($this->admin)->post(route('cotizaciones.store'), [
            'fecha_emision' => now()->toDateString(), 'descuento' => 10, 'condiciones' => 'Entrega en 48 h',
            'productos' => [['id' => $a->id, 'cantidad' => 2], ['id' => $b->id, 'cantidad' => 1]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $c = Cotizacion::with('detalles')->firstOrFail();
        $this->assertSame('CT01', $c->serie);
        $this->assertEquals(2.95, $c->descuento);          // 10 % de 29.50
        $this->assertEquals(26.55, $c->total);
        $this->assertEquals(22.50, $c->subtotal);
        $this->assertEquals(4.05, $c->total_impuestos);
        $this->assertEquals($c->total, round($c->detalles->sum('total'), 2));
        $this->assertSame(now()->addDays(15)->toDateString(), $c->vence_el->toDateString());

        $this->actingAs($this->admin)->get(route('cotizaciones.index'))->assertOk()->assertSee($c->numero_completo);
        $this->actingAs($this->admin)->get(route('cotizaciones.show', $c))->assertOk()->assertSee('S/ 26.55')->assertSee('Convertir en venta');
        $this->actingAs($this->admin)->get(route('cotizaciones.imprimir', $c))->assertOk()->assertSee('COTIZACIÓN')->assertSee('Entrega en 48 h');
    }

    public function test_convertir_en_venta_la_marca_y_anular_la_venta_la_libera(): void
    {
        $this->actingAs($this->admin)->post(route('caja.abrir'), ['caja_id' => Caja::where('empresa_id', $this->empresa->id)->value('id'), 'monto_apertura' => 50]);
        $p = $this->productoConStock($this->empresa, 10, ['precio_unitario' => 20]);
        $c = $this->cotizar($p, 3);

        $this->actingAs($this->admin)->patch(route('cotizaciones.estado', $c), ['estado' => 'aceptada'])->assertSessionHasNoErrors();
        $this->assertSame('aceptada', $c->refresh()->estado);
        $this->actingAs($this->admin)->get(route('cotizaciones.edit', $c))->assertRedirect(route('cotizaciones.show', $c));   // ya no se edita

        // Convertir: abre «Nueva venta» con los datos de la cotización
        $this->actingAs($this->admin)->post(route('cotizaciones.convertir', $c))
            ->assertRedirect(route('ventas.create'))
            ->assertSessionHasInput('cotizacion_id', $c->id)
            ->assertSessionHasInput('productos', [['id' => $p->id, 'cantidad' => 3.0]]);
        $this->actingAs($this->admin)->withSession(['_old_input' => ['cotizacion_id' => $c->id, 'cotizacion_ref' => $c->numero_completo]])
            ->get(route('ventas.create'))->assertOk()->assertSee('Venta desde la cotización')->assertSee('name="cotizacion_id"', false);

        $this->app->forgetScopedInstances();
        $this->actingAs($this->admin)->post(route('ventas.store'), [
            'accion' => 'registrar', 'correlativo_id' => Correlativo::where('empresa_id', $this->empresa->id)->where('serie', 'B001')->value('id'),
            'almacen_id' => $this->almacen($this->empresa)->id, 'fecha_emision' => now()->toDateString(), 'metodo_pago' => 'efectivo',
            'cotizacion_id' => $c->id, 'productos' => [['id' => $p->id, 'cantidad' => 3]],
        ])->assertSessionHasNoErrors();

        $venta = Venta::latest('id')->firstOrFail();
        $this->assertSame('convertida', $c->refresh()->estado);
        $this->assertSame($venta->id, $c->venta_id);
        $this->actingAs($this->admin)->get(route('cotizaciones.show', $c))->assertOk()->assertSee($venta->numero_completo)->assertDontSee('Convertir en venta');
        $this->actingAs($this->admin)->post(route('cotizaciones.convertir', $c))->assertSessionHasErrors('cotizacion');

        // Si la venta se anula, la cotización vuelve a estar disponible
        $this->actingAs($this->admin)->patch(route('ventas.anular', $venta));
        $this->assertSame('anulada', $venta->refresh()->estado);
        $this->assertSame('aceptada', $c->refresh()->estado);
        $this->assertNull($c->venta_id);
    }

    public function test_estados_edicion_y_restricciones(): void
    {
        $p = $this->productoConStock($this->empresa, 0, ['precio_unitario' => 10]);
        $c = $this->cotizar($p, 1);

        // Mientras está emitida se edita (sin cambiar el número)
        $this->actingAs($this->admin)->get(route('cotizaciones.edit', $c))->assertOk();
        $this->actingAs($this->admin)->put(route('cotizaciones.update', $c), [
            'fecha_emision' => now()->toDateString(), 'productos' => [['id' => $p->id, 'cantidad' => 4]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(40.0, $c->refresh()->total);
        $this->assertSame(1, (int) $c->numero);

        // Rechazar → reabrir → anular; transiciones inválidas
        $this->actingAs($this->admin)->patch(route('cotizaciones.estado', $c), ['estado' => 'rechazada'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('cotizaciones.convertir', $c))->assertSessionHasErrors('cotizacion');
        $this->actingAs($this->admin)->patch(route('cotizaciones.estado', $c), ['estado' => 'aceptada'])->assertSessionHasErrors('estado');
        $this->actingAs($this->admin)->patch(route('cotizaciones.estado', $c), ['estado' => 'emitida'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->patch(route('cotizaciones.estado', $c), ['estado' => 'anulada'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put(route('cotizaciones.update', $c), [
            'fecha_emision' => now()->toDateString(), 'productos' => [['id' => $p->id, 'cantidad' => 1]],
        ])->assertSessionHasErrors('cotizacion');

        // Vencida: se deduce de la validez
        $vieja = $this->cotizar($p, 1, ['fecha_emision' => now()->subDays(20)->toDateString(), 'vence_el' => now()->subDays(5)->toDateString()]);
        $this->assertSame('vencida', $vieja->estadoEfectivo());
        $this->actingAs($this->admin)->get(route('cotizaciones.index', ['estado' => 'vencida']))->assertOk()->assertSee($vieja->numero_completo)->assertDontSee($c->numero_completo);

        // Otra empresa no la ve; sin permiso de ventas no entra
        $otra = $this->crearEmpresa();
        $ajeno = $this->usuarioCon('Administrador', $otra);
        $this->actingAs($ajeno)->get(route('cotizaciones.show', $c))->assertNotFound();
        $this->actingAs($this->usuarioCon('Almacenero', $this->empresa))->get(route('cotizaciones.index'))->assertForbidden();
    }

    private function cotizar($producto, float $cantidad, array $extra = []): Cotizacion
    {
        $this->actingAs($this->admin)->post(route('cotizaciones.store'), $extra + [
            'fecha_emision' => now()->toDateString(), 'productos' => [['id' => $producto->id, 'cantidad' => $cantidad]],
        ])->assertSessionHasNoErrors();

        return Cotizacion::latest('id')->firstOrFail();
    }
}
