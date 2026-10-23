<?php

namespace Database\Seeders\Negocios;

use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Catalogo\Models\Categoria;
use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Plataforma\Models\Plan;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Erp\Models\Proveedor;
use App\Modulos\Nucleo\Models\TipoDocumento;
use App\Modulos\Catalogo\Models\TipoUnidad;
use App\Modulos\Catalogo\Models\UnidadConversion;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Erp\Services\EmpresaService;
use App\Modulos\Erp\Services\InventarioService;
use App\Modulos\Erp\Services\LoteService;
use App\Modulos\Plataforma\Services\SuscripcionService;
use App\Modulos\Web\Models\Tienda;
use App\Modulos\Web\Models\ZonaEnvio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Herramientas comunes de los seeders de negocio (uno por empresa). Todo es idempotente: volver a
 * correr el seeder no duplica nada. El stock entra por InventarioService (queda en el kardex).
 */
trait SiembraNegocio
{
    protected Empresa $empresa;

    /** La empresa la creó este seeder (en una base que ya tenía datos, las existentes no reciben plan) */
    protected bool $empresaNueva = false;

    /** @var array<string, int> nombre de categoría => id */
    protected array $categorias = [];

    /** Empresa con almacén, caja, series y motivos de ajuste */
    protected function empresa(array $datos): Empresa
    {
        $this->empresa = Empresa::firstOrCreate(['ruc' => $datos['ruc']], $datos + ['moneda' => 'PEN', 'igv' => 18, 'activo' => true, 'sunat_modo' => 'beta']);
        $this->empresaNueva = $this->empresa->wasRecentlyCreated;
        app(EmpresaService::class)->inicializar($this->empresa);

        return $this->empresa;
    }

    /** Usuario con acceso a la empresa. $rol: rol del sistema o rol propio de la empresa (rolPropio) */
    protected function usuario(string $email, string $nombre, string $clave, string $rol, ?string $apellido = null, ?string $telefono = null): User
    {
        $user = User::firstOrCreate(['email' => mb_strtolower($email)], [
            'name' => $nombre, 'apellido' => $apellido, 'telefono' => $telefono, 'password' => Hash::make($clave), 'activo' => true,
        ]);
        $user->empresas()->syncWithoutDetaching([$this->empresa->id]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->empresa->id);
        $role = Role::query()->where('name', $rol)->where(fn ($q) => $q->where('empresa_id', $this->empresa->id)->orWhereNull('empresa_id'))
            ->orderByRaw('CASE WHEN empresa_id IS NULL THEN 1 ELSE 0 END')->firstOrFail();
        $user->unsetRelation('roles')->syncRoles([$role]);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        return $user;
    }

    /** Rol propio de la empresa (como los que se crean en Configuración → Roles) */
    protected function rolPropio(string $nombre, array $permisos): Role
    {
        $rol = Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web', 'empresa_id' => $this->empresa->id]);
        $rol->syncPermissions(Permission::whereIn('name', $permisos)->get());

        return $rol;
    }

    /** @param  array<string, string>  $categorias  nombre => descripción */
    protected function categorias(array $categorias): void
    {
        foreach ($categorias as $nombre => $descripcion) {
            $this->categorias[$nombre] = Categoria::firstOrCreate(
                ['empresa_id' => $this->empresa->id, 'nombre' => $nombre], ['descripcion' => $descripcion],
            )->id;
        }
    }

