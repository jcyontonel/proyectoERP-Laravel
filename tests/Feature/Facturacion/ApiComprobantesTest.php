<?php

namespace Tests\Feature\Facturacion;

use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Facturacion\Models\SistemaExterno;
use App\Modulos\Facturacion\Sunat\RespuestaSunat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConSistemaExterno;
use Tests\TestCase;

/** API v1 para sistemas externos (antes la API del facturador separado) */
class ApiComprobantesTest extends TestCase
{
    use ConSistemaExterno, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->conSistema();
    }

    public function test_sin_token_o_con_token_invalido_responde_401(): void
    {
        $this->postJson('/api/v1/comprobantes', [])->assertUnauthorized();
        $this->withToken('fct_falso')->getJson('/api/v1/ping')->assertUnauthorized();
        $this->api()->getJson('/api/v1/ping')->assertOk()->assertJsonPath('emisores.0.ruc', '20123456789')->assertJsonPath('emisores.0.modo', 'beta');

        $this->sistema->update(['activo' => false]);
        $this->api()->getJson('/api/v1/ping')->assertUnauthorized();
    }

    public function test_emite_factura_aceptada_con_xml_firmado_y_es_idempotente(): void
    {
        $r = $this->api()->postJson('/api/v1/comprobantes', $this->factura())
            ->assertCreated()
            ->assertJsonPath('estado', 'aceptado')
            ->assertJsonPath('numero_completo', 'F001-1')
            ->assertJsonPath('total', 118)
            ->assertJsonPath('totales.igv', 18)
            ->assertJsonPath('referencia', 'venta-1');

        $xml = $this->sunat->llamadas[0]['xml'];
        $this->assertSame('20123456789-01-F001-1', $this->sunat->llamadas[0]['nombre']);
        $this->assertStringContainsString('<ds:SignatureValue>', $xml);
        $this->assertStringContainsString('CIENTO DIECIOCHO CON 00/100 SOLES', $xml);
        $this->assertNotNull($r->json('hash'));

        // Mismo número otra vez: no se reenvía, devuelve el existente
        $this->api()->postJson('/api/v1/comprobantes', $this->factura())->assertOk()->assertHeader('X-Idempotente', 'si')->assertJsonPath('id', $r->json('id'));
        $this->assertCount(1, $this->sunat->llamadas);

        $this->api()->get($r->json('enlaces.xml'))->assertOk()->assertHeader('Content-Type', 'application/xml');
        $this->api()->get($r->json('enlaces.cdr'))->assertOk();
        $this->api()->getJson('/api/v1/comprobantes?referencia=venta-1')->assertOk()->assertJsonCount(1, 'data');

        // Otro sistema (aunque sea de la misma empresa) no lo ve; otra empresa tampoco
        [, $otro] = SistemaExterno::registrar($this->empresa, 'Otro');
        $this->withToken($otro)->getJson('/api/v1/comprobantes/' . $r->json('id'))->assertNotFound();
        [, $ajeno] = SistemaExterno::registrar($this->crearEmpresa(), 'Ajeno');
        $this->withToken($ajeno)->getJson('/api/v1/comprobantes/' . $r->json('id'))->assertNotFound();
    }

    public function test_validaciones_de_sunat(): void
    {
        $this->api()->postJson('/api/v1/comprobantes', $this->factura(['cliente' => ['tipo_doc' => '1', 'numero' => '12345678']]))
            ->assertUnprocessable()->assertJsonValidationErrors('cliente.tipo_doc');
        $this->api()->postJson('/api/v1/comprobantes', $this->factura(['serie' => 'B001']))->assertJsonValidationErrors('serie');
        $this->api()->postJson('/api/v1/comprobantes', $this->factura(['emisor' => '20999999999']))->assertJsonValidationErrors('emisor');

        // Boleta de 700 o más sin documento del cliente
        $this->api()->postJson('/api/v1/comprobantes', [
            'tipo' => '03', 'serie' => 'B001', 'numero' => 1,
            'items' => [['descripcion' => 'Jaula', 'cantidad' => 1, 'precio_unitario' => 800]],
        ])->assertJsonValidationErrors('cliente.numero');

        // Nota: la serie sigue la letra del comprobante afectado
        $this->api()->postJson('/api/v1/comprobantes', $this->factura([
            'tipo' => '07', 'serie' => 'B001', 'afectado' => ['tipo' => '01', 'serie' => 'F001', 'numero' => 1], 'motivo' => ['codigo' => '01', 'descripcion' => 'Anulación'],
        ]))->assertJsonValidationErrors('serie');
        $this->assertSame([], $this->sunat->llamadas);
    }

    public function test_boleta_sin_cliente_y_nota_de_credito(): void
    {
        $this->api()->postJson('/api/v1/comprobantes', [
            'tipo' => '03', 'serie' => 'B001', 'numero' => 7,
            'items' => [['descripcion' => 'Arena', 'cantidad' => 1, 'precio_unitario' => 25.9]],
        ])->assertCreated()->assertJsonPath('estado', 'aceptado');
        $this->assertSame('0', Documento::firstWhere('tipo', '03')->cliente_tipo_doc);

        $nc = $this->api()->postJson('/api/v1/comprobantes', [
            'tipo' => '07', 'serie' => 'B001', 'numero' => 1,
            'cliente' => ['tipo_doc' => '0', 'numero' => '00000000', 'nombre' => 'CLIENTES VARIOS'],
            'items' => [['descripcion' => 'Arena', 'cantidad' => 1, 'precio_unitario' => 25.9]],
            'afectado' => ['tipo' => '03', 'serie' => 'B001', 'numero' => 7], 'motivo' => ['codigo' => '07', 'descripcion' => 'Devolución'],
        ])->assertCreated()->json();

        $this->assertSame('aceptado', $nc['estado']);
        $this->assertStringContainsString('<cbc:ID>B001-7</cbc:ID>', $this->sunat->llamadas[1]['xml']);
        $this->assertNotNull(Documento::firstWhere('tipo', '07')->afectado_id);
    }

    public function test_rechazo_permite_corregir_y_reenviar_el_mismo_numero(): void
    {
        $this->sunat->encolar(new RespuestaSunat(RespuestaSunat::RECHAZADO, '2017', 'El numero de documento de identidad del receptor debe ser RUC'));
        $r = $this->api()->postJson('/api/v1/comprobantes', $this->factura())->assertCreated()->assertJsonPath('estado', 'rechazado')
            ->assertJsonPath('sunat.codigo', '2017');

        $this->api()->postJson('/api/v1/comprobantes', $this->factura(['cliente' => ['nombre' => 'CORREGIDO SAC']]))
            ->assertCreated()->assertJsonPath('estado', 'aceptado')->assertJsonPath('id', $r->json('id'));
        $this->assertSame('CORREGIDO SAC', Documento::first()->cliente_nombre);
    }

    public function test_asincrono_no_envia_hasta_el_timer(): void
    {
        $this->api()->postJson('/api/v1/comprobantes', $this->factura(['asincrono' => true]))->assertAccepted()->assertJsonPath('estado', 'pendiente');
        $this->assertSame([], $this->sunat->llamadas);

        $this->artisan('facturacion:procesar')->assertSuccessful();
        $this->assertSame('aceptado', Documento::first()->estado);
    }
}
