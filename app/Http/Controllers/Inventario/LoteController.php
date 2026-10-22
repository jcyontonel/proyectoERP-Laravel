<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\FiltrarInventarioRequest;
use App\Models\Almacen;
use App\Models\Kardex;
use App\Models\Lote;
use App\Services\NotificacionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Lotes y vencimientos: stock por lote y almacén, alertas de vencimiento */
class LoteController extends Controller
{
    use ResuelveEmpresa;

    public function index(FiltrarInventarioRequest $request)
    {
        $empresaId = $this->empresaId($request);
        $f = $request->validated();
        $dias = NotificacionService::DIAS_POR_VENCER;
        $conStock = (bool) ($f['con_stock'] ?? true);

        $lotes = Lote::query()->deEmpresa($empresaId)
            ->with(['producto:id,nombre,codigo,tipo_unidad_id', 'producto.tipoUnidad:id,descripcion', 'existencias.almacen:id,nombre'])
            ->withSum(['existencias as cantidad' => fn ($q) => $q->when(filled($f['almacen_id'] ?? null), fn ($w) => $w->where('almacen_id', $f['almacen_id']))], 'cantidad')
            ->when($conStock, fn ($q) => $q->whereHas('existencias', fn ($e) => $e->where('cantidad', '>', 0)
                ->when(filled($f['almacen_id'] ?? null), fn ($w) => $w->where('almacen_id', $f['almacen_id']))))
            ->when(filled($f['q'] ?? null), fn ($q) => $q->where(fn (Builder $w) => $w
                ->whereLike('codigo_lote', '%' . trim($f['q']) . '%')
                ->orWhereHas('producto', fn ($p) => $p->whereLike('nombre', '%' . trim($f['q']) . '%')->orWhereLike('codigo', '%' . trim($f['q']) . '%'))))
            ->when(($f['vencimiento'] ?? null) === 'vencidos', fn ($q) => $q->whereDate('fecha_vencimiento', '<', today()))
            ->when(($f['vencimiento'] ?? null) === 'por_vencer', fn ($q) => $q->whereBetween('fecha_vencimiento', [today(), today()->addDays($dias)]))
            ->when(($f['vencimiento'] ?? null) === 'vigentes', fn ($q) => $q->whereDate('fecha_vencimiento', '>', today()->addDays($dias)))
            ->when(($f['vencimiento'] ?? null) === 'sin_fecha', fn ($q) => $q->whereNull('fecha_vencimiento'))
            ->orderByRaw('fecha_vencimiento IS NULL')->orderBy('fecha_vencimiento')
            ->paginate(20)->withQueryString();

        $base = Lote::query()->deEmpresa($empresaId)->whereHas('existencias', fn ($e) => $e->where('cantidad', '>', 0));

        return view('inventario.lotes.index', [
            'lotes'     => $lotes,
            'almacenes' => Almacen::query()->deEmpresa($empresaId)->orderBy('nombre')->get(['id', 'nombre']),
            'dias'      => $dias,
            'resumen'   => [
                'vencidos'   => (clone $base)->whereDate('fecha_vencimiento', '<', today())->count(),
                'por_vencer' => (clone $base)->whereBetween('fecha_vencimiento', [today(), today()->addDays($dias)])->count(),
                'con_stock'  => (clone $base)->count(),
            ],
        ]);
    }

    public function show(Lote $lote)
    {
        $lote->load(['producto.tipoUnidad', 'existencias.almacen']);

        return view('inventario.lotes.show', [
            'lote'        => $lote,
            'movimientos' => Kardex::query()->where('lote_id', $lote->id)->with('almacen:id,nombre')
                ->orderByDesc('fecha_movimiento')->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    /** Corrige la fecha de vencimiento o desactiva el lote */
    public function update(Request $request, Lote $lote)
    {
        $datos = $request->validate([
            'fecha_vencimiento' => ['nullable', 'date'],
            'fecha_fabricacion' => ['nullable', 'date', 'before_or_equal:fecha_vencimiento'],
            'activo'            => ['boolean'],
        ]);

        $lote->update(['activo' => $request->boolean('activo', true)] + $datos);

        return back()->with('status', "Lote {$lote->codigo_lote} actualizado.");
    }
}
