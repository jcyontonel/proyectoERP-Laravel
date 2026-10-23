<?php

namespace App\Modulos\Erp\Http\Controllers;

use App\Modulos\Nucleo\Http\Controllers\Controller;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Erp\Http\Requests\FiltrarVentasRequest;
use App\Modulos\Erp\Http\Requests\StoreVentaRequest;
use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Catalogo\Models\UnidadConversion;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Erp\Services\CajaService;
use App\Modulos\Facturacion\Services\FacturacionElectronicaService;
use App\Modulos\Erp\Services\VentaExportService;
use App\Modulos\Erp\Services\VentaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    use ResuelveEmpresa;

    /** Listado con filtros (q, tipo, estado, desde, hasta) y paginación */
    public function index(FiltrarVentasRequest $request)
    {
        $metodos = config('ventas.metodos_pago');

        $ventas = Venta::query()
            ->deEmpresa($this->empresaId($request))
            ->with('cliente.tipoDocumento')
            ->filtrar($request->validated())
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            // La vista recibe objetos listos para pintar
            ->through(fn (Venta $v) => (object) [
                'id'             => $v->id,
                'numero'         => $v->numero_completo,
                'fecha'          => $v->fecha_emision,
                'cliente_nombre' => $v->cliente?->nombre_completo ?: 'Público general',
                'cliente_doc'    => $v->cliente?->documento_etiqueta ?? '—',
                'tipo'           => $v->tipo_etiqueta,
                'metodo'         => $v->metodo_pago,
                'metodo_label'   => $metodos[$v->metodo_pago] ?? Str::headline((string) $v->metodo_pago),
                'total'          => $v->total,
                'estado'         => $v->estado,
            ]);

        return view('ventas.index', compact('ventas'));
    }

    /** Formulario de nueva venta */
    public function create(Request $request)
    {
        return view('ventas.create', $this->datosFormulario($this->empresaId($request)));
    }

    /** Registra la venta o guarda el borrador */
    public function store(StoreVentaRequest $request, VentaService $ventas)
    {
        $venta = $ventas->crear(
            $request->validated(),
            $request->user()->id,
            $this->empresaId($request),
        );

        $mensaje = $venta->esBorrador()
            ? "Borrador {$venta->numero_completo} guardado. No afectó stock ni correlativos."
            : "Venta {$venta->numero_completo} registrada correctamente."
                . FacturacionElectronicaService::aviso(app(FacturacionElectronicaService::class)->emitirSeguro($venta));

        return redirect()->route('ventas.show', $venta)->with('status', $mensaje)
            // Preferencias → imprimir el ticket apenas se registra
            ->with('imprimir_ticket', ! $venta->esBorrador() && $request->user()->preferencias()->imprimir_al_vender);
    }

    /** Detalle de una venta */
    public function show(Request $request, Venta $venta)
    {
        $this->propia($request, $venta);

        $venta->load(['empresa', 'almacen', 'usuario', 'cliente.tipoDocumento', 'detalles.producto', 'detalles.tipoUnidad', 'pagos.usuario:id,name', 'notas', 'sunat']);

        return view('ventas.show', compact('venta'));
    }

    /** Edición de un borrador (mismo formulario de nueva venta, con los datos cargados) */
    public function edit(Request $request, Venta $venta)
    {
        $this->propia($request, $venta);

        if (! $venta->esEditable()) {
            return redirect()->route('ventas.show', $venta)->with('error', 'Solo se pueden editar los borradores.');
        }

        $venta->load('detalles');

        return view('ventas.create', $this->datosFormulario($venta->empresa_id) + ['venta' => $venta]);
    }

    /** Guarda los cambios de un borrador (o lo registra) */
    public function update(StoreVentaRequest $request, Venta $venta, VentaService $ventas)
    {
        $empresaId = $this->empresaId($request);
        $this->propia($request, $venta);

        if (! $venta->esEditable()) {
            return redirect()->route('ventas.show', $venta)->with('error', 'Solo se pueden editar los borradores.');
        }

        $venta = $ventas->actualizar($venta, $request->validated(), $request->user()->id, $empresaId);

        $mensaje = $venta->esBorrador()
            ? "Borrador {$venta->numero_completo} actualizado."
            : "Venta {$venta->numero_completo} registrada correctamente."
                . FacturacionElectronicaService::aviso(app(FacturacionElectronicaService::class)->emitirSeguro($venta));

        return redirect()->route('ventas.show', $venta)->with('status', $mensaje);
    }

    /** Ticket imprimible (80 mm) */
    public function ticket(Request $request, Venta $venta)
    {
        $this->propia($request, $venta);

        $venta->load(['empresa', 'usuario', 'cliente.tipoDocumento', 'detalles.tipoUnidad']);

        return view('ventas.ticket', compact('venta'));
    }

    /** Exporta con los mismos filtros del listado: formato=xlsx (CSV para Excel) | pdf (vista para imprimir) */
    public function export(FiltrarVentasRequest $request, VentaExportService $exportador)
    {
        $empresaId = $this->empresaId($request);
        $filtros   = $request->validated();

        $query = Venta::query()
            ->deEmpresa($empresaId)
            ->filtrar($filtros)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        if (($filtros['formato'] ?? 'xlsx') === 'pdf') {
            $ventas = $query->with(['cliente.tipoDocumento', 'usuario'])
                ->limit((int) config('ventas.export_max', 10000))
                ->get();

            return view('ventas.export', [
                'ventas'  => $ventas,
                'empresa' => Empresa::find($empresaId),
                'filtros' => $filtros,
            ]);
        }

        return $exportador->csv($query);
    }

    /** Anula una venta completada y repone el stock */
    public function anular(Request $request, Venta $venta, VentaService $ventas)
    {
        $this->propia($request, $venta);

        try {
            $ventas->anular($venta, $request->user()->id);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        // En SUNAT: comunicación de baja (facturas) o resumen diario (boletas); el ticket se consulta solo
        $baja = '';
        try {
            $reg = app(FacturacionElectronicaService::class)->anular($venta);
            $baja = match (true) {
                ! $reg => '',
                in_array($reg->baja_estado, ['enviado', 'aceptado'], true) => ' Se pidió la baja a SUNAT.',
                $reg->baja_estado === 'pendiente' => ' La baja se enviará a SUNAT en segundo plano (o cuando vuelva la conexión).',
                in_array($reg->baja_estado, ['rechazado', 'error'], true) => ' SUNAT no aceptó la baja: ' . $reg->baja->mensaje(),
                $reg->fueAceptado() => ' ' . $reg->ultimo_error,
                default => ' Aún no estaba aceptado en SUNAT: la baja se pedirá al aceptarse.',
            };
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('status', "El comprobante {$venta->numero_completo} fue anulado y el stock repuesto.{$baja}");
    }

    /* ───────────────────────── Privado ───────────────────────── */

    /** La venta debe pertenecer a la empresa actual del usuario */
    private function propia(Request $request, Venta $venta): void
    {
        abort_unless($venta->empresa_id === $this->empresaId($request), 404);
    }

    /** Catálogos del formulario de venta (nueva o edición de borrador) */
    private function datosFormulario(int $empresaId): array
    {
        $empresa = Empresa::findOrFail($empresaId);

        // Comprobantes disponibles (tipo + serie). numero = último emitido → el siguiente es +1
        $correlativos = $empresa->correlativos()->activo()->where('modulo', Correlativo::MODULO_VENTA)
            ->orderBy('tipo')->orderBy('serie')
            ->get()
            ->map(fn (Correlativo $c) => [
                'id'           => $c->id,
                'tipo'         => $c->tipo,
                'destipo'      => $c->destipo,
                'serie'        => $c->serie,
                'numero'       => $c->numero,
                'siguiente'    => $c->siguiente(),
                'requiere_ruc' => $c->requiereRuc(),
            ])->values();

        $almacenes = $empresa->almacenes()->activo()
            ->orderByDesc('es_principal')->orderBy('nombre')
            ->get(['id', 'nombre', 'es_principal']);

        // Clientes de la empresa con su tipo de documento
        $clientes = $empresa->clientes()
            ->where('clientes.activo', true)
            ->with('tipoDocumento:id,codigo,descripcion')
            ->orderBy('clientes.nombre')
            ->get()
            ->map(fn (Cliente $c) => [
                'id'                    => $c->id,
                'nombre'                => $c->nombre_completo,
                'numero_documento'      => $c->numero_documento,
                'email'                 => $c->correo,
                'telefono'              => $c->telefono,
                'tipo_documento_codigo' => $c->tipoDocumento?->codigo,
                'tipo_documento'        => $c->tipoDocumento?->descripcion,
            ])->values();

        // Productos con el stock de cada almacén (los servicios no llevan stock)
        $stocks = AlmacenProducto::query()
            ->whereIn('almacen_id', $almacenes->pluck('id'))
            ->get(['almacen_id', 'producto_id', 'stock'])
            ->groupBy('producto_id');

        // Lo que se puede obtener partiendo el stock de origen (ej. kilos de los sacos): [destino][almacen] => cantidad
        $porFraccionar = [];
        if (config('inventario.fraccionar_al_vender')) {
            $reglas = UnidadConversion::query()->where('empresa_id', $empresaId)->fraccionamientos()->activo()->get(['producto_id', 'producto_destino_id', 'factor']);
            foreach ($reglas as $r) {
                foreach ($stocks[$r->producto_id] ?? [] as $s) {
                    $porFraccionar[$r->producto_destino_id][$s->almacen_id] = round(($porFraccionar[$r->producto_destino_id][$s->almacen_id] ?? 0) + floor((float) $s->stock) * $r->factor, 4);
                }
            }
        }

        $productos = $empresa->productos()->activo()
            ->with('tipoUnidad:id,codigo,descripcion')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'precio_unitario', 'es_servicio', 'tipo_unidad_id'])
            ->map(fn (Producto $p) => [
                'id'              => $p->id,
                'codigo'          => $p->codigo,
                'nombre'          => $p->nombre,
                'precio_unitario' => $p->precio_unitario,
                'es_servicio'     => $p->es_servicio,
                'unidad'          => $p->tipoUnidad?->descripcion,
                'decimal'         => $p->seVendeFraccionado(),   // kilos, litros…: admite 0.5, 2.25
                'stocks'          => ($stocks[$p->id] ?? collect())
                    ->mapWithKeys(fn ($s) => [$s->almacen_id => $s->stock])->all(),
                'fraccionable'    => $porFraccionar[$p->id] ?? [],
            ])->values();

        return [
            'empresa'      => $empresa,
            'correlativos' => $correlativos,
            'almacenes'    => $almacenes,
            'clientes'     => $clientes,
            'productos'    => $productos,
            'metodosPago'  => config('ventas.metodos_pago'),
            // Reglas de la empresa (tabla empresas)
            'igv'                => (float) $empresa->igv,
            'preciosIncluyenIgv' => (bool) $empresa->precios_incluyen_igv,
            'controlarStock'     => (bool) $empresa->controlar_stock,
            // Turno de caja del vendedor: sin él solo se pueden guardar borradores (config/caja.php)
            'cajaAbierta'        => app(CajaService::class)->sesionAbierta($empresaId, (int) auth()->id()),
            'exigeCaja'          => app(CajaService::class)->exigeTurno($empresaId),
        ];
    }
}
