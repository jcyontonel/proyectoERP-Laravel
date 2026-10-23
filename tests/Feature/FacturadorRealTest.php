<?php

namespace Tests\Feature;

use App\Modulos\Erp\Models\Caja;
use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Facturacion\Models\ComprobanteSunat;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Erp\Models\Nota;
use App\Modulos\Nucleo\Models\TipoDocumento;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Erp\Services\EmpresaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/**
 * Prueba de punta a punta (opcional): ERP → facturador real → SUNAT beta.
 * Se salta salvo que se indique el facturador:
 *   FACTURADOR_E2E_URL=http://127.0.0.1:8090/api/v1 FACTURADOR_E2E_TOKEN=fct_… php artisan test --filter=FacturadorRealTest
 */
class FacturadorRealTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('FACTURADOR_E2E_URL') || ! getenv('FACTURADOR_E2E_TOKEN')) {
            $this->markTestSkipped('Prueba con el facturador real: define FACTURADOR_E2E_URL y FACTURADOR_E2E_TOKEN.');
        }
        config(['facturador.url' => getenv('FACTURADOR_E2E_URL'), 'facturador.token' => getenv('FACTURADOR_E2E_TOKEN'), 'facturador.timeout' => 90]);

        $this->sembrarCatalogos();
        // RUC al azar en cada corrida: SUNAT beta acepta cualquier RUC con MODDATOS
        $this->empresa = $this->crearEmpresa(['ruc' => '20' . random_int(100000000, 999999999), 'igv' => 18, 'precios_incluyen_igv' => true,
            'direccion' => 'AV. LAS MASCOTAS 123', 'ubigeo' => '150101', 'departamento' => 'LIMA', 'provincia' => 'LIMA', 'distrito' => 'LIMA', 'sunat_modo' => 'beta']);
        app(EmpresaService::class)->series($this->empresa);
        $this->crearCaja($this->empresa);
        $this->admin = $this->usuarioCon('Administrador', $this->empresa);
        $this->actingAs($this->admin)->post(route('caja.abrir'), ['caja_id' => Caja::where('empresa_id', $this->empresa->id)->value('id'), 'monto_apertura' => 500]);
    }

    public function test_boleta_factura_nota_y_baja_en_sunat_beta(): void
    {
        // Boleta sin cliente, con descuento global
        $boleta = $this->vender('B001', ['descuento' => 10]);
        $this->assertSame('aceptado', $boleta->sunat?->estado, (string) $boleta->sunat?->mensaje_respuesta);

        // Factura a un RUC
        $cliente = Cliente::factory()->create([
            'tipo_documento_id' => TipoDocumento::where('codigo', '6')->value('id'), 'numero_documento' => '20100070970',
            'nombre' => 'SUPERMERCADOS PERUANOS SOCIEDAD ANONIMA', 'apellido' => null, 'direccion' => 'CAL. MORELLI 181',
        ]);
        $cliente->empresas()->attach($this->empresa->id);
        $factura = $this->vender('F001', ['cliente_id' => $cliente->id]);
        $this->assertSame('aceptado', $factura->sunat?->estado, (string) $factura->sunat?->mensaje_respuesta);

        // Nota de crédito por devolución de un ítem de la boleta
        $this->app->forgetScopedInstances();
        $this->actingAs($this->admin)->post(route('ventas.notas.store', $boleta), [
            'tipo' => '07', 'motivo_codigo' => '07', 'items' => [$boleta->detalles->first()->id => 1], 'metodo' => 'efectivo',
        ])->assertSessionHasNoErrors();
        $nota = Nota::latest('id')->firstOrFail();
        $this->assertSame('aceptado', $nota->sunat?->estado, (string) $nota->sunat?->mensaje_respuesta);

        // Anular la factura: comunicación de baja con ticket
        $this->actingAs($this->admin)->patch(route('ventas.anular', $factura))->assertSessionHas('status');
        $reg = $factura->refresh()->sunat;
        $this->assertContains($reg->baja_estado, ['enviado', 'aceptado'], (string) $reg->mensaje_respuesta);
        $this->assertNotNull($reg->ticket);

        fwrite(STDERR, "\n  RUC {$this->empresa->ruc}: " . ComprobanteSunat::all()->map(fn ($c) => "{$c->serie}-{$c->numero} {$c->estado}" . ($c->baja_estado ? " (baja {$c->baja_estado})" : ''))->implode(' · ') . "\n");
    }

    /** Pago de suscripción: la empresa de la plataforma factura a la empresa cliente y se anula (baja) */
    public function test_pago_de_suscripcion_emite_factura_en_sunat_beta(): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $cliente = $this->crearEmpresa(['ruc' => '20100070970', 'razon_social' => 'SUPERMERCADOS PERUANOS SOCIEDAD ANONIMA', 'direccion' => 'CAL. MORELLI 181']);
        config(['suscripciones.facturacion.emisor_empresa_id' => $this->empresa->id, 'services.admin.token' => 'token-e2e']);
        $servicio = app(\App\Modulos\Suscripciones\Services\SuscripcionService::class);
        $s = $servicio->asignar($cliente, \App\Modulos\Suscripciones\Models\Plan::where('codigo', 'basico')->firstOrFail(), ['periodicidad' => 'mensual']);

        $pago = $servicio->registrarPago($s, ['metodo' => 'yape', 'referencia' => 'E2E-1']);
        $reg = $pago->comprobante()->first();
        $this->assertSame('aceptado', $reg?->estado, (string) $reg?->mensaje_respuesta);
        $this->assertSame('F900', $reg->serie);

        $servicio->anularPago($pago->refresh(), 'Prueba');
        $this->assertContains($reg->refresh()->baja_estado, ['enviado', 'aceptado'], (string) $reg->mensaje_respuesta);

        fwrite(STDERR, "\n  Suscripción: {$reg->serie}-{$reg->numero} {$reg->estado} (baja {$reg->baja_estado}) · monto S/ {$pago->monto}\n");
    }

    private function vender(string $serie, array $extra = []): Venta
    {
        $this->app->forgetScopedInstances();
        $p1 = $this->productoConStock($this->empresa, 20, ['precio_unitario' => 118.00, 'nombre' => "ALIMENTO PERRO ADULTO 15KG {$serie}"]);
        $p2 = $this->productoConStock($this->empresa, 20, ['precio_unitario' => 25.90, 'nombre' => "ARENA SANITARIA 5KG {$serie}"]);

        $this->actingAs($this->admin)->post(route('ventas.store'), $extra + [
            'accion' => 'registrar', 'correlativo_id' => Correlativo::where('empresa_id', $this->empresa->id)->where('serie', $serie)->value('id'),
            'almacen_id' => $this->almacen($this->empresa)->id, 'fecha_emision' => now()->toDateString(), 'metodo_pago' => 'efectivo',
            'productos' => [['id' => $p1->id, 'cantidad' => 2], ['id' => $p2->id, 'cantidad' => 3]],
        ])->assertSessionHasNoErrors();

        return Venta::latest('id')->with(['sunat', 'detalles'])->firstOrFail();
    }
}
