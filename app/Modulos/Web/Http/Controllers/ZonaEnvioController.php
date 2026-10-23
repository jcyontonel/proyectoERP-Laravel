<?php

namespace App\Modulos\Web\Http\Controllers;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Web\Models\PedidoWeb;
use App\Modulos\Web\Models\ZonaEnvio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Página web → Zonas de envío (plan Tienda): costo y tiempo de entrega de cada zona */
class ZonaEnvioController extends Controller
{
    use ResuelveEmpresa;

    public function index(Request $request)
    {
        return view('web.zonas', ['zonas' => ZonaEnvio::query()->deEmpresa($this->empresaId($request))->orderBy('orden')->orderBy('nombre')->get()]);
    }

    public function store(Request $request)
    {
        $empresaId = $this->empresaId($request);
        ZonaEnvio::create(['empresa_id' => $empresaId] + $this->validar($request, $empresaId));

        return back()->with('status', 'Zona de envío agregada.');
    }

    public function update(Request $request, ZonaEnvio $zona)
    {
        $zona->update($this->validar($request, $zona->empresa_id, $zona));

        return back()->with('status', "Zona «{$zona->nombre}» actualizada.");
    }

    public function destroy(ZonaEnvio $zona)
    {
        if (PedidoWeb::query()->where('zona_envio_id', $zona->id)->exists()) {
            $zona->update(['activo' => false]);

            return back()->with('status', "«{$zona->nombre}» tiene pedidos: se desactivó en lugar de eliminarla.");
        }
        $zona->delete();

        return back()->with('status', 'Zona eliminada.');
    }

    private function validar(Request $request, int $empresaId, ?ZonaEnvio $zona = null): array
    {
        $d = $request->validate([
            'nombre'         => ['required', 'string', 'max:100', Rule::unique('zonas_envio', 'nombre')->where('empresa_id', $empresaId)->ignore($zona?->id)],
            'costo'          => ['required', 'numeric', 'min:0', 'max:9999'],
            'tiempo_entrega' => ['nullable', 'string', 'max:60'],
            'activo'         => ['boolean'],
            'orden'          => ['nullable', 'integer', 'min:0', 'max:999'],
        ], ['nombre.unique' => 'Ya tienes una zona con ese nombre.']);

        return $d + ['activo' => true, 'orden' => 0];
    }
}
