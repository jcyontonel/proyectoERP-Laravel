<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\FiltrarInventarioRequest;
use App\Http\Requests\Inventario\TransferenciaRequest;
use App\Models\Almacen;
use App\Models\TransferenciaAlmacen;
use App\Services\TransferenciaService;
use App\Support\CatalogoInventario;
use Illuminate\Http\Request;

class TransferenciaController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private TransferenciaService $transferencias)
    {
    }

    public function index(FiltrarInventarioRequest $request)
    {
        $empresaId = $this->empresaId($request);

        return view('inventario.transferencias.index', [
            'transferencias' => TransferenciaAlmacen::query()->deEmpresa($empresaId)
                ->with(['origen:id,nombre', 'destino:id,nombre', 'usuario:id,name,apellido'])
                ->withCount('detalles')
                ->filtrar($request->validated())
                ->latest('fecha_movimiento')->latest('id')
                ->paginate(15)->withQueryString(),
            'almacenes'  => Almacen::query()->deEmpresa($empresaId)->orderBy('nombre')->get(['id', 'nombre']),
            'enTransito' => TransferenciaAlmacen::query()->deEmpresa($empresaId)->where('estado', TransferenciaAlmacen::EN_TRANSITO)->count(),
        ]);
    }

    public function create(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $almacenes = $this->almacenes($empresaId);

        if ($almacenes->count() < 2) {
            return redirect()->route('transferencias.index')->with('error', 'Necesitas al menos dos almacenes activos para transferir mercadería.');
        }

        return view('inventario.transferencias.form', [
            'transferencia' => new TransferenciaAlmacen(['fecha_movimiento' => now(), 'almacen_origen_id' => $almacenes->first()->id]),
            'almacenes'     => $almacenes,
            'productos'     => CatalogoInventario::productos($empresaId),
        ]);
    }

    public function store(TransferenciaRequest $request)
    {
        $t = $this->transferencias->guardar($request->validated(), $request->user(), $this->empresaId($request));

        return redirect()->route('transferencias.show', $t)->with('status', $this->mensaje($t));
    }

    public function show(TransferenciaAlmacen $transferencia)
    {
        $transferencia->load(['origen', 'destino', 'usuario', 'receptor', 'detalles.producto.tipoUnidad', 'detalles.lote']);

        return view('inventario.transferencias.show', ['transferencia' => $transferencia]);
    }

    public function edit(TransferenciaAlmacen $transferencia)
    {
        if ($transferencia->estado !== TransferenciaAlmacen::BORRADOR) {
            return redirect()->route('transferencias.show', $transferencia)->with('error', 'Solo se pueden editar transferencias en borrador.');
        }

        return view('inventario.transferencias.form', [
            'transferencia' => $transferencia->load('detalles.producto', 'detalles.lote'),
            'almacenes'     => $this->almacenes($transferencia->empresa_id),
            'productos'     => CatalogoInventario::productos($transferencia->empresa_id),
        ]);
    }

    public function update(TransferenciaRequest $request, TransferenciaAlmacen $transferencia)
    {
        $t = $this->transferencias->guardar($request->validated(), $request->user(), $transferencia->empresa_id, $transferencia);

        return redirect()->route('transferencias.show', $t)->with('status', $this->mensaje($t));
    }

    public function enviar(Request $request, TransferenciaAlmacen $transferencia)
    {
        $this->transferencias->enviar($transferencia, $request->user());

        return back()->with('status', "Transferencia {$transferencia->numero_completo} enviada: la mercadería salió del almacén de origen.");
    }

    public function recibir(Request $request, TransferenciaAlmacen $transferencia)
    {
        $datos = $request->validate([
            'recibidas'   => ['nullable', 'array'],
            'recibidas.*' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $recibidas = collect($datos['recibidas'] ?? [])->filter(fn ($v) => $v !== null && $v !== '')->all();
        $t = $this->transferencias->recibir($transferencia, $recibidas, $request->user(), $datos['observacion'] ?? null);

        return back()->with('status', "Transferencia {$t->numero_completo} recibida en {$t->destino?->nombre}.");
    }

    public function anular(Request $request, TransferenciaAlmacen $transferencia)
    {
        $enTransito = $transferencia->estado === TransferenciaAlmacen::EN_TRANSITO;
        $this->transferencias->anular($transferencia, $request->user());

        return back()->with('status', "Transferencia {$transferencia->numero_completo} anulada" . ($enTransito ? ': la mercadería volvió al almacén de origen.' : '.'));
    }

    private function almacenes(int $empresaId)
    {
        return Almacen::query()->deEmpresa($empresaId)->activo()->orderByDesc('es_principal')->orderBy('nombre')->get(['id', 'nombre', 'es_principal']);
    }

    private function mensaje(TransferenciaAlmacen $t): string
    {
        return $t->estado === TransferenciaAlmacen::EN_TRANSITO
            ? "Transferencia {$t->numero_completo} enviada: está en tránsito hasta que el almacén de destino la reciba."
            : "Borrador {$t->numero_completo} guardado. Aún no mueve stock.";
    }
}
