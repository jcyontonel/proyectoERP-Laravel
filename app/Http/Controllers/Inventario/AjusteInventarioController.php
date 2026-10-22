<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\AjusteRequest;
use App\Http\Requests\Inventario\FiltrarInventarioRequest;
use App\Models\AjusteInventario;
use App\Models\Almacen;
use App\Models\MotivoAjuste;
use App\Services\AjusteService;
use App\Support\CatalogoInventario;
use Illuminate\Http\Request;

class AjusteInventarioController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private AjusteService $ajustes)
    {
    }

    public function index(FiltrarInventarioRequest $request)
    {
        $empresaId = $this->empresaId($request);

        return view('inventario.ajustes.index', [
            'ajustes'    => AjusteInventario::query()->deEmpresa($empresaId)
                ->with(['almacen:id,nombre', 'motivo:id,nombre,requiere_aprobacion', 'usuario:id,name,apellido'])
                ->withCount('detalles')
                ->filtrar($request->validated())
                ->latest('fecha_movimiento')->latest('id')
                ->paginate(15)->withQueryString(),
            'almacenes'  => Almacen::query()->deEmpresa($empresaId)->orderBy('nombre')->get(['id', 'nombre']),
            'pendientes' => AjusteInventario::query()->deEmpresa($empresaId)->where('estado', AjusteInventario::PENDIENTE)->count(),
        ]);
    }

    public function create(Request $request)
    {
        return view('inventario.ajustes.form', $this->formulario($this->empresaId($request)) + [
            'ajuste' => new AjusteInventario(['tipo' => $request->query('tipo', 'ingreso'), 'fecha_movimiento' => now()]),
        ]);
    }

    public function store(AjusteRequest $request)
    {
        $ajuste = $this->ajustes->guardar($request->validated(), $request->user(), $this->empresaId($request));

        return redirect()->route('ajustes.show', $ajuste)->with('status', $this->mensaje($ajuste));
    }

    public function show(AjusteInventario $ajuste)
    {
        $ajuste->load(['almacen', 'motivo', 'usuario', 'aprobador', 'detalles.producto.tipoUnidad', 'detalles.lote']);

        return view('inventario.ajustes.show', ['ajuste' => $ajuste]);
    }

    public function edit(Request $request, AjusteInventario $ajuste)
    {
        if (! $ajuste->esEditable()) {
            return redirect()->route('ajustes.show', $ajuste)->with('error', 'Solo se pueden editar ajustes en borrador o pendientes.');
        }

        return view('inventario.ajustes.form', $this->formulario($ajuste->empresa_id) + ['ajuste' => $ajuste->load('detalles.producto', 'detalles.lote')]);
    }

    public function update(AjusteRequest $request, AjusteInventario $ajuste)
    {
        $ajuste = $this->ajustes->guardar($request->validated(), $request->user(), $ajuste->empresa_id, $ajuste);

        return redirect()->route('ajustes.show', $ajuste)->with('status', $this->mensaje($ajuste));
    }

    public function confirmar(Request $request, AjusteInventario $ajuste)
    {
        $this->ajustes->confirmar($ajuste, $request->user());

        return back()->with('status', "Ajuste {$ajuste->numero_completo} confirmado: el stock y el kardex fueron actualizados.");
    }

    public function anular(AjusteInventario $ajuste)
    {
        $this->ajustes->anular($ajuste);

        return back()->with('status', "Ajuste {$ajuste->numero_completo} anulado.");
    }

    private function formulario(int $empresaId): array
    {
        return [
            'almacenes' => Almacen::query()->deEmpresa($empresaId)->activo()->orderByDesc('es_principal')->orderBy('nombre')->get(['id', 'nombre', 'es_principal']),
            'motivos'   => MotivoAjuste::query()->deEmpresa($empresaId)->activo()->orderBy('nombre')->get(['id', 'nombre', 'tipo_movimiento', 'requiere_aprobacion']),
            'productos' => CatalogoInventario::productos($empresaId),
        ];
    }

    private function mensaje(AjusteInventario $a): string
    {
        return match ($a->estado) {
            AjusteInventario::CONFIRMADO => "Ajuste {$a->numero_completo} confirmado: el stock y el kardex fueron actualizados.",
            AjusteInventario::PENDIENTE  => "Ajuste {$a->numero_completo} enviado a aprobación: el motivo requiere la autorización de un responsable.",
            default                      => "Borrador {$a->numero_completo} guardado. Aún no mueve stock.",
        };
    }
}
