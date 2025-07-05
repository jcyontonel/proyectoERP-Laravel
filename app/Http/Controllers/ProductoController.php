<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProductoController extends Controller
{
    public function index(): JsonResponse
    {
        $empresaIdDelUsuario = auth()->user()->empresa_id;
        $productos = Producto::where('empresa_id', $empresaIdDelUsuario)
            ->with(['categoria', 'tipoUnidad', 'impuesto']) // si usas relaciones
            ->get();

        return response()->json($productos);
    }

    public function store(StoreProductoRequest $request): JsonResponse
    {
        $empresaIdDelUsuario = auth()->user()->empresa_id;

        $data = $request->validated();
        $data['empresa_id'] = $empresaIdDelUsuario;

        $producto = Producto::create($data);

        return response()->json([
            'message' => 'Producto creado correctamente.',
            'data' => $producto
        ], 201);
    }

    public function show(Producto $producto): JsonResponse
    {
        $empresaIdDelUsuario = auth()->user()->empresa_id;

        if ($producto->empresa_id !== $empresaIdDelUsuario) {
            return response()->json(['message' => 'No autorizado para ver este producto.'], 403);
        }
        return response()->json($producto);
    }

    public function update(UpdateProductoRequest $request, Producto $producto): JsonResponse
    {
        $empresaIdDelUsuario = auth()->user()->empresa_id;

        if ($producto->empresa_id !== $empresaIdDelUsuario) {
            return response()->json(['message' => 'No autorizado para actualizar este producto.'], 403);
        }

        $datos = $request->validated();
        $producto->update($datos);

        return response()->json(['message' => 'Producto actualizado correctamente', 'producto' => $producto]);
    }

    public function destroy(Producto $producto): JsonResponse
    {
        $empresaIdDelUsuario = auth()->user()->empresa_id;

        if ($producto->empresa_id !== $empresaIdDelUsuario) {
            return response()->json(['message' => 'No autorizado para eliminar este producto.'], 403);
        }

        $producto->delete(); // Soft delete

        return response()->json(['message' => 'Producto eliminado correctamente (soft delete).']);
    }
}
