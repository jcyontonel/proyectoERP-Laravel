<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Caja\FiltrarTurnosRequest;
use App\Http\Requests\Caja\MovimientoCajaRequest;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Services\CajaService;
use Illuminate\Http\Request;

/** Ingresos y egresos manuales de caja (gastos, retiros, fondos adicionales) */
class MovimientoCajaController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private CajaService $cajas)
    {
    }

    public function index(FiltrarTurnosRequest $request)
    {
        $empresaId = $this->empresaId($request);
        $f = $request->validated();
        $todos = $request->user()->can('ver-reportes');

        $base = MovimientoCaja::query()
            ->deEmpresa($empresaId)
            ->unless($todos, fn ($q) => $q->where('user_id', $request->user()->id))
            ->when(filled($f['tipo'] ?? null), fn ($q) => $q->where('tipo', $f['tipo']))
            ->when(filled($f['caja_id'] ?? null), fn ($q) => $q->whereHas('sesion', fn ($s) => $s->where('caja_id', $f['caja_id'])))
            ->when(filled($f['q'] ?? null), fn ($q) => $q->whereLike('concepto', '%' . trim($f['q']) . '%'))
            ->when(filled($f['desde'] ?? null), fn ($q) => $q->whereDate('fecha_movimiento', '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn ($q) => $q->whereDate('fecha_movimiento', '<=', $f['hasta']));

        $totales = (clone $base)->where('estado', MovimientoCaja::REGISTRADO)
            ->selectRaw('tipo, SUM(monto) AS total')->groupBy('tipo')->pluck('total', 'tipo');

        return view('caja.movimientos.index', [
            'movimientos' => (clone $base)->with(['sesion.caja:id,nombre', 'usuario:id,name,apellido'])
                ->latest('fecha_movimiento')->paginate(20)->withQueryString(),
            'totales'     => ['ingreso' => (float) ($totales['ingreso'] ?? 0), 'egreso' => (float) ($totales['egreso'] ?? 0)],
            'cajas'       => Caja::query()->deEmpresa($empresaId)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(MovimientoCajaRequest $request)
    {
        $sesion = $this->cajas->sesionAbierta($this->empresaId($request), $request->user()->id);

        if (! $sesion) {
            return redirect()->route('caja.index')->with('error', 'Abre tu turno de caja para registrar movimientos.');
        }

        $m = $this->cajas->registrarMovimiento($sesion, $request->validated(), $request->user()->id);

        return back()->with('status', ($m->esIngreso() ? 'Ingreso' : 'Egreso') . " de S/ " . number_format($m->monto, 2) . " registrado: {$m->concepto}.");
    }

    public function anular(Request $request, MovimientoCaja $movimiento)
    {
        abort_unless($movimiento->user_id === $request->user()->id || $request->user()->can('ver-reportes'), 403);

        $this->cajas->anularMovimiento($movimiento);

        return back()->with('status', "Movimiento «{$movimiento->concepto}» anulado.");
    }
}
