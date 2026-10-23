<?php

namespace Tests\Feature\Facturacion;

use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Facturacion\Sunat\RespuestaSunat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConSistemaExterno;
use Tests\Fakes\PasarelaFalsa;
use Tests\TestCase;

/** Sin internet: el comprobante queda pendiente, el timer lo reenvía y avisa al sistema externo por webhook */
class TimerYWebhookTest extends TestCase
{
    use ConSistemaExterno, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conSistema();
    }

    public function test_sin_conexion_queda_pendiente_y_el_timer_lo_reenvia_y_avisa(): void
    {
        Http::fake(['erp.test/*' => Http::response(null, 204)]);
        $this->sunat->encolar(PasarelaFalsa::sinConexion());

        $id = $this->api()->postJson('/api/v1/comprobantes', $this->factura())
            ->assertCreated()->assertJsonPath('estado', 'pendiente')->assertJsonPath('envio.intentos', 1)
            ->assertJsonPath('envio.ultimo_error', '[HTTP] Could not connect to host')->json('id');

        // Aún no toca reintentar
        $this->artisan('facturacion:procesar');
        $this->assertCount(1, $this->sunat->llamadas);

        // Pasó un minuto y volvió la conexión
        $this->travel(2)->minutes();
        $this->artisan('facturacion:procesar')->expectsOutputToContain('aceptados: 1');

        $doc = Documento::firstWhere('uuid', $id);
        $this->assertSame('aceptado', $doc->estado);
        $this->assertSame('entregado', $doc->webhook_estado);

        Http::assertSent(function (Request $req) use ($id) {
            [$t, $v1] = sscanf($req->header('X-Facturador-Firma')[0], 't=%d,v1=%s');

            return $req->url() === 'https://erp.test/webhook'
                && $req['evento'] === 'comprobante.actualizado'
                && $req['comprobante']['id'] === $id
                && $req['comprobante']['estado'] === 'aceptado'
                && hash_equals(hash_hmac('sha256', $t . '.' . $req->body(), $this->sistema->webhook_secreto), $v1);
        });
    }

    public function test_espera_creciente_y_corte_si_sigue_sin_conexion(): void
    {
        Http::fake();
        $this->sunat->encolar(PasarelaFalsa::sinConexion(), PasarelaFalsa::sinConexion());
        $this->api()->postJson('/api/v1/comprobantes', $this->factura(['asincrono' => true]));
        $this->api()->postJson('/api/v1/comprobantes', $this->factura(['numero' => 2, 'asincrono' => true]));

        // Primer envío falla: el segundo no se intenta en la misma vuelta
        $this->artisan('facturacion:procesar')->expectsOutputToContain('sin conexión');
        $this->assertCount(1, $this->sunat->llamadas);

        $this->travel(90)->seconds();
        $this->artisan('facturacion:procesar');   // 2.º intento del primero: falla otra vez
        $primero = Documento::where('numero', 1)->first();
        $this->assertSame(2, $primero->intentos);
        $this->assertTrue($primero->proximo_intento_at->gt(now()->addMinute()));   // 2.ª espera: 2 minutos

        $this->travel(3)->minutes();
        $this->artisan('facturacion:procesar');
        $this->assertSame(2, Documento::where('estado', 'aceptado')->count());
    }

    public function test_sunat_fuera_de_servicio_se_reintenta_pero_un_error_de_credenciales_no(): void
    {
        Http::fake();
        $this->sunat->encolar(RespuestaSunat::porCodigo('109', 'El servicio de autenticación no está disponible'));
        $this->api()->postJson('/api/v1/comprobantes', $this->factura())->assertJsonPath('estado', 'pendiente');

        $this->sunat->encolar(RespuestaSunat::porCodigo('102', 'Usuario o contraseña incorrectos'));
        $this->api()->postJson('/api/v1/comprobantes', $this->factura(['numero' => 2]))->assertJsonPath('estado', 'error')
            ->assertJsonPath('envio.proximo_intento', null);
    }

    public function test_webhook_caido_se_reintenta_hasta_entregar(): void
    {
        Http::fakeSequence('erp.test/*')->push('error', 500)->push('ok', 200);
        $this->sunat->encolar(PasarelaFalsa::sinConexion());
        $this->api()->postJson('/api/v1/comprobantes', $this->factura());

        $this->travel(2)->minutes();
        $this->artisan('facturacion:procesar');
        $doc = Documento::first();
        $this->assertSame('pendiente', $doc->webhook_estado);
        $this->assertSame('HTTP 500', $doc->webhook_ultimo_error);

        $this->travel(2)->minutes();
        $this->artisan('facturacion:procesar');
        $this->assertSame('entregado', $doc->refresh()->webhook_estado);
        $this->assertSame(2, $doc->webhook_intentos);
    }

    public function test_pasado_el_plazo_deja_de_reintentar(): void
    {
        Http::fake();
        $this->sunat->encolar(PasarelaFalsa::sinConexion());
        $this->api()->postJson('/api/v1/comprobantes', $this->factura());

        $this->travel(8)->days();
        $this->artisan('facturacion:procesar');
        $doc = Documento::first();
        $this->assertSame('error', $doc->estado);
        $this->assertStringContainsString('plazo', $doc->ultimo_error);
    }
}
