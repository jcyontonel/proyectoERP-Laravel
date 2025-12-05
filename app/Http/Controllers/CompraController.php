<?php
namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Traits\RegistraKardex;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCompraRequest;
use App\Http\Requests\UpdateCompraRequest;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    use RegistraKardex;

    /**
     * Listado de compras
     */
    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id ?? auth()->user()->empresas->first()->id;

        $compras = Compra::with(['proveedor', 'usuario'])
            ->where('empresa_id', $empresaId)
            ->latest()
            ->paginate(10);

        return view('compras.index', compact('compras'));
    }

    /**
     * Mostrar formulario de creación
     */
    public function create()
    {
        $empresaId = auth()->user()->empresa_id ?? auth()->user()->empresas->first()->id;

        $proveedores = Proveedor::where('empresa_id', $empresaId)->where('activo', true)->get();
        $productos = Producto::where('empresa_id', $empresaId)->where('activo', true)->get();

        return view('compras.create', compact('proveedores', 'productos'));
    }

    /**
     * Guardar nueva compra
     */
    public function store(StoreCompraRequest $request)
    {
        DB::beginTransaction();
        try {
            $empresaId = auth()->user()->empresa_id ?? auth()->user()->empresas->first()->id;

            // Crear compra
            $compra = Compra::create([
                'empresa_id' => $empresaId,
                'proveedor_id' => $request->proveedor_id,
                'user_id' => auth()->id(),
                'serie' => $request->serie,
                'numero' => $request->numero,
                'fecha_emision' => $request->fecha_emision,
                'tipo_comprobante' => $request->tipo_comprobante,
                'moneda' => $request->moneda,
                'subtotal' => $request->subtotal,
                'total_impuestos' => $request->total_impuestos,
                'total' => $request->total,
                'estado' => 'registrada',
                'observacion' => $request->observacion,
            ]);

            // Guardar detalles
            foreach ($request->detalles as $detalle) {
                $producto = Producto::findOrFail($detalle['producto_id']);

                $detalleCompra = DetalleCompra::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $producto->id,
                    'descripcion' => $detalle['descripcion'] ?? $producto->nombre,
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'subtotal' => $detalle['subtotal'],
                    'impuesto' => $detalle['impuesto'] ?? 0,
                    'total' => $detalle['total'],
                ]);

                // Actualizar stock del producto
                $producto->stock = ($producto->stock ?? 0) + $detalleCompra->cantidad;
                $producto->save();

                // Registrar movimiento en Kardex
                $this->registrarMovimientoKardex([
                    'empresa_id' => $empresaId,
                    'producto_id' => $producto->id,
                    'tipo_movimiento' => 'ingreso',
                    'motivo' => 'Compra',
                    'referencia_tipo' => 'compra',
                    'referencia_id' => $compra->id,
                    'referencia_serie' => $compra->serie,
                    'referencia_numero' => $compra->numero,
                    'cantidad' => $detalleCompra->cantidad,
                    'costo_unitario' => $detalleCompra->precio_unitario,
                    'observacion' => "Ingreso por compra {$compra->serie}-{$compra->numero}",
                ]);
            }

            DB::commit();

            return redirect()->route('compras.index')->with('success', 'Compra registrada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return back()->with('error', 'Error al registrar la compra: ' . $th->getMessage());
        }
    }

    /**
     * Mostrar una compra
     */
    public function show(Compra $compra)
    {
        $compra->load(['proveedor', 'usuario', 'detalles.producto']);
        return view('compras.show', compact('compra'));
    }

    /**
     * Mostrar formulario de edición
     */
    public function edit(Compra $compra)
    {
        $empresaId = $compra->empresa_id;
        $proveedores = Proveedor::where('empresa_id', $empresaId)->where('activo', true)->get();
        $productos = Producto::where('empresa_id', $empresaId)->where('activo', true)->get();

        $compra->load('detalles');

        return view('compras.edit', compact('compra', 'proveedores', 'productos'));
    }

    /**
     * Actualizar compra
     */
    public function update(UpdateCompraRequest $request, Compra $compra)
    {
        DB::beginTransaction();
        try {
            $compra->update([
                'proveedor_id' => $request->proveedor_id,
                'serie' => $request->serie,
                'numero' => $request->numero,
                'fecha_emision' => $request->fecha_emision,
                'tipo_comprobante' => $request->tipo_comprobante,
                'moneda' => $request->moneda,
                'subtotal' => $request->subtotal,
                'total_impuestos' => $request->total_impuestos,
                'total' => $request->total,
                'observacion' => $request->observacion,
            ]);

            // Eliminar detalles anteriores y recalcular stock
            foreach ($compra->detalles as $detalleAntiguo) {
                $producto = $detalleAntiguo->producto;
                $producto->stock = max(0, ($producto->stock ?? 0) - $detalleAntiguo->cantidad);
                $producto->save();
                $detalleAntiguo->delete();
            }

            // Crear nuevos detalles
            foreach ($request->detalles as $detalle) {
                $producto = Producto::findOrFail($detalle['producto_id']);
                $detalleCompra = DetalleCompra::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $producto->id,
                    'descripcion' => $detalle['descripcion'] ?? $producto->nombre,
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'subtotal' => $detalle['subtotal'],
                    'impuesto' => $detalle['impuesto'] ?? 0,
                    'total' => $detalle['total'],
                ]);

                $producto->stock = ($producto->stock ?? 0) + $detalleCompra->cantidad;
                $producto->save();
            }

            DB::commit();

            return redirect()->route('compras.index')->with('success', 'Compra actualizada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar la compra: ' . $th->getMessage());
        }
    }

    /**
     * Eliminar compra
     */
    public function destroy(Compra $compra)
    {
        DB::beginTransaction();
        try {
            foreach ($compra->detalles as $detalle) {
                $producto = $detalle->producto;
                $producto->stock = max(0, ($producto->stock ?? 0) - $detalle->cantidad);
                $producto->save();
            }

            $compra->delete();
            DB::commit();

            return redirect()->route('compras.index')->with('success', 'Compra eliminada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return back()->with('error', 'Error al eliminar la compra: ' . $th->getMessage());
        }
    }
}
