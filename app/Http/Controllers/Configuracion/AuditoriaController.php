<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Models\AuditoriaEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Bitácora de la empresa: quién hizo qué, cuándo y desde dónde (solo lectura) */
class AuditoriaController extends Controller
{
    use ResuelveEmpresa;

    /** Nombre legible de cada entidad auditada */
    public const ENTIDADES = [
        'Venta' => 'Venta', 'Compra' => 'Compra', 'Pago' => 'Cobro / pago', 'Producto' => 'Producto', 'Categoria' => 'Categoría',
        'Cliente' => 'Cliente', 'Proveedor' => 'Proveedor', 'AjusteInventario' => 'Ajuste de inventario',
        'TransferenciaAlmacen' => 'Transferencia', 'Fraccionamiento' => 'Fraccionamiento', 'UnidadConversion' => 'Regla de conversión',
        'CajaSesion' => 'Turno de caja', 'MovimientoCaja' => 'Movimiento de caja', 'Caja' => 'Caja', 'Almacen' => 'Almacén',
        'MotivoAjuste' => 'Motivo de ajuste', 'Empresa' => 'Empresa', 'User' => 'Usuario', 'Rol' => 'Rol',
        'Correlativo' => 'Serie', 'Impuesto' => 'Impuesto', 'TipoUnidad' => 'Unidad de medida',
    ];

    public const ACCIONES = [
        'creado' => 'Creó', 'actualizado' => 'Modificó', 'eliminado' => 'Eliminó', 'restaurado' => 'Restauró',
        'inicio de sesión' => 'Inició sesión', 'cierre de sesión' => 'Cerró sesión', 'acceso fallido' => 'Acceso fallido',
        'permisos' => 'Cambió permisos', 'rol asignado' => 'Asignó rol', 'acceso quitado' => 'Quitó acceso',
    ];

    public function index(Request $request)
    {
        $f = $this->filtros($request);
        $empresaId = $this->empresaId($request);

        return view('configuracion.auditoria', [
            'eventos'   => $this->consulta($empresaId, $f)->with('usuario:id,name,apellido,email')->paginate(30)->withQueryString(),
            'usuarios'  => AuditoriaEvento::query()->where('empresa_id', $empresaId)->whereNotNull('user_id')
                ->distinct()->pluck('user_id')
                ->pipe(fn ($ids) => \App\Models\User::withTrashed()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'apellido'])),
            'entidades' => self::ENTIDADES,
            'acciones'  => self::ACCIONES,
            'hoy'       => AuditoriaEvento::query()->where('empresa_id', $empresaId)->where('created_at', '>=', today())->count(),
        ]);
    }

    public function export(Request $request)
    {
        $eventos = $this->consulta($this->empresaId($request), $this->filtros($request))->with('usuario:id,name,apellido,email')->limit(10000)->get();

        return response()->streamDownload(function () use ($eventos) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Usuario', 'Acción', 'Entidad', 'ID', 'Antes', 'Después', 'IP']);
            foreach ($eventos as $e) {
                fputcsv($out, [
                    $e->created_at->format('d/m/Y H:i:s'),
                    $e->usuario?->nombre_completo ?? 'Sistema',
                    self::ACCIONES[$e->accion] ?? $e->accion,
                    self::ENTIDADES[$e->entidad] ?? $e->entidad,
                    $e->entidad_id,
                    $e->datos_anteriores ? json_encode($e->datos_anteriores, JSON_UNESCAPED_UNICODE) : '',
                    $e->datos_nuevos ? json_encode($e->datos_nuevos, JSON_UNESCAPED_UNICODE) : '',
                    $e->ip,
                ]);
            }
            fclose($out);
        }, 'auditoria_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtros(Request $request): array
    {
        return $request->validate([
            'usuario' => ['nullable', 'integer'],
            'entidad' => ['nullable', 'string', 'max:100'],
            'accion'  => ['nullable', 'string', 'max:50'],
            'id'      => ['nullable', 'integer'],
            'desde'   => ['nullable', 'date'],
            'hasta'   => ['nullable', 'date'],
        ]);
    }

    private function consulta(int $empresaId, array $f): Builder
    {
        return AuditoriaEvento::query()
            ->where('empresa_id', $empresaId)
            ->when(filled($f['usuario'] ?? null), fn ($q) => $q->where('user_id', $f['usuario']))
            ->when(filled($f['entidad'] ?? null), fn ($q) => $q->where('entidad', $f['entidad']))
            ->when(filled($f['accion'] ?? null), fn ($q) => $q->where('accion', $f['accion']))
            ->when(filled($f['id'] ?? null), fn ($q) => $q->where('entidad_id', $f['id']))
            ->when(filled($f['desde'] ?? null), fn ($q) => $q->where('created_at', '>=', $f['desde'] . ' 00:00:00'))
            ->when(filled($f['hasta'] ?? null), fn ($q) => $q->where('created_at', '<=', $f['hasta'] . ' 23:59:59'))
            ->orderByDesc('created_at')->orderByDesc('id');
    }
}
