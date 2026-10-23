<?php

namespace Tests\Concerns;

use App\Modulos\Erp\Models\Almacen;
use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Erp\Models\Caja;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Nucleo\Support\EmpresaActual;
use Database\Seeders\ImpuestoSeeder;
use Database\Seeders\MonedaSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Database\Seeders\TipoDocumentoSeeder;
use Database\Seeders\TipoUnidadSeeder;
use Spatie\Permission\PermissionRegistrar;

/** Catálogos, empresa con almacén y usuarios con rol POR empresa (Spatie teams) */
trait ConEmpresa
{
    protected function sembrarCatalogos(): void
    {
        $this->seed([MonedaSeeder::class, TipoDocumentoSeeder::class, TipoUnidadSeeder::class, ImpuestoSeeder::class, RolesYPermisosSeeder::class]);
    }

    protected function crearEmpresa(array $datos = []): Empresa
    {
        $empresa = Empresa::factory()->create($datos);

        Almacen::create(['empresa_id' => $empresa->id, 'codigo' => 'ALM-01', 'nombre' => 'Almacén Principal', 'es_principal' => true]);

        return $empresa;
    }

    protected function usuarioCon(string $rol, Empresa $empresa): User
    {
        $user = User::factory()->create();
        $user->empresas()->attach($empresa->id);

        app(PermissionRegistrar::class)->setPermissionsTeamId($empresa->id);
        $user->assignRole($rol);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        EmpresaActual::olvidar();

        return $user;
    }

    /** Ejecuta código "como" el usuario dentro de su empresa (permisos de Spatie por team) */
    protected function comoUsuario(User $user, Empresa $empresa): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($empresa->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $user;
    }

    protected function almacen(Empresa $empresa, string $codigo = 'ALM-01'): Almacen
    {
        return Almacen::where('empresa_id', $empresa->id)->where('codigo', $codigo)->firstOrFail();
    }

    protected function crearCaja(Empresa $empresa): Caja
    {
        return Caja::create(['empresa_id' => $empresa->id, 'codigo' => 'CAJA-01', 'nombre' => 'Caja Principal', 'activo' => true]);
    }

    protected function crearCorrelativoVenta(Empresa $empresa): Correlativo
    {
        return Correlativo::create(['empresa_id' => $empresa->id, 'modulo' => 'venta', 'tipo' => '03', 'destipo' => 'Boleta', 'serie' => 'B001', 'numero' => 0]);
    }

    /** Producto con stock en el almacén indicado (sin pasar por kardex: solo para preparar escenarios) */
    protected function productoConStock(Empresa $empresa, float $stock, array $datos = [], ?Almacen $almacen = null): Producto
    {
        $p = Producto::factory()->create($datos + ['empresa_id' => $empresa->id, 'precio_unitario' => 10, 'costo_unitario' => 6]);
        AlmacenProducto::create(['almacen_id' => ($almacen ?? $this->almacen($empresa))->id, 'producto_id' => $p->id, 'stock' => $stock, 'stock_minimo' => 0]);

        return $p;
    }
}
