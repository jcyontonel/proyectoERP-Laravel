<?php

namespace Tests\Feature;

use App\Modulos\Nucleo\Models\AuditoriaEvento;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\Impuesto;
use App\Modulos\Nucleo\Models\PreferenciaUsuario;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Catalogo\Models\TipoUnidad;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Nucleo\Support\EmpresaActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

/** Impuestos, unidades, conexión SUNAT, auditoría, mi perfil y preferencias */
class ConfiguracionAvanzadaTest extends TestCase
{
    use ConEmpresa, RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->empresa = $this->crearEmpresa();
        $this->admin = $this->usuarioCon('Administrador', $this->empresa);
    }

    /* ───────────────────────── Catálogos comunes ───────────────────────── */

    public function test_impuestos_del_catalogo_sunat_y_proteccion_entre_empresas(): void
    {
        $this->actingAs($this->admin)->get(route('impuestos.index'))->assertOk()->assertSee('Gravado - IGV');

        // Alta desde el catálogo 07: el tributo lo pone el sistema
        $this->actingAs($this->admin)->post(route('impuestos.store'), ['tipo_afectacion' => '17', 'codigo' => 'ivap', 'nombre' => 'IVAP', 'porcentaje' => 4, 'activo' => 1])
            ->assertRedirect(route('impuestos.index'));
        $ivap = Impuesto::where('codigo', 'IVAP')->firstOrFail();
        $this->assertSame('1016', $ivap->codigo_tributo);
        $this->assertEquals(4.0, $ivap->porcentaje);

        // Exonerado no lleva tasa aunque se envíe
        $this->actingAs($this->admin)->post(route('impuestos.store'), ['tipo_afectacion' => '21', 'codigo' => 'EXOG', 'nombre' => 'Exonerado gratuito', 'porcentaje' => 18]);
        $this->assertEquals(0.0, Impuesto::where('codigo', 'EXOG')->value('porcentaje'));
        $this->actingAs($this->admin)->post(route('impuestos.store'), ['tipo_afectacion' => '17', 'codigo' => 'X', 'nombre' => 'X'])->assertSessionHasErrors('tipo_afectacion');

        // Usado por otra empresa: no se modifica ni se elimina desde aquí
        $otra = $this->crearEmpresa();
        Producto::factory()->create(['empresa_id' => $otra->id, 'impuesto_id' => $ivap->id]);
        $this->actingAs($this->admin)->put(route('impuestos.update', $ivap), ['nombre' => 'Cambiado', 'porcentaje' => 10])->assertForbidden();
        $this->actingAs($this->admin)->delete(route('impuestos.destroy', $ivap))->assertSessionHas('error');
        $this->actingAs($this->admin)->get(route('impuestos.edit', $ivap))->assertOk()->assertSee('solo lectura');

        // Sin uso: se elimina
        $exog = Impuesto::where('codigo', 'EXOG')->firstOrFail();
        $this->actingAs($this->admin)->delete(route('impuestos.destroy', $exog))->assertSessionHas('status');
        $this->assertModelMissing($exog);

        // Permiso
        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))->get(route('impuestos.index'))->assertForbidden();
    }

    public function test_unidades_de_medida(): void
    {
        $this->actingAs($this->admin)->post(route('unidades.store'), ['codigo' => 'sco15', 'codigo_sunat' => 'sa', 'descripcion' => 'Saco de 15 kg'])
            ->assertRedirect(route('unidades.index'));
        $saco = TipoUnidad::where('codigo', 'SCO15')->firstOrFail();
        $this->assertSame('SA', $saco->codigo_sunat);

        $this->actingAs($this->admin)->post(route('unidades.store'), ['codigo' => 'SCO15', 'descripcion' => 'Repetida'])->assertSessionHasErrors('codigo');
        $this->actingAs($this->admin)->post(route('unidades.store'), ['codigo' => 'MAL COD', 'descripcion' => 'Con espacio'])->assertSessionHasErrors('codigo');

        $this->actingAs($this->admin)->put(route('unidades.update', $saco), ['codigo' => 'SCO15', 'codigo_sunat' => 'SA', 'descripcion' => 'Saco 15 kg'])->assertSessionHas('status');
        $this->assertSame('Saco 15 kg', $saco->refresh()->descripcion);

        // En uso por mi empresa: se edita pero no se elimina; por otra empresa: ni se edita
        Producto::factory()->create(['empresa_id' => $this->empresa->id, 'tipo_unidad_id' => $saco->id]);
        $this->actingAs($this->admin)->delete(route('unidades.destroy', $saco))->assertSessionHas('error');
        Producto::factory()->create(['empresa_id' => $this->crearEmpresa()->id, 'tipo_unidad_id' => $saco->id]);
        $this->actingAs($this->admin)->put(route('unidades.update', $saco), ['codigo' => 'SCO15', 'descripcion' => 'X'])->assertForbidden();

        $this->actingAs($this->admin)->get(route('unidades.index', ['q' => 'saco']))->assertOk()->assertSee('Saco 15 kg')->assertSee('Compartida');
    }

    /* ───────────────────────── SUNAT ───────────────────────── */

    public function test_conexion_sunat_con_certificado(): void
    {
        Storage::fake('local');
        $cnf = 'C:/xampp/php/extras/ssl/openssl.cnf';
        $opciones = is_file($cnf) ? ['config' => $cnf] : [];
        $clave = @openssl_pkey_new(['private_key_bits' => 2048] + $opciones);
        if (! $clave) {
            $this->markTestSkipped('OpenSSL no puede generar claves en este entorno (falta openssl.cnf).');
        }

        $ruc = $this->empresa->ruc;
        $csr = openssl_csr_new(['commonName' => 'EMPRESA DE PRUEBA', 'serialNumber' => $ruc], $clave, $opciones);
        $cert = openssl_csr_sign($csr, null, $clave, 365, $opciones);
        openssl_pkcs12_export($cert, $pfx, $clave, 'secreta');
        $archivo = fn () => UploadedFile::fake()->createWithContent('cert.pfx', $pfx);

        $this->actingAs($this->admin)->get(route('config.sunat'))->assertOk()->assertSee('Beta (pruebas)');

        // Contraseña incorrecta
        $this->actingAs($this->admin)->put(route('config.sunat.update'), ['sunat_modo' => 'beta', 'certificado' => $archivo(), 'certificado_clave' => 'otra'])
            ->assertSessionHasErrors('certificado');
        $this->assertNull($this->empresa->refresh()->certificado_path);

        // Producción sin todo lo necesario: no se permite
        $this->actingAs($this->admin)->put(route('config.sunat.update'), ['sunat_modo' => 'produccion'])->assertSessionHasErrors('sunat_modo');

        // Todo completo: usuario y clave SOL, certificado, dirección/ubigeo y serie de boleta
        $this->empresa->update(['direccion' => 'Av. Lima 123', 'ubigeo' => '150101']);
        $this->crearCorrelativoVenta($this->empresa);
        $this->actingAs($this->admin)->put(route('config.sunat.update'), [
            'sunat_modo' => 'produccion', 'sunat_usuario_sol' => 'factura1', 'sunat_clave_sol' => 'Clave123',
            'certificado' => $archivo(), 'certificado_clave' => 'secreta',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $e = $this->empresa->refresh();
        $this->assertSame('produccion', $e->sunat_modo);
        $this->assertSame('FACTURA1', $e->sunat_usuario_sol);
        $this->assertSame('Clave123', $e->sunat_clave_sol);   // cifrada en la base (cast encrypted)
        $this->assertNotSame('Clave123', \DB::table('empresas')->where('id', $e->id)->value('sunat_clave_sol'));
        Storage::disk('local')->assertExists($e->certificado_path);

        // La pantalla muestra el certificado pero nunca las claves
        $this->actingAs($this->admin)->get(route('config.sunat'))->assertOk()
            ->assertSee('EMPRESA DE PRUEBA')->assertSee('Vigente')->assertDontSee('Clave123')->assertDontSee('secreta');

        // Guardar sin clave mantiene la actual
        $this->actingAs($this->admin)->put(route('config.sunat.update'), ['sunat_modo' => 'produccion', 'sunat_usuario_sol' => 'FACTURA1'])->assertSessionHasNoErrors();
        $this->assertSame('Clave123', $e->refresh()->sunat_clave_sol);
    }

    /* ───────────────────────── Auditoría ───────────────────────── */

    public function test_la_auditoria_registra_cambios_sin_datos_sensibles(): void
    {
        $producto = $this->productoConStock($this->empresa, 5, ['nombre' => 'Galletas', 'precio_unitario' => 10]);
        $this->actingAs($this->admin);
        $producto->update(['precio_unitario' => 12.5]);
        $this->admin->update(['password' => Hash::make('nueva-clave-1')]);

        $evento = AuditoriaEvento::where('entidad', 'Producto')->where('accion', 'actualizado')->latest('id')->firstOrFail();
        $this->assertEquals(10, $evento->datos_anteriores['precio_unitario']);
        $this->assertEquals(12.5, $evento->datos_nuevos['precio_unitario']);
        $this->assertSame($this->empresa->id, $evento->empresa_id);
        $this->assertSame($this->admin->id, $evento->user_id);

        $clave = AuditoriaEvento::where('entidad', 'User')->where('accion', 'actualizado')->latest('id')->firstOrFail();
        $this->assertSame('••••••', $clave->datos_nuevos['password']);

        // Login fallido y exitoso quedan registrados
        auth()->logout();
        $this->post(route('login.post'), ['email' => $this->admin->email, 'password' => 'mal']);
        $this->post(route('login.post'), ['email' => $this->admin->email, 'password' => 'nueva-clave-1'])->assertRedirect(route('inicio'));
        $this->assertTrue(AuditoriaEvento::where('accion', 'acceso fallido')->exists());
        $this->assertTrue(AuditoriaEvento::where('accion', 'inicio de sesión')->where('user_id', $this->admin->id)->exists());

        // Pantalla, filtros y exportación; solo la empresa propia
        $this->actingAs($this->admin)->get(route('config.auditoria', ['entidad' => 'Producto']))->assertOk()->assertSee('precio_unitario')->assertSee('12.5');
        $this->assertStringContainsString('Galletas', $this->actingAs($this->admin)->get(route('config.auditoria.export'))->streamedContent());

        $ajeno = $this->usuarioCon('Administrador', $this->crearEmpresa());
        $this->actingAs($ajeno)->get(route('config.auditoria', ['entidad' => 'Producto']))->assertOk()->assertDontSee('precio_unitario');
        $this->actingAs($this->usuarioCon('Contador', $this->empresa))->get(route('config.auditoria'))->assertForbidden();
    }

    /* ───────────────────────── Mi cuenta ───────────────────────── */

    public function test_mi_perfil_y_cambio_de_contrasena(): void
    {
        $vendedor = $this->usuarioCon('Vendedor', $this->empresa);
        $vendedor->update(['password' => Hash::make('clave-vieja1')]);

        $this->actingAs($vendedor)->get(route('perfil.edit'))->assertOk()->assertSee('Vendedor');
        $this->actingAs($vendedor)->put(route('perfil.update'), ['name' => 'Rosa', 'apellido' => 'Díaz', 'email' => 'ROSA@ricopet.pe'])->assertSessionHas('status');
        $this->assertSame('rosa@ricopet.pe', $vendedor->refresh()->email);
        $this->actingAs($vendedor)->put(route('perfil.update'), ['name' => 'Rosa', 'email' => $this->admin->email])->assertSessionHasErrors('email');

        $this->actingAs($vendedor)->put(route('perfil.password'), ['password_actual' => 'equivocada', 'password' => 'nueva-clave2', 'password_confirmation' => 'nueva-clave2'])
            ->assertSessionHasErrors('password_actual');
        $this->assertNull(session()->getOldInput('password_actual'));   // las claves no vuelven al formulario
        $this->actingAs($vendedor)->put(route('perfil.password'), ['password_actual' => 'clave-vieja1', 'password' => 'nueva-clave2', 'password_confirmation' => 'nueva-clave2'])
            ->assertSessionHas('status');
        $this->assertTrue(Hash::check('nueva-clave2', $vendedor->refresh()->password));
    }

    public function test_preferencias_empresa_de_inicio_alertas_y_ticket(): void
    {
        $otra = $this->crearEmpresa(['nombre_comercial' => 'Sucursal Norte']);
        $this->admin->empresas()->attach($otra->id);
        EmpresaActual::olvidar();

        $this->actingAs($this->admin)->get(route('perfil.preferencias'))->assertOk()->assertSee('Sucursal Norte');

        $this->actingAs($this->admin)->put(route('perfil.preferencias.guardar'), [
            'empresa_inicio_id' => $otra->id, 'ticket_ancho' => '58', 'imprimir_al_vender' => 1,
            'alertas' => ['stock_bajo', 'cobranzas'],   // el resto queda oculto
        ])->assertSessionHas('status');

        $pref = PreferenciaUsuario::where('user_id', $this->admin->id)->firstOrFail();
        $this->assertSame('58', $pref->ticket_ancho);
        $this->assertTrue($pref->ocultaAlerta('caja'));
        $this->assertFalse($pref->ocultaAlerta('stock_bajo'));

        // Sin empresa en la sesión, empieza en la preferida
        EmpresaActual::olvidar();
        $this->assertSame($otra->id, EmpresaActual::id($this->admin->fresh()));

        // Empresa a la que no tiene acceso: se rechaza
        $this->actingAs($this->admin)->put(route('perfil.preferencias.guardar'), ['empresa_inicio_id' => $this->crearEmpresa()->id, 'ticket_ancho' => '80'])
            ->assertSessionHasErrors('empresa_inicio_id');

        // La alerta de caja cerrada (oculta) ya no aparece
        $alertas = app(\App\Modulos\Erp\Services\NotificacionService::class)->alertas($this->empresa->id, $this->comoUsuario($this->admin->fresh(), $this->empresa));
        $this->assertFalse($alertas->contains('titulo', 'Caja cerrada'));
    }

    public function test_imprimir_al_vender_y_ticket_de_58_mm(): void
    {
        $this->crearCorrelativoVenta($this->empresa);
        config(['caja.exigir_para_vender' => false]);
        PreferenciaUsuario::create(['user_id' => $this->admin->id, 'ticket_ancho' => '58', 'imprimir_al_vender' => true]);
        $producto = $this->productoConStock($this->empresa, 5);

        $this->actingAs($this->admin)->post(route('ventas.store'), [
            'accion' => 'registrar', 'correlativo_id' => Correlativo::where('empresa_id', $this->empresa->id)->value('id'),
            'almacen_id' => $this->almacen($this->empresa)->id, 'fecha_emision' => now()->toDateString(), 'metodo_pago' => 'efectivo',
            'productos' => [['id' => $producto->id, 'cantidad' => 1]],
        ])->assertSessionHas('imprimir_ticket', true);

        $venta = \App\Modulos\Erp\Models\Venta::firstOrFail();
        $this->actingAs($this->admin)->get(route('ventas.ticket', [$venta, 'auto' => 1]))->assertOk()->assertSee('54mm')->assertSee('window.print()', false);
    }
}
