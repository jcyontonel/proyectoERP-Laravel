<?php

namespace Tests\Feature;

use App\Models\Correlativo;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ConEmpresa;
use Tests\TestCase;

class ConfiguracionTest extends TestCase
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

    /* ───────────────────────── Empresa ───────────────────────── */

    public function test_actualiza_los_datos_y_el_logo_de_la_empresa(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->get(route('config.empresa'))->assertOk()->assertSee($this->empresa->ruc);

        $this->actingAs($this->admin)->put(route('config.empresa.update'), $this->datosEmpresa([
            'razon_social' => 'RICOPET S.A.C.', 'igv' => 10, 'precios_incluyen_igv' => 0,
        ]))->assertRedirect(route('config.empresa'))->assertSessionHas('status');

        $e = $this->empresa->refresh();
        $this->assertSame('RICOPET S.A.C.', $e->razon_social);
        $this->assertEquals(10.0, $e->igv);
        $this->assertFalse($e->precios_incluyen_igv);

        // El logo se sube aparte: no depende de que el resto del formulario sea válido
        $this->actingAs($this->admin)->post(route('config.empresa.logo.subir'), [
            // PNG real de 1×1 (el PHP de consola puede no tener GD para fake()->image())
            'logo' => UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')),
        ])->assertRedirect(route('config.empresa'))->assertSessionHas('status');
        Storage::disk('local')->assertExists($e->refresh()->logo_path);
        $this->actingAs($this->admin)->get(route('config.empresa.logo'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->admin)->get(route('config.empresa'))->assertSee('Cambiar logo');

        // Un logo de más de 2 MB o que no es imagen se rechaza con un mensaje claro
        $this->actingAs($this->admin)->post(route('config.empresa.logo.subir'), ['logo' => UploadedFile::fake()->create('logo.png', 3000, 'image/png')])
            ->assertSessionHasErrors(['logo' => 'El logo no debe pesar más de 2 MB.']);
        $this->actingAs($this->admin)->post(route('config.empresa.logo.subir'), ['logo' => UploadedFile::fake()->createWithContent('logo.pdf', '%PDF-1.4')])
            ->assertSessionHasErrors('logo');

        // Quitar el logo borra también el archivo
        $ruta = $e->logo_path;
        $this->actingAs($this->admin)->delete(route('config.empresa.logo.quitar'))->assertSessionHas('status');
        $this->assertNull($e->refresh()->logo_path);
        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_valida_el_ruc_y_exige_permiso(): void
    {
        $this->actingAs($this->admin)->put(route('config.empresa.update'), $this->datosEmpresa(['ruc' => '30123456789']))->assertSessionHasErrors('ruc');
        $otra = $this->crearEmpresa();
        $this->actingAs($this->admin)->put(route('config.empresa.update'), $this->datosEmpresa(['ruc' => $otra->ruc]))->assertSessionHasErrors('ruc');

        $vendedor = $this->usuarioCon('Vendedor', $this->empresa);
        $this->actingAs($vendedor)->get(route('config.empresa'))->assertForbidden();
        $this->actingAs($vendedor)->get(route('config.empresa.logo'))->assertNotFound();   // sin logo, pero la ruta no es de administración
    }

    /* ───────────────────────── Usuarios ───────────────────────── */

    public function test_crea_un_usuario_con_rol_y_puede_iniciar_sesion(): void
    {
        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Ana', 'apellido' => 'Ríos', 'email' => 'ANA@ricopet.pe', 'password' => 'clave1234', 'password_confirmation' => 'clave1234',
            'rol_id' => $this->rol('Vendedor')->id,
        ])->assertRedirect(route('usuarios.index'))->assertSessionHas('status');

        $ana = User::where('email', 'ana@ricopet.pe')->firstOrFail();
        $this->assertTrue($ana->empresas()->whereKey($this->empresa->id)->exists());
        $this->assertTrue($this->comoUsuario($ana, $this->empresa)->hasRole('Vendedor'));

        $this->actingAs($this->admin)->get(route('usuarios.index'))->assertOk()->assertSee('Ana Ríos')->assertSee('Vendedor');

        auth()->logout();
        $this->post(route('login.post'), ['email' => 'ana@ricopet.pe', 'password' => 'clave1234'])->assertRedirect('/');
    }

    public function test_un_correo_existente_recibe_acceso_sin_cambiar_su_cuenta(): void
    {
        $otra = $this->crearEmpresa();
        $externo = $this->usuarioCon('Contador', $otra);
        $clave = $externo->password;

        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'email' => $externo->email, 'name' => 'Otro nombre', 'password' => 'nueva1234', 'password_confirmation' => 'nueva1234',
            'rol_id' => $this->rol('Vendedor')->id,
        ])->assertSessionHas('status', fn ($m) => str_contains($m, 'ya tenía cuenta'));

        $externo->refresh();
        $this->assertSame($clave, $externo->password);
        $this->assertNotSame('Otro nombre', $externo->name);
        $this->assertTrue($this->comoUsuario($externo, $this->empresa)->hasRole('Vendedor'));
        $this->assertTrue($this->comoUsuario($externo, $otra)->hasRole('Contador'));   // su rol en la otra empresa no cambia

        // Trabaja en otra empresa: aquí solo se cambia su rol, no sus datos
        $this->actingAs($this->admin)->put(route('usuarios.update', $externo), [
            'email' => $externo->email, 'name' => 'Cambiado', 'rol_id' => $this->rol('Contador')->id,
        ])->assertSessionHas('status');
        $this->assertNotSame('Cambiado', $externo->refresh()->name);
        $this->assertTrue($this->comoUsuario($externo, $this->empresa)->hasRole('Contador'));

        // Repetir el alta falla
        $this->actingAs($this->admin)->post(route('usuarios.store'), ['email' => $externo->email, 'rol_id' => $this->rol('Vendedor')->id])
            ->assertSessionHasErrors('email');
    }

    public function test_reglas_de_seguridad_de_usuarios(): void
    {
        // No puede cambiar su propio rol ni quitarse el acceso
        $this->actingAs($this->admin)->put(route('usuarios.update', $this->admin), [
            'email' => $this->admin->email, 'name' => $this->admin->name, 'rol_id' => $this->rol('Vendedor')->id,
        ])->assertSessionHasErrors('rol_id');
        $this->actingAs($this->admin)->delete(route('usuarios.destroy', $this->admin))->assertSessionHasErrors('usuario');

        // Un rol propio con gestión de usuarios no puede dejar a la empresa sin Administrador
        $jefe = $this->usuarioConRolPropio('Jefe de tienda', ['gestionar-usuarios']);
        $this->actingAs($jefe)->put(route('usuarios.update', $this->admin), [
            'email' => $this->admin->email, 'name' => $this->admin->name, 'rol_id' => $this->rol('Vendedor')->id,
        ])->assertSessionHasErrors('rol_id');
        $this->actingAs($jefe)->delete(route('usuarios.destroy', $this->admin))->assertSessionHasErrors('rol_id');
        $this->assertTrue($this->comoUsuario($this->admin, $this->empresa)->hasRole('Administrador'));

        // Usuario de otra empresa: 404
        $ajeno = $this->usuarioCon('Vendedor', $this->crearEmpresa());
        $this->actingAs($this->admin)->get(route('usuarios.edit', $ajeno))->assertNotFound();

        // El vendedor no administra usuarios
        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))->get(route('usuarios.index'))->assertForbidden();
    }

    public function test_desactivar_y_quitar_acceso(): void
    {
        $vendedor = $this->usuarioCon('Vendedor', $this->empresa);
        $vendedor->update(['password' => Hash::make('clave1234')]);

        $this->actingAs($this->admin)->put(route('usuarios.update', $vendedor), [
            'email' => $vendedor->email, 'name' => $vendedor->name, 'rol_id' => $this->rol('Vendedor')->id, 'activo' => 0,
        ])->assertSessionHas('status');
        $this->assertFalse($vendedor->refresh()->activo);

        // Con la sesión abierta se le cierra; y no puede volver a entrar
        $this->actingAs($vendedor)->get(route('inicio'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.post'), ['email' => $vendedor->email, 'password' => 'clave1234'])->assertSessionHas('error');
        $this->assertGuest();

        // Quitar acceso: sale de la empresa y pierde el rol en ella
        $this->actingAs($this->admin)->delete(route('usuarios.destroy', $vendedor))->assertSessionHas('status');
        $this->assertFalse($vendedor->empresas()->whereKey($this->empresa->id)->exists());
        $this->assertFalse($this->comoUsuario($vendedor, $this->empresa)->hasRole('Vendedor'));
        $this->assertNotNull(User::find($vendedor->id));
    }

    /* ───────────────────────── Roles ───────────────────────── */

    public function test_roles_propios_con_permisos(): void
    {
        $this->actingAs($this->admin)->get(route('roles.index'))->assertOk()->assertSee('Administrador')->assertSee('Vendedor');
        $this->actingAs($this->admin)->get(route('roles.create', ['desde' => $this->rol('Vendedor')->id]))->assertOk()->assertSee('Vendedor (copia)');

        $this->actingAs($this->admin)->post(route('roles.store'), ['name' => 'Cajero', 'permisos' => ['gestionar-caja', 'emitir-ventas']])
            ->assertRedirect(route('roles.index'));
        $cajero = Role::where('name', 'Cajero')->firstOrFail();
        $this->assertSame($this->empresa->id, (int) $cajero->empresa_id);

        // Nombre repetido con un rol del sistema
        $this->actingAs($this->admin)->post(route('roles.store'), ['name' => 'Vendedor'])->assertSessionHasErrors('name');

        // Asignado a un usuario, el permiso aplica
        $ana = User::factory()->create();
        $ana->empresas()->attach($this->empresa->id);
        $this->actingAs($this->admin)->put(route('usuarios.update', $ana), ['email' => $ana->email, 'name' => $ana->name, 'rol_id' => $cajero->id])
            ->assertSessionHas('status');
        $this->assertTrue($this->comoUsuario($ana->refresh(), $this->empresa)->can('gestionar-caja'));
        $this->assertFalse($ana->can('ver-reportes'));

        // Editar sus permisos
        $this->actingAs($this->admin)->put(route('roles.update', $cajero->id), ['name' => 'Cajero', 'permisos' => ['gestionar-caja', 'ver-reportes']]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertTrue($this->comoUsuario($ana->refresh(), $this->empresa)->can('ver-reportes'));

        // Con usuarios no se elimina; los del sistema no se tocan
        $this->actingAs($this->admin)->delete(route('roles.destroy', $cajero->id))->assertSessionHas('error');
        $this->actingAs($this->admin)->put(route('roles.update', $this->rol('Vendedor')->id), ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->admin)->delete(route('roles.destroy', $this->rol('Vendedor')->id))->assertForbidden();

        // Un rol propio de otra empresa no es visible
        $ajeno = Role::create(['name' => 'Ajeno', 'guard_name' => 'web', 'empresa_id' => $this->crearEmpresa()->id]);
        $this->actingAs($this->admin)->get(route('roles.edit', $ajeno->id))->assertNotFound();
    }

    /* ───────────────────────── Series ───────────────────────── */

    public function test_series_y_correlativos(): void
    {
        $this->actingAs($this->admin)->post(route('series.store'), ['modulo' => 'venta', 'tipo' => '01', 'serie' => 'f002', 'destipo' => 'Factura', 'activo' => 1])
            ->assertRedirect(route('series.index'));
        $f002 = Correlativo::where('serie', 'F002')->firstOrFail();
        $this->assertSame(0, $f002->numero);

        // Formato SUNAT, duplicados y tipo que no corresponde al módulo
        $this->actingAs($this->admin)->post(route('series.store'), ['modulo' => 'venta', 'tipo' => '01', 'serie' => 'B002', 'destipo' => 'Factura'])->assertSessionHasErrors('serie');
        $this->actingAs($this->admin)->post(route('series.store'), ['modulo' => 'venta', 'tipo' => '01', 'serie' => 'F002', 'destipo' => 'Factura'])->assertSessionHasErrors('serie');
        $this->actingAs($this->admin)->post(route('series.store'), ['modulo' => 'ajuste', 'tipo' => '01', 'serie' => 'AJ09', 'destipo' => 'X'])->assertSessionHasErrors('tipo');

        // Ya emitió: no cambia de serie ni retrocede, sí adelanta el número
        $f002->update(['numero' => 120]);
        $this->actingAs($this->admin)->put(route('series.update', $f002), ['serie' => 'F009', 'destipo' => 'Factura', 'numero' => 100, 'activo' => 1])->assertSessionHasErrors('numero');
        $this->actingAs($this->admin)->put(route('series.update', $f002), ['serie' => 'F009', 'destipo' => 'Factura electrónica', 'numero' => 150, 'activo' => 0])->assertSessionHas('status');
        $f002->refresh();
        $this->assertSame('F002', $f002->serie);
        $this->assertSame(150, $f002->numero);
        $this->assertFalse($f002->activo);
        $this->actingAs($this->admin)->delete(route('series.destroy', $f002))->assertSessionHas('error');

        // Sin uso se elimina y la serie queda libre
        $b009 = Correlativo::create(['empresa_id' => $this->empresa->id, 'modulo' => 'venta', 'tipo' => '03', 'destipo' => 'Boleta', 'serie' => 'B009']);
        $this->actingAs($this->admin)->delete(route('series.destroy', $b009))->assertSessionHas('status');
        $this->actingAs($this->admin)->post(route('series.store'), ['modulo' => 'venta', 'tipo' => '03', 'serie' => 'B009', 'destipo' => 'Boleta'])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get(route('series.index'))->assertOk()->assertSee('F002-000151');

        // Otra empresa y permisos
        $ajena = Correlativo::create(['empresa_id' => $this->crearEmpresa()->id, 'modulo' => 'venta', 'tipo' => '01', 'destipo' => 'Factura', 'serie' => 'F001']);
        $this->actingAs($this->admin)->get(route('series.edit', $ajena))->assertNotFound();
        $this->actingAs($this->usuarioCon('Contador', $this->empresa))->get(route('series.index'))->assertOk();
        $this->actingAs($this->usuarioCon('Vendedor', $this->empresa))->get(route('series.index'))->assertForbidden();
    }

    /* ───────────────────────── Auxiliares ───────────────────────── */

    private function rol(string $nombre): Role
    {
        return Role::whereNull('empresa_id')->where('name', $nombre)->firstOrFail();
    }

    private function usuarioConRolPropio(string $nombre, array $permisos): User
    {
        $rol = Role::create(['name' => $nombre, 'guard_name' => 'web', 'empresa_id' => $this->empresa->id]);
        $rol->syncPermissions($permisos);

        $user = User::factory()->create();
        $user->empresas()->attach($this->empresa->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->empresa->id);
        $user->assignRole($rol);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $user->unsetRelation('roles');
    }

    private function datosEmpresa(array $cambios = []): array
    {
        return $cambios + [
            'ruc' => $this->empresa->ruc, 'razon_social' => $this->empresa->razon_social, 'moneda' => 'PEN',
            'igv' => 18, 'precios_incluyen_igv' => 1, 'controlar_stock' => 1,
        ];
    }
}
