<?php

namespace App\Modulos\Erp\Http\Controllers;

use App\Modulos\Nucleo\Http\Controllers\Controller;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Erp\Http\Requests\ClienteRequest;
use App\Modulos\Erp\Http\Requests\FiltrarClientesRequest;
use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Erp\Models\Pago;
use App\Modulos\Nucleo\Models\TipoDocumento;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Erp\Services\ClienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ClienteController extends Controller
{
    use ResuelveEmpresa;

    /** Listado con filtros (q, tipo_documento_id, estado) y resumen de la cartera */
    public function index(FiltrarClientesRequest $request)
    {
        $empresaId = $this->empresaId($request);

        $deEstaEmpresa = fn ($q) => $q->where('empresa_id', $empresaId)->where('estado', Venta::COMPLETADA);

        $clientes = Cliente::query()
            ->deEmpresa($empresaId)
            ->with('tipoDocumento')
            ->withCount(['ventas as ventas_count' => $deEstaEmpresa])
            ->withSum(['ventas as vendido' => $deEstaEmpresa], 'total')
            ->withMax(['ventas as ultima_compra' => $deEstaEmpresa], 'fecha_emision')
            ->filtrar($request->validated())
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('clientes.index', [
            'clientes'       => $clientes,
            'tiposDocumento' => $this->tiposDocumento(),
            'resumen'        => $this->resumenCartera($empresaId),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Cliente::class);

        return view('clientes.form', [
            'cliente'        => new Cliente(['activo' => true]),
            'tiposDocumento' => $this->tiposDocumento(),
        ]);
    }

    /** Alta normal (redirige) o alta rápida desde Nueva venta (responde JSON) */
    public function store(ClienteRequest $request, ClienteService $clientes)
    {
        $cliente = $clientes->registrar($request->validated(), $this->empresaId($request));

        if ($request->expectsJson()) {
            return response()->json($this->payload($cliente), 201);
        }

        return redirect()->route('clientes.show', $cliente)->with('status', "Cliente «{$cliente->nombre_completo}» creado.");
    }

    /** Ficha: datos, resumen de compras, saldo al crédito, productos favoritos y últimas ventas */
    public function show(Request $request, Cliente $cliente)
    {
        Gate::authorize('view', $cliente);
        $empresaId = $this->empresaId($request);

        $cliente->load('tipoDocumento');

        $completadas = Venta::query()->deEmpresa($empresaId)->completadas()->where('cliente_id', $cliente->id);

        $agregado = (clone $completadas)->selectRaw('COUNT(*) AS ventas, COALESCE(SUM(total), 0) AS vendido, MAX(fecha_emision) AS ultima, MIN(fecha_emision) AS primera')->first();

        $saldo = (float) (clone $completadas)->where('condicion_pago', 'credito')
            ->selectRaw('COALESCE(SUM(ventas.total - (select COALESCE(sum(p.monto), 0) from pagos p where p.venta_id = ventas.id and p.estado = ? and p.deleted_at is null)), 0) AS saldo', [Pago::REGISTRADO])
            ->value('saldo');

        $favoritos = DB::table('detalle_ventas as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.venta_id')
            ->where('v.empresa_id', $empresaId)->where('v.cliente_id', $cliente->id)
            ->where('v.estado', Venta::COMPLETADA)
            ->whereNull('v.deleted_at')->whereNull('dv.deleted_at')
            ->groupBy('dv.producto_id', 'dv.descripcion')
            ->selectRaw('dv.producto_id, dv.descripcion, SUM(dv.cantidad) AS cantidad, SUM(dv.total) AS importe, COUNT(DISTINCT v.id) AS veces')
            ->orderByDesc('importe')
            ->limit(5)
            ->get();

        return view('clientes.show', [
            'cliente'   => $cliente,
            'resumen'   => [
                'ventas'  => (int) $agregado->ventas,
                'vendido' => (float) $agregado->vendido,
                'ticket'  => $agregado->ventas > 0 ? round($agregado->vendido / $agregado->ventas, 2) : 0,
                'ultima'  => $agregado->ultima ? Carbon::parse($agregado->ultima) : null,
                'primera' => $agregado->primera ? Carbon::parse($agregado->primera) : null,
                'saldo'   => $saldo,
            ],
            'favoritos' => $favoritos,
            'ventas'    => Venta::query()->deEmpresa($empresaId)->where('cliente_id', $cliente->id)
                ->orderByDesc('fecha_emision')->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    /**
     * Nueva venta: busca un DNI/RUC en la empresa y, si no es cliente suyo, lo registra con RENIEC/SUNAT (JSON).
     */
    public function documento(Request $request, string $numero, ClienteService $clientes)
    {
        $empresaId = $this->empresaId($request);

        $yaEsCliente = Cliente::query()->deEmpresa($empresaId)->where('numero_documento', $numero)->exists();
        if (! $yaEsCliente) {
            Gate::authorize('create', Cliente::class);
        }

        [$cliente, $origen] = $clientes->obtenerPorDocumento($numero, $empresaId);

        return response()->json($this->payload($cliente) + [
            'origen'    => $origen,
            'direccion' => $cliente->direccion,
            'mensaje'   => match ($origen) {
                ClienteService::ORIGEN_EXTERNO => 'Cliente registrado automáticamente con los datos de ' . (strlen($numero) === 8 ? 'RENIEC.' : 'SUNAT.'),
                default                        => null,
            },
        ], $origen === ClienteService::ORIGEN_EMPRESA ? 200 : 201);
    }

    /** Formulario de cliente: datos de RENIEC/SUNAT para autocompletar (no registra nada) */
    public function consulta(Request $request, string $numero, ClienteService $clientes)
    {
        return response()->json($clientes->consultarDocumento($numero, $this->empresaId($request)));
    }

    public function edit(Request $request, Cliente $cliente)
    {
        Gate::authorize('update', $cliente);

        return view('clientes.form', [
            'cliente'        => $cliente,
            'tiposDocumento' => $this->tiposDocumento(),
        ]);
    }

    public function update(ClienteRequest $request, Cliente $cliente, ClienteService $clientes)
    {
        $clientes->actualizar($cliente, $request->validated());

        return redirect()->route('clientes.show', $cliente)->with('status', "Cliente «{$cliente->nombre_completo}» actualizado.");
    }

    public function destroy(Request $request, Cliente $cliente, ClienteService $clientes)
    {
        Gate::authorize('delete', $cliente);

        try {
            $clientes->eliminar($cliente);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('clientes.index')->with('status', "Cliente «{$cliente->nombre_completo}» eliminado.");
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function tiposDocumento()
    {
        return TipoDocumento::orderBy('codigo')->get(['id', 'codigo', 'abreviatura', 'descripcion']);
    }

    private function resumenCartera(int $empresaId): array
    {
        $base = Cliente::query()->deEmpresa($empresaId);

        return [
            'total'     => (clone $base)->count(),
            'activos'   => (clone $base)->activo()->count(),
            'empresas'  => (clone $base)->whereHas('tipoDocumento', fn ($q) => $q->where('codigo', '6'))->count(),
            'compraron' => Venta::query()->deEmpresa($empresaId)->completadas()
                ->whereNotNull('cliente_id')
                ->where('fecha_emision', '>=', now()->startOfMonth())
                ->distinct()->count('cliente_id'),
        ];
    }

    /** Forma que espera el buscador de Nueva venta */
    private function payload(Cliente $c): array
    {
        $c->loadMissing('tipoDocumento');

        return [
            'id'        => $c->id,
            'nombre'    => $c->nombre_completo,
            'doc'       => (string) $c->numero_documento,
            'tipo_doc'  => $c->tipo_doc_etiqueta,
            'es_ruc'    => $c->es_ruc,
            'email'     => $c->correo,
            'telefono'  => $c->telefono,
        ];
    }
}
