<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Correlativo;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** API de administración que consume el proyecto admin */
class AdminApiTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private const TOKEN = 'token-de-prueba-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->seed(PlanSeeder::class);
        config(['services.admin.token' => self::TOKEN]);
    }

    public function test_exige_el_token(): void
    {
        $this->getJson('/api/admin/resumen')->assertUnauthorized();
        $this->getJson('/api/admin/resumen', ['Authorization' => 'Bearer otro'])->assertUnauthorized();

        config(['services.admin.token' => null]);
        $this->getJson('/api/admin/resumen', ['Authorization' => 'Bearer ' . self::TOKEN])->assertStatus(503);
    }

    public function test_alta_completa_de_empresa_con_su_administrador_y_plan(): void
    {
        $basico = Plan::where('codigo', 'basico')->first();

        $r = $this->api('post', 'empresas', [
            'ruc' => '20601234567', 'razon_social' => 'Mascotas del Norte SAC', 'nombre_comercial' => 'MasNorte', 'direccion' => 'Av. Grau 100',
            'admin' => ['name' => 'Lucía', 'apellido' => 'Paz', 'email' => 'LUCIA@masnorte.pe', 'password' => 'clave1234'],
            'plan_id' => $basico->id, 'periodicidad' => 'mensual', 'prueba' => true,
        ])->assertCreated()->assertJsonPath('data.suscripcion.estado_efectivo', 'prueba');

        $empresa = Empresa::where('ruc', '20601234567')->firstOrFail();
        $this->assertTrue(Almacen::where('empresa_id', $empresa->id)->where('es_principal', true)->exists());
        $this->assertTrue(Correlativo::where('empresa_id', $empresa->id)->where('serie', 'F001')->exists());

        $lucia = User::where('email', 'lucia@masnorte.pe')->firstOrFail();
        app(PermissionRegistrar::class)->setPermissionsTeamId($empresa->id);
        $this->assertTrue($lucia->hasRole('Administrador'));
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        // La administradora puede entrar al ERP de inmediato
        $this->post(route('login.post'), ['email' => 'lucia@masnorte.pe', 'password' => 'clave1234'])->assertRedirect('/');

        // RUC repetido y sin contraseña para un usuario nuevo
        $this->api('post', 'empresas', ['ruc' => '20601234567', 'razon_social' => 'X', 'admin' => ['name' => 'X', 'email' => 'x@x.pe']])->assertJsonValidationErrors('ruc');
        $this->api('post', 'empresas', ['ruc' => '20609999999', 'razon_social' => 'X', 'admin' => ['name' => 'X', 'email' => 'nuevo@x.pe']])->assertJsonValidationErrors('admin.password');
    }

    public function test_listado_ficha_y_filtros_de_empresas(): void
    {
        $con = $this->crearEmpresa(['razon_social' => 'Con Plan SAC']);
        $sin = $this->crearEmpresa(['razon_social' => 'Sin Plan SAC']);
        $this->api('post', "empresas/{$con->id}/suscripcion", ['plan_id' => Plan::where('codigo', 'total')->value('id'), 'periodicidad' => 'anual', 'pagado' => true, 'metodo' => 'transferencia'])
            ->assertCreated();

        $this->api('get', 'empresas?estado=activa')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.razon_social', 'Con Plan SAC');
        $this->api('get', 'empresas?estado=sin_plan')->assertOk()->assertJsonPath('data.0.razon_social', 'Sin Plan SAC');
        $this->api('get', 'empresas?q=con plan')->assertOk()->assertJsonCount(1, 'data');

        $this->api('get', "empresas/{$con->id}")->assertOk()
            ->assertJsonPath('data.estado', 'activa')
            ->assertJsonPath('data.limites.productos.maximo', null)
            ->assertJsonPath('data.pagos.0.monto', 1490)
            ->assertJsonPath('data.pagos.0.estado', 'pagado');

        $this->api('put', "empresas/{$sin->id}", ['ruc' => $sin->ruc, 'razon_social' => 'Renombrada SAC', 'activo' => false])->assertOk()->assertJsonPath('data.activo', false);
    }

    public function test_suscripcion_pagos_suspension_y_resumen(): void
    {
        $empresa = $this->crearEmpresa();
        $s = $this->api('post', "empresas/{$empresa->id}/suscripcion", ['plan_id' => Plan::where('codigo', 'basico')->value('id'), 'periodicidad' => 'trimestral'])
            ->assertCreated()->assertJsonPath('data.estado_efectivo', 'vencida')->json('data');

        // Cobro pendiente del primer periodo → pagar
        $pendiente = $this->api('get', 'pagos?estado=pendiente')->assertOk()->assertJsonPath('totales.pendiente.total', 139)->json('data.0');
        $this->api('post', "empresas/{$empresa->id}/pagos", ['pago_id' => $pendiente['id'], 'metodo' => 'yape', 'referencia' => '998877'], ['X-Admin-Usuario' => 'Ana Soporte'])
            ->assertCreated()->assertJsonPath('data.estado', 'pagado')->assertJsonPath('data.registrado_por', 'Ana Soporte');
        $this->assertSame(Suscripcion::ACTIVA, Suscripcion::find($s['id'])->estadoEfectivo());

        // Suspender y reactivar a mano
        $this->api('post', "suscripciones/{$s['id']}/suspender", [])->assertJsonValidationErrors('motivo');
        $this->api('post', "suscripciones/{$s['id']}/suspender", ['motivo' => 'Pedido del cliente'])->assertOk()->assertJsonPath('data.estado_efectivo', 'suspendida');
        $this->api('post', "suscripciones/{$s['id']}/reactivar")->assertOk()->assertJsonPath('data.estado_efectivo', 'activa');

        $this->api('get', 'resumen')->assertOk()->assertJsonPath('data.empresas.activa', 1)->assertJsonPath('data.cobrado_mes', 139)
            ->assertJsonPath('data.mrr', round(139 / 3, 2));

        // Anular el pago y cancelar
        $pago = $this->api('get', "pagos?empresa_id={$empresa->id}&estado=pagado")->json('data.0');
        $this->api('post', "pagos/{$pago['id']}/anular", ['motivo' => 'Error'])->assertOk()->assertJsonPath('data.estado', 'anulado');
        $this->api('post', "suscripciones/{$s['id']}/cancelar")->assertOk()->assertJsonPath('data.estado', 'cancelada');
        $this->api('post', "empresas/{$empresa->id}/pagos", ['metodo' => 'yape'])->assertStatus(422);
    }

    public function test_planes_y_ofertas(): void
    {
        $this->api('get', 'planes')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.codigo', 'basico')->assertJsonPath('data.1.codigo', 'pro')->assertJsonPath('data.2.codigo', 'total')
            ->assertJsonPath('data.0.modulos', ['notas', 'cuentas_cobrar', 'reportes_basicos']);

        $plan = $this->api('post', 'planes', [
            'codigo' => 'empresarial', 'nombre' => 'Empresarial', 'max_comprobantes_mes' => null, 'max_usuarios' => 50, 'activo' => true, 'orden' => 4,
            'precios' => [['periodicidad' => 'mensual', 'precio' => 199], ['periodicidad' => 'anual', 'precio' => 1990], ['periodicidad' => 'trimestral', 'precio' => null]],
        ])->assertCreated()->assertJsonCount(2, 'data.precios')->json('data');

        $this->api('put', "planes/{$plan['id']}", ['codigo' => 'empresarial', 'nombre' => 'Empresarial', 'max_usuarios' => 60,
            'precios' => [['periodicidad' => 'mensual', 'precio' => 189]]])->assertOk()->assertJsonPath('data.max_usuarios', 60)->assertJsonPath('data.precios.0.precio', 189);
        $this->api('delete', "planes/{$plan['id']}")->assertNoContent();

        $oferta = $this->api('post', 'ofertas', ['nombre' => 'Cyber', 'codigo' => 'cyber', 'tipo' => 'porcentaje', 'valor' => 30, 'desde' => today()->toDateString(), 'max_usos' => 10])
            ->assertCreated()->assertJsonPath('data.codigo', 'CYBER')->assertJsonPath('data.vigente', true)->json('data');
        $this->api('post', 'ofertas', ['nombre' => 'Mal', 'tipo' => 'porcentaje', 'valor' => 150, 'desde' => today()->toDateString()])->assertJsonValidationErrors('valor');

        $empresa = $this->crearEmpresa();
        $this->api('post', "empresas/{$empresa->id}/suscripcion", ['plan_id' => Plan::where('codigo', 'total')->value('id'), 'periodicidad' => 'mensual', 'oferta' => 'cyber', 'pagado' => true])
            ->assertCreated();
        $this->api('get', "empresas/{$empresa->id}")->assertJsonPath('data.pagos.0.monto', 104.3);   // Total mensual 149 − 30 %
        $this->api('delete', "ofertas/{$oferta['id']}")->assertStatus(422);   // ya se usó
    }

    public function test_usuarios_principales_de_la_empresa(): void
    {
        $empresa = $this->crearEmpresa();
        $admin = $this->usuarioCon('Administrador', $empresa);

        $this->api('get', "empresas/{$empresa->id}/usuarios")->assertOk()->assertJsonPath('data.0.rol', 'Administrador')->assertJsonFragment(['Vendedor']);

        $nuevo = $this->api('post', "empresas/{$empresa->id}/usuarios", ['name' => 'Pedro', 'email' => 'pedro@x.pe', 'password' => 'clave1234', 'rol' => 'Vendedor'])
            ->assertCreated()->assertJsonPath('data.rol', 'Vendedor')->json('data');

        // Cambiar rol, restablecer contraseña, desactivar
        $this->api('put', "empresas/{$empresa->id}/usuarios/{$nuevo['id']}", ['rol' => 'Contador'])->assertOk()->assertJsonPath('data.rol', 'Contador');
        $this->api('put', "empresas/{$empresa->id}/usuarios/{$nuevo['id']}/password", ['password' => 'nueva12345'])->assertOk();
        $this->assertTrue(Hash::check('nueva12345', User::find($nuevo['id'])->password));
        $this->api('put', "empresas/{$empresa->id}/usuarios/{$nuevo['id']}", ['activo' => false])->assertOk()->assertJsonPath('data.activo', false);

        // No puede quedarse sin administrador
        $this->api('put', "empresas/{$empresa->id}/usuarios/{$admin->id}", ['rol' => 'Vendedor'])->assertJsonValidationErrors('rol_id');
        $this->api('delete', "empresas/{$empresa->id}/usuarios/{$admin->id}")->assertJsonValidationErrors('rol_id');

        // Quitar acceso; un usuario de otra empresa da 404
        $this->api('delete', "empresas/{$empresa->id}/usuarios/{$nuevo['id']}")->assertOk();
        $ajeno = $this->usuarioCon('Vendedor', $this->crearEmpresa());
        $this->api('put', "empresas/{$empresa->id}/usuarios/{$ajeno->id}", ['activo' => false])->assertNotFound();
    }

    public function test_consulta_ruc_y_catalogos(): void
    {
        config(['services.apiperu.token' => 'x']);
        Http::fake(['*/ruc' => Http::response(['success' => true, 'data' => ['ruc' => '20601234567', 'nombre_o_razon_social' => 'MASCOTAS SAC', 'direccion' => 'AV. LIMA 1', 'estado' => 'ACTIVO', 'condicion' => 'HABIDO']])]);

        $this->api('get', 'consulta-ruc/20601234567')->assertOk()->assertJsonPath('data.nombre', 'MASCOTAS SAC');
        $this->api('get', 'catalogos')->assertOk()->assertJsonCount(3, 'data.periodicidades')->assertJsonPath('data.dias_gracia', 5);
    }

    private function api(string $metodo, string $ruta, array $datos = [], array $cabeceras = [])
    {
        $this->app->forgetScopedInstances();

        return $this->json(strtoupper($metodo), "/api/admin/{$ruta}", $datos, ['Authorization' => 'Bearer ' . self::TOKEN] + $cabeceras);
    }
}
