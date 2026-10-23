<?php

namespace App\Modulos\Erp\Http\Controllers;

use App\Modulos\Nucleo\Http\Controllers\Controller;

use App\Modulos\Erp\Http\Requests\ProveedorRequest;
use App\Modulos\Erp\Models\Proveedor;
use App\Modulos\Nucleo\Models\TipoDocumento;
use App\Modulos\Nucleo\Support\EmpresaActual;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $this->empresaId($request);

        $proveedores = Proveedor::query()
            ->deEmpresa($empresaId)
            ->with('tipoDocumento')
            ->withCount('compras')
            ->buscar($request->query('q'))
            ->when($request->query('estado') === 'activos', fn ($q) => $q->where('activo', true))
            ->when($request->query('estado') === 'inactivos', fn ($q) => $q->where('activo', false))
            ->orderBy('razon_social')
            ->paginate(15)
            ->withQueryString();

        return view('proveedores.index', compact('proveedores'));
    }

    public function create(Request $request)
    {
        $this->empresaId($request);

        return view('proveedores.form', [
            'proveedor'      => new Proveedor(['pais' => 'Perú', 'activo' => true]),
            'tiposDocumento' => TipoDocumento::orderBy('descripcion')->get(['id', 'codigo', 'descripcion']),
        ]);
    }

    /** Alta normal (redirige) o alta rápida desde Nueva compra (responde JSON) */
    public function store(ProveedorRequest $request)
    {
        $proveedor = Proveedor::create($request->validated() + ['empresa_id' => $this->empresaId($request)]);
        $proveedor->load('tipoDocumento');

        if ($request->expectsJson()) {
            return response()->json([
                'id'       => $proveedor->id,
                'nombre'   => $proveedor->nombre,
                'doc'      => (string) $proveedor->numero_documento,
                'tipo_doc' => $proveedor->tipo_doc_etiqueta,
                'contacto' => $proveedor->contacto_nombre,
                'telefono' => $proveedor->telefono ?: $proveedor->celular,
                'email'    => $proveedor->email,
            ], 201);
        }

        return redirect()->route('proveedores.index')->with('status', "Proveedor «{$proveedor->nombre}» creado.");
    }

    public function edit(Request $request, Proveedor $proveedor)
    {
        $this->propio($request, $proveedor);

        return view('proveedores.form', [
            'proveedor'      => $proveedor,
            'tiposDocumento' => TipoDocumento::orderBy('descripcion')->get(['id', 'codigo', 'descripcion']),
        ]);
    }

    public function update(ProveedorRequest $request, Proveedor $proveedor)
    {
        $this->propio($request, $proveedor);

        $proveedor->update($request->validated());

        return redirect()->route('proveedores.index')->with('status', "Proveedor «{$proveedor->nombre}» actualizado.");
    }

    /** Elimina solo si nunca tuvo compras (si no, conviene desactivarlo) */
    public function destroy(Request $request, Proveedor $proveedor)
    {
        $this->propio($request, $proveedor);

        if ($proveedor->compras()->exists()) {
            return back()->with('error', "«{$proveedor->nombre}» tiene compras registradas: no se puede eliminar. Desactívalo desde Editar.");
        }

        $proveedor->delete();

        return back()->with('status', "Proveedor «{$proveedor->nombre}» eliminado.");
    }

    private function empresaId(Request $request): int
    {
        $id = EmpresaActual::id($request->user());
        abort_if(! $id, 403, 'Tu usuario no tiene una empresa asignada.');

        return $id;
    }

    private function propio(Request $request, Proveedor $proveedor): void
    {
        abort_unless($proveedor->empresa_id === $this->empresaId($request), 404);
    }
}