    /**
     * Productos con stock inicial. Cada uno: categoria, unidad (código: UND, CJ, KG…), codigo, nombre, precio
     * y opcionales: descripcion, costo (sin IGV; por defecto ~61 % del precio), servicio, stock, minimo,
     * lotes [[código, vencimiento 'Y-m-d', cantidad], …] (si tiene lotes, el stock sale de ellos).
     */
    protected function productos(array $productos): void
    {
        $inventario = app(InventarioService::class);
        $almacen = app(EmpresaService::class)->almacenPrincipal($this->empresa);
        $usuarioId = $this->empresa->usuarios()->orderBy('users.id')->value('users.id');
        $unidades = TipoUnidad::pluck('id', 'codigo');

        foreach ($productos as $p) {
            $servicio = (bool) ($p['servicio'] ?? false);
            $producto = Producto::firstOrCreate(['empresa_id' => $this->empresa->id, 'nombre' => $p['nombre']], [
                'categoria_id'    => $this->categorias[$p['categoria']] ?? null,
                'tipo_unidad_id'  => $unidades[$p['unidad'] ?? ($servicio ? 'SRV' : 'UND')] ?? null,
                'codigo'          => $p['codigo'] ?? null,
                'descripcion'     => $p['descripcion'] ?? null,
                'precio_unitario' => $p['precio'],
                'costo_unitario'  => $p['costo'] ?? round($p['precio'] / 1.18 * 0.72, 2),
                'es_servicio'     => $servicio,
                'maneja_lotes'    => ! empty($p['lotes']),
                'activo'          => true,
            ]);
            if (! $producto->wasRecentlyCreated || $servicio) {
                continue;
            }

            $ctx = ['empresa_id' => $this->empresa->id, 'user_id' => $usuarioId, 'ref' => ['tipo' => 'stock_inicial'],
                'costo' => $producto->costo_unitario, 'fecha' => Carbon::now(), 'obs' => 'Stock inicial (seeder)'];
            if (! empty($p['lotes'])) {
                foreach ($p['lotes'] as [$codigo, $vence, $cantidad]) {
                    $lote = app(LoteService::class)->obtenerOCrear($this->empresa->id, $producto, $codigo, $vence);
                    $inventario->ingreso($producto, $almacen->id, $cantidad, $ctx + ['lote' => $lote]);
                }
            } elseif (($p['stock'] ?? 10) > 0) {
                $inventario->ingreso($producto, $almacen->id, $p['stock'] ?? 10, $ctx);
            }

            AlmacenProducto::where('almacen_id', $almacen->id)->where('producto_id', $producto->id)->update(['stock_minimo' => $p['minimo'] ?? 3]);
        }
    }

    /** Fraccionamiento: 1 [origen] = [factor] [destino] (ej. 1 caja = 100 tabletas). Se parte solo al vender */
    protected function fraccionamientos(array $reglas): void
    {
        foreach ($reglas as [$origen, $destino, $factor]) {
            $o = $this->producto($origen);
            $d = $this->producto($destino);
            if (! $o || ! $d) {
                continue;
            }
            UnidadConversion::updateOrCreate(
                ['producto_id' => $o->id, 'producto_destino_id' => $d->id, 'unidad_origen_id' => $o->tipo_unidad_id, 'unidad_destino_id' => $d->tipo_unidad_id],
                ['empresa_id' => $this->empresa->id, 'factor' => $factor, 'activo' => true],
            );
            $d->update(['costo_unitario' => round($o->costo_unitario / $factor, 6)]);
        }
    }

    /** Presentación de venta del mismo producto: [producto, código de unidad, unidades base, precio] */
    protected function presentaciones(array $presentaciones): void
    {
        $unidades = TipoUnidad::pluck('id', 'codigo');
        foreach ($presentaciones as [$nombre, $codigoUnidad, $factor, $precio]) {
            $p = $this->producto($nombre);
            $unidad = $unidades[$codigoUnidad] ?? null;
            if (! $p || ! $unidad || $unidad === $p->tipo_unidad_id) {
                continue;
            }
            UnidadConversion::updateOrCreate(
                ['producto_id' => $p->id, 'producto_destino_id' => null, 'unidad_origen_id' => $unidad, 'unidad_destino_id' => $p->tipo_unidad_id],
                ['empresa_id' => $this->empresa->id, 'factor' => $factor, 'precio_venta' => $precio, 'activo' => true],
            );
        }
    }

