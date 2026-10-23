<?php

namespace Tests\Feature;

use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Suscripciones\Models\Oferta;
use App\Modulos\Suscripciones\Models\PagoSuscripcion;
use App\Modulos\Suscripciones\Models\Plan;
use App\Modulos\Suscripciones\Models\Suscripcion;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Suscripciones\Services\SuscripcionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** Planes, límites, vigencia por fechas, pagos y ofertas (lado ERP) */
class SuscripcionTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->seed(PlanSeeder::class);
        $this->empresa = $this->crearEmpresa();
        $this->crearCorrelativoVenta($this->empresa);
        config(['caja.exigir_para_vender' => false]);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(10, 0));
    }

    public function test_planes_iniciales(): void
    {
        // Tres planes en este orden, cada uno con más módulos que el anterior
        $this->assertSame(['basico', 'pro', 'total'], Plan::where('activo', true)->orderBy('orden')->pluck('codigo')->all());
        $basico = Plan::where('codigo', 'basico')->with('precios')->firstOrFail();
        $this->assertSame(['mensual', 'trimestral', 'anual'], $basico->precios->pluck('periodicidad')->all());
        $this->assertNull(Plan::where('codigo', 'total')->value('max_comprobantes_mes'));

        [$pro, $total] = [Plan::where('codigo', 'pro')->first(), Plan::where('codigo', 'total')->first()];
        $this->assertTrue($basico->incluye('notas') && ! $basico->incluye('compras') && ! $basico->incluye('cotizaciones'));
        $this->assertTrue($pro->incluye('compras') && $pro->incluye('cotizaciones') && ! $pro->incluye('lotes'));
        $this->assertTrue(collect(array_keys(config('suscripciones.modulos')))->every(fn ($m) => $total->incluye($m)));
    }

    public function test_limite_de_comprobantes_al_mes(): void
    {
        $plan = Plan::where('codigo', 'basico')->firstOrFail();
        $plan->update(['max_comprobantes_mes' => 2]);
        $this->servicio()->asignar($this->empresa, $plan, ['periodicidad' => 'mensual', 'pagado' => true]);
        $admin = $this->usuarioCon('Administrador', $this->empresa);

        $this->vender($admin)->assertSessionHasNoErrors();
        $this->vender($admin, 'borrador')->assertSessionHasNoErrors();   // el borrador no cuenta
        $this->vender($admin)->assertSessionHasNoErrors();
        $this->vender($admin)->assertSessionHasErrors(['suscripcion' => 'Llegaste al límite de tu plan Básico: 2 comprobantes al mes. Para seguir, cambia a un plan superior.']);
        $this->assertSame(2, Venta::where('estado', '!=', Venta::BORRADOR)->count());

        // Mes nuevo: el contador vuelve a cero
        $this->travelTo(now()->addMonth()->startOfMonth()->addDay());
        $this->servicio()->registrarPago(Suscripcion::firstOrFail(), ['metodo' => 'yape']);
        $this->vender($admin)->assertSessionHasNoErrors();
    }

    public function test_limites_de_productos_usuarios_y_almacenes(): void
    {
        $plan = Plan::where('codigo', 'basico')->firstOrFail();
        $plan->update(['max_productos' => 1, 'max_usuarios' => 1, 'max_almacenes' => 1]);
        $this->servicio()->asignar($this->empresa, $plan, ['periodicidad' => 'mensual', 'pagado' => true]);
        $admin = $this->usuarioCon('Administrador', $this->empresa);   // 1 usuario: ya en el límite

        $this->productoConStock($this->empresa, 0);                     // 1 producto: en el límite
        $this->expectaLimite(fn () => app(SuscripcionService::class)->exigir($this->empresa->id, 'productos'));

        $this->actingAs($admin)->post(route('almacenes.store'), ['codigo' => 'ALM-02', 'nombre' => 'Sede 2'])->assertSessionHasErrors('suscripcion');
        $this->actingAs($admin)->post(route('usuarios.store'), ['email' => 'nuevo@x.pe', 'name' => 'N', 'password' => 'clave1234', 'password_confirmation' => 'clave1234',
            'rol_id' => \Spatie\Permission\Models\Role::whereNull('empresa_id')->where('name', 'Vendedor')->value('id')])->assertSessionHasErrors('suscripcion');
    }

    public function test_sin_pagar_hay_dias_de_gracia_y_luego_se_suspende(): void
    {
        $s = $this->servicio()->asignar($this->empresa, Plan::where('codigo', 'basico')->first(), ['periodicidad' => 'mensual']);
        $this->assertSame(Suscripcion::VENCIDA, $s->estadoEfectivo());           // vence ayer: en gracia
        $this->assertSame(1, $s->pagos()->where('estado', PagoSuscripcion::PENDIENTE)->count());   // primer cobro generado
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $this->vender($admin)->assertSessionHasNoErrors();

        $this->travel(6)->days();
        $this->assertSame(Suscripcion::SUSPENDIDA, $s->refresh()->estadoEfectivo());
        $this->vender($admin)->assertSessionHas('error', fn ($m) => str_contains($m, 'suspendida'));
        $this->assertSame(1, Venta::count());   // solo la venta de los días de gracia; la de suspendida no se registró
        $this->actingAs($admin)->get(route('ventas.index'))->assertOk()->assertSee('suspendida');   // consulta sí, con aviso

        // Pagar reactiva: el periodo empieza hoy (no cubre los días suspendidos)
        $pago = $this->servicio()->registrarPago($s, ['metodo' => 'transferencia', 'referencia' => 'OP-1'], 'Soporte');
        $this->assertSame(today()->toDateString(), $pago->periodo_desde->toDateString());
        $this->assertSame(today()->addMonthNoOverflow()->subDay()->toDateString(), $s->refresh()->vence_el->toDateString());
        $this->assertSame(Suscripcion::ACTIVA, $s->estadoEfectivo());
        $this->assertSame('Soporte', $pago->registrado_por);
        $this->vender($admin)->assertSessionHasNoErrors();
    }

    public function test_pagos_consecutivos_y_anulacion_del_ultimo(): void
    {
        $s = $this->servicio()->asignar($this->empresa, Plan::where('codigo', 'total')->first(), ['periodicidad' => 'trimestral', 'pagado' => true, 'metodo' => 'yape']);
        $this->assertSame('2026-06-09', $s->vence_el->toDateString());          // 10/03 + 3 meses − 1 día

        $segundo = $this->servicio()->registrarPago($s, ['metodo' => 'yape']);    // por adelantado: continúa tras el vencimiento
        $this->assertSame('2026-06-10', $segundo->periodo_desde->toDateString());
        $this->assertSame('2026-09-09', $s->refresh()->vence_el->toDateString());
        $this->assertEquals(419.0, $segundo->monto);   // Total trimestral

        $primero = $s->pagos()->where('estado', PagoSuscripcion::PAGADO)->orderBy('id')->first();
        $this->expectaLimite(fn () => $this->servicio()->anularPago($primero), 'pago');   // no es el último

        $this->servicio()->anularPago($segundo, 'Pago duplicado');
        $this->assertSame('2026-06-09', $s->refresh()->vence_el->toDateString());
        $this->assertSame(PagoSuscripcion::ANULADO, $segundo->refresh()->estado);
    }

    public function test_ofertas_en_el_primer_pago_y_en_todos(): void
    {
        $plan = Plan::where('codigo', 'basico')->first();
        Oferta::create(['nombre' => 'Lanzamiento', 'codigo' => 'LANZA20', 'tipo' => 'porcentaje', 'valor' => 20, 'desde' => '2026-03-01', 'hasta' => '2026-03-31', 'max_usos' => 1, 'solo_primer_pago' => true]);

        $s = $this->servicio()->asignar($this->empresa, $plan, ['periodicidad' => 'mensual', 'oferta' => 'lanza20', 'pagado' => true]);
        $primer = $s->pagos()->first();
        $this->assertEquals(49.0, $primer->monto_bruto);
        $this->assertEquals(9.8, $primer->descuento);
        $this->assertEquals(39.2, $primer->monto);

        $renovacion = $this->servicio()->generarCobro($s->refresh());
        $this->assertEquals(0.0, $renovacion->descuento);                         // solo el primer pago

        // Agotada (max_usos = 1): otra empresa ya no puede usarla
        $otra = $this->crearEmpresa();
        $this->expectaLimite(fn () => $this->servicio()->asignar($otra, $plan, ['periodicidad' => 'mensual', 'oferta' => 'LANZA20']), 'oferta');

        // Monto fijo para todos los pagos, solo en el anual
        Oferta::create(['nombre' => 'Anual fiel', 'tipo' => 'monto', 'valor' => 50, 'desde' => '2026-01-01', 'periodicidad' => 'anual', 'solo_primer_pago' => false]);
        $id = Oferta::where('nombre', 'Anual fiel')->value('id');
        $this->expectaLimite(fn () => $this->servicio()->asignar($otra, $plan, ['periodicidad' => 'mensual', 'oferta' => (string) $id]), 'oferta');
        $s2 = $this->servicio()->asignar($otra, $plan, ['periodicidad' => 'anual', 'oferta' => (string) $id, 'pagado' => true]);
        $this->assertEquals(440.0, $s2->pagos()->first()->monto);
        $this->assertEquals(440.0, $this->servicio()->generarCobro($s2->refresh())->monto);
    }

    public function test_prueba_cambio_de_plan_y_cobros_automaticos(): void
    {
        $s = $this->servicio()->asignar($this->empresa, Plan::where('codigo', 'basico')->first(), ['periodicidad' => 'mensual', 'prueba' => true]);
        $this->assertSame(Suscripcion::PRUEBA, $s->estadoEfectivo());
        $this->assertSame('2026-03-24', $s->vence_el->toDateString());           // 15 días
        $this->assertSame(0, $s->pagos()->count());

        // A 7 días del vencimiento la tarea diaria genera el cobro (una sola vez)
        $this->travelTo(now()->setDate(2026, 3, 18));
        $this->artisan('suscripciones:procesar')->assertSuccessful();
        $this->artisan('suscripciones:procesar')->assertSuccessful();
        $cobro = $s->pagos()->sole();
        $this->assertSame('2026-03-25', $cobro->periodo_desde->toDateString());   // continúa tras la prueba

        // Cambio a Total: la anterior queda cancelada y su cobro pendiente se reemplaza
        $nueva = $this->servicio()->asignar($this->empresa, Plan::where('codigo', 'total')->first(), ['periodicidad' => 'anual', 'pagado' => true]);
        $this->assertSame(Suscripcion::CANCELADA, $s->refresh()->estado);
        $this->assertSame(PagoSuscripcion::ANULADO, $cobro->refresh()->estado);
        $this->assertSame($nueva->id, app(SuscripcionService::class)->vigente($this->empresa->id)->id);
        $this->assertEquals(1490.0, $nueva->pagos()->where('estado', PagoSuscripcion::PAGADO)->value('monto'));   // Total anual
    }

    public function test_mi_suscripcion_y_aviso_en_el_erp(): void
    {
        $plan = Plan::where('codigo', 'basico')->firstOrFail();
        $plan->update(['max_productos' => 2, 'max_comprobantes_mes' => 5]);
        $this->servicio()->asignar($this->empresa, $plan, ['periodicidad' => 'mensual', 'pagado' => true]);
        $this->productoConStock($this->empresa, 0);
        $this->productoConStock($this->empresa, 0);
        $admin = $this->usuarioCon('Administrador', $this->empresa);

        $this->actingAs($admin)->get(route('config.suscripcion'))->assertOk()->assertSee('Básico')->assertSee('Pagado hasta')->assertSee('09/04/2026');

        // Productos, usuarios o almacenes en su tope no generan aviso (1 de 1 almacén es normal)
        $this->actingAs($admin)->get(route('inicio'))->assertOk()->assertDontSee('de 2 productos')->assertDontSee('almacenes de tu plan');

        // Comprobantes por agotarse sí: 4 de 5 (80 %)
        foreach (range(1, 4) as $_) {
            $this->vender($admin)->assertSessionHasNoErrors();
        }
        $this->app->forgetScopedInstances();
        $this->actingAs($admin)->get(route('inicio'))->assertOk()->assertSee('Te quedan 1 de 5 comprobantes este mes');
        $this->vender($admin)->assertSessionHasNoErrors();
        $this->app->forgetScopedInstances();
        $this->actingAs($admin)->get(route('inicio'))->assertOk()->assertSee('Emitiste los 5 comprobantes de este mes');

        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))->get(route('config.suscripcion'))->assertForbidden();
    }

    public function test_suspendida_queda_en_solo_lectura(): void
    {
        $this->crearCaja($this->empresa);
        // Plan Total (todos los módulos): así solo se prueba el efecto de la suspensión
        $s = $this->servicio()->asignar($this->empresa, Plan::where('codigo', 'total')->first(), ['periodicidad' => 'mensual', 'pagado' => true]);
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $cajaId = \App\Modulos\Erp\Models\Caja::where('empresa_id', $this->empresa->id)->value('id');

        // Con el turno abierto antes de la suspensión
        $this->actingAs($admin)->post(route('caja.abrir'), ['caja_id' => $cajaId, 'monto_apertura' => 50])->assertSessionHasNoErrors();
        $this->servicio()->suspender($s, 'Falta de pago');
        $this->app->forgetScopedInstances();

        // Bloqueado: formularios y cualquier escritura
        foreach (['ventas.create', 'compras.create', 'productos.create', 'clientes.create', 'ajustes.create', 'transferencias.create', 'usuarios.create'] as $ruta) {
            $this->actingAs($admin)->get(route($ruta))->assertRedirect(route('config.suscripcion'))->assertSessionHas('error');
        }
        $this->actingAs($admin)->post(route('caja.movimientos.store'), ['tipo' => 'egreso', 'concepto' => 'X', 'metodo_pago' => 'efectivo', 'monto' => 5])->assertSessionHas('error');
        $this->actingAs($admin)->post(route('categorias.store'), ['nombre' => 'Nueva'])->assertSessionHas('error');
        $this->actingAs($admin)->put(route('config.empresa.update'), ['ruc' => $this->empresa->ruc, 'razon_social' => 'X'])->assertSessionHas('error');
        $this->assertSame(0, \App\Modulos\Erp\Models\MovimientoCaja::count());
        $this->assertDatabaseMissing('categorias', ['nombre' => 'Nueva']);
        $this->actingAs($admin)->getJson(route('clientes.documento', '12345678'))->assertForbidden();

        // Permitido: consultar, exportar, mi perfil y cerrar el turno abierto
        foreach (['inicio', 'ventas.index', 'compras.index', 'productos.index', 'caja.index', 'config.suscripcion', 'perfil.edit'] as $ruta) {
            $this->actingAs($admin)->get(route($ruta))->assertOk();
        }
        $this->actingAs($admin)->get(route('ventas.export'))->assertOk();
        $this->actingAs($admin)->get(route('caja.cierre'))->assertOk();
        $this->actingAs($admin)->post(route('caja.cerrar'), ['monto_contado' => 50])->assertSessionHasNoErrors();

        // Al reactivar vuelve todo
        $this->servicio()->reactivar($s->refresh());
        $this->app->forgetScopedInstances();
        $this->actingAs($admin)->get(route('ventas.create'))->assertOk();
    }

    public function test_sin_plan_no_hay_limites(): void
    {
        $admin = $this->usuarioCon('Administrador', $this->empresa);
        $this->vender($admin)->assertSessionHasNoErrors();

        config(['suscripciones.sin_plan_ilimitado' => false]);
        $this->app->forgetScopedInstances();
        $this->vender($admin)->assertSessionHasErrors('suscripcion');
    }

    /* ───────────────────────── Auxiliares ───────────────────────── */

    private function servicio(): SuscripcionService
    {
        $this->app->forgetScopedInstances();

        return app(SuscripcionService::class);
    }

    private function vender($user, string $accion = 'registrar')
    {
        $this->app->forgetScopedInstances();   // cada petición real tiene su propia instancia
        $producto = $this->productoConStock($this->empresa, 5, ['precio_unitario' => 10]);

        return $this->actingAs($user)->post(route('ventas.store'), [
            'accion' => $accion, 'correlativo_id' => Correlativo::where('empresa_id', $this->empresa->id)->value('id'),
            'almacen_id' => $this->almacen($this->empresa)->id, 'fecha_emision' => now()->toDateString(), 'metodo_pago' => 'efectivo',
            'productos' => [['id' => $producto->id, 'cantidad' => 1]],
        ]);
    }

    private function expectaLimite(callable $fn, string $campo = 'suscripcion'): void
    {
        try {
            $fn();
            $this->fail("Se esperaba un error de validación en «{$campo}».");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors());
        }
    }
}
