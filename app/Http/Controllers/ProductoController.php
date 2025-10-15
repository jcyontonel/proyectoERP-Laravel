<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Empresa;
use App\Models\Categoria;
use App\Models\TipoUnidad;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    /**
     * Mostrar lista de productos.
     */
    public function index()
    {
        $empresaIds = Auth::user()->empresas->pluck('id');
        $productos = Producto::with('categoria')
            ->whereIn('empresa_id', $empresaIds)
            ->orderBy('nombre', 'asc')
            ->get();

        $categorias = Categoria::whereIn('empresa_id', $empresaIds)
            ->orderBy('nombre')
            ->get();

        return view('productos.index', compact('productos', 'categorias'));
    }

    /**
     * Mostrar formulario de creación.
     */
    public function create()
    {
        $empresaIds = Auth::user()->empresas->pluck('id');

        $categorias = Categoria::whereIn('empresa_id', $empresaIds)
            ->orderBy('nombre')
            ->get();

        $tiposUnidad = TipoUnidad::orderBy('codigo')->get();

        // Puedes obtener una empresa específica si el usuario solo tiene una
        $empresa = Auth::user()->empresas->first();

        return view('productos.create', compact('categorias', 'tiposUnidad', 'empresa'));
    }

    /**
     * Guardar un nuevo producto.
     */
    public function store(StoreProductoRequest $request)
    {
        $empresa = Auth::user()->empresas->first();

        $data = $request->validated();
        $data['empresa_id'] = $empresa->id;
        $data['es_servicio'] = $request->has('es_servicio');
        $data['activo'] = $request->has('activo');

        Producto::create($data);

        return redirect()->route('productos.index')
            ->with('success', '✅ Producto registrado correctamente.');
    }

    /**
     * Mostrar detalle de un producto.
     */
    public function show(Producto $producto)
    {
         // Asegurar que el usuario tenga permiso de ver el producto (misma empresa)
        $this->authorizeEmpresa($producto);

        // Cargar las relaciones necesarias
        $producto->load(['empresa', 'categoria', 'tipoUnidad']);

        // Retornar la vista con el producto cargado
        return view('productos.show', compact('producto'));
    }

    /**
     * Mostrar formulario de edición.
     */
    public function edit(Producto $producto)
    {
        $this->authorizeEmpresa($producto);

        $empresaIds = Auth::user()->empresas->pluck('id');

        $categorias = Categoria::whereIn('empresa_id', $empresaIds)
            ->orderBy('nombre')
            ->get();

        // Puedes obtener una empresa específica si el usuario solo tiene una
        $empresa = Auth::user()->empresas->first();

        $tiposUnidad = TipoUnidad::orderBy('codigo')->get();
        return view('productos.edit', compact('producto', 'categorias', 'tiposUnidad', 'empresa'));
    }

    /**
     * Actualizar un producto.
     */
    public function update(UpdateProductoRequest $request, Producto $producto)
    {
        $this->authorizeEmpresa($producto);
        $data = $request->validated();
        $data['es_servicio'] = $request->has('es_servicio');
        $data['activo'] = $request->has('activo');

        $producto->update($data);

        return redirect()->route('productos.index')
            ->with('success', '✅ Producto actualizado correctamente.');
    }

    /**
     * Eliminar un producto (soft delete).
     */
    public function destroy(Producto $producto)
    {
        $this->authorizeEmpresa($producto);

        $producto->delete();

        return redirect()->route('productos.index')
            ->with('success', '🗑️ Producto eliminado correctamente.');
    }

    /**
     * Verificar que el producto pertenezca a una empresa del usuario autenticado.
     */
    private function authorizeEmpresa(Producto $producto)
    {
        $empresaIds = Auth::user()->empresas->pluck('id');

        if (! $empresaIds->contains($producto->empresa_id)) {
            abort(403, 'No tienes permiso para acceder a este producto.');
        }
    }
}
