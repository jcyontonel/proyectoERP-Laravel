<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Correlativo;
use App\Models\Empresa;
use App\Models\MotivoAjuste;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Alta de empresas y su configuración inicial: almacén principal, caja, series y motivos de ajuste.
 * Lo usan los seeders y el proyecto admin (API). Todo es idempotente (firstOrCreate).
 */
class EmpresaService
{
    /** Series iniciales. modulo: el formulario de ventas solo muestra las de 'venta' */
    public const SERIES = [
        ['modulo' => 'venta',           'tipo' => '00', 'destipo' => 'Ticket',               'serie' => 'T001'],
        ['modulo' => 'venta',           'tipo' => '01', 'destipo' => 'Factura',              'serie' => 'F001'],
        ['modulo' => 'venta',           'tipo' => '03', 'destipo' => 'Boleta',               'serie' => 'B001'],
        ['modulo' => 'nota',            'tipo' => '07', 'destipo' => 'Nota de crédito',      'serie' => 'FC01'], // sobre facturas
        ['modulo' => 'nota',            'tipo' => '07', 'destipo' => 'Nota de crédito',      'serie' => 'BC01'], // sobre boletas
        ['modulo' => 'nota',            'tipo' => '08', 'destipo' => 'Nota de débito',       'serie' => 'FD01'],
        ['modulo' => 'nota',            'tipo' => '08', 'destipo' => 'Nota de débito',       'serie' => 'BD01'],
        ['modulo' => 'ajuste',          'tipo' => 'AJ', 'destipo' => 'Ajuste de inventario', 'serie' => 'AJ01'],
        ['modulo' => 'transferencia',   'tipo' => 'TR', 'destipo' => 'Transferencia',        'serie' => 'TR01'],
        ['modulo' => 'devolucion',      'tipo' => 'DV', 'destipo' => 'Devolución',           'serie' => 'DV01'],
        ['modulo' => 'fraccionamiento', 'tipo' => 'FR', 'destipo' => 'Fraccionamiento',      'serie' => 'FR01'],
        ['modulo' => 'cotizacion',      'tipo' => 'CT', 'destipo' => 'Cotización',           'serie' => 'CT01'],
        ['modulo' => 'orden_compra',    'tipo' => 'OC', 'destipo' => 'Orden de compra',      'serie' => 'OC01'],
    ];

    public const MOTIVOS_AJUSTE = [
        ['codigo' => 'INV-INICIAL', 'nombre' => 'Inventario inicial',          'tipo_movimiento' => 'ingreso', 'requiere_aprobacion' => false],
        ['codigo' => 'CONTEO',      'nombre' => 'Diferencia de conteo físico', 'tipo_movimiento' => 'ambos',   'requiere_aprobacion' => true],
        ['codigo' => 'MERMA',       'nombre' => 'Merma',                       'tipo_movimiento' => 'egreso',  'requiere_aprobacion' => true],
        ['codigo' => 'VENCIDO',     'nombre' => 'Producto vencido',            'tipo_movimiento' => 'egreso',  'requiere_aprobacion' => true],
        ['codigo' => 'DANADO',      'nombre' => 'Producto dañado',             'tipo_movimiento' => 'egreso',  'requiere_aprobacion' => true],
        ['codigo' => 'CONSUMO',     'nombre' => 'Consumo interno',             'tipo_movimiento' => 'egreso',  'requiere_aprobacion' => false],
        ['codigo' => 'OBSEQUIO',    'nombre' => 'Obsequio / muestra',          'tipo_movimiento' => 'egreso',  'requiere_aprobacion' => false],
        ['codigo' => 'HALLAZGO',    'nombre' => 'Sobrante encontrado',         'tipo_movimiento' => 'ingreso', 'requiere_aprobacion' => true],
    ];

    /**
     * Crea la empresa lista para usar y su usuario administrador (si el correo ya existe, se le da acceso).
     *
     * @param  array  $datos  columnas de empresas (ruc, razon_social, …)
     * @param  array{name: string, apellido?: ?string, email: string, password?: ?string, telefono?: ?string}  $admin
     * @return array{0: Empresa, 1: User, 2: bool}  [empresa, administrador, el usuario ya existía]
     */
    public function crear(array $datos, array $admin): array
    {
        return DB::transaction(function () use ($datos, $admin) {
            $empresa = Empresa::create($datos + ['moneda' => 'PEN', 'igv' => 18, 'activo' => true]);
            $this->inicializar($empresa);

            $email = mb_strtolower(trim($admin['email']));
            $user = User::query()->where('email', $email)->first();
            $existia = (bool) $user;
            if (! $user) {
                if (blank($admin['password'] ?? null)) {
                    throw ValidationException::withMessages(['admin.password' => 'Indica la contraseña del administrador de la empresa.']);
                }
                $user = User::create([
                    'name' => $admin['name'], 'apellido' => $admin['apellido'] ?? null, 'telefono' => $admin['telefono'] ?? null,
                    'email' => $email, 'password' => $admin['password'], 'activo' => true,
                ]);
            }

            $this->darAcceso($empresa, $user, config('permisos.rol_administrador'));

            return [$empresa, $user, $existia];
        });
    }

    /** Acceso del usuario a la empresa con un rol del sistema */
    public function darAcceso(Empresa $empresa, User $user, string $rol): void
    {
        $user->empresas()->syncWithoutDetaching([$empresa->id]);

        $registrar = app(PermissionRegistrar::class);
        $anterior = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($empresa->id);
        try {
            $user->unsetRelation('roles')->syncRoles([Role::query()->whereNull('empresa_id')->where('name', $rol)->firstOrFail()]);
        } finally {
            $registrar->setPermissionsTeamId($anterior);
        }
    }

    /** Configuración inicial de la empresa (idempotente) */
    public function inicializar(Empresa $empresa): void
    {
        $almacen = $this->almacenPrincipal($empresa);
        $this->cajaPrincipal($empresa, $almacen);
        $this->series($empresa);
        $this->motivosAjuste($empresa);
    }

    public function almacenPrincipal(Empresa $empresa): Almacen
    {
        return Almacen::firstOrCreate(
            ['empresa_id' => $empresa->id, 'codigo' => 'ALM-01'],
            ['nombre' => 'Almacén Principal', 'direccion' => $empresa->direccion ?? 'Dirección General', 'es_principal' => true, 'activo' => true]
        );
    }

    public function cajaPrincipal(Empresa $empresa, ?Almacen $almacen = null): Caja
    {
        $almacen ??= Almacen::query()->where('empresa_id', $empresa->id)->where('es_principal', true)->first();

        return Caja::firstOrCreate(
            ['empresa_id' => $empresa->id, 'codigo' => 'CAJA-01'],
            ['almacen_id' => $almacen?->id, 'nombre' => 'Caja Principal', 'activo' => true]
        );
    }

    public function series(Empresa $empresa): void
    {
        foreach (self::SERIES as $s) {
            Correlativo::firstOrCreate(
                ['empresa_id' => $empresa->id, 'tipo' => $s['tipo'], 'serie' => $s['serie']],
                ['modulo' => $s['modulo'], 'destipo' => $s['destipo'], 'numero' => 0, 'activo' => true]
            );
        }
    }

    public function motivosAjuste(Empresa $empresa): void
    {
        foreach (self::MOTIVOS_AJUSTE as $m) {
            MotivoAjuste::firstOrCreate(['empresa_id' => $empresa->id, 'codigo' => $m['codigo']], $m + ['activo' => true]);
        }
    }
}
