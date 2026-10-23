<?php

namespace Tests\Feature\Facturacion;

use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Facturacion\Sunat\RespuestaSunat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConSistemaExterno;
use Tests\TestCase;

/** Anulación por la API v1: comunicación de baja (facturas) o resumen diario (boletas), con ticket que consulta el timer */
class AnulacionApiTest extends TestCase
{
    use ConSistemaExterno, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->conSistema();
    }

    public function test_factura_se_anula_por_comunicacion_de_baja(): void
    {
        $id = $this->api()->postJson('/api/v1/comprobantes', $this->factura())->json('id');

        $this->api()->postJson("/api/v1/comprobantes/{$id}/anular", ['motivo' => 'Error en el RUC'])
            ->assertAccepted()->assertJsonPath('estado', 'aceptado')->assertJsonPath('baja.tipo', 'RA')
            ->assertJsonPath('baja.estado', 'enviado')->assertJsonPath('baja.ticket', '1700000000001');
        $this->assertStringContainsString('Error en el RUC', $this->sunat->llamadas[1]['xml']);
        $this->assertStringStartsWith('20123456789-RA-' . now()->format('Ymd') . '-1', $this->sunat->llamadas[1]['nombre']);

        // El ticket aún se procesa; en la siguiente consulta termina
        $this->sunat->encolar(new RespuestaSunat(RespuestaSunat::EN_PROCESO, '98', 'En proceso'));
        $this->travel(2)->minutes();
        $this->artisan('facturacion:procesar');
        $this->assertSame('aceptado', Documento::firstWhere('uuid', $id)->estado);

        $this->travel(2)->minutes();
        $this->artisan('facturacion:procesar');
        $this->api()->getJson("/api/v1/comprobantes/{$id}")->assertJsonPath('estado', 'anulado')->assertJsonPath('baja.estado', 'aceptado');

        Http::assertSent(fn (Request $r) => $r['comprobante']['id'] === $id && $r['comprobante']['estado'] === 'anulado');

        // No se anula dos veces
        $this->api()->postJson("/api/v1/comprobantes/{$id}/anular", ['motivo' => 'Otra vez'])->assertJsonValidationErrors('comprobante');
    }

    public function test_boleta_se_anula_por_resumen_diario_y_un_rechazo_no_la_anula(): void
    {
        $id = $this->api()->postJson('/api/v1/comprobantes', [
            'tipo' => '03', 'serie' => 'B001', 'numero' => 3,
            'items' => [['descripcion' => 'Collar', 'cantidad' => 1, 'precio_unitario' => 30]],
        ])->json('id');

        $this->api()->postJson("/api/v1/comprobantes/{$id}/anular", ['motivo' => 'Error'])->assertJsonPath('baja.tipo', 'RC');
        $this->assertStringContainsString('<cbc:ConditionCode>3</cbc:ConditionCode>', $this->sunat->llamadas[1]['xml']);

        $this->sunat->encolar(new RespuestaSunat(RespuestaSunat::RECHAZADO, '2663', 'El documento indicado no existe'));
        $this->travel(2)->minutes();
        $this->artisan('facturacion:procesar');
        $this->api()->getJson("/api/v1/comprobantes/{$id}")->assertJsonPath('estado', 'aceptado')->assertJsonPath('baja.estado', 'rechazado');

        // Tras un rechazo se puede volver a intentar la baja
        $this->api()->postJson("/api/v1/comprobantes/{$id}/anular", ['motivo' => 'Error'])->assertAccepted()->assertJsonPath('baja.numero', 'RC-' . now()->format('Ymd') . '-2');
    }

    public function test_reglas_de_anulacion(): void
    {
        $this->sunat->encolar(new RespuestaSunat(RespuestaSunat::RECHAZADO, '2800', 'Rechazo de prueba'));
        $rechazada = $this->api()->postJson('/api/v1/comprobantes', $this->factura())->json('id');
        $this->api()->postJson("/api/v1/comprobantes/{$rechazada}/anular", ['motivo' => 'x'])->assertJsonValidationErrors('comprobante');

        $vieja = $this->api()->postJson('/api/v1/comprobantes', $this->factura(['numero' => 2, 'fecha_emision' => now()->subDays(9)->toDateTimeString()]))->json('id');
        $this->api()->postJson("/api/v1/comprobantes/{$vieja}/anular", ['motivo' => 'x'])->assertJsonValidationErrors('comprobante');
    }
}
