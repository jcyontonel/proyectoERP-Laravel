<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Requests\NotaRequest;
use App\Models\Nota;
use App\Models\Venta;
use App\Services\CajaService;
use App\Services\Facturacion\FacturacionElectronicaService;
use App\Services\NotaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Notas de crédito y débito sobre facturas y boletas */
class NotaController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private NotaService $notas)
    {
    }

    public function index(Request $request)
    {
        $f = $request->validate([
            'q'      => ['nullable', 'string', 'max:100'],
            'tipo'   => ['nullable', Rule::in([Nota::CREDITO, Nota::DEBITO])],
            'estado' => ['nullable', Rule::in([Nota::EMITIDA, Nota::ANULADA])],
            'desde'  => ['nullable', 'date'],
            'hasta'  => ['nullable', 'date'],
        ]);
        $base = Nota::query()->deEmpresa($this->empresaId($request))->filtrar($f);

        return view('notas.index', [
            'notas'   => (clone $base)->with(['venta.cliente', 'usuario:id,name'])->orderByDesc('fecha_emision')->orderByDesc('id')->paginate(20)->withQueryString(),
            'totales' => (clone $base)->where('estado', Nota::EMITIDA)->selectRaw('tipo, COUNT(*) AS cantidad, SUM(total) AS total')->groupBy('tipo')->get()->keyBy('tipo'),
        ]);
    }

    /** Formulario de una nota sobre la venta (?tipo=07 crédito | 08 débito) */
    public function create(Request $request, Venta $venta, CajaService $cajas)
    {
        $tipo = $request->query('tipo') === Nota::DEBITO ? Nota::DEBITO : Nota::CREDITO;
        if (! $venta->admiteNotas()) {
            return redirect()->route('ventas.show', $venta)->with('error', 'Solo se emiten notas sobre facturas y boletas emitidas (no sobre tickets, borradores ni anuladas).');
        }

        $venta->load(['cliente', 'detalles.producto', 'notas' => fn ($q) => $q->emitidas()]);
        $creditoPrevio = round($venta->notas->where('tipo', Nota::CREDITO)->sum('total'), 2);

        return view('notas.create', [
            'venta'       => $venta,
            'tipo'        => $tipo,
            'motivos'     => Nota::MOTIVOS[$tipo],
            'pendientes'  => $this->notas->pendientesDeDevolver($venta),
            'disponible'  => round($venta->total - $creditoPrevio, 2),
            'conCredito'  => $creditoPrevio > 0,
            'saldo'       => $venta->esCredito() ? $venta->saldo() : 0.0,
            'metodos'     => config('ventas.metodos_pago'),
            'cajaAbierta' => $cajas->sesionAbierta($venta->empresa_id, $request->user()->id),
        ]);
    }

    public function store(NotaRequest $request, Venta $venta)
    {
        $nota = $this->notas->emitir($venta, $request->validated(), $request->user());
        $sunat = FacturacionElectronicaService::aviso(app(FacturacionElectronicaService::class)->emitirSeguro($nota));

        return redirect()->route('notas.show', $nota)->with('status', "{$nota->tipo_etiqueta} {$nota->numero_completo} emitida por S/ " . number_format($nota->total, 2) . '.' . $sunat);
    }

    public function show(Nota $nota)
    {
        return view('notas.show', ['nota' => $nota->load(['venta.cliente.tipoDocumento', 'detalles.tipoUnidad', 'usuario:id,name', 'devolucion', 'sunat'])]);
    }

    public function ticket(Nota $nota)
    {
        return view('notas.ticket', ['nota' => $nota->load(['venta.cliente.tipoDocumento', 'venta.empresa', 'detalles', 'usuario:id,name'])]);
    }

    public function anular(Request $request, Nota $nota)
    {
        $this->notas->anular($nota, $request->user());
        try {
            app(FacturacionElectronicaService::class)->anular($nota->refresh());   // baja en SUNAT si ya estaba aceptada
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('notas.show', $nota)->with('status', "{$nota->tipo_etiqueta} {$nota->numero_completo} anulada: se revirtieron el stock y los movimientos de dinero.");
    }
}
