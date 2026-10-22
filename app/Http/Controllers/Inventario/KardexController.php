<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\FiltrarInventarioRequest;
use App\Models\Almacen;
use App\Models\Kardex;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Kardex valorizado: entradas y salidas por producto, almacén, documento y fecha */
class KardexController extends Controller
{
    use ResuelveEmpresa;

    public function index(FiltrarInventarioRequest $request)
    {
        $empresaId = $this->empresaId($request);
        $f = $request->validated();
        $base = $this->consulta($empresaId, $f);

        $totales = (clone $base)->selectRaw("
            COALESCE(SUM(CASE WHEN tipo_movimiento = 'ingreso' THEN cantidad ELSE 0 END), 0) AS entradas,
            COALESCE(SUM(CASE WHEN tipo_movimiento = 'egreso' THEN cantidad ELSE 0 END), 0) AS salidas,
            COALESCE(SUM(CASE WHEN tipo_movimiento = 'ingreso' THEN costo_total ELSE -costo_total END), 0) AS valor")->first();

        return view('inventario.kardex.index', [
            'movimientos' => (clone $base)->with(['producto:id,nombre,codigo,tipo_unidad_id', 'producto.tipoUnidad:id,descripcion', 'almacen:id,nombre', 'lote:id,codigo_lote'])
                ->orderByDesc('fecha_movimiento')->orderByDesc('id')->paginate(25)->withQueryString(),
            'totales'   => $totales,
            'producto'  => filled($f['producto_id'] ?? null) ? Producto::query()->deEmpresa($empresaId)->with('tipoUnidad')->find($f['producto_id']) : null,
            'productos' => Producto::query()->deEmpresa($empresaId)->where('es_servicio', false)->orderBy('nombre')->get(['id', 'nombre', 'codigo']),
            'almacenes' => Almacen::query()->deEmpresa($empresaId)->orderBy('nombre')->get(['id', 'nombre']),
            'tipos'     => self::tiposReferencia(),
        ]);
    }

    /** CSV (UTF-8 con BOM para Excel) con los mismos filtros del listado */
    public function export(FiltrarInventarioRequest $request): StreamedResponse
    {
        $consulta = $this->consulta($this->empresaId($request), $request->validated())
            ->with(['producto:id,nombre,codigo', 'almacen:id,nombre', 'lote:id,codigo_lote', 'usuario:id,name,apellido'])
            ->orderBy('fecha_movimiento')->orderBy('id');

        return response()->streamDownload(function () use ($consulta) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Fecha', 'Código', 'Producto', 'Almacén', 'Lote', 'Documento', 'Tipo', 'Cantidad', 'Costo unit.', 'Costo total', 'Stock anterior', 'Stock nuevo', 'Usuario'], ';');

            $consulta->chunk(500, function ($filas) use ($salida) {
                foreach ($filas as $k) {
                    fputcsv($salida, [
                        $k->fecha_movimiento?->format('d/m/Y H:i'), $k->producto?->codigo, $k->producto?->nombre, $k->almacen?->nombre,
                        $k->lote?->codigo_lote, $k->referencia_label, $k->tipo_movimiento, $k->cantidad, $k->costo_unitario,
                        $k->costo_total, $k->stock_anterior, $k->stock_nuevo, $k->usuario?->nombre_completo,
                    ], ';');
                }
            });
            fclose($salida);
        }, 'kardex-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function tiposReferencia(): array
    {
        return [
            'compra'           => 'Compras',
            'venta'            => 'Ventas',
            'ajuste'           => 'Ajustes',
            'transferencia'    => 'Transferencias',
            'fraccionamiento'  => 'Fraccionamientos',
            'stock_inicial'    => 'Stock inicial',
            'anulacion_venta'  => 'Anulaciones de venta',
            'anulacion_compra' => 'Anulaciones de compra',
        ];
    }

    private function consulta(int $empresaId, array $f): Builder
    {
        return Kardex::query()
            ->where('empresa_id', $empresaId)
            ->when(filled($f['producto_id'] ?? null), fn ($q) => $q->where('producto_id', $f['producto_id']))
            ->when(filled($f['almacen_id'] ?? null), fn ($q) => $q->where('almacen_id', $f['almacen_id']))
            ->when(filled($f['tipo'] ?? null), fn ($q) => $q->where('referencia_tipo', $f['tipo']))
            ->when(filled($f['q'] ?? null), fn ($q) => $q->where(fn ($w) => $w
                ->whereLike('referencia_numero', '%' . trim($f['q']) . '%')
                ->orWhereLike('observacion', '%' . trim($f['q']) . '%')))
            ->when(filled($f['desde'] ?? null), fn ($q) => $q->whereDate('fecha_movimiento', '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn ($q) => $q->whereDate('fecha_movimiento', '<=', $f['hasta']));
    }
}
