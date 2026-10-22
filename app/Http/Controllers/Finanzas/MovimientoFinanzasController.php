<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Services\CuentasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Historial de cobros y pagos (todos los métodos), con anulación y exportación */
class MovimientoFinanzasController extends Controller
{
    use ResuelveEmpresa;

    public function index(Request $request)
    {
        $query = $this->consulta($request);

        $totales = (clone $query)->reorder()->where('pagos.estado', Pago::REGISTRADO)
            ->selectRaw('tipo, sum(monto) as total')->groupBy('tipo')->pluck('total', 'tipo');

        return view('finanzas.movimientos', [
            'pagos'     => $query->paginate(25)->withQueryString(),
            'cobrado'   => round((float) ($totales[Pago::TIPO_COBRO] ?? 0), 2),
            'pagado'    => round((float) ($totales[Pago::TIPO_PAGO] ?? 0), 2),
            'verPagos'  => Gate::allows('cuentas-por-pagar'),
            'metodos'   => config('ventas.metodos_pago') + config('compras.metodos_pago'),
        ]);
    }

    public function export(Request $request)
    {
        $pagos = $this->consulta($request)->limit(10000)->get();
        $metodos = config('ventas.metodos_pago') + config('compras.metodos_pago');

        return response()->streamDownload(function () use ($pagos, $metodos) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Tipo', 'Documento', 'Cliente / proveedor', 'Método', 'N.º operación', 'Monto', 'Estado', 'Registrado por', 'Observación']);

            foreach ($pagos as $p) {
                $doc = $p->venta ?? $p->compra;
                fputcsv($out, [
                    $p->fecha_pago->format('d/m/Y H:i'),
                    $p->tipo === Pago::TIPO_COBRO ? 'Cobro' : 'Pago',
                    $doc?->numero_completo ?? '',
                    $this->seguro($p->venta ? ($p->venta->cliente?->nombre_completo ?? '') : ($p->compra?->proveedor?->nombre ?? '')),
                    $metodos[$p->metodo_pago] ?? $p->metodo_pago,
                    $this->seguro((string) $p->referencia),
                    number_format($p->monto, 2, '.', ''),
                    ucfirst($p->estado),
                    $this->seguro($p->usuario?->name ?? ''),
                    $this->seguro((string) $p->observacion),
                ]);
            }

            fclose($out);
        }, 'movimientos_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Anula un cobro (registrar-pagos) o un pago a proveedor (cuentas-por-pagar) */
    public function anular(Request $request, Pago $pago, CuentasService $cuentas)
    {
        Gate::authorize($pago->tipo === Pago::TIPO_PAGO ? 'cuentas-por-pagar' : 'registrar-pagos');

        $cuentas->anular($pago, $request->user());

        return back()->with('status', ($pago->tipo === Pago::TIPO_COBRO ? 'Cobro' : 'Pago') . ' de S/ ' . number_format($pago->monto, 2) . ' anulado. El saldo del documento se restableció.');
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function consulta(Request $request): Builder
    {
        $f = $request->validate([
            'q'      => ['nullable', 'string', 'max:100'],
            'tipo'   => ['nullable', 'in:cobro,pago'],
            'metodo' => ['nullable', 'string', 'max:30'],
            'estado' => ['nullable', 'in:registrado,anulado'],
            'desde'  => ['nullable', 'date'],
            'hasta'  => ['nullable', 'date'],
        ]);

        // Quien no maneja cuentas por pagar solo ve los cobros
        $tipo = Gate::allows('cuentas-por-pagar') ? ($f['tipo'] ?? null) : Pago::TIPO_COBRO;

        return Pago::query()
            ->deEmpresa($this->empresaId($request))
            ->with(['venta.cliente', 'compra.proveedor', 'usuario:id,name,apellido', 'sesion'])
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->when(filled($f['metodo'] ?? null), fn ($q) => $q->where('metodo_pago', $f['metodo']))
            ->when(filled($f['estado'] ?? null), fn ($q) => $q->where('estado', $f['estado']))
            ->when(filled($f['desde'] ?? null), fn ($q) => $q->where('fecha_pago', '>=', $f['desde'] . ' 00:00:00'))
            ->when(filled($f['hasta'] ?? null), fn ($q) => $q->where('fecha_pago', '<=', $f['hasta'] . ' 23:59:59'))
            ->when(filled($f['q'] ?? null), fn ($q) => $q->where(fn ($w) => $w
                ->whereLike('referencia', '%' . $f['q'] . '%')
                ->orWhereHas('venta.cliente', fn ($c) => $c->whereLike('nombre', '%' . $f['q'] . '%')->orWhereLike('apellido', '%' . $f['q'] . '%')->orWhereLike('numero_documento', '%' . $f['q'] . '%'))
                ->orWhereHas('compra.proveedor', fn ($p) => $p->whereLike('razon_social', '%' . $f['q'] . '%')->orWhereLike('numero_documento', '%' . $f['q'] . '%'))))
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id');
    }

    /** Evita inyección de fórmulas al abrir el CSV en Excel */
    private function seguro(string $valor): string
    {
        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'" . $valor : $valor;
    }
}
