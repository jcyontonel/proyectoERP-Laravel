<?php

namespace App\Modulos\Erp\Http\Controllers\Inventario;

use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Erp\Models\Fraccionamiento;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Catalogo\Models\UnidadConversion;
use App\Modulos\Erp\Services\FraccionamientoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Reglas "1 [este producto] = factor [otro producto]" desde la ficha del producto de origen */
class ReglaFraccionamientoController extends Controller
{
    public function store(Request $request, Producto $producto, FraccionamientoService $fracciones)
    {
        Gate::authorize('update', $producto);

        $d = $request->validate([
            'producto_destino_id' => ['required', Rule::exists('productos', 'id')->where('empresa_id', $producto->empresa_id)->where(fn ($q) => $q->where('es_servicio', false))->whereNull('deleted_at')],
            'factor'              => ['required', 'numeric', 'min:0.0001', 'max:999999'],
        ], ['producto_destino_id.required' => 'Elige el producto que se obtiene al partir.']);

        $destino = Producto::findOrFail($d['producto_destino_id']);
        $regla = $fracciones->guardarRegla($producto, $destino, (float) $d['factor']);

        return back()->with('status', "Regla guardada: 1 {$producto->nombre} = {$regla->factor} de «{$destino->nombre}».");
    }

    public function destroy(Producto $producto, UnidadConversion $regla)
    {
        Gate::authorize('update', $producto);
        abort_unless($regla->producto_id === $producto->id && $regla->esFraccionamiento(), 404);

        // Con historial se desactiva (los fraccionamientos guardan la referencia)
        if (Fraccionamiento::query()->where('unidad_conversion_id', $regla->id)->exists()) {
            $regla->update(['activo' => false]);

            return back()->with('status', 'La regla tiene fraccionamientos registrados: se desactivó.');
        }

        $regla->delete();

        return back()->with('status', 'Regla de fraccionamiento eliminada.');
    }
}
