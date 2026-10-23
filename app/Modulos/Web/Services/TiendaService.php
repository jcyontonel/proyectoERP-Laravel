<?php

namespace App\Modulos\Web\Services;

use App\Modulos\Catalogo\Models\Categoria;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Plataforma\Models\ProductoPlataforma;
use App\Modulos\Plataforma\Services\SuscripcionService;
use App\Modulos\Web\Models\PedidoWeb;
use App\Modulos\Web\Models\Tienda;
use App\Modulos\Web\Models\ZonaEnvio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * La tienda pública de una empresa: si está disponible, lo que se muestra (todo en una sola respuesta) y los
 * pedidos. Los precios y el stock salen siempre de la base de datos, nunca del navegador.
 */
class TiendaService
{
    public const CATALOGO = 'catalogo';   // muestrario: productos y contacto por WhatsApp
    public const TIENDA = 'tienda';       // además carrito, pedidos, pago y envíos

    public function __construct(private SuscripcionService $suscripciones, private CulqiService $culqi)
    {
    }

    /** Publicada, de una empresa activa y con su suscripción web al día; si no, null (la página responde 404) */
    public function disponible(string $slug): ?Tienda
    {
        $tienda = Tienda::with('empresa')->where('slug', mb_strtolower($slug))->where('publicada', true)->first();
        if (! $tienda || ! $tienda->empresa?->activo) {
            return null;
        }
        $s = $this->suscripciones->vigente($tienda->empresa_id, ProductoPlataforma::WEB);

        return $s && $s->permiteOperar() ? $tienda : null;
    }

    /** catalogo | tienda, según el plan web de la empresa */
    public function modalidad(int $empresaId): string
    {
        $modulos = $this->suscripciones->modulos($empresaId, ProductoPlataforma::WEB) ?? [];

        return in_array('web_tienda', $modulos, true) ? self::TIENDA : self::CATALOGO;
    }

    /** Todo lo que necesita la página en una sola respuesta: tienda, empresa, categorías, productos, zonas y pagos */
    public function datosPublicos(Tienda $tienda): array
    {
        $empresa = $tienda->empresa;
        $modalidad = $this->modalidad($empresa->id);
        $productos = $this->publicados($empresa->id);
        $culqi = $tienda->culqi();

        return [
            'tienda' => [
                'slug' => $tienda->slug, 'nombre' => $tienda->nombre(), 'lema' => $tienda->lema, 'descripcion' => $tienda->descripcion,
                'color' => $tienda->color, 'whatsapp' => $tienda->whatsapp, 'correo' => $tienda->correo ?: $empresa->email,
                'modalidad' => $modalidad, 'mostrar_stock' => $tienda->mostrar_stock,
                'logo' => $empresa->logo_path && Storage::disk('local')->exists($empresa->logo_path) ? route('tienda.logo', $tienda->slug) . '?v=' . $empresa->updated_at?->timestamp : null,
            ],
            'empresa' => [
                'razon_social' => $empresa->razon_social, 'ruc' => $empresa->ruc, 'telefono' => $empresa->telefono,
                'direccion' => collect([$empresa->direccion, $empresa->distrito, $empresa->provincia])->filter()->join(', '),
            ],
            'categorias' => Categoria::query()->where('empresa_id', $empresa->id)->whereIn('id', $productos->pluck('categoria_id')->filter()->unique())
                ->orderBy('nombre')->get(['id', 'nombre'])->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values(),
            'productos' => $productos->map(fn (Producto $p) => $this->producto($p, $tienda))->values(),
            'compra' => $modalidad === self::TIENDA ? [
                'pedido_minimo' => $tienda->pedido_minimo,
                'recojo' => $tienda->recojo_en_tienda ? ['direccion' => $tienda->direccion_recojo ?: $empresa->direccion] : null,
                'zonas' => ZonaEnvio::query()->deEmpresa($empresa->id)->activo()->orderBy('orden')->orderBy('nombre')
                    ->get(['id', 'nombre', 'costo', 'tiempo_entrega'])->toArray(),
                'metodos_pago' => collect($tienda->metodosDisponibles())->map(fn ($m) => ['clave' => $m, 'nombre' => Tienda::METODOS_PAGO[$m]])->values(),
                'instrucciones_pago' => $tienda->instrucciones_pago,
                'culqi' => $culqi['publica'] ? ['llave_publica' => $culqi['publica'], 'modo' => $culqi['modo']] : null,
            ] : null,
        ];
    }

