<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Requests\OrdenCompraRequest;
use App\Models\Empresa;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\OrdenCompraService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Órdenes de compra: se reciben registrando compras con el formulario de compra ya llenado */
class OrdenCompraController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private OrdenCompraService $ordenes)
    {
    }

    public function index(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $f = $request->validate([
            'q'            => ['nullable', 'string', 'max:100'],
            'estado'       => ['nullable', Rule::in(array_keys(OrdenCompra::ESTADOS))],
            'proveedor_id' => ['nullable', 'integer'],
            'desde'        => ['nullable', 'date'],
            'hasta'        => ['nullable', 'date'],
        ]);
        $base = OrdenCompra::query()->deEmpresa($empresaId);

        return view('ordenes.index', [
            'ordenes'     => (clone $base)->filtrar($f)->with(['proveedor', 'almacen:id,nombre'])->orderByDesc('fecha_emision')->orderByDesc('id')->paginate(20)->withQueryString(),
            'pendientes'  => (clone $base)->whereIn('estado', [OrdenCompra::EMITIDA, OrdenCompra::PARCIAL])->selectRaw('COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total')->first(),
            'atrasadas'   => (clone $base)->whereIn('estado', [OrdenCompra::EMITIDA, OrdenCompra::PARCIAL])->whereNotNull('fecha_entrega')->whereDate('fecha_entrega', '<', today()->toDateString())->count(),
            'proveedores' => Proveedor::query()->where('empresa_id', $empresaId)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
        ]);
    }

    public function create(Request $request)
    {
        return view('ordenes.form', $this->datosFormulario($this->empresaId($request)) + ['orden' => null]);
    }

    public function store(OrdenCompraRequest $request)
    {
        $orden = $this->ordenes->guardar($request->validated(), $request->user()->id, $this->empresaId($request));

        return redirect()->route('ordenes.show', $orden)->with('status', "Orden {$orden->numero_completo} emitida por S/ " . number_format($orden->total, 2) . '.');
    }

    public function show(OrdenCompra $orden)
    {
        return view('ordenes.show', ['orden' => $orden->load(['proveedor.tipoDocumento', 'almacen', 'detalles.producto.tipoUnidad', 'usuario:id,name', 'compras'])]);
    }

    public function edit(OrdenCompra $orden)
    {
        if (! $orden->esEditable()) {
            return redirect()->route('ordenes.show', $orden)->with('error', 'Solo se editan órdenes que aún no reciben mercadería.');
        }

        return view('ordenes.form', $this->datosFormulario($orden->empresa_id) + ['orden' => $orden->load('detalles')]);
    }

    public function update(OrdenCompraRequest $request, OrdenCompra $orden)
    {
        $orden = $this->ordenes->guardar($request->validated(), $request->user()->id, $orden->empresa_id, $orden);

        return redirect()->route('ordenes.show', $orden)->with('status', "Orden {$orden->numero_completo} actualizada.");
    }

    /** Abre «Nueva compra» con el proveedor, el almacén y lo que falta recibir */
    public function recibir(OrdenCompra $orden)
    {
        $datos = $this->ordenes->datosParaCompra($orden->load('detalles'));

        return redirect()->route('compras.create')->withInput($datos + ['orden_compra_ref' => $orden->numero_completo]);
    }

    public function cerrar(OrdenCompra $orden)
    {
        $this->ordenes->cerrar($orden);

        return redirect()->route('ordenes.show', $orden)->with('status', "Orden {$orden->numero_completo} cerrada: ya no se espera el resto de la mercadería.");
    }

    public function anular(OrdenCompra $orden)
    {
        $this->ordenes->anular($orden);

        return redirect()->route('ordenes.show', $orden)->with('status', "Orden {$orden->numero_completo} anulada.");
    }

    public function imprimir(OrdenCompra $orden)
    {
        return view('ordenes.imprimir', ['orden' => $orden->load(['proveedor.tipoDocumento', 'almacen', 'detalles.producto.tipoUnidad', 'usuario:id,name']), 'empresa' => Empresa::find($orden->empresa_id)]);
    }

    private function datosFormulario(int $empresaId): array
    {
        $empresa = Empresa::findOrFail($empresaId);

        return [
            'empresa'     => $empresa,
            'proveedores' => $empresa->proveedores()->activo()->orderBy('razon_social')->get()
                ->map(fn (Proveedor $p) => ['id' => $p->id, 'nombre' => $p->nombre, 'doc' => (string) $p->numero_documento]),
            'almacenes'   => $empresa->almacenes()->activo()->orderByDesc('es_principal')->orderBy('nombre')->get(['id', 'nombre', 'es_principal']),
            // precio = último costo registrado (sugerido; se edita en la orden)
            'productos'   => $empresa->productos()->activo()->with('tipoUnidad:id,descripcion')->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'costo_unitario', 'tipo_unidad_id'])
                ->map(fn (Producto $p) => ['id' => $p->id, 'nombre' => $p->nombre, 'codigo' => (string) $p->codigo, 'precio' => (float) $p->costo_unitario, 'unidad' => $p->tipoUnidad?->descripcion]),
        ];
    }
}
