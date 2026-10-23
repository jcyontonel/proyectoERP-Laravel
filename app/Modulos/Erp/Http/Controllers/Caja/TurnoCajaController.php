<?php

namespace App\Modulos\Erp\Http\Controllers\Caja;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Erp\Http\Requests\Caja\AbrirCajaRequest;
use App\Modulos\Erp\Http\Requests\Caja\CerrarCajaRequest;
use App\Modulos\Erp\Http\Requests\Caja\FiltrarTurnosRequest;
use App\Modulos\Erp\Models\Caja;
use App\Modulos\Erp\Models\CajaSesion;
use App\Modulos\Nucleo\Models\User;
use App\Modulos\Erp\Services\CajaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Turno de caja: pantalla del turno actual, apertura, cierre con arqueo e historial */
class TurnoCajaController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private CajaService $cajas)
    {
    }

    /** Turno abierto del usuario o, si no tiene, el formulario de apertura */
    public function actual(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $sesion = $this->cajas->sesionAbierta($empresaId, $request->user()->id);

        if (! $sesion) {
            return view('caja.apertura', [
                'cajas' => Caja::query()->deEmpresa($empresaId)->activo()
                    ->with(['almacen:id,nombre', 'sesionAbierta.usuario:id,name,apellido'])
                    ->orderBy('nombre')->get(),
                'ultimo' => CajaSesion::query()->deEmpresa($empresaId)->where('user_id', $request->user()->id)
                    ->where('estado', CajaSesion::CERRADA)->latest('fecha_cierre')->first(),
            ]);
        }

        return view('caja.actual', $this->detalle($sesion) + [
            'conceptos' => config('caja.conceptos'),
            'metodos'   => config('ventas.metodos_pago'),
        ]);
    }

    public function abrir(AbrirCajaRequest $request)
    {
        $sesion = $this->cajas->abrir($this->empresaId($request), $request->user()->id, $request->validated());

        return redirect()->route('caja.index')
            ->with('status', "Turno abierto en {$sesion->caja->nombre} con S/ " . number_format($sesion->monto_apertura, 2) . '.');
    }

    /** Formulario de cierre con arqueo */
    public function cierre(Request $request)
    {
        $sesion = $this->cajas->sesionAbierta($this->empresaId($request), $request->user()->id);

        if (! $sesion) {
            return redirect()->route('caja.index')->with('error', 'No tienes un turno de caja abierto.');
        }

        return view('caja.cierre', [
            'sesion'         => $sesion,
            'resumen'        => $this->cajas->resumen($sesion),
            'denominaciones' => config('caja.denominaciones'),
            'tolerancia'     => (float) config('caja.tolerancia_diferencia'),
        ]);
    }

    public function cerrar(CerrarCajaRequest $request)
    {
        $sesion = $this->cajas->sesionAbierta($this->empresaId($request), $request->user()->id);

        if (! $sesion) {
            return redirect()->route('caja.index')->with('error', 'No tienes un turno de caja abierto.');
        }

        $sesion = $this->cajas->cerrar($sesion, $request->validated(), $request->user()->id);

        $dif = $sesion->diferencia;
        $mensaje = abs($dif) < 0.005 ? 'Turno cerrado: el arqueo cuadra.'
            : 'Turno cerrado con ' . ($dif > 0 ? 'sobrante' : 'faltante') . ' de S/ ' . number_format(abs($dif), 2) . '.';

        return redirect()->route('caja.turnos.show', $sesion)->with('status', $mensaje);
    }

    /** Historial de turnos (propios, o de todos con "ver-reportes") */
    public function index(FiltrarTurnosRequest $request)
    {
        $empresaId = $this->empresaId($request);
        $f = $request->validated();
        $todos = $request->user()->can('ver-reportes');

        $turnos = CajaSesion::query()
            ->deEmpresa($empresaId)
            ->with(['caja:id,nombre', 'usuario:id,name,apellido'])
            ->unless($todos, fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($todos && filled($f['user_id'] ?? null), fn ($q) => $q->where('user_id', $f['user_id']))
            ->when(filled($f['caja_id'] ?? null), fn ($q) => $q->where('caja_id', $f['caja_id']))
            ->when(filled($f['estado'] ?? null), fn ($q) => $q->where('estado', $f['estado']))
            ->when(filled($f['desde'] ?? null), fn ($q) => $q->whereDate('fecha_apertura', '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn ($q) => $q->whereDate('fecha_apertura', '<=', $f['hasta']))
            ->withSum(['pagos as cobrado' => fn ($q) => $q->where('estado', 'registrado')->where('tipo', 'cobro')], 'monto')
            ->latest('fecha_apertura')
            ->paginate(15)
            ->withQueryString();

        return view('caja.turnos.index', [
            'turnos'   => $turnos,
            'todos'    => $todos,
            'cajas'    => Caja::query()->deEmpresa($empresaId)->orderBy('nombre')->get(['id', 'nombre']),
            'usuarios' => $todos ? User::query()->whereHas('empresas', fn ($q) => $q->whereKey($empresaId))->orderBy('name')->get(['id', 'name', 'apellido']) : collect(),
        ]);
    }

    public function show(CajaSesion $sesion)
    {
        Gate::authorize('view', $sesion);

        return view('caja.turnos.show', $this->detalle($sesion));
    }

    /** Reporte de arqueo para imprimir (80 mm / A4) */
    public function imprimir(CajaSesion $sesion)
    {
        Gate::authorize('view', $sesion);

        return view('caja.turnos.imprimir', $this->detalle($sesion) + ['denominaciones' => config('caja.denominaciones')]);
    }

    /** Datos comunes del turno actual, el detalle y la impresión */
    private function detalle(CajaSesion $sesion): array
    {
        $sesion->loadMissing(['caja', 'usuario', 'cerradoPor', 'empresa']);

        return [
            'sesion'      => $sesion,
            'resumen'     => $this->cajas->resumen($sesion),
            'movimientos' => $sesion->movimientos()->with('usuario:id,name,apellido')->latest('fecha_movimiento')->get(),
            'pagos'       => $sesion->pagos()->with(['venta:id,serie,numero,tipo', 'compra:id,serie,numero,tipo_comprobante'])
                ->latest('fecha_pago')->latest('id')->limit(50)->get(),
        ];
    }
}
