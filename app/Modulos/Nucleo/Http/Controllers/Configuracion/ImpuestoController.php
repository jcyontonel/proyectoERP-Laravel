<?php

namespace App\Modulos\Nucleo\Http\Controllers\Configuracion;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Nucleo\Models\Impuesto;
use App\Modulos\Catalogo\Services\CatalogoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Afectaciones al IGV (catálogo común a todas las empresas). El tipo de afectación y el tributo
 * vienen del catálogo SUNAT y no cambian; nombre, porcentaje y estado se editan solo si ninguna
 * otra empresa usa la afectación.
 */
class ImpuestoController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private CatalogoService $catalogo)
    {
    }

    public function index(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $uso = $this->catalogo->uso('impuesto_id');

        return view('configuracion.impuestos.index', [
            'impuestos'    => Impuesto::query()->orderBy('tipo_afectacion')->get(),
            'usoPropio'    => $uso->map(fn ($e) => (int) ($e[$empresaId] ?? 0)),
            'usoOtras'     => $uso->map(fn ($e) => $e->keys()->contains(fn ($id) => (int) $id !== $empresaId)),
            'afectaciones' => Impuesto::AFECTACIONES,
        ]);
    }

    public function create()
    {
        return view('configuracion.impuestos.form', [
            'impuesto'     => new Impuesto(['activo' => true, 'porcentaje' => 0]),
            'afectaciones' => collect(Impuesto::AFECTACIONES)->except(Impuesto::pluck('tipo_afectacion')->all()),
            'bloqueado'    => false,
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'tipo_afectacion' => ['required', Rule::in(array_keys(Impuesto::AFECTACIONES)), Rule::unique('impuestos', 'tipo_afectacion')],
            'codigo'          => ['required', 'string', 'max:10', 'alpha_dash', Rule::unique('impuestos', 'codigo')],
            'nombre'          => ['required', 'string', 'max:60'],
            'porcentaje'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'activo'          => ['boolean'],
        ], ['tipo_afectacion.unique' => 'Esa afectación ya está registrada.', 'codigo.unique' => 'Ese código ya existe.']);

        [, $tributo, $conPorcentaje] = Impuesto::AFECTACIONES[$d['tipo_afectacion']];
        $impuesto = Impuesto::create([
            'tipo_afectacion' => $d['tipo_afectacion'],
            'codigo'          => strtoupper($d['codigo']),
            'nombre'          => $d['nombre'],
            'codigo_tributo'  => $tributo,
            'porcentaje'      => $conPorcentaje ? (float) ($d['porcentaje'] ?? 0) : 0,
            'activo'          => $request->boolean('activo'),
        ]);

        return redirect()->route('impuestos.index')->with('status', "Afectación «{$impuesto->nombre}» registrada.");
    }

    public function edit(Request $request, Impuesto $impuesto)
    {
        return view('configuracion.impuestos.form', [
            'impuesto'     => $impuesto,
            'afectaciones' => collect(Impuesto::AFECTACIONES)->only($impuesto->tipo_afectacion),
            'bloqueado'    => $this->catalogo->usadoPorOtras('impuesto_id', $impuesto->id, $this->empresaId($request)),
        ]);
    }

    public function update(Request $request, Impuesto $impuesto)
    {
        abort_if($this->catalogo->usadoPorOtras('impuesto_id', $impuesto->id, $this->empresaId($request)), 403,
            'Otras empresas usan esta afectación: no se puede modificar desde aquí.');

        $d = $request->validate([
            'nombre'     => ['required', 'string', 'max:60'],
            'porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'activo'     => ['boolean'],
        ]);
        $conPorcentaje = Impuesto::AFECTACIONES[$impuesto->tipo_afectacion][2] ?? false;

        $impuesto->update([
            'nombre'     => $d['nombre'],
            'porcentaje' => $conPorcentaje ? (float) ($d['porcentaje'] ?? 0) : 0,
            'activo'     => $request->boolean('activo'),
        ]);

        return redirect()->route('impuestos.index')->with('status', "Afectación «{$impuesto->nombre}» actualizada.");
    }

    public function destroy(Impuesto $impuesto)
    {
        if ($this->catalogo->enUso('impuesto_id', $impuesto->id)) {
            return back()->with('error', "«{$impuesto->nombre}» está en uso por productos o documentos: desactívala en lugar de eliminarla.");
        }

        $impuesto->delete();

        return redirect()->route('impuestos.index')->with('status', "Afectación «{$impuesto->nombre}» eliminada.");
    }
}
