<?php

namespace Tests\Feature;

use App\Modulos\Erp\Models\Caja;
use App\Modulos\Facturacion\Models\ComprobanteSunat;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Erp\Services\EmpresaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** ERP ↔ facturador electrónico: envío, sin conexión, reintento, webhook y baja */
class FacturacionElectronicaTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private const URL = 'https://facturador.test/api/v1';

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['facturador.url' => self::URL, 'facturador.token' => 'fct_prueba', 'facturador.webhook_secreto' => 'whs_secreto']);
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa(['ruc' => '20123456789', 'igv' => 18, 'precios_incluyen_igv' => true]);
        app(EmpresaService::class)->series($this->empresa);
        $this->crearCaja($this->empresa);
        $this->admin = $this->usuarioCon('Administrador', $this->empresa);
        $this->actingAs($this->admin)->post(route('caja.abrir'), ['caja_id' => Caja::where('empresa_id', $this->empresa->id)->value('id'), 'monto_apertura' => 100]);
    }

    public function test_la_boleta_se_envia_al_facturador_y_queda_aceptada(): void
    {
        Http::fake([self::URL . '/comprobantes' => Http::response($this->respuesta('aceptado'), 201)]);

        $venta = $this->vender()->assertSessionHas('status', fn ($m) => str_contains($m, 'SUNAT: aceptado'));
        $venta = Venta::latest('id')->firstOrFail();

        Http::assertSent(function (Request $r) use ($venta) {
            $item = $r['items'][0];

            return $r->url() === self::URL . '/comprobantes' && $r->hasHeader('Authorization', 'Bearer fct_prueba')
                && $r['emisor'] === '20123456789' && $r['tipo'] === '03' && $r['serie'] === 'B001' && $r['numero'] === (int) $venta->numero
                && $r['cliente']['tipo_doc'] === '0' && $r['referencia'] === "venta:{$venta->id}"
                && $item['cantidad'] == 2 && $item['precio_unitario'] == 11.8 && $item['valor_venta'] == 20.0 && $item['igv'] == 3.6;
        });

        $reg = $venta->sunat;
        $this->assertSame('aceptado', $reg->estado);
        $this->assertSame('uuid-1', $reg->facturador_id);
        $this->assertSame('0', $reg->codigo_respuesta);
        $this->actingAs($this->admin)->get(route('ventas.show', $venta))->assertOk()->assertSee('Aceptado por SUNAT');
        $this->actingAs($this->admin)->get(route('sunat.index'))->assertOk()->assertSee($venta->numero_completo);
    }

    public function test_sin_conexion_queda_pendiente_y_el_timer_la_envia(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        $this->vender()->assertSessionHas('status', fn ($m) => str_contains($m, 'pendiente de envío'));

        $reg = ComprobanteSunat::firstOrFail();
        $this->assertSame('pendiente', $reg->estado);
        $this->assertNull($reg->facturador_id);
        $this->assertNotNull($reg->proximo_intento_at);
        $this->assertSame(1, Venta::count());   // la venta se registró igual

        // Volvió la conexión
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([self::URL . '/comprobantes' => Http::response($this->respuesta('aceptado'), 201)]);
        $this->travel(2)->minutes();
        $this->artisan('facturacion:sincronizar')->assertSuccessful();

        $this->assertSame('aceptado', $reg->refresh()->estado);
        $this->assertSame(2, $reg->intentos);
    }

    public function test_si_la_empresa_no_esta_en_el_facturador_la_registra_y_reintenta(): void
    {
        Http::fake([
            self::URL . '/emisores/*' => Http::response(['ruc' => '20123456789'], 201),
            self::URL . '/comprobantes' => Http::sequence()
                ->push(['message' => 'x', 'errors' => ['emisor' => ['El RUC 20123456789 no está registrado']]], 422)
                ->push($this->respuesta('aceptado'), 201),
        ]);

        $this->vender();

        $this->assertSame('aceptado', ComprobanteSunat::firstOrFail()->estado);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/emisores/20123456789') && $r['modo'] === 'beta');
    }

    public function test_webhook_firmado_actualiza_el_estado(): void
    {
        Http::fake([self::URL . '/comprobantes' => Http::response($this->respuesta('pendiente'), 201)]);
        $this->vender();
        $reg = ComprobanteSunat::firstOrFail();
        $this->assertSame('pendiente', $reg->estado);
        $this->assertSame('uuid-1', $reg->facturador_id);   // ya está en el facturador: él reintenta

        $cuerpo = json_encode(['evento' => 'comprobante.actualizado', 'comprobante' => $this->respuesta('aceptado')]);
        $t = time();
        $this->call('POST', '/api/facturador/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FACTURADOR_FIRMA' => "t={$t},v1=" . hash_hmac('sha256', "{$t}.{$cuerpo}", 'whs_secreto')], $cuerpo)
            ->assertNoContent();
        $this->assertSame('aceptado', $reg->refresh()->estado);

        // Firma inválida o vencida
        $this->call('POST', '/api/facturador/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FACTURADOR_FIRMA' => "t={$t},v1=falsa"], $cuerpo)->assertUnauthorized();
        $viejo = $t - 3600;
        $this->call('POST', '/api/facturador/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FACTURADOR_FIRMA' => "t={$viejo},v1=" . hash_hmac('sha256', "{$viejo}.{$cuerpo}", 'whs_secreto')], $cuerpo)->assertUnauthorized();
    }

    public function test_anular_la_venta_pide_la_baja_en_sunat(): void
    {
        Http::fake([
            self::URL . '/comprobantes' => Http::response($this->respuesta('aceptado'), 201),
            self::URL . '/comprobantes/uuid-1/anular' => Http::response($this->respuesta('aceptado', ['baja' => ['estado' => 'enviado', 'ticket' => '170000']]), 202),
        ]);
        $this->vender();
        $venta = Venta::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->patch(route('ventas.anular', $venta))->assertSessionHas('status', fn ($m) => str_contains($m, 'Se pidió la baja a SUNAT'));

        $reg = $venta->sunat;
        $this->assertSame('enviado', $reg->baja_estado);
        $this->assertSame('170000', $reg->ticket);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/anular') && $r['motivo'] === 'ANULACION DE LA OPERACION');
        $this->actingAs($this->admin)->get(route('sunat.resumenes'))->assertOk()->assertSee($venta->numero_completo);
    }

    public function test_sin_configuracion_no_se_envia_nada(): void
    {
        config(['facturador.url' => null]);
        Http::fake();

        $this->vender()->assertSessionHas('status', fn ($m) => ! str_contains($m, 'SUNAT'));

        Http::assertNothingSent();
        $this->assertSame(0, ComprobanteSunat::count());
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

    private function respuesta(string $estado, array $extra = []): array
    {
        return array_replace_recursive([
            'id' => 'uuid-1', 'estado' => $estado, 'aceptado' => $estado === 'aceptado', 'hash' => 'abc123=', 'nombre_archivo' => '20123456789-03-B001-1',
            'sunat' => ['codigo' => $estado === 'aceptado' ? '0' : null, 'descripcion' => $estado === 'aceptado' ? 'La Boleta numero B001-1, ha sido aceptada' : null, 'observaciones' => [], 'ticket' => null],
            'envio' => ['intentos' => 1, 'ultimo_error' => $estado === 'pendiente' ? '[HTTP] Could not connect to host' : null],
            'baja' => null,
        ], $extra);
    }
}
