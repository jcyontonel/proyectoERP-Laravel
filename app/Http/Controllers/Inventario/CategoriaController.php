<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    use ResuelveEmpresa;

    public function index(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $q = trim((string) $request->query('q'));

        $categorias = Categoria::query()->deEmpresa($empresaId)
            ->withCount(['productos', 'productos as activos_count' => fn ($p) => $p->where('activo', true)])
            ->when($q !== '', fn ($w) => $w->whereLike('nombre', "%{$q}%"))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('inventario.categorias.index', [
            'categorias'   => $categorias,
            'sinCategoria' => \App\Models\Producto::query()->deEmpresa($empresaId)->whereNull('categoria_id')->count(),
        ]);
    }

    public function create()
    {
        return view('inventario.categorias.form', ['categoria' => new Categoria()]);
    }

    /** Alta normal o rápida desde el formulario de producto (JSON) */
    public function store(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $categoria = Categoria::create($this->validar($request, $empresaId) + ['empresa_id' => $empresaId]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $categoria->id, 'nombre' => $categoria->nombre], 201);
        }

        return redirect()->route('categorias.index')->with('status', "Categoría «{$categoria->nombre}» creada.");
    }

    public function edit(Categoria $categoria)
    {
        return view('inventario.categorias.form', ['categoria' => $categoria]);
    }

    public function update(Request $request, Categoria $categoria)
    {
        $categoria->update($this->validar($request, $categoria->empresa_id, $categoria->id));

        return redirect()->route('categorias.index')->with('status', "Categoría «{$categoria->nombre}» actualizada.");
    }

    /** Solo si no tiene productos (de lo contrario quedarían sin categoría por sorpresa) */
    public function destroy(Categoria $categoria)
    {
        if ($categoria->productos()->exists()) {
            return back()->with('error', "«{$categoria->nombre}» tiene productos: muévelos a otra categoría antes de eliminarla.");
        }

        $categoria->delete();

        return redirect()->route('categorias.index')->with('status', "Categoría «{$categoria->nombre}» eliminada.");
    }

    private function validar(Request $request, int $empresaId, ?int $ignorar = null): array
    {
        // El índice único (empresa + nombre) incluye a las categorías eliminadas
        return $request->validate([
            'nombre'      => ['required', 'string', 'max:255', Rule::unique('categorias', 'nombre')->where('empresa_id', $empresaId)->ignore($ignorar)],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ], ['nombre.unique' => 'Ya existe una categoría con ese nombre (puede estar eliminada).']);
    }
}
