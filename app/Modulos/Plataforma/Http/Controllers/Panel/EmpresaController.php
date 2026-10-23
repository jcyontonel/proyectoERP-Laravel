<?php

namespace App\Modulos\Plataforma\Http\Controllers\Panel;

use App\Modulos\Nucleo\Http\Controllers\Controller;

use App\Modulos\Plataforma\Support\ApiAdmin;
use Illuminate\Http\Request;

/** Empresas clientes del ERP */
class EmpresaController extends Controller
{
    public function __construct(private ApiAdmin $erp)
    {
    }

    public function index(Request $request)
    {
        $f = $request->only(['q', 'estado', 'plan_id', 'page']);

        return view('plataforma::empresas.index', [
            'empresas' => $this->erp->paginar($this->erp->get('empresas', array_filter($f))),
            'planes'   => $this->erp->catalogos()['planes'],
        ]);
    }

    public function create()
    {
        return view('plataforma::empresas.create', [
            'planes'    => collect($this->erp->get('planes')['data'])->where('activo', true)->values(),
            'catalogos' => $this->erp->catalogos(),
        ]);
    }

    /** Alta completa en el ERP: empresa, configuración inicial, administrador y plan */
    public function store(Request $request)
    {
        $d = $request->validate([
            'ruc'          => ['required', 'digits:11'],
            'razon_social' => ['required', 'string', 'max:255'],
            'admin.name'   => ['required', 'string', 'max:100'],
            'admin.email'  => ['required', 'email'],
        ]);

        $r = $this->erp->post('empresas', array_merge($request->except(['_token', '_dialogo', 'modo']), [
            'prueba' => $request->boolean('prueba'),
            'pagado' => $request->boolean('pagado'),
        ]));

        return redirect()->route('plataforma.empresas.show', $r['data']['id'])->with('status', $r['message'] ?? "Empresa {$d['razon_social']} creada.");
    }

    public function show(int $empresa)
    {
        $ficha = $this->erp->get("empresas/{$empresa}")['data'];
        $usuarios = $this->erp->get("empresas/{$empresa}/usuarios");

        return view('plataforma::empresas.show', [
            'ficha'     => $ficha,
            'e'         => $ficha['empresa'],
            'usuarios'  => $usuarios['data'],
            'roles'     => $usuarios['roles'],
            'planes'    => collect($this->erp->get('planes')['data'])->where('activo', true)->values(),
            'catalogos' => $this->erp->catalogos(),
        ]);
    }

    public function edit(int $empresa)
    {
        return view('plataforma::empresas.edit', ['e' => $this->erp->get("empresas/{$empresa}")['data']['empresa']]);
    }

    public function update(Request $request, int $empresa)
    {
        $this->erp->put("empresas/{$empresa}", array_merge($request->except(['_token', '_method']), ['activo' => $request->boolean('activo')]));

        return redirect()->route('plataforma.empresas.show', $empresa)->with('status', 'Datos de la empresa actualizados.');
    }

    /** RUC → datos de SUNAT (la consulta la hace el ERP con su token de APIPeru) */
    public function consultaRuc(string $ruc)
    {
        return response()->json($this->erp->get("consulta-ruc/{$ruc}"));
    }
}
