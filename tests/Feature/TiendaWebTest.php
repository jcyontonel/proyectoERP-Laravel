<?php

namespace Tests\Feature;

use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Erp\Models\Caja;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Erp\Services\EmpresaService;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Plataforma\Models\Plan;
use App\Modulos\Plataforma\Services\SuscripcionService;
use App\Modulos\Web\Models\PedidoWeb;
use App\Modulos\Web\Models\Tienda;
use App\Modulos\Web\Models\ZonaEnvio;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** Página web: suscripción, catálogo vs tienda, pedidos (precios del servidor), Culqi y conversión en venta */
class TiendaWebTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private Producto $alimento;

    private Producto $collar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->seed(PlanSeeder::class);
        $this->empresa = $this->crearEmpresa(['ruc' => '20601234567', 'razon_social' => 'Mascotas del Norte SAC', 'nombre_comercial' => 'MasNorte', 'precios_incluyen_igv' => true]);
        app(EmpresaService::class)->series($this->empresa);
        $this->admin = $this->usuarioCon('Administrador', $this->empresa);
        $this->alimento = $this->productoConStock($this->empresa, 5, ['nombre' => 'Alimento 15 kg', 'precio_unitario' => 150, 'publicar_web' => true]);
        $this->collar = $this->productoConStock($this->empresa, 10, ['nombre' => 'Collar', 'precio_unitario' => 25, 'publicar_web' => false]);
    }

    public function test_sin_suscripcion_web_no_hay_bloque_ni_pagina(): void
    {
        $this->actingAs($this->admin)->get(route('inicio'))->assertOk()->assertDontSee(route('web.tienda.edit'));
        $this->actingAs($this->admin)->get(route('web.tienda.edit'))->assertRedirect();
        $this->actingAs($this->admin)->get(route('web.pedidos.index'))->assertRedirect();

        // Aunque la tienda exista y esté publicada, sin plan web no se ve
        Tienda::deEmpresa($this->empresa)->update(['publicada' => true]);
        $this->get(route('tienda.publica', Tienda::first()->slug))->assertNotFound();
        $this->getJson(route('api.tienda.datos', Tienda::first()->slug))->assertNotFound();
    }

    public function test_plan_catalogo_muestra_solo_lo_publicado_y_no_recibe_pedidos(): void
    {
        $this->contratar('catalogo');
        $this->actingAs($this->admin)->get(route('inicio'))->assertSee(route('web.tienda.edit'))->assertDontSee(route('web.pedidos.index'));
        $this->actingAs($this->admin)->get(route('web.pedidos.index'))->assertRedirect();   // es del plan Tienda

        $slug = $this->configurar();
        $this->get(route('tienda.publica', $slug))->assertOk()->assertSee('MasNorte')->assertSee(route('api.tienda.datos', $slug), false);

        $r = $this->getJson(route('api.tienda.datos', $slug))->assertOk()
            ->assertJsonPath('tienda.modalidad', 'catalogo')->assertJsonPath('tienda.whatsapp', '51987654321')
            ->assertJsonPath('compra', null)->assertJsonCount(1, 'productos')->assertJsonPath('productos.0.nombre', 'Alimento 15 kg');
        $this->assertEquals(150, $r->json('productos.0.precio'));

        $this->postJson(route('api.tienda.pedidos.store', $slug), $this->pedido())->assertUnprocessable()->assertJsonValidationErrors('pedido');
    }

    public function test_plan_tienda_registra_el_pedido_con_los_precios_del_servidor(): void
    {
        $this->contratar('tienda');
        $slug = $this->configurar();
        $this->actingAs($this->admin)->post(route('web.zonas.store'), ['nombre' => 'Trujillo centro', 'costo' => 8, 'tiempo_entrega' => '24 h'])->assertSessionHasNoErrors();
        $zona = ZonaEnvio::firstOrFail();

        $datos = $this->getJson(route('api.tienda.datos', $slug))->assertOk()->assertJsonPath('tienda.modalidad', 'tienda')
            ->assertJsonPath('compra.zonas.0.nombre', 'Trujillo centro')->assertJsonPath('compra.culqi', null);
        $this->assertSame(['contra_entrega', 'transferencia'], array_column($datos->json('compra.metodos_pago'), 'clave'));

        // Más de lo que hay en stock / un producto no publicado
        $this->postJson(route('api.tienda.pedidos.store', $slug), $this->pedido(['items' => [['id' => $this->alimento->id, 'cantidad' => 9]]]))
            ->assertJsonValidationErrors('items');
        $this->postJson(route('api.tienda.pedidos.store', $slug), $this->pedido(['items' => [['id' => $this->collar->id, 'cantidad' => 1]]]))
            ->assertJsonValidationErrors('items');

        $r = $this->postJson(route('api.tienda.pedidos.store', $slug), $this->pedido([
            'entrega' => 'envio', 'zona_envio_id' => $zona->id, 'direccion' => 'Jr. Pizarro 123',
            'items' => [['id' => $this->alimento->id, 'cantidad' => 2, 'precio' => 1]],   // el precio del navegador se ignora
        ]))->assertCreated()->assertJsonPath('codigo', 'W-000001');
        $this->assertEquals(308, $r->json('total'));   // 2 × 150 + 8 de envío

        $pedido = PedidoWeb::firstOrFail();
        $this->assertSame('nuevo', $pedido->estado);
        $this->assertSame($this->empresa->id, $pedido->empresa_id);
        $this->actingAs($this->admin)->get(route('web.pedidos.index'))->assertOk()->assertSee('W-000001');
        $this->actingAs($this->admin)->get(route('web.pedidos.show', $pedido))->assertOk()->assertSee('Jr. Pizarro 123');
    }

    public function test_pedido_minimo_y_recojo_desactivado(): void
    {
        $this->contratar('tienda');
        $slug = $this->configurar(['pedido_minimo' => 500, 'recojo_en_tienda' => 0]);

        $this->postJson(route('api.tienda.pedidos.store', $slug), $this->pedido())->assertJsonValidationErrors('items');
        Tienda::first()->update(['pedido_minimo' => 0]);
        $this->postJson(route('api.tienda.pedidos.store', $slug), $this->pedido())->assertJsonValidationErrors('entrega');
    }

    public function test_pago_con_tarjeta_por_culqi_en_modo_prueba(): void
    {
        $this->contratar('tienda');
        $slug = $this->configurar(['metodos_pago' => ['tarjeta'], 'culqi_llave_publica' => 'pk_test_abc123', 'culqi_llave_secreta' => 'sk_test_xyz789']);
        $this->getJson(route('api.tienda.datos', $slug))->assertJsonPath('compra.culqi.llave_publica', 'pk_test_abc123')->assertJsonPath('compra.culqi.modo', 'prueba')
            ->assertJsonMissing(['sk_test_xyz789']);

        // Rechazada: no queda pedido
        Http::fake(['api.culqi.com/*' => Http::response(['object' => 'error', 'user_message' => 'Tu tarjeta no tiene fondos suficientes.'], 402)]);
        $this->postJson(route('api.tienda.pedidos.store', $slug), $this->pedido(['metodo_pago' => 'tarjeta', 'culqi_token' => 'tkn_test_1']))
            ->assertUnprocessable()->assertJsonPath('errors.pago.0', 'Tu tarjeta no tiene fondos suficientes.');
        $this->assertSame(0, PedidoWeb::count());

        // Aprobada: pedido pagado con el id del cargo
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['api.culqi.com/*' => Http::response(['id' => 'chr_test_ok', 'object' => 'charge'], 201)]);
        $this->postJson(route('api.tienda.pedidos.store', $slug), $this->pedido(['metodo_pago' => 'tarjeta', 'culqi_token' => 'tkn_test_2']))
            ->assertCreated()->assertJsonPath('pagado', true);
        $this->assertSame('chr_test_ok', PedidoWeb::firstOrFail()->culqi_cargo_id);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer sk_test_xyz789') && $r['amount'] === 15000 && $r['source_id'] === 'tkn_test_2');
    }

    public function test_las_llaves_deben_ser_del_modo_elegido(): void
    {
        $this->contratar('tienda');
        $this->configurar();
        $this->actingAs($this->admin)->put(route('web.tienda.update'), $this->formTienda(['culqi_modo' => 'produccion', 'culqi_llave_publica' => 'pk_test_abc']))
            ->assertSessionHasErrors('culqi_llave_publica');
    }

    public function test_el_pedido_se_convierte_en_venta(): void
    {
        $this->contratar('tienda');
        $slug = $this->configurar();
        $this->postJson(route('api.tienda.pedidos.store', $slug), $this->pedido(['cliente' => ['documento' => '44556677', 'tipo_doc' => '1']]))->assertCreated();
        $pedido = PedidoWeb::firstOrFail();

        $this->actingAs($this->admin)->post(route('web.pedidos.convertir', $pedido))->assertRedirect(route('ventas.create'))
            ->assertSessionHasInput('pedido_id', $pedido->id)->assertSessionHasInput('productos', [['id' => $this->alimento->id, 'cantidad' => 1.0]]);
        $clienteId = session()->getOldInput('cliente_id');
        $this->assertNotNull($clienteId);   // el cliente quedó registrado en la empresa

        $this->crearCaja($this->empresa);
        $this->actingAs($this->admin)->post(route('caja.abrir'), ['caja_id' => Caja::where('empresa_id', $this->empresa->id)->value('id'), 'monto_apertura' => 0]);
        $this->app->forgetScopedInstances();
        $this->actingAs($this->admin)->post(route('ventas.store'), [
            'accion' => 'registrar', 'correlativo_id' => Correlativo::where('empresa_id', $this->empresa->id)->where('serie', 'B001')->value('id'),
            'almacen_id' => $this->almacen($this->empresa)->id, 'fecha_emision' => now()->toDateString(), 'metodo_pago' => 'efectivo',
            'cliente_id' => $clienteId, 'pedido_id' => $pedido->id, 'productos' => [['id' => $this->alimento->id, 'cantidad' => 1]],
        ])->assertSessionHasNoErrors();

        $pedido->refresh();
        $this->assertSame(Venta::latest('id')->value('id'), $pedido->venta_id);
        $this->assertSame('confirmado', $pedido->estado);
        $this->actingAs($this->admin)->post(route('web.pedidos.convertir', $pedido))->assertSessionHas('error');
    }

    public function test_productos_publicados_y_foto_desde_el_erp(): void
    {
        $this->contratar('catalogo');
        $slug = $this->configurar();

        $this->actingAs($this->admin)->put(route('web.productos.update', $this->collar), ['publicar_web' => 1])->assertSessionHas('status');
        // PNG real de 1×1 (UploadedFile::fake()->image() exige GD, que no viene en este PHP)
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $this->actingAs($this->admin)->put(route('web.productos.update', $this->collar), ['imagen' => UploadedFile::fake()->createWithContent('collar.png', $png)])->assertSessionHasNoErrors();
        $this->assertNotNull($this->collar->refresh()->imagen_path);

        $productos = $this->getJson(route('api.tienda.datos', $slug))->assertJsonCount(2, 'productos')->json('productos');
        $collar = collect($productos)->firstWhere('nombre', 'Collar');
        $this->get(parse_url($collar['imagen'], PHP_URL_PATH))->assertOk();

        // Un producto que no se publica no expone su foto; otra empresa no edita los productos ajenos
        $this->actingAs($this->admin)->post(route('web.productos.masivo'), ['ids' => [$this->collar->id], 'publicar' => 0]);
        $this->get(route('tienda.imagen', [$slug, $this->collar->id]))->assertNotFound();
        $otro = $this->usuarioCon('Administrador', $this->crearEmpresa());
        $this->actingAs($otro)->put(route('web.productos.update', $this->collar), ['publicar_web' => 1])->assertNotFound();
    }

    /* ───────── Ayudas ───────── */

    private function contratar(string $plan): void
    {
        app(SuscripcionService::class)->asignar($this->empresa, Plan::where('codigo', $plan)->firstOrFail(), ['periodicidad' => 'mensual', 'prueba' => true]);
        $this->app->forgetScopedInstances();
    }

    /** Configura y publica la tienda desde el ERP; devuelve su slug */
    private function configurar(array $extra = []): string
    {
        $this->actingAs($this->admin)->put(route('web.tienda.update'), $this->formTienda($extra))->assertSessionHasNoErrors();

        return Tienda::where('empresa_id', $this->empresa->id)->value('slug');
    }

    private function formTienda(array $extra = []): array
    {
        return $extra + [
            'slug' => 'masnorte', 'publicada' => 1, 'titulo' => 'MasNorte', 'whatsapp' => '51987654321', 'color' => '#0f766e',
            'recojo_en_tienda' => 1, 'pedido_minimo' => 0, 'metodos_pago' => ['contra_entrega', 'transferencia'], 'culqi_modo' => 'prueba',
        ];
    }

    private function pedido(array $extra = []): array
    {
        return array_replace_recursive([
            'cliente' => ['nombre' => 'Ana Torres', 'telefono' => '987111222', 'correo' => 'ana@correo.pe'],
            'entrega' => 'recojo', 'metodo_pago' => 'contra_entrega',
            'items' => [['id' => $this->alimento->id, 'cantidad' => 1]],
        ], $extra);
    }
}
