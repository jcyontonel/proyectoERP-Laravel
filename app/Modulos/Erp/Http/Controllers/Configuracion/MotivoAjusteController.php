<?php

namespace App\Modulos\Erp\Http\Controllers\Configuracion;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Erp\Models\AjusteInventario;
use App\Modulos\Erp\Models\MotivoAjuste;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MotivoAjusteController extends Controller
{
    use ResuelveEmpresa;

    public const TIPOS = ['ingreso' => 'Solo ingresos', 'egreso' => 'Solo salidas', 'ambos' => 'Ingresos, salidas y conteos'];

    public function index(Request $request)
    {
        $empresaId = $this->empresaId($request);

        return view('configuracion.motivos.index', [
            'motivos' => MotivoAjuste::query()->deEmpresa($empresaId)->orderBy('nombre')->get(),
            'usos'    => AjusteInventario::query()->deEmpresa($empresaId)->selectRaw('motivo_ajuste_id, COUNT(*) AS total')
                ->groupBy('motivo_ajuste_id')->pluck('total', 'motivo_ajuste_id'),
            'tipos'   => self::TIPOS,
        ]);
    }

    public function create()
    {
        return view('configuracion.motivos.form', ['motivo' => new MotivoAjuste(['activo' => true, 'tipo_movimiento' => 'egreso']), 'tipos' => self::TIPOS]);
    }

    public function store(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $motivo = MotivoAjuste::create($this->validar($request, $empresaId) + ['empresa_id' => $empresaId]);

        return redirect()->route('motivos.index')->with('status', "Motivo «{$motivo->nombre}» creado.");
    }

    public function edit(MotivoAjuste $motivo)
    {
        return view('configuracion.motivos.form', ['motivo' => $motivo, 'tipos' => self::TIPOS]);
    }

    public function update(Request $request, MotivoAjuste $motivo)
    {
        $motivo->update($this->validar($request, $motivo->empresa_id, $motivo->id));

        return redirect()->route('motivos.index')->with('status', "Motivo «{$motivo->nombre}» actualizado.");
    }

    public function destroy(MotivoAjuste $motivo)
    {
        if (AjusteInventario::query()->where('motivo_ajuste_id', $motivo->id)->exists()) {
            return back()->with('error', "«{$motivo->nombre}» ya se usó en ajustes: desactívalo en lugar de eliminarlo.");
        }

        $motivo->delete();

        return redirect()->route('motivos.index')->with('status', "Motivo «{$motivo->nombre}» eliminado.");
    }

    private function validar(Request $request, int $empresaId, ?int $ignorar = null): array
    {
        $datos = $request->validate([
            'codigo'              => ['required', 'string', 'max:50', Rule::unique('motivos_ajuste')->where('empresa_id', $empresaId)->ignore($ignorar)],
            'nombre'              => ['required', 'string', 'max:120'],
            'tipo_movimiento'     => ['required', Rule::in(array_keys(self::TIPOS))],
            'requiere_aprobacion' => ['boolean'],
            'activo'              => ['boolean'],
        ], ['codigo.unique' => 'Ya existe un motivo con ese código.']);

        return [
            'codigo'              => strtoupper(trim($datos['codigo'])),
            'requiere_aprobacion' => $request->boolean('requiere_aprobacion'),
            'activo'              => $request->boolean('activo'),
        ] + $datos;
    }
}
