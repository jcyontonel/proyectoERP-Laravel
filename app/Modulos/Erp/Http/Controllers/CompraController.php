<?php

namespace App\Modulos\Erp\Http\Controllers;

use App\Modulos\Nucleo\Http\Controllers\Controller;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Erp\Http\Requests\FiltrarComprasRequest;
use App\Modulos\Erp\Http\Requests\StoreCompraRequest;
use App\Modulos\Erp\Http\Requests\StorePagoCompraRequest;
use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Erp\Models\Compra;
use App\Modulos\Erp\Models\DetalleCompra;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Erp\Models\Proveedor;
use App\Modulos\Nucleo\Models\TipoDocumento;
use App\Modulos\Erp\Services\CompraExportService;
use App\Modulos\Erp\Services\CajaService;
use App\Modulos\Erp\Services\CompraService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompraController extends Controller
{
    use ResuelveEmpresa;

    /** Listado con filtros (q, estado, pago, proveedor_id, desde, hasta) */
    public function index(FiltrarComprasRequest $request)
    {
        $empresaId = $this->empresaId($request);

        $compras = Compra::query()
            ->deEmpresa($empresaId)
            ->with(['proveedor.tipoDocumento', 'almacen'])
            ->withSum(['pagos as pagado' => fn ($q) => $q->where('estado', 'registrado')], 'monto')
            ->filtrar($request->validated())
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Totales de lo filtrado (solo compras registradas)
        $pendiente = Compra::query()->deEmpresa($empresaId)->filtrar($request->validated())
            ->where('estado', Compra::REGISTRADA)
            ->withSum(['pagos as pagado' => fn ($q) => $q->where('estado', 'registrado')], 'monto')
            ->get()
            ->sum(fn (Compra $c) => $c->saldo());

        $proveedores = Proveedor::query()->deEmpresa($empresaId)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']);

        return view('compras.index', compact('compras', 'pendiente', 'proveedores'));
    }

    /** Formulario de nueva compra */
    public function create(Request $request)
    {
        $empresaId = $this->empresaId($request);

        return view('compras.create', $this->datosFormulario($empresaId) + [
            'cajaAbierta' => app(CajaService::class)->sesionAbierta($empresaId, $request->user()->id),
        ]);
    }

    /** Registra la compra: detalle, stock, kardex, costo y pago */
    public function store(StoreCompraRequest $request, CompraService $compras)
    {
        $compra = $compras->crear($request->validated(), $request->user()->id, $this->empresaId($request));

        $precios = count($compras->preciosActualizados);

        return redirect()->route('compras.show', $compra)
            ->with('status', "Compra {$compra->numero_completo} registrada. El stock y el kardex fueron actualizados"
                . ($precios ? " y se actualizó el precio de venta de {$precios} " . str('producto')->plural($precios) . '.' : '.'));
    }

    public function show(Request $request, Compra $compra)
    {
        $this->propia($request, $compra);

        $compra->load([
            'empresa', 'almacen', 'usuario', 'proveedor.tipoDocumento',
            'detalles.producto', 'detalles.lote', 'detalles.tipoUnidad',
            'pagos.usuario', 'ordenesCompra',
        ]);

        return view('compras.show', compact('compra'));
    }

    /** Anula la compra, devuelve el stock ingresado y anula sus pagos */
    public function anular(Request $request, Compra $compra, CompraService $compras)
    {
        $this->propia($request, $compra);

        try {
            $compras->anular($compra, $request->user()->id);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('status', "La compra {$compra->numero_completo} fue anulada y el stock revertido.");
    }

    /** Pago (total o parcial) de una compra a crédito */
    public function storePago(StorePagoCompraRequest $request, Compra $compra, CompraService $compras)
    {
        $this->propia($request, $compra);

        $compras->registrarPago($compra, $request->validated(), $request->user()->id);

        return back()->with('status', 'Pago registrado correctamente.');
    }

    /** CSV para Excel con los mismos filtros del listado */
    public function export(FiltrarComprasRequest $request, CompraExportService $exportador)
    {
        $query = Compra::query()
            ->deEmpresa($this->empresaId($request))
            ->filtrar($request->validated())
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        return $exportador->csv($query);
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function propia(Request $request, Compra $compra): void
    {
        abort_unless($compra->empresa_id === $this->empresaId($request), 404);
    }

    /** Catálogos del formulario de compra */
    private function datosFormulario(int $empresaId): array
    {
        $empresa = Empresa::findOrFail($empresaId);

        $proveedores = $empresa->proveedores()->activo()
            ->with('tipoDocumento:id,codigo,descripcion')
            ->orderBy('razon_social')
            ->get()
            ->map(fn (Proveedor $p) => [
                'id'       => $p->id,
                'nombre'   => $p->nombre,
                'doc'      => (string) $p->numero_documento,
                'tipo_doc' => $p->tipo_doc_etiqueta,
                'contacto' => $p->contacto_nombre,
                'telefono' => $p->telefono ?: $p->celular,
                'email'    => $p->email,
            ])->values();

        $almacenes = $empresa->almacenes()->activo()
            ->orderByDesc('es_principal')->orderBy('nombre')
            ->get(['id', 'nombre', 'es_principal']);

        // Stock actual por almacén (informativo en el buscador)
        $stocks = AlmacenProducto::query()
            ->whereIn('almacen_id', $almacenes->pluck('id'))
            ->get(['almacen_id', 'producto_id', 'stock'])
            ->groupBy('producto_id');

        // Última compra registrada de cada producto: costo, fecha y proveedor (para comparar al registrar)
        $ultimas = DetalleCompra::query()
            ->whereIn('id', DetalleCompra::query()->selectRaw('MAX(detalle_compras.id)')
                ->join('compras', 'compras.id', '=', 'detalle_compras.compra_id')
                ->where('compras.empresa_id', $empresaId)->where('compras.estado', Compra::REGISTRADA)
                ->groupBy('detalle_compras.producto_id'))
            ->with('compra.proveedor:id,razon_social,nombre_comercial')
            ->get()
            ->keyBy('producto_id');

        $productos = $empresa->productos()->activo()
            ->with('tipoUnidad:id,descripcion')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'costo_unitario', 'precio_unitario', 'es_servicio', 'maneja_lotes', 'tipo_unidad_id'])
            ->map(fn (Producto $p) => [
                'id'          => $p->id,
                'codigo'      => $p->codigo,
                'nombre'      => $p->nombre,
                'costo'       => $p->costo_unitario,
                'precio'      => $p->precio_unitario,
                'unidad'      => $p->tipoUnidad?->descripcion,
                'lotes'       => $p->maneja_lotes,
                'ultima'      => isset($ultimas[$p->id]) ? [
                    'costo'     => (float) $ultimas[$p->id]->precio_unitario,
                    'fecha'     => $ultimas[$p->id]->compra?->fecha_emision?->format('d/m/Y'),
                    'proveedor' => $ultimas[$p->id]->compra?->proveedor?->nombre,
                ] : null,
                'es_servicio' => $p->es_servicio,
                'stocks'      => ($stocks[$p->id] ?? collect())->mapWithKeys(fn ($s) => [$s->almacen_id => $s->stock])->all(),
            ])->values();

        return [
            'empresa'            => $empresa,
            'proveedores'        => $proveedores,
            'almacenes'          => $almacenes,
            'productos'          => $productos,
            'tiposDocumento'     => TipoDocumento::orderBy('descripcion')->get(['id', 'codigo', 'descripcion']),
            'tiposComprobante'   => config('compras.tipos_comprobante'),
            'monedas'            => config('compras.monedas'),
            'metodosPago'        => config('compras.metodos_pago'),
            'igv'                => (float) $empresa->igv,
            'preciosIncluyenIgv' => (bool) $empresa->precios_incluyen_igv,
            'controlarStock'     => (bool) $empresa->controlar_stock,
        ];
    }
}
