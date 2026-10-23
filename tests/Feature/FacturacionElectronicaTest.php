<?php

namespace Tests\Feature;

use App\Modulos\Erp\Models\Caja;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Erp\Services\EmpresaService;
use App\Modulos\Facturacion\Jobs\EnviarDocumento;
use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Facturacion\Sunat\RespuestaSunat;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ConEmpresa;
use Tests\Fakes\PasarelaFalsa;
use Tests\TestCase;

/** ERP → facturación electrónica de la plataforma: firma, envío en cola, sin conexión, reintento y baja */
class FacturacionElectronicaTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa(['ruc' => '20123456789', 'igv' => 18, 'precios_incluyen_igv' => true]);
        app(EmpresaService::class)->series($this->empresa);
        $this->crearCaja($this->empresa);
        $this->admin = $this->usuarioCon('Administrador', $this->empresa);
        $this->actingAs($this->admin)->post(route('caja.abrir'), ['caja_id' => Caja::where('empresa_id', $this->empresa->id)->value('id'), 'monto_apertura' => 100]);
    }

    public function test_la_boleta_se_firma_se_envia_y_queda_aceptada(): void
    {
        $this->vender()->assertSessionHas('status', fn ($m) => str_contains($m, 'SUNAT: aceptado'));
        $venta = Venta::latest('id')->firstOrFail();

        $this->assertCount(1, $this->sunat->llamadas);
        $llamada = $this->sunat->llamadas[0];
        $this->assertSame("20123456789-03-B001-{$venta->numero}", $llamada['nombre']);
        $this->assertStringContainsString('<ds:SignatureValue>', $llamada['xml']);
        $this->assertStringContainsString('VEINTITRÉS CON 60/100 SOLES', $llamada['xml']);   // 2 × 11.80

        $doc = $venta->sunat;
        $this->assertSame('aceptado', $doc->estado);
        $this->assertSame('0', $doc->sunat_codigo);
        $this->assertSame("venta:{$venta->id}", $doc->referencia);
        $this->assertEquals(23.6, $doc->total);
        $this->assertEquals(3.6, $doc->datos['totales']['igv']);
        $this->actingAs($this->admin)->get(route('ventas.show', $venta))->assertOk()->assertSee('Aceptado por SUNAT');
        $this->actingAs($this->admin)->get(route('sunat.index'))->assertOk()->assertSee($venta->numero_completo);
        $this->actingAs($this->admin)->get(route('sunat.archivo', [$doc, 'xml']))->assertOk()->assertHeader('Content-Type', 'application/xml');
        $this->actingAs($this->admin)->get(route('sunat.archivo', [$doc, 'cdr']))->assertOk();
    }

    public function test_en_cola_la_venta_no_espera_a_sunat(): void
    {
        Queue::fake();

        $this->vender()->assertSessionHas('status', fn ($m) => str_contains($m, 'se envía en segundo plano'));

        $doc = Documento::firstOrFail();
        $this->assertSame('pendiente', $doc->estado);
        $this->assertNotNull($doc->xml_path);   // ya firmado
        $this->assertSame([], $this->sunat->llamadas);
        Queue::assertPushed(EnviarDocumento::class, fn ($job) => $job->documentoId === $doc->id);
    }

    public function test_sin_conexion_queda_pendiente_y_el_timer_la_envia(): void
    {
        $this->sunat->encolar(PasarelaFalsa::sinConexion());
        $this->vender()->assertSessionHas('status', fn ($m) => str_contains($m, 'pendiente de envío'));

        $doc = Documento::firstOrFail();
        $this->assertSame('pendiente', $doc->estado);
        $this->assertSame('[HTTP] Could not connect to host', $doc->ultimo_error);
        $this->assertSame(1, Venta::count());   // la venta se registró igual

        // Aún no toca reintentar; pasado el minuto vuelve la conexión
        $this->artisan('facturacion:procesar')->assertSuccessful();
        $this->assertCount(1, $this->sunat->llamadas);
        $this->travel(2)->minutes();
        $this->artisan('facturacion:procesar')->expectsOutputToContain('aceptados: 1');

        $this->assertSame('aceptado', $doc->refresh()->estado);
        $this->assertSame(2, $doc->intentos);
    }

    public function test_un_rechazo_se_corrige_y_se_reenvia_con_el_mismo_numero(): void
    {
        $this->sunat->encolar(new RespuestaSunat(RespuestaSunat::RECHAZADO, '2017', 'El numero de documento de identidad del receptor debe ser RUC'));
        $this->vender();
        $doc = Documento::firstOrFail();
        $this->assertSame('rechazado', $doc->estado);

        $this->actingAs($this->admin)->post(route('sunat.reenviar', $doc))->assertSessionHas('status', fn ($m) => str_contains($m, 'aceptado'));
        $this->assertSame('aceptado', $doc->refresh()->estado);
        $this->assertSame(1, Documento::count());
        $this->assertSame([$this->sunat->llamadas[0]['nombre']], array_unique(array_column($this->sunat->llamadas, 'nombre')));
    }

    public function test_anular_la_venta_pide_la_baja_y_el_ticket_la_confirma(): void
    {
        $this->vender();
        $venta = Venta::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->patch(route('ventas.anular', $venta))->assertSessionHas('status', fn ($m) => str_contains($m, 'Se pidió la baja a SUNAT'));

        $doc = $venta->sunat;
        $this->assertSame('enviado', $doc->baja_estado);
        $this->assertSame('RC', $doc->baja->tipo);   // boleta: resumen diario
        $this->assertStringContainsString('<cbc:ConditionCode>3</cbc:ConditionCode>', $this->sunat->llamadas[1]['xml']);
        $this->actingAs($this->admin)->get(route('sunat.resumenes'))->assertOk()->assertSee($venta->numero_completo);

        $this->travel(2)->minutes();
        $this->artisan('facturacion:procesar');
        $this->assertSame('anulado', $doc->refresh()->estado);
        $this->assertSame('aceptado', $doc->baja_estado);
    }

    public function test_anulada_antes_de_que_sunat_la_acepte_pide_la_baja_al_aceptarse(): void
    {
        $this->sunat->encolar(PasarelaFalsa::sinConexion());
        $this->vender();
        $venta = Venta::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->patch(route('ventas.anular', $venta))->assertSessionHas('status', fn ($m) => str_contains($m, 'la baja se pedirá al aceptarse'));
        $this->assertNull($venta->sunat->baja_id);

        $this->travel(2)->minutes();
        $this->artisan('facturacion:procesar');

        $doc = $venta->sunat()->first();
        $this->assertSame('aceptado', $doc->estado);
        $this->assertSame('RC', $doc->baja?->tipo);
        $this->assertSame(['sendBill', 'sendBill', 'sendSummary'], $this->sunat->metodos());
    }

    public function test_apagada_no_se_emite_nada(): void
    {
        config(['facturacion.activa' => false]);

        $this->vender()->assertSessionHas('status', fn ($m) => ! str_contains($m, 'SUNAT'));

        $this->assertSame([], $this->sunat->llamadas);
        $this->assertSame(0, Documento::count());
    }

    public function test_en_produccion_sin_sol_ni_certificado_queda_en_error_sin_frenar_la_venta(): void
    {
        $this->empresa->update(['sunat_modo' => 'produccion']);

        $this->vender()->assertSessionHas('status', fn ($m) => str_contains($m, 'error de envío'));

        $doc = Documento::firstOrFail();
        $this->assertSame('error', $doc->estado);
        $this->assertStringContainsString('usuario y clave SOL', $doc->ultimo_error);
        $this->assertSame(1, Venta::count());
        $this->assertSame([], $this->sunat->llamadas);
    }

    private function vender()
    {
        $this->app->forgetScopedInstances();
        $producto = $this->productoConStock($this->empresa, 10, ['precio_unitario' => 11.80]);

        return $this->actingAs($this->admin)->post(route('ventas.store'), [
            'accion' => 'registrar', 'correlativo_id' => Correlativo::where('empresa_id', $this->empresa->id)->where('serie', 'B001')->value('id'),
            'almacen_id' => $this->almacen($this->empresa)->id, 'fecha_emision' => now()->toDateString(), 'metodo_pago' => 'efectivo',
            'productos' => [['id' => $producto->id, 'cantidad' => 2]],
        ])->assertSessionHasNoErrors();
    }
}
