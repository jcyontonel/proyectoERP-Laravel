<?php

namespace App\Modulos\Erp\Http\Controllers\Inventario;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Erp\Http\Requests\Inventario\FiltrarInventarioRequest;
use App\Modulos\Erp\Http\Requests\Inventario\FraccionamientoRequest;
use App\Modulos\Erp\Models\Almacen;
use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Erp\Models\ExistenciaLote;
use App\Modulos\Erp\Models\Fraccionamiento;
use App\Modulos\Catalogo\Models\UnidadConversion;
use App\Modulos\Erp\Services\FraccionamientoService;
use Illuminate\Http\Request;

/** Partir productos (1 saco → 15 kg): manuales o automáticos al vender */
class FraccionamientoController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private FraccionamientoService $fracciones)
    {
    }

    public function index(FiltrarInventarioRequest $request)
    {
        $empresaId = $this->empresaId($request);

        return view('inventario.fraccionamientos.index', [
            'fraccionamientos' => Fraccionamiento::query()->deEmpresa($empresaId)
                ->with(['productoOrigen.tipoUnidad', 'productoDestino.tipoUnidad', 'almacen:id,nombre', 'usuario:id,name,apellido', 'venta:id,serie,numero'])
                ->filtrar($request->validated())
                ->latest('fecha_movimiento')->latest('id')
                ->paginate(20)->withQueryString(),
            'almacenes' => Almacen::query()->deEmpresa($empresaId)->orderBy('nombre')->get(['id', 'nombre']),
            'reglas'    => $this->reglas($empresaId)->count(),
        ]);
    }

    public function create(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $reglas = $this->reglas($empresaId);

        if ($reglas->isEmpty()) {
            return redirect()->route('fraccionamientos.index')
                ->with('error', 'Aún no hay reglas de fraccionamiento. Créalas desde la ficha del producto de origen (ej. el saco).');
        }

        $almacenes = Almacen::query()->deEmpresa($empresaId)->activo()->orderByDesc('es_principal')->orderBy('nombre')->get(['id', 'nombre']);
        $origenes = $reglas->pluck('producto_id')->unique();

        $stocks = AlmacenProducto::query()->whereIn('producto_id', $origenes)->get(['almacen_id', 'producto_id', 'stock'])->groupBy('producto_id');
        $lotes = ExistenciaLote::query()
            ->join('lotes', 'lotes.id', '=', 'existencias_lote.lote_id')
            ->whereIn('lotes.producto_id', $origenes)->where('existencias_lote.cantidad', '>', 0)->whereNull('lotes.deleted_at')
            ->orderBy('lotes.fecha_vencimiento')
            ->get(['lotes.producto_id', 'lotes.codigo_lote', 'lotes.fecha_vencimiento', 'existencias_lote.almacen_id', 'existencias_lote.cantidad'])
            ->groupBy('producto_id');

        return view('inventario.fraccionamientos.form', [
            'almacenes' => $almacenes,
            'reglas'    => $reglas->map(fn (UnidadConversion $r) => [
                'id'             => $r->id,
                'origen'         => $r->producto->nombre,
                'unidad_origen'  => $r->producto->tipoUnidad?->descripcion ?? 'unidad',
                'destino'        => $r->productoDestino->nombre,
                'unidad_destino' => $r->productoDestino->tipoUnidad?->descripcion ?? 'unidad',
                'factor'         => $r->factor,
                'costo'          => $r->producto->costo_unitario,
                'lotes_activo'   => $r->producto->maneja_lotes,
                'stocks'         => (object) ($stocks[$r->producto_id] ?? collect())->mapWithKeys(fn ($s) => [$s->almacen_id => (float) $s->stock])->all(),
                'lotes'          => ($lotes[$r->producto_id] ?? collect())->map(fn ($l) => [
                    'codigo' => $l->codigo_lote, 'vence' => $l->fecha_vencimiento, 'almacen' => $l->almacen_id, 'cantidad' => (float) $l->cantidad,
                ])->values(),
            ])->values(),
            'reglaInicial' => (int) $request->query('regla'),
        ]);
    }

    public function store(FraccionamientoRequest $request)
    {
        $d = $request->validated();
        $regla = UnidadConversion::query()->with(['producto', 'productoDestino'])->findOrFail($d['unidad_conversion_id']);

        $f = $this->fracciones->fraccionar($regla, (int) $d['almacen_id'], (float) $d['cantidad'], $request->user(), $d['lote'] ?? null, 'manual', $d['observacion'] ?? null);

        return redirect()->route('fraccionamientos.show', $f)
            ->with('status', "Listo: {$f->cantidad_origen} de «{$regla->producto->nombre}» se partieron en {$f->cantidad_destino} de «{$regla->productoDestino->nombre}».");
    }

    public function show(Fraccionamiento $fraccionamiento)
    {
        $fraccionamiento->load(['productoOrigen.tipoUnidad', 'productoDestino.tipoUnidad', 'almacen', 'usuario', 'loteOrigen', 'loteDestino', 'venta']);

        return view('inventario.fraccionamientos.show', ['f' => $fraccionamiento]);
    }

    public function anular(Request $request, Fraccionamiento $fraccionamiento)
    {
        $this->fracciones->anular($fraccionamiento, $request->user());

        return back()->with('status', "Fraccionamiento {$fraccionamiento->numero_completo} anulado: el stock volvió al producto de origen.");
    }

    private function reglas(int $empresaId)
    {
        return UnidadConversion::query()->where('empresa_id', $empresaId)->fraccionamientos()->activo()
            ->with(['producto.tipoUnidad', 'productoDestino.tipoUnidad'])
            ->whereHas('producto', fn ($q) => $q->where('activo', true))
            ->get()
            ->sortBy(fn ($r) => $r->producto->nombre)
            ->values();
    }
}
