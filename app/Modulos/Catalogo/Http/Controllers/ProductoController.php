<?php

namespace App\Modulos\Catalogo\Http\Controllers;

use App\Modulos\Nucleo\Http\Controllers\Controller;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Catalogo\Http\Requests\FiltrarProductosRequest;
use App\Modulos\Catalogo\Http\Requests\ProductoRequest;
use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Catalogo\Models\Categoria;
use App\Modulos\Erp\Models\DetalleCompra;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\Impuesto;
use App\Modulos\Erp\Models\Kardex;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Catalogo\Models\TipoUnidad;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Catalogo\Services\ProductoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    use ResuelveEmpresa;

    /** Listado con filtros (q, categoria_id, tipo, estado, stock, orden) y resumen del catálogo */
    public function index(FiltrarProductosRequest $request)
    {
        $empresaId = $this->empresaId($request);
        $f = $request->validated();

        $productos = Producto::query()
            ->deEmpresa($empresaId)
            ->with(['categoria:id,nombre', 'tipoUnidad'])
            ->withSum('stocks as stock_total', 'stock')
            ->withCount(['conversiones as presentaciones' => fn ($q) => $q->where('activo', true)])
            ->withExists(['stocks as stock_bajo' => fn ($q) => $q->where('stock_minimo', '>', 0)->whereColumn('stock', '<=', 'stock_minimo')])
            ->filtrar($f)
            ->when(($f['orden'] ?? 'nombre') === 'precio', fn ($q) => $q->orderByDesc('precio_unitario'))
            ->when(($f['orden'] ?? 'nombre') === 'stock', fn ($q) => $q->orderBy('stock_total'))
            ->when(($f['orden'] ?? 'nombre') === 'recientes', fn ($q) => $q->latest())
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('productos.index', [
            'productos'  => $productos,
            'categorias' => Categoria::query()->deEmpresa($empresaId)->orderBy('nombre')->get(['id', 'nombre']),
            'resumen'    => $this->resumenCatalogo($empresaId),
            'verCostos'  => $request->user()->can('verCostos', Producto::class),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Producto::class);

        return view('productos.form', $this->datosFormulario($this->empresaId($request)) + [
            'producto'    => new Producto(['activo' => true, 'es_servicio' => false, 'precio_unitario' => 0, 'costo_unitario' => 0]),
            'stocks'      => collect(),
            'bloqueaBase' => false,
        ]);
    }

    public function store(ProductoRequest $request, ProductoService $productos)
    {
        $producto = $productos->crear($request->validated(), $request->user()->id, $this->empresaId($request));

        return redirect()->route('productos.show', $producto)->with('status', "Producto «{$producto->nombre}» creado.");
    }

    /** Ficha: datos, presentaciones, stock por almacén, lotes, ventas de 30 días, última compra y kardex */
    public function show(Request $request, Producto $producto)
    {
        Gate::authorize('view', $producto);

        $producto->load(['categoria', 'tipoUnidad', 'impuesto', 'conversiones.unidadOrigen',
            'fraccionaEn.productoDestino.tipoUnidad', 'seObtieneDe.producto.tipoUnidad']);

        $stocks = AlmacenProducto::query()->with('almacen')->where('producto_id', $producto->id)->get()
            ->sortByDesc(fn ($s) => $s->almacen?->es_principal)->values();

        $lotes = $producto->maneja_lotes
            ? $producto->lotes()->withSum('existencias as cantidad', 'cantidad')->orderBy('fecha_vencimiento')->limit(10)->get()
            : collect();

        $movimientos = Kardex::query()
            ->with(['almacen', 'lote'])
            ->where('empresa_id', $producto->empresa_id)
            ->where('producto_id', $producto->id)
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $ventas30 = DB::table('detalle_ventas as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.venta_id')
            ->where('dv.producto_id', $producto->id)
            ->where('v.estado', Venta::COMPLETADA)
            ->whereNull('v.deleted_at')->whereNull('dv.deleted_at')
            ->where('v.fecha_emision', '>=', now()->subDays(30)->startOfDay())
            ->selectRaw('COALESCE(SUM(dv.cantidad), 0) AS cantidad, COALESCE(SUM(dv.total), 0) AS importe, COUNT(DISTINCT v.id) AS ventas')
            ->first();

        $ultimaCompra = DetalleCompra::query()
            ->with('compra.proveedor')
            ->where('producto_id', $producto->id)
            ->whereHas('compra', fn ($q) => $q->where('estado', 'registrada'))
            ->latest('id')
            ->first();

        return view('productos.show', [
            'producto'       => $producto,
            'presentaciones' => $producto->presentaciones(),
            'stocks'         => $stocks,
            'lotes'          => $lotes,
            'movimientos'    => $movimientos,
            'ventas30'       => $ventas30,
            'ultimaCompra'   => $ultimaCompra,
            'empresa'        => Empresa::find($producto->empresa_id),
            'verCostos'      => $request->user()->can('verCostos', Producto::class),
            // Productos a los que se puede partir este (para crear reglas de fraccionamiento)
            'destinos'       => $producto->es_servicio ? collect() : Producto::query()->deEmpresa($producto->empresa_id)->activo()
                ->where('es_servicio', false)->whereKeyNot($producto->id)->with('tipoUnidad:id,descripcion')->orderBy('nombre')->get(['id', 'nombre', 'tipo_unidad_id']),
        ]);
    }

    public function edit(Request $request, Producto $producto)
    {
        Gate::authorize('update', $producto);

        $producto->load(['conversiones.unidadOrigen']);

        return view('productos.form', $this->datosFormulario($producto->empresa_id) + [
            'producto'    => $producto,
            'stocks'      => AlmacenProducto::query()->where('producto_id', $producto->id)->get()->keyBy('almacen_id'),
            // Con historial no se puede cambiar la unidad base ni si es servicio
            'bloqueaBase' => $producto->tieneMovimientos(),
        ]);
    }

    public function update(ProductoRequest $request, Producto $producto, ProductoService $productos)
    {
        $productos->actualizar($producto, $request->validated(), $request->user()->id, $this->empresaId($request));

        return redirect()->route('productos.show', $producto)->with('status', "Producto «{$producto->nombre}» actualizado.");
    }

    public function destroy(Request $request, Producto $producto, ProductoService $productos)
    {
        Gate::authorize('delete', $producto);

        try {
            $productos->eliminar($producto);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('productos.index')->with('status', "Producto «{$producto->nombre}» eliminado.");
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function resumenCatalogo(int $empresaId): array
    {
        $activos = Producto::query()->deEmpresa($empresaId)->activo();

        return [
            'total'     => (clone $activos)->count(),
            'servicios' => (clone $activos)->where('es_servicio', true)->count(),
            'bajo'      => (clone $activos)->conStockBajo()->count(),
            'agotados'  => (clone $activos)->agotados()->count(),
            'valor'     => (float) DB::table('almacen_producto as ap')
                ->join('productos as p', 'p.id', '=', 'ap.producto_id')
                ->where('p.empresa_id', $empresaId)->whereNull('p.deleted_at')->where('ap.stock', '>', 0)
                ->sum(DB::raw('ap.stock * p.costo_unitario')),
        ];
    }

    private function datosFormulario(int $empresaId): array
    {
        $empresa = Empresa::findOrFail($empresaId);

        return [
            'empresa'     => $empresa,
            'categorias'  => Categoria::query()->deEmpresa($empresaId)->orderBy('nombre')->get(['id', 'nombre']),
            'tiposUnidad' => TipoUnidad::orderBy('descripcion')->get(['id', 'codigo', 'descripcion']),
            'impuestos'   => Impuesto::activo()->orderBy('tipo_afectacion')->get(['id', 'codigo', 'nombre', 'porcentaje']),
            'almacenes'   => $empresa->almacenes()->activo()->orderByDesc('es_principal')->orderBy('nombre')->get(['id', 'nombre', 'es_principal']),
            // Sin control de inventario (solo WEB) el stock se edita aquí
            'stockDirecto' => ProductoService::stockDirecto($empresaId),
        ];
    }
}
