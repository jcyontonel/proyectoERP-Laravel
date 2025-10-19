<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Empresa;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCategoriaRequest;
use App\Http\Requests\UpdateCategoriaRequest;


class CategoriaController extends Controller
{
    /**
     * Mostrar listado de categorías.
     */
    public function index()
    {
        $empresa = auth()->user()->empresas->first();
        $categorias = Categoria::where('empresa_id', $empresa->id)
            ->orderBy('nombre')
            ->get();

        return view('categorias.index', compact('categorias', 'empresa'));
    }

    /**
     * Mostrar formulario de creación.
     */
    public function create()
    {
        $empresa = auth()->user()->empresas->first();
        return view('categorias.create', compact('empresa'));
    }

    /**
     * Guardar una nueva categoría.
     */
    public function store(StoreCategoriaRequest $request)
    {
        $validated = $request->validated();

        Categoria::create($validated);

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría registrada correctamente.');
    }

     /**
     * Mostrar detalle de una categoría.
     */
    public function show(Categoria $categoria)
    {
        $this->authorizeCategoria($categoria);
        return view('categorias.show', compact('categoria'));
    }

    /**
     * Mostrar formulario de edición.
     */
    public function edit(Categoria $categoria)
    {
        $this->authorizeCategoria($categoria);
        $empresa = auth()->user()->empresas->first();

        return view('categorias.edit', compact('categoria', 'empresa'));
    }

    /**
     * Actualizar una categoría existente.
     */
    public function update(UpdateCategoriaRequest $request, Categoria $categoria)
    {
        $this->authorizeCategoria($categoria);
        $validated = $request->validated();

        $categoria->update($validated);

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría actualizada correctamente.');
    }

    /**
     * Eliminar una categoría.
     */
    public function destroy(Categoria $categoria)
    {
        $this->authorizeCategoria($categoria);
        $categoria->delete();

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría eliminada correctamente.');
    }

    /**
     * Verifica que la categoría pertenezca a la empresa del usuario.
     */
    private function authorizeCategoria(Categoria $categoria)
    {
        if (!auth()->user()->empresas->pluck('id')->contains($categoria->empresa_id)) {
            abort(403, 'No tienes permiso para acceder a esta categoría.');
        }
    }
}