    /** Clientes: [tipo_doc (1 DNI, 6 RUC, 4 CE), número, nombre, apellido, dirección, correo, teléfono] */
    protected function clientes(array $clientes): void
    {
        $tipos = TipoDocumento::pluck('id', 'codigo');
        foreach ($clientes as [$tipo, $numero, $nombre, $apellido, $direccion, $correo, $telefono]) {
            Cliente::firstOrCreate(['empresa_id' => $this->empresa->id, 'tipo_documento_id' => $tipos[$tipo], 'numero_documento' => $numero], [
                'nombre' => $nombre, 'apellido' => $apellido, 'direccion' => $direccion,
                'correo' => $correo, 'telefono' => $telefono, 'activo' => true,
            ]);
        }
    }

    /** Proveedores: [RUC, razón social, nombre comercial, contacto, teléfono, correo, dirección, distrito] */
    protected function proveedores(array $proveedores): void
    {
        $ruc = TipoDocumento::where('codigo', '6')->value('id');
        foreach ($proveedores as [$numero, $razon, $comercial, $contacto, $telefono, $correo, $direccion, $distrito]) {
            Proveedor::firstOrCreate(['empresa_id' => $this->empresa->id, 'numero_documento' => $numero], [
                'tipo_documento_id' => $ruc, 'razon_social' => $razon, 'nombre_comercial' => $comercial, 'contacto_nombre' => $contacto,
                'celular' => $telefono, 'email' => $correo, 'direccion' => $direccion, 'distrito' => $distrito, 'pais' => 'Perú', 'activo' => true,
            ]);
        }
    }

    /**
     * Plan del ERP. pagado: registra el primer pago (sin comprobante electrónico: los seeders no llaman
     * al facturador); si no, queda en prueba.
     */
    protected function suscripcion(string $plan, string $periodicidad, bool $pagado): void
    {
        // Solo a empresas que crea este seeder: a una existente no se le inventan pagos ni se le pone plazo
        if (! $this->empresaNueva || $this->empresa->suscripciones()->exists()) {
            return;
        }
        app(SuscripcionService::class)->asignar($this->empresa, Plan::where('codigo', $plan)->firstOrFail(), [
            'periodicidad' => $periodicidad, 'prueba' => ! $pagado, 'pagado' => $pagado,
            'metodo' => 'transferencia', 'referencia' => 'SEED', 'comprobante' => 'ninguno',
        ], 'Seeder');
    }

    /**
     * Página web de demostración: plan web en prueba, tienda publicada, productos publicados (los primeros
     * destacados) y zonas de envío. Solo a empresas que crea este seeder (a una existente no se le publica nada).
     *
     * @param  array<int, string>  $publicar  nombres de productos; los $destacados primeros se destacan
     * @param  array<int, array{0: string, 1: float, 2: string}>  $zonas  [nombre, costo, tiempo]
     */
    protected function paginaWeb(string $plan, array $tienda, array $publicar, int $destacados = 4, array $zonas = []): void
    {
        if (! $this->empresaNueva) {
            return;
        }
        $servicio = app(SuscripcionService::class);
        if (! $servicio->vigente($this->empresa->id, 'web')) {
            $servicio->asignar($this->empresa, Plan::where('codigo', $plan)->firstOrFail(), ['periodicidad' => 'mensual', 'prueba' => true], 'Seeder');
        }

        Tienda::deEmpresa($this->empresa)->update($tienda + ['publicada' => true, 'metodos_pago' => ['contra_entrega', 'transferencia', 'yape', 'tarjeta']]);
        foreach (array_values($publicar) as $i => $nombre) {
            $this->producto($nombre)?->update(['publicar_web' => true, 'destacado_web' => $i < $destacados]);
        }
        foreach ($zonas as $orden => [$nombre, $costo, $tiempo]) {
            ZonaEnvio::firstOrCreate(['empresa_id' => $this->empresa->id, 'nombre' => $nombre], ['costo' => $costo, 'tiempo_entrega' => $tiempo, 'orden' => $orden, 'activo' => true]);
        }
    }

    protected function producto(string $nombre): ?Producto
    {
        return Producto::where('empresa_id', $this->empresa->id)->where('nombre', $nombre)->first();
    }

    /** Contraseña de los usuarios de demostración (SEEDER_PASSWORD en .env) */
    protected function claveDemo(): string
    {
        return (string) env('SEEDER_PASSWORD', 'demo1234');
    }
}
