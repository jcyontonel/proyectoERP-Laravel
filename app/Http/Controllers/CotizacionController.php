<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Requests\CotizacionRequest;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Empresa;
use App\Models\Producto;
use App\Services\CotizacionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cotizaciones (proformas): se convierten en venta con el formulario de venta ya llenado */
class CotizacionController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private CotizacionService $cotizaciones)
    {
    }

    public function index(Request $request)
    {
        $f = $request->validate([
            'q'      => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in([Cotizacion::EMITIDA, Cotizacion::ACEPTADA, Cotizacion::VENCIDA, Cotizacion::RECHAZADA, Cotizacion::CONVERTIDA, Cotizacion::ANULADA])],
            'desde'  => ['nullable', 'date'],
            'hasta'  => ['nullable', 'date'],
        ]);
        $base = Cotizacion::query()->deEmpresa($this->empresaId($request));

        return view('cotizaciones.index', [
            'cotizaciones' => (clone $base)->filtrar($f)->with(['cliente', 'venta:id,serie,numero'])->orderByDesc('fecha_emision')->orderByDesc('id')->paginate(20)->withQueryString(),
            'abiertas'     => (clone $base)->whereIn('estado', [Cotizacion::EMITIDA, Cotizacion::ACEPTADA])->whereDate('vence_el', '>=', today()->toDateString())
                ->selectRaw('COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total')->first(),
            'convertidas'  => (clone $base)->where('estado', Cotizacion::CONVERTIDA)->whereDate('fecha_emision', '>=', today()->startOfMonth()->toDateString())
                ->selectRaw('COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total')->first(),
        ]);
    }

    public function create(Request $request)
    {
        return view('cotizaciones.form', $this->datosFormulario($this->empresaId($request)) + ['cotizacion' => null]);
    }

    public function store(CotizacionRequest $request)
    {
        $cot = $this->cotizaciones->guardar($request->validated(), $request->user()->id, $this->empresaId($request));

        return redirect()->route('cotizaciones.show', $cot)->with('status', "Cotización {$cot->numero_completo} emitida por S/ " . number_format($cot->total, 2) . '.');
    }

    public function show(Cotizacion $cotizacion)
    {
        return view('cotizaciones.show', ['cotizacion' => $cotizacion->load(['cliente.tipoDocumento', 'detalles.tipoUnidad', 'usuario:id,name', 'venta'])]);
    }

    public function edit(Cotizacion $cotizacion)
    {
        if (! $cotizacion->esEditable()) {
            return redirect()->route('cotizaciones.show', $cotizacion)->with('error', 'Solo se editan cotizaciones emitidas.');
        }

        return view('cotizaciones.form', $this->datosFormulario($cotizacion->empresa_id) + ['cotizacion' => $cotizacion->load('detalles')]);
    }

    public function update(CotizacionRequest $request, Cotizacion $cotizacion)
    {
        $cot = $this->cotizaciones->guardar($request->validated(), $request->user()->id, $cotizacion->empresa_id, $cotizacion);

        return redirect()->route('cotizaciones.show', $cot)->with('status', "Cotización {$cot->numero_completo} actualizada.");
    }

    /** aceptada | rechazada | anulada | emitida (reabrir) */
    public function estado(Request $request, Cotizacion $cotizacion)
    {
        $estado = $request->validate(['estado' => ['required', Rule::in([Cotizacion::ACEPTADA, Cotizacion::RECHAZADA, Cotizacion::ANULADA, Cotizacion::EMITIDA])]])['estado'];
        $this->cotizaciones->cambiarEstado($cotizacion, $estado);

        $texto = [Cotizacion::ACEPTADA => 'marcada como aceptada', Cotizacion::RECHAZADA => 'marcada como rechazada', Cotizacion::ANULADA => 'anulada', Cotizacion::EMITIDA => 'reabierta'][$estado];

        return redirect()->route('cotizaciones.show', $cotizacion)->with('status', "Cotización {$cotizacion->numero_completo} {$texto}.");
    }

    /** Abre «Nueva venta» con el cliente, los productos y el descuento de la cotización */
    public function convertir(Cotizacion $cotizacion)
    {
        $datos = $this->cotizaciones->datosParaVenta($cotizacion->load('detalles'));

        return redirect()->route('ventas.create')
            ->withInput($datos + ['cotizacion_ref' => $cotizacion->numero_completo]);
    }

    public function imprimir(Cotizacion $cotizacion)
    {
        return view('cotizaciones.imprimir', ['cotizacion' => $cotizacion->load(['cliente.tipoDocumento', 'detalles.tipoUnidad', 'usuario:id,name']), 'empresa' => Empresa::find($cotizacion->empresa_id)]);
    }

    private function datosFormulario(int $empresaId): array
    {
        $empresa = Empresa::findOrFail($empresaId);

        return [
            'empresa'   => $empresa,
            'clientes'  => $empresa->clientes()->where('clientes.activo', true)->orderBy('clientes.nombre')->get()
                ->map(fn (Cliente $c) => ['id' => $c->id, 'nombre' => $c->nombre_completo, 'doc' => $c->numero_documento]),
            'productos' => $empresa->productos()->activo()->with('tipoUnidad:id,descripcion')->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'precio_unitario', 'tipo_unidad_id'])
                ->map(fn (Producto $p) => ['id' => $p->id, 'nombre' => $p->nombre, 'codigo' => (string) $p->codigo, 'precio' => (float) $p->precio_unitario, 'unidad' => $p->tipoUnidad?->descripcion]),
        ];
    }
}
