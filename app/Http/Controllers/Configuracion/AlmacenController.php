<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\AlmacenProducto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AlmacenController extends Controller
{
    use ResuelveEmpresa;

    public function index(Request $request)
    {
        $empresaId = $this->empresaId($request);

        return view('configuracion.almacenes.index', [
            'almacenes' => Almacen::query()->deEmpresa($empresaId)
                ->withCount(['stocks as productos' => fn ($q) => $q->where('stock', '>', 0)])
                ->withSum('stocks as unidades', 'stock')
                ->orderByDesc('es_principal')->orderBy('nombre')->get(),
            'valores' => AlmacenProducto::query()
                ->join('productos as p', 'p.id', '=', 'almacen_producto.producto_id')
                ->where('p.empresa_id', $empresaId)->where('almacen_producto.stock', '>', 0)
                ->groupBy('almacen_producto.almacen_id')
                ->selectRaw('almacen_producto.almacen_id, SUM(almacen_producto.stock * p.costo_unitario) AS valor')
                ->pluck('valor', 'almacen_id'),
        ]);
    }

    public function create()
    {
        return view('configuracion.almacenes.form', ['almacen' => new Almacen(['activo' => true])]);
    }

    public function store(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $almacen = DB::transaction(fn () => $this->guardar(new Almacen(['empresa_id' => $empresaId]), $this->validar($request, $empresaId)));

        return redirect()->route('almacenes.index')->with('status', "Almacén «{$almacen->nombre}» creado.");
    }

    public function edit(Almacen $almacen)
    {
        return view('configuracion.almacenes.form', ['almacen' => $almacen]);
    }

    public function update(Request $request, Almacen $almacen)
    {
        DB::transaction(fn () => $this->guardar($almacen, $this->validar($request, $almacen->empresa_id, $almacen->id)));

        return redirect()->route('almacenes.index')->with('status', "Almacén «{$almacen->nombre}» actualizado.");
    }

    /** Solo si nunca tuvo stock: con historial se desactiva */
    public function destroy(Almacen $almacen)
    {
        if ($almacen->es_principal) {
            return back()->with('error', 'El almacén principal no se puede eliminar: marca otro como principal primero.');
        }
        if ($almacen->stocks()->exists() || DB::table('kardex')->where('almacen_id', $almacen->id)->exists()) {
            return back()->with('error', "«{$almacen->nombre}» tiene stock o movimientos: desactívalo en lugar de eliminarlo.");
        }

        $almacen->delete();

        return redirect()->route('almacenes.index')->with('status', "Almacén «{$almacen->nombre}» eliminado.");
    }

    private function validar(Request $request, int $empresaId, ?int $ignorar = null): array
    {
        return $request->validate([
            'codigo'       => ['required', 'string', 'max:20', Rule::unique('almacenes')->where('empresa_id', $empresaId)->ignore($ignorar)],
            'nombre'       => ['required', 'string', 'max:255'],
            'direccion'    => ['nullable', 'string', 'max:255'],
            'es_principal' => ['boolean'],
            'activo'       => ['boolean'],
        ], ['codigo.unique' => 'Ya existe un almacén con ese código.']);
    }

    /** Un único almacén principal por empresa, y el principal siempre activo */
    private function guardar(Almacen $almacen, array $d): Almacen
    {
        $d['codigo'] = strtoupper(trim($d['codigo']));
        $d['es_principal'] = (bool) ($d['es_principal'] ?? false);
        $d['activo'] = $d['es_principal'] || (bool) ($d['activo'] ?? true);

        $almacen->fill($d)->save();

        if ($almacen->es_principal) {
            Almacen::query()->deEmpresa($almacen->empresa_id)->whereKeyNot($almacen->id)->update(['es_principal' => false]);
        }

        return $almacen;
    }
}
