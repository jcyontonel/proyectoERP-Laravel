<?php

namespace Tests\Feature;

use App\Modulos\Facturacion\Models\ComprobanteSunat;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Suscripciones\Models\PagoSuscripcion;
use App\Modulos\Suscripciones\Models\Plan;
use App\Modulos\Suscripciones\Services\SuscripcionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** Cada pago de suscripción emite factura/boleta de la plataforma y la envía a SUNAT por el facturador */
class ComprobanteSuscripcionTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private const TOKEN = 'token-de-prueba-0123456789abcdef';
    private const URL = 'https://facturador.test/api/v1';

    private Empresa $plataforma;

    private Empresa $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->seed(PlanSeeder::class);
        $this->plataforma = $this->crearEmpresa(['ruc' => '20111111111', 'razon_social' => 'RICOPET SOFTWARE SAC', 'igv' => 18]);
        $this->cliente = $this->crearEmpresa(['ruc' => '20601234567', 'razon_social' => 'MASCOTAS DEL NORTE SAC', 'direccion' => 'AV. GRAU 100']);
        config([
            'services.admin.token' => self::TOKEN,
            'facturador.url' => self::URL, 'facturador.token' => 'fct_prueba',
            'suscripciones.facturacion.emisor_empresa_id' => $this->plataforma->id,
        ]);
        app(SuscripcionService::class)->asignar($this->cliente, Plan::where('codigo', 'basico')->firstOrFail(), ['periodicidad' => 'mensual']);
    }

    public function test_el_pago_emite_factura_al_ruc_del_cliente_y_queda_aceptada(): void
    {
        $this->facturadorResponde();

        $r = $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'yape', 'referencia' => 'OP-123'])
            ->assertCreated()
            ->assertJsonPath('data.comprobante.numero', 'F900-1')
            ->assertJsonPath('data.comprobante.estado', 'aceptado')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'SUNAT: aceptado'));

        $pago = PagoSuscripcion::findOrFail($r->json('data.id'));
        Http::assertSent(function (Request $q) use ($pago) {
            return $q->url() === self::URL . '/comprobantes'
                && $q['emisor'] === '20111111111' && $q['tipo'] === '01' && $q['serie'] === 'F900' && $q['numero'] === 1
                && $q['cliente'] === ['tipo_doc' => '6', 'numero' => '20601234567', 'nombre' => 'MASCOTAS DEL NORTE SAC', 'direccion' => 'AV. GRAU 100', 'email' => $q['cliente']['email']]
                && $q['items'][0]['precio_unitario'] == $pago->monto && $q['items'][0]['unidad'] === 'ZZ'
                && str_contains($q['items'][0]['descripcion'], 'Plan Básico') && str_contains($q['observaciones'], 'Yape · ref. OP-123')
                && $q['referencia'] === "suscripcion:{$pago->id}";
        });

        // El comprobante es de la empresa emisora (aparece en sus "Envíos a SUNAT")
        $this->assertSame($this->plataforma->id, $pago->comprobante->empresa_id);

        // Representación impresa (admin y empresa cliente)
        $this->api('get', "pagos/{$pago->id}/comprobante/impreso")->assertOk()->assertSee('FACTURA ELECTRÓNICA')->assertSee('F900-1')->assertSee('20601234567');
        $usuario = $this->usuarioCon('Administrador', $this->cliente);
        $this->actingAs($usuario)->get(route('config.suscripcion'))->assertOk()->assertSee('F900-1');
        $this->actingAs($usuario)->get(route('config.suscripcion.comprobante', [$pago, 'impreso']))->assertOk()->assertSee('RICOPET SOFTWARE SAC');
        $ajeno = $this->usuarioCon('Administrador', $this->plataforma);
        $this->actingAs($ajeno)->get(route('config.suscripcion.comprobante', [$pago, 'impreso']))->assertNotFound();
    }

    public function test_boleta_o_sin_comprobante_a_pedido(): void
    {
        $this->facturadorResponde();
        $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'efectivo', 'comprobante' => 'boleta'])
            ->assertCreated()->assertJsonPath('data.comprobante.numero', 'B900-1');
        $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'efectivo', 'comprobante' => 'ninguno'])
            ->assertCreated()->assertJsonPath('data.comprobante', null);

        // Se puede emitir después, a mano
        $pago = PagoSuscripcion::latest('id')->firstOrFail();
        $this->api('post', "pagos/{$pago->id}/comprobante", ['tipo' => 'factura'])->assertOk()->assertJsonPath('data.comprobante.numero', 'F900-1');
    }

    public function test_sin_conexion_queda_pendiente_y_se_reenvia(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        $id = $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'transferencia'])
            ->assertCreated()->assertJsonPath('data.comprobante.estado', 'pendiente')->json('data.id');
        $this->assertSame('activa', $this->cliente->suscripciones()->latest('id')->first()->estado);   // el pago se registró igual

        Http::swap(new \Illuminate\Http\Client\Factory());
        $this->facturadorResponde();
        $this->travel(2)->minutes();
        $this->artisan('facturacion:sincronizar')->assertSuccessful();
        $this->assertSame('aceptado', PagoSuscripcion::findOrFail($id)->comprobante->estado);

        // Mismo número en el reintento
        Http::assertSent(fn (Request $q) => $q['serie'] === 'F900' && $q['numero'] === 1);
    }

    public function test_anular_el_pago_pide_la_baja_o_emite_nota_de_credito(): void
    {
        $this->facturadorResponde();

        // Reciente: comunicación de baja
        $id = $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'yape'])->json('data.id');
        $this->api('post', "pagos/{$id}/anular", ['motivo' => 'Pago duplicado'])->assertOk()
            ->assertJsonPath('data.estado', 'anulado')->assertJsonPath('data.comprobante.baja_estado', 'enviado');
        Http::assertSent(fn (Request $q) => str_ends_with($q->url(), '/anular'));

        // Antiguo (más de 7 días): nota de crédito FC01 que referencia la factura
        $id2 = $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'yape'])->json('data.id');
        $this->travel(10)->days();
        $this->api('post', "pagos/{$id2}/anular")->assertOk()->assertJsonPath('data.nota_credito.numero', 'FC01-1')
            ->assertJsonPath('data.nota_credito.estado', 'aceptado');
        $factura = PagoSuscripcion::findOrFail($id2)->comprobante;
        Http::assertSent(fn (Request $q) => ($q['tipo'] ?? null) === '07' && $q['serie'] === 'FC01'
            && $q['afectado'] === ['tipo' => '01', 'serie' => $factura->serie, 'numero' => (int) $factura->numero] && $q['motivo']['codigo'] === '01');
    }

    public function test_sin_emisor_configurado_no_se_emite(): void
    {
        config(['suscripciones.facturacion.emisor_empresa_id' => null]);
        Http::fake();

        $id = $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'yape'])->assertCreated()->assertJsonPath('data.comprobante', null)->json('data.id');
        $this->api('post', "pagos/{$id}/comprobante")->assertStatus(422);
        Http::assertNothingSent();
        $this->assertSame(0, ComprobanteSunat::count());
    }

    /** Facturador simulado: acepta todo y devuelve la baja en proceso */
    private function facturadorResponde(): void
    {
        Http::fake(function (Request $q) {
            if (str_ends_with($q->url(), '/anular')) {
                return Http::response(['id' => 'uuid-baja', 'estado' => 'aceptado', 'baja' => ['estado' => 'enviado', 'ticket' => '1700']], 202);
            }

            return Http::response([
                'id' => "uuid-{$q['serie']}-{$q['numero']}", 'estado' => 'aceptado', 'hash' => 'h4sh=', 'nombre_archivo' => "{$q['emisor']}-{$q['tipo']}-{$q['serie']}-{$q['numero']}",
                'sunat' => ['codigo' => '0', 'descripcion' => 'ha sido aceptada', 'observaciones' => []], 'envio' => ['ultimo_error' => null], 'baja' => null,
            ], 201);
        });
    }

    private function api(string $metodo, string $ruta, array $datos = [])
    {
        $this->app->forgetScopedInstances();

        return $this->json(strtoupper($metodo), "/api/admin/{$ruta}", $datos, ['Authorization' => 'Bearer ' . self::TOKEN]);
    }
}
