<?php

namespace App\Modulos\Web\Http\Controllers;

use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Web\Models\Tienda;
use App\Modulos\Web\Services\TiendaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Tienda pública (sin sesión). La página es una aplicación Vue que pide TODO en una sola llamada asíncrona
 * (GET /api/tienda/{slug}); el carrito vive en el navegador y el pedido se envía al final.
 */
class PublicaController extends Controller
{
    public function __construct(private TiendaService $tiendas)
    {
    }

    /** Directorio de tiendas publicadas y con su plan web al día */
    public function directorio()
    {
        $tiendas = Tienda::query()->with('empresa')->where('publicada', true)->orderBy('titulo')->get()
            ->filter(fn (Tienda $t) => $this->tiendas->disponible($t->slug))->values();

        return view('web::directorio', ['tiendas' => $tiendas]);
    }

    /** La página (cascarón de la app Vue) */
    public function pagina(string $slug)
    {
        $tienda = $this->tiendas->disponible($slug) ?? abort(404, 'Esta tienda no está disponible.');

        return view('web::publica', ['tienda' => $tienda]);
    }

    /** Todo de una vez: tienda, empresa, categorías, productos publicados, zonas y pagos */
    public function datos(string $slug)
    {
        $tienda = $this->tiendas->disponible($slug) ?? abort(404, 'Esta tienda no está disponible.');

        return response()->json($this->tiendas->datosPublicos($tienda))
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function pedido(Request $request, string $slug)
    {
        $tienda = $this->tiendas->disponible($slug) ?? abort(404, 'Esta tienda no está disponible.');

        $d = $request->validate([
            'cliente'             => ['required', 'array'],
            'cliente.nombre'      => ['required', 'string', 'max:150'],
            'cliente.telefono'    => ['required', 'string', 'max:20', 'regex:/^[0-9 +()-]{6,20}$/'],
            'cliente.correo'      => ['nullable', 'email', 'max:150'],
            'cliente.tipo_doc'    => ['nullable', Rule::in(['1', '6', '4'])],
            'cliente.documento'   => ['nullable', 'string', 'max:15', 'regex:/^[0-9A-Za-z]+$/'],
            'entrega'             => ['required', Rule::in(['envio', 'recojo'])],
            'zona_envio_id'       => ['nullable', 'integer'],
            'direccion'           => ['nullable', 'string', 'max:255'],
            'referencia'          => ['nullable', 'string', 'max:255'],
            'metodo_pago'         => ['required', Rule::in(array_keys(Tienda::METODOS_PAGO))],
            'culqi_token'         => ['nullable', 'string', 'max:100'],
            'notas'               => ['nullable', 'string', 'max:500'],
            'items'               => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id'          => ['required', 'integer'],
            'items.*.cantidad'    => ['required', 'numeric', 'min:0.001', 'max:9999'],
        ], [
            'cliente.nombre.required'   => 'Escribe tu nombre.',
            'cliente.telefono.required' => 'Escribe tu teléfono para coordinar la entrega.',
            'cliente.telefono.regex'    => 'El teléfono no es válido.',
            'items.required'            => 'Tu carrito está vacío.',
        ]);

        $pedido = $this->tiendas->crearPedido($tienda, $d);

        return response()->json([
            'codigo'  => $pedido->codigo(),
            'total'   => $pedido->total,
            'pagado'  => $pedido->pago_estado === 'pagado',
            'mensaje' => "¡Gracias! Recibimos tu pedido {$pedido->codigo()}. Te contactaremos para coordinar la entrega.",
        ], 201);
    }

    public function logo(string $slug)
    {
        $tienda = $this->tiendas->disponible($slug) ?? abort(404);

        return $this->archivo($tienda->empresa->logo_path);
    }

    /** Imagen de un producto publicado */
    public function imagen(string $slug, int $producto)
    {
        $tienda = $this->tiendas->disponible($slug) ?? abort(404);
        $p = Producto::query()->where('empresa_id', $tienda->empresa_id)->where('publicar_web', true)->findOrFail($producto);

        return $this->archivo($p->imagen_path);
    }

    private function archivo(?string $ruta)
    {
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);

        return response()->file(Storage::disk('local')->path($ruta), ['Cache-Control' => 'public, max-age=86400']);
    }
}