    /**
     * Registra un pedido (plan Tienda). Con tarjeta, primero se cobra en Culqi: si el cobro falla no hay pedido.
     *
     * @param  array{cliente: array, entrega: string, zona_envio_id?: ?int, direccion?: ?string, referencia?: ?string, metodo_pago: string, items: array, notas?: ?string, culqi_token?: ?string}  $d
     */
    public function crearPedido(Tienda $tienda, array $d): PedidoWeb
    {
        $empresa = $tienda->empresa;
        if ($this->modalidad($empresa->id) !== self::TIENDA) {
            throw ValidationException::withMessages(['pedido' => 'Esta tienda solo muestra su catálogo: escríbenos por WhatsApp para pedir.']);
        }
        if (! in_array($d['metodo_pago'], $tienda->metodosDisponibles(), true)) {
            throw ValidationException::withMessages(['metodo_pago' => 'Ese método de pago no está disponible.']);
        }

        // Productos y precios de la base de datos (solo los publicados)
        $cantidades = collect($d['items'])->groupBy('id')->map(fn ($g) => round($g->sum(fn ($i) => (float) $i['cantidad']), 3));
        $productos = $this->publicados($empresa->id)->whereIn('id', $cantidades->keys())->keyBy('id');
        $lineas = [];
        foreach ($cantidades as $id => $cantidad) {
            $p = $productos->get($id) ?? throw ValidationException::withMessages(['items' => 'Un producto del carrito ya no está disponible. Actualiza la página.']);
            $disponible = $this->stock($p);
            if ($disponible !== null && $cantidad > $disponible) {
                throw ValidationException::withMessages(['items' => "Solo quedan {$this->numero($disponible)} de «{$p->nombre}»."]);
            }
            $precio = $this->precioFinal($p);
            $lineas[] = ['producto_id' => $p->id, 'descripcion' => $p->nombre, 'cantidad' => $cantidad, 'precio_unitario' => $precio, 'total' => round($precio * $cantidad, 2)];
        }
        $subtotal = round(array_sum(array_column($lineas, 'total')), 2);
        if ($subtotal < $tienda->pedido_minimo) {
            throw ValidationException::withMessages(['items' => 'El pedido mínimo es S/ ' . number_format($tienda->pedido_minimo, 2) . '.']);
        }

        $zona = null;
        if ($d['entrega'] === 'envio') {
            $zona = ZonaEnvio::query()->deEmpresa($empresa->id)->activo()->find($d['zona_envio_id'] ?? 0)
                ?? throw ValidationException::withMessages(['zona_envio_id' => 'Elige la zona de envío.']);
            if (blank($d['direccion'] ?? null)) {
                throw ValidationException::withMessages(['direccion' => 'Indica la dirección de entrega.']);
            }
        } elseif (! $tienda->recojo_en_tienda) {
            throw ValidationException::withMessages(['entrega' => 'Esta tienda solo hace envíos.']);
        }
        $total = round($subtotal + ($zona?->costo ?? 0), 2);

        // Tarjeta: se cobra antes de guardar (si Culqi rechaza, no queda pedido)
        $cargo = null;
        if ($d['metodo_pago'] === 'tarjeta') {
            if (blank($d['culqi_token'] ?? null) || blank($d['cliente']['correo'] ?? null)) {
                throw ValidationException::withMessages(['pago' => 'Completa los datos de la tarjeta y tu correo.']);
            }
            $cargo = $this->culqi->cobrar($tienda, $d['culqi_token'], $total, $d['cliente']['correo'], "Pedido en {$tienda->nombre()}", ['tienda' => $tienda->slug]);
        }

        return DB::transaction(function () use ($empresa, $d, $lineas, $subtotal, $zona, $total, $cargo) {
            $numero = (int) PedidoWeb::query()->where('empresa_id', $empresa->id)->lockForUpdate()->max('numero') + 1;
            $pedido = PedidoWeb::create([
                'empresa_id' => $empresa->id, 'numero' => $numero, 'estado' => PedidoWeb::NUEVO,
                'cliente_nombre' => trim($d['cliente']['nombre']), 'cliente_tipo_doc' => $d['cliente']['tipo_doc'] ?? null,
                'cliente_documento' => $d['cliente']['documento'] ?? null, 'cliente_telefono' => $d['cliente']['telefono'],
                'cliente_correo' => $d['cliente']['correo'] ?? null, 'entrega' => $d['entrega'], 'zona_envio_id' => $zona?->id,
                'direccion' => $zona ? $d['direccion'] : null, 'referencia' => $zona ? ($d['referencia'] ?? null) : null,
                'subtotal' => $subtotal, 'costo_envio' => $zona?->costo ?? 0, 'total' => $total, 'metodo_pago' => $d['metodo_pago'],
                'pago_estado' => $cargo ? 'pagado' : 'pendiente', 'culqi_cargo_id' => $cargo, 'notas' => $d['notas'] ?? null,
            ]);
            $pedido->detalles()->createMany($lineas);

            return $pedido;
        });
    }

    /** Productos publicados, activos y con precio, con su categoría y stock */
    public function publicados(int $empresaId): Collection
    {
        return Producto::query()->where('empresa_id', $empresaId)->where('publicar_web', true)->where('activo', true)
            ->where('precio_unitario', '>', 0)->with('stocks', 'empresa')
            ->orderByDesc('destacado_web')->orderBy('nombre')->get();
    }

    /** Precio que paga el cliente (con IGV) */
    public function precioFinal(Producto $p): float
    {
        $empresa = $p->empresa;

        return $empresa->precios_incluyen_igv ? (float) $p->precio_unitario : round($p->precio_unitario * (1 + $empresa->igv / 100), 2);
    }

    /** Unidades disponibles (null = sin control: servicio o empresa que no controla stock) */
    public function stock(Producto $p): ?float
    {
        if ($p->es_servicio || ! $p->empresa->controlar_stock) {
            return null;
        }

        return max(0.0, (float) $p->stocks->sum('stock'));
    }

    private function producto(Producto $p, Tienda $tienda): array
    {
        $stock = $this->stock($p);

        return [
            'id' => $p->id, 'nombre' => $p->nombre, 'descripcion' => $p->descripcion, 'categoria_id' => $p->categoria_id,
            'precio' => $this->precioFinal($p), 'destacado' => $p->destacado_web,
            'imagen' => $p->imagen_path ? route('tienda.imagen', [$tienda->slug, $p->id]) . '?v=' . $p->updated_at?->timestamp : null,
            'agotado' => $stock !== null && $stock <= 0,
            'stock' => $tienda->mostrar_stock && $stock !== null ? $stock : null,
            'maximo' => $stock,   // el carrito no deja pedir más de lo disponible
        ];
    }

    private function numero(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    }
}
