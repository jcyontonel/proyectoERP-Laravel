<?php

namespace App\Modulos\Web\Http\Controllers;

use App\Modulos\Catalogo\Models\Categoria;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Página web → Productos publicados: qué productos del catálogo se ven en la web (nunca todos por defecto),
 * cuáles se destacan y su foto.
 */
class ProductoWebController extends Controller
{
    use ResuelveEmpresa;

    public function index(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $f = $request->validate([
            'q'            => ['nullable', 'string', 'max:100'],
            'categoria_id' => ['nullable', 'integer'],
            'ver'          => ['nullable', 'in:todos,publicados,sin_publicar'],
        ]);

        $base = Producto::query()->where('empresa_id', $empresaId)->where('activo', true);

        return view('web.productos', [
            'productos'  => (clone $base)->with('categoria')
                ->when(filled($f['q'] ?? null), fn ($q) => $q->where(fn ($w) => $w->whereLike('nombre', '%' . $f['q'] . '%')->orWhereLike('codigo', '%' . $f['q'] . '%')))
                ->when(filled($f['categoria_id'] ?? null), fn ($q) => $q->where('categoria_id', $f['categoria_id']))
                ->when(($f['ver'] ?? null) === 'publicados', fn ($q) => $q->where('publicar_web', true))
                ->when(($f['ver'] ?? null) === 'sin_publicar', fn ($q) => $q->where('publicar_web', false))
                ->orderByDesc('publicar_web')->orderBy('nombre')->paginate(30)->withQueryString(),
            'categorias' => Categoria::query()->where('empresa_id', $empresaId)->orderBy('nombre')->get(['id', 'nombre']),
            'publicados' => (clone $base)->where('publicar_web', true)->count(),
        ]);
    }

    /** Publicar / destacar (y foto nueva o quitarla) */
    public function update(Request $request, Producto $producto)
    {
        abort_unless($producto->empresa_id === $this->empresaId($request), 404);
        $d = $request->validate([
            'publicar_web'  => ['sometimes', 'boolean'],
            'destacado_web' => ['sometimes', 'boolean'],
            'imagen'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'quitar_imagen' => ['sometimes', 'boolean'],
        ], ['imagen.max' => 'La foto no debe pesar más de 2 MB.', 'imagen.image' => 'El archivo debe ser una imagen (JPG, PNG o WebP).']);

        if ($request->hasFile('imagen')) {
            $this->borrarImagen($producto);
            $producto->imagen_path = $request->file('imagen')->store("tiendas/{$producto->empresa_id}/productos", 'local');
        } elseif ($request->boolean('quitar_imagen')) {
            $this->borrarImagen($producto);
            $producto->imagen_path = null;
        }
        if (array_key_exists('publicar_web', $d)) {
            $producto->publicar_web = (bool) $d['publicar_web'];
        }
        if (array_key_exists('destacado_web', $d)) {
            $producto->destacado_web = (bool) $d['destacado_web'];
        }
        $producto->save();

        return back()->with('status', "«{$producto->nombre}» " . ($producto->publicar_web ? 'se muestra en tu página web.' : 'no se muestra en tu página web.'));
    }

    /** Publica u oculta varios a la vez */
    public function masivo(Request $request)
    {
        $d = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'], 'publicar' => ['required', 'boolean']]);
        $n = Producto::query()->where('empresa_id', $this->empresaId($request))->where('activo', true)->whereIn('id', $d['ids'])
            ->update(['publicar_web' => (bool) $d['publicar'], 'updated_at' => now()]);

        return back()->with('status', "{$n} " . ($n === 1 ? 'producto' : 'productos') . ($d['publicar'] ? ' publicados.' : ' ocultos de la web.'));
    }

    private function borrarImagen(Producto $p): void
    {
        if ($p->imagen_path && Storage::disk('local')->exists($p->imagen_path)) {
            Storage::disk('local')->delete($p->imagen_path);
        }
    }
}
