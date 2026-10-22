<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Caja;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cajas registradoras de la empresa (la operación de turnos está en Caja\TurnoCajaController) */
class CajaController extends Controller
{
    use ResuelveEmpresa;

    public function index(Request $request)
    {
        return view('configuracion.cajas.index', [
            'cajas' => Caja::query()->deEmpresa($this->empresaId($request))
                ->with(['almacen:id,nombre', 'sesionAbierta.usuario:id,name,apellido'])
                ->withCount('sesiones')
                ->orderBy('nombre')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('configuracion.cajas.form', ['caja' => new Caja(['activo' => true]), 'almacenes' => $this->almacenes($this->empresaId($request))]);
    }

    public function store(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $caja = Caja::create($this->validar($request, $empresaId) + ['empresa_id' => $empresaId]);

        return redirect()->route('cajas.index')->with('status', "Caja «{$caja->nombre}» creada.");
    }

    public function edit(Caja $caja)
    {
        return view('configuracion.cajas.form', ['caja' => $caja, 'almacenes' => $this->almacenes($caja->empresa_id)]);
    }

    public function update(Request $request, Caja $caja)
    {
        $datos = $this->validar($request, $caja->empresa_id, $caja->id);

        if (! $datos['activo'] && $caja->sesionAbierta()->exists()) {
            return back()->withInput()->with('error', 'No puedes desactivar una caja con un turno abierto.');
        }

        $caja->update($datos);

        return redirect()->route('cajas.index')->with('status', "Caja «{$caja->nombre}» actualizada.");
    }

    public function destroy(Caja $caja)
    {
        if ($caja->sesiones()->exists()) {
            return back()->with('error', "«{$caja->nombre}» ya tiene turnos registrados: desactívala en lugar de eliminarla.");
        }

        $caja->delete();

        return redirect()->route('cajas.index')->with('status', "Caja «{$caja->nombre}» eliminada.");
    }

    private function validar(Request $request, int $empresaId, ?int $ignorar = null): array
    {
        $datos = $request->validate([
            'codigo'     => ['required', 'string', 'max:20', Rule::unique('cajas')->where('empresa_id', $empresaId)->ignore($ignorar)],
            'nombre'     => ['required', 'string', 'max:100'],
            'almacen_id' => ['nullable', Rule::exists('almacenes', 'id')->where('empresa_id', $empresaId)->whereNull('deleted_at')],
            'activo'     => ['boolean'],
        ], ['codigo.unique' => 'Ya existe una caja con ese código.']);

        return ['codigo' => strtoupper(trim($datos['codigo'])), 'activo' => $request->boolean('activo')] + $datos;
    }

    private function almacenes(int $empresaId)
    {
        return Almacen::query()->deEmpresa($empresaId)->activo()->orderBy('nombre')->get(['id', 'nombre']);
    }
}
