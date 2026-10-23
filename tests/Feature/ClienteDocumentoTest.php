<?php

namespace Tests\Feature;

use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\TipoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** Búsqueda de DNI/RUC desde Nueva venta (APIPeru simulado con Http::fake: no consume la cuota real) */
class ClienteDocumentoTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.apiperu.token' => 'token-de-prueba', 'services.apiperu.url' => 'https://api.apiperu.dev']);
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa();
    }

    public function test_dni_nuevo_se_registra_con_reniec(): void
    {
        Http::fake(['api.apiperu.dev/dni' => Http::response(['success' => true, 'data' => [
            'numero' => '44556677', 'nombres' => 'VALERIA SOFIA', 'apellido_paterno' => 'MENDOZA', 'apellido_materno' => 'RIOS',
        ]])]);

        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))
            ->getJson(route('clientes.documento', '44556677'))
            ->assertCreated()
            ->assertJson(['nombre' => 'Valeria Sofia Mendoza Rios', 'doc' => '44556677', 'tipo_doc' => 'DNI', 'origen' => 'externo']);

        $c = Cliente::where('numero_documento', '44556677')->firstOrFail();
        $this->assertSame('Mendoza Rios', $c->apellido);
        $this->assertTrue($c->empresas()->whereKey($this->empresa->id)->exists());

        // El token va en la cabecera Authorization (nunca al navegador)
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer token-de-prueba') && $r['dni'] === '44556677');
    }

    public function test_ruc_nuevo_se_registra_con_sunat(): void
    {
        Http::fake(['api.apiperu.dev/ruc' => Http::response(['success' => true, 'data' => [
            'ruc' => '20605432189', 'nombre_o_razon_social' => 'VETERINARIA PATITAS S.A.C.', 'direccion_completa' => 'AV. LIMA 123 - BARRANCA',
            'estado' => 'ACTIVO', 'condicion' => 'HABIDO',
        ]])]);

        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))
            ->getJson(route('clientes.documento', '20605432189'))
            ->assertCreated()
            ->assertJson(['nombre' => 'VETERINARIA PATITAS S.A.C.', 'es_ruc' => true, 'direccion' => 'AV. LIMA 123 - BARRANCA']);
    }

    public function test_cliente_de_otra_empresa_solo_se_vincula_sin_consultar_la_api(): void
    {
        Http::fake();
        $c = Cliente::factory()->create(['tipo_documento_id' => TipoDocumento::where('codigo', '1')->value('id'), 'numero_documento' => '70345621']);
        $c->empresas()->attach($this->crearEmpresa()->id);

        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))
            ->getJson(route('clientes.documento', '70345621'))
            ->assertCreated()
            ->assertJson(['id' => $c->id, 'origen' => 'sistema']);

        Http::assertNothingSent();
        $this->assertSame(2, $c->empresas()->count());
    }

    public function test_documento_no_encontrado_responde_422(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'No encontrado'], 404)]);

        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))
            ->getJson(route('clientes.documento', '12345678'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('numero_documento');

        $this->assertSame(0, Cliente::count());
    }

    public function test_sin_token_configurado_avisa(): void
    {
        config(['services.apiperu.token' => null]);

        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))
            ->getJson(route('clientes.documento', '12345678'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.numero_documento.0', fn ($m) => str_contains($m, 'APIPERU_TOKEN'));
    }

    public function test_el_almacenero_no_puede_registrar_por_documento(): void
    {
        Http::fake();

        $this->actingAs($this->usuarioCon('Almacenero', $this->empresa))
            ->getJson(route('clientes.documento', '12345678'))
            ->assertForbidden();
    }

    public function test_consulta_para_el_formulario_no_registra(): void
    {
        Http::fake(['api.apiperu.dev/dni' => Http::response(['success' => true, 'data' => ['nombres' => 'ANA', 'apellido_paterno' => 'TORRES', 'apellido_materno' => 'GIL']])]);

        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))
            ->getJson(route('clientes.consulta', '11223344'))
            ->assertOk()
            ->assertJson(['nombre' => 'Ana', 'apellido' => 'Torres Gil']);

        $this->assertSame(0, Cliente::count());
    }
}
