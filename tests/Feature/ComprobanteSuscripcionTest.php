<?php

namespace Tests\Feature;

use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Plataforma\Models\PagoSuscripcion;
use App\Modulos\Plataforma\Models\Plan;
use App\Modulos\Plataforma\Models\Titular;
use App\Modulos\Plataforma\Services\SuscripcionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\Fakes\PasarelaFalsa;
use Tests\TestCase;

/** Cada pago de suscripción emite factura/boleta de la empresa de la plataforma y la envía a SUNAT */
class ComprobanteSuscripcionTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private const TOKEN = 'token-de-prueba-0123456789abcdef';

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
            'suscripciones.facturacion.emisor_ruc' => null,
            'suscripciones.facturacion.emisor_empresa_id' => $this->plataforma->id,
        ]);
        app(SuscripcionService::class)->asignar($this->cliente, Plan::where('codigo', 'basico')->firstOrFail(), ['periodicidad' => 'mensual']);
    }

    public function test_el_pago_emite_factura_al_ruc_del_cliente_y_queda_aceptada(): void
    {
        $r = $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'yape', 'referencia' => 'OP-123'])
            ->assertCreated()
            ->assertJsonPath('data.comprobante.numero', 'F900-1')
            ->assertJsonPath('data.comprobante.estado', 'aceptado')
            ->assertJsonPath('data.comprobante.tiene_archivos', true)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'SUNAT: aceptado'));

        $pago = PagoSuscripcion::findOrFail($r->json('data.id'));
        $llamada = $this->sunat->llamadas[0];
        $this->assertSame('20111111111-01-F900-1', $llamada['nombre']);
        $this->assertStringContainsString('<cbc:ID schemeID="6">20601234567</cbc:ID>', $llamada['xml']);
        $this->assertStringContainsString('unitCode="ZZ"', $llamada['xml']);
        $this->assertStringContainsString('Plan Básico', $llamada['xml']);

        $doc = $pago->comprobante;
        $this->assertSame($this->plataforma->id, $doc->empresa_id);   // es de la empresa emisora (sus "Envíos a SUNAT")
        $this->assertEquals($pago->monto, $doc->total);
        $this->assertStringContainsString('Yape · ref. OP-123', $doc->datos['observaciones']);
        $this->assertSame("suscripcion:{$pago->id}", $doc->referencia);

        // Representación impresa y archivos (admin y empresa cliente)
        $this->api('get', "pagos/{$pago->id}/comprobante/impreso")->assertOk()->assertSee('FACTURA ELECTRÓNICA')->assertSee('F900-1')->assertSee('20601234567');
        $this->api('get', "pagos/{$pago->id}/comprobante/xml")->assertOk()->assertHeader('Content-Type', 'application/xml');
        $usuario = $this->usuarioCon('Administrador', $this->cliente);
        $this->actingAs($usuario)->get(route('config.suscripcion'))->assertOk()->assertSee('F900-1');
        $this->actingAs($usuario)->get(route('config.suscripcion.comprobante', [$pago, 'impreso']))->assertOk()->assertSee('RICOPET SOFTWARE SAC');
        $this->actingAs($usuario)->get(route('config.suscripcion.comprobante', [$pago, 'cdr']))->assertOk();
        $ajeno = $this->usuarioCon('Administrador', $this->plataforma);
        $this->actingAs($ajeno)->get(route('config.suscripcion.comprobante', [$pago, 'impreso']))->assertNotFound();
    }

    public function test_boleta_o_sin_comprobante_a_pedido(): void
    {
        $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'efectivo', 'comprobante' => 'boleta'])
            ->assertCreated()->assertJsonPath('data.comprobante.numero', 'B900-1');
        $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'efectivo', 'comprobante' => 'ninguno'])
            ->assertCreated()->assertJsonPath('data.comprobante', null);

        // Se puede emitir después, a mano
        $pago = PagoSuscripcion::latest('id')->firstOrFail();
        $this->api('post', "pagos/{$pago->id}/comprobante", ['tipo' => 'factura'])->assertOk()->assertJsonPath('data.comprobante.numero', 'F900-1');
    }

    public function test_sin_conexion_queda_pendiente_y_se_reenvia_con_el_mismo_numero(): void
    {
        $this->sunat->encolar(PasarelaFalsa::sinConexion());
        $id = $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'transferencia'])
            ->assertCreated()->assertJsonPath('data.comprobante.estado', 'pendiente')->json('data.id');
        $this->assertSame('activa', $this->cliente->suscripciones()->latest('id')->first()->estado);   // el pago se registró igual

        $this->travel(2)->minutes();
        $this->artisan('facturacion:procesar')->assertSuccessful();
        $this->assertSame('aceptado', PagoSuscripcion::findOrFail($id)->comprobante->estado);
        $this->assertSame(['20111111111-01-F900-1'], array_values(array_unique(array_column($this->sunat->llamadas, 'nombre'))));
    }

    public function test_anular_el_pago_pide_la_baja_o_emite_nota_de_credito(): void
    {
        // Reciente: comunicación de baja
        $id = $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'yape'])->json('data.id');
        $this->api('post', "pagos/{$id}/anular", ['motivo' => 'Pago duplicado'])->assertOk()
            ->assertJsonPath('data.estado', 'anulado')->assertJsonPath('data.comprobante.baja_estado', 'enviado');
        $this->assertSame('sendSummary', $this->sunat->llamadas[1]['metodo']);
        $this->assertStringContainsString('-RA-', $this->sunat->llamadas[1]['nombre']);

        // Antiguo (más de 7 días): nota de crédito FC01 que referencia la factura
        $id2 = $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'yape'])->json('data.id');
        $this->travel(10)->days();
        $this->api('post', "pagos/{$id2}/anular")->assertOk()->assertJsonPath('data.nota_credito.numero', 'FC01-1')
            ->assertJsonPath('data.nota_credito.estado', 'aceptado');
        $factura = PagoSuscripcion::findOrFail($id2)->comprobante;
        $nota = end($this->sunat->llamadas);
        $this->assertSame('20111111111-07-FC01-1', $nota['nombre']);
        $this->assertStringContainsString("<cbc:ID>{$factura->numeroCompleto()}</cbc:ID>", $nota['xml']);
        $this->assertStringContainsString('ANULACION DE LA OPERACION', $nota['xml']);
    }

    public function test_el_comprobante_sale_a_nombre_del_titular_y_con_dni_es_boleta(): void
    {
        $titular = Titular::create(['tipo_documento' => '1', 'numero_documento' => '44556677', 'nombre' => 'ANA TORRES GIL', 'direccion' => 'JR. UNO 1']);
        $otra = $this->crearEmpresa(['ruc' => '20605555551']);
        app(SuscripcionService::class)->asignar($otra, Plan::where('codigo', 'basico')->firstOrFail(), ['periodicidad' => 'mensual', 'titular_id' => $titular->id]);

        $this->api('post', "empresas/{$otra->id}/pagos", ['metodo' => 'yape'])->assertCreated()->assertJsonPath('data.comprobante.numero', 'B900-1');
        $xml = end($this->sunat->llamadas)['xml'];
        $this->assertStringContainsString('<cbc:ID schemeID="1">44556677</cbc:ID>', $xml);
        $this->assertStringContainsString('ANA TORRES GIL', $xml);
        $this->assertStringContainsString('Suscripción RicoPet ERP — Plan Básico', $xml);
    }

    public function test_sin_emisor_configurado_no_se_emite(): void
    {
        config(['suscripciones.facturacion.emisor_empresa_id' => null]);

        $id = $this->api('post', "empresas/{$this->cliente->id}/pagos", ['metodo' => 'yape'])->assertCreated()->assertJsonPath('data.comprobante', null)->json('data.id');
        $this->api('post', "pagos/{$id}/comprobante")->assertStatus(422);
        $this->assertSame([], $this->sunat->llamadas);
        $this->assertSame(0, Documento::count());
    }

    private function api(string $metodo, string $ruta, array $datos = [])
    {
        $this->app->forgetScopedInstances();

        return $this->json(strtoupper($metodo), "/api/admin/{$ruta}", $datos, ['Authorization' => 'Bearer ' . self::TOKEN]);
    }
}
