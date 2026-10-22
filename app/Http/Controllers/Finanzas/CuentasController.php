<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finanzas\RegistrarPagoRequest;
use App\Models\Compra;
use App\Models\Venta;
use App\Services\CuentasService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Base de Cuentas por cobrar y por pagar: misma pantalla y misma lógica, distinto "modo".
 * Las subclases definen los textos, la relación con el tercero y las rutas (config()).
 */
abstract class CuentasController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(protected CuentasService $cuentas)
    {
    }

    /**
     * modo, titulo, tercero, terceros, relacion, prefijo (rutas), verbo, accion, metodos,
     * ruta_doc, ruta_tercero, nombre (fn tercero → string), documento (fn tercero → string)
     */
    abstract protected function config(): array;

    /** Listado de documentos pendientes o resumen por tercero, con antigüedad de la deuda */
    public function index(Request $request)
    {
        $f = $request->validate([
            'q'      => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:vencidas,por_vencer,al_dia'],
            'tramo'  => ['nullable', 'in:' . implode(',', array_keys(CuentasService::TRAMOS))],
            'vista'  => ['nullable', 'in:documentos,terceros'],
            'page'   => ['nullable', 'integer', 'min:1'],
        ]);
        $c = $this->config();

        $todos = $this->cuentas->pendientes($c['modo'], $this->empresaId($request))->with($c['relacion'])->get();
        $resumen = $this->cuentas->resumen($todos, $c['modo']);
        $filtrados = $this->filtrar($todos, $f);

        $vista = $f['vista'] ?? 'documentos';
        $filas = $vista === 'terceros' ? $this->porTercero($filtrados) : $filtrados->sortBy(fn ($d) => $this->orden($d))->values();

        return view('finanzas.cuentas.index', [
            'c'         => $c,
            'vista'     => $vista,
            'resumen'   => $resumen,
            'tramos'    => CuentasService::TRAMOS,
            'filas'     => $this->paginar($filas, $request),
            'filtrados' => ['total' => round($filtrados->sum(fn ($d) => $d->saldo()), 2), 'documentos' => $filtrados->count()],
        ]);
    }

    /** Estado de cuenta de un cliente o proveedor: documentos del período, pagos y saldo */
    protected function estadoCuenta(Request $request, Model $tercero, $documentos)
    {
        $f = $request->validate(['desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date']]);
        $desde = isset($f['desde']) ? Carbon::parse($f['desde']) : now()->subMonths(6)->startOfMonth();
        $hasta = isset($f['hasta']) ? Carbon::parse($f['hasta']) : now();

        $docs = $documentos
            ->whereBetween('fecha_emision', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->with(['pagos' => fn ($q) => $q->with('usuario:id,name,apellido')->orderBy('fecha_pago')])
            ->withSum(['pagosRegistrados as pagado'], 'monto')
            ->orderByDesc('fecha_emision')
            ->get();

        $pendientes = $this->pendientesDe($tercero);

        return view('finanzas.cuentas.estado-cuenta', [
            'c'          => $this->config(),
            'tercero'    => $tercero,
            'documentos' => $docs,
            'pendientes' => $pendientes->sortBy(fn ($d) => $this->orden($d))->values(),
            'resumen'    => $this->cuentas->resumen($pendientes, $this->config()['modo']),
            'desde'      => $desde,
            'hasta'      => $hasta,
            'facturado'  => round($docs->where('estado', '!=', 'anulada')->sum('total'), 2),
            'pagado'     => round($docs->where('estado', '!=', 'anulada')->sum(fn ($d) => $d->montoPagado()), 2),
        ]);
    }

    protected function registrarEn(RegistrarPagoRequest $request, Venta|Compra $documento)
    {
        $pago = $this->cuentas->registrar($documento, $request->validated(), $request->user());
        $c = $this->config();

        return back()->with('status', ucfirst($c['accion']) . " de S/ " . number_format($pago->monto, 2) . " registrado en {$documento->numero_completo}."
            . ($documento->refresh()->saldo() <= 0 ? ' El documento quedó cancelado.' : ' Saldo: S/ ' . number_format($documento->saldo(), 2) . '.'));
    }

    protected function abonarA(RegistrarPagoRequest $request, Model $tercero)
    {
        $pagos = $this->cuentas->abonar($this->pendientesDe($tercero), $request->validated(), $request->user());

        return back()->with('status', 'Abono de S/ ' . number_format($pagos->sum('monto'), 2) . ' aplicado a ' . $pagos->count() . ' '
            . Str::plural('documento', $pagos->count()) . ', del más antiguo al más reciente.');
    }

    /** Pendientes de un tercero en la empresa actual (con 'pagado' cargado) */
    protected function pendientesDe(Model $tercero): Collection
    {
        $c = $this->config();

        return $this->cuentas->pendientes($c['modo'], $this->empresaId(request()))
            ->where($c['relacion'] . '_id', $tercero->id)
            ->get();
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function filtrar(Collection $docs, array $f): Collection
    {
        $c = $this->config();
        $q = Str::lower(trim($f['q'] ?? ''));

        return $docs
            ->when($q !== '', fn ($col) => $col->filter(function ($d) use ($q, $c) {
                $t = $d->{$c['relacion']};

                return Str::contains(Str::lower($d->numero_completo . ' ' . ($t ? ($c['nombre'])($t) . ' ' . ($c['documento'])($t) : '')), $q);
            }))
            ->when(($f['estado'] ?? null) === 'vencidas', fn ($col) => $col->filter(fn ($d) => $d->estaVencida()))
            ->when(($f['estado'] ?? null) === 'por_vencer', fn ($col) => $col->filter(fn ($d) => ! $d->estaVencida() && $d->fecha_vencimiento?->lte(today()->addDays(7))))
            ->when(($f['estado'] ?? null) === 'al_dia', fn ($col) => $col->reject(fn ($d) => $d->estaVencida()))
            ->when(filled($f['tramo'] ?? null), fn ($col) => $col->filter(fn ($d) => $this->cuentas->tramo($d->diasVencido()) === $f['tramo']));
    }

    /** Una fila por cliente/proveedor: documentos, deuda, vencido y el vencimiento más antiguo */
    private function porTercero(Collection $docs): Collection
    {
        $c = $this->config();

        return $docs->groupBy($c['relacion'] . '_id')->map(fn ($grupo) => (object) [
            'tercero'     => $grupo->first()->{$c['relacion']},
            'documentos'  => $grupo->count(),
            'saldo'       => round($grupo->sum(fn ($d) => $d->saldo()), 2),
            'vencido'     => round($grupo->filter(fn ($d) => $d->estaVencida())->sum(fn ($d) => $d->saldo()), 2),
            'max_atraso'  => $grupo->max(fn ($d) => $d->diasVencido()),
            'proximo'     => $grupo->pluck('fecha_vencimiento')->filter()->min(),
        ])->sortByDesc('saldo')->values();
    }

    private function orden($d): string
    {
        return ($d->fecha_vencimiento ?? $d->fecha_emision)->format('Y-m-d') . str_pad((string) $d->id, 10, '0', STR_PAD_LEFT);
    }

    private function paginar(Collection $filas, Request $request): LengthAwarePaginator
    {
        $pagina = max((int) $request->query('page', 1), 1);

        return (new LengthAwarePaginator($filas->forPage($pagina, 20)->values(), $filas->count(), 20, $pagina))
            ->withPath($request->url())->withQueryString();
    }
}
