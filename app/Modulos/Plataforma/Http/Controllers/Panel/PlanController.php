<?php

namespace App\Modulos\Plataforma\Http\Controllers\Panel;

use App\Modulos\Nucleo\Http\Controllers\Controller;

use App\Modulos\Plataforma\Support\ApiAdmin;
use Illuminate\Http\Request;

/** Planes: límites y precios mensual / trimestral / anual */
class PlanController extends Controller
{
    public function __construct(private ApiAdmin $erp)
    {
    }

    public function index()
    {
        return view('plataforma::planes.index', ['planes' => $this->erp->get('planes')['data'], 'catalogoModulos' => $this->erp->catalogos()['modulos'] ?? [], 'productos' => $this->erp->catalogos()['productos'] ?? []]);
    }

    public function create()
    {
        return view('plataforma::planes.form', ['plan' => ['activo' => true, 'precios' => [], 'modulos' => [], 'producto' => request('producto', 'erp')], 'productos' => $this->erp->catalogos()['productos'] ?? []]);
    }

    public function store(Request $request)
    {
        $plan = $this->erp->post('planes', $this->datos($request))['data'];

        return redirect()->route('plataforma.planes.index')->with('status', "Plan {$plan['nombre']} creado.");
    }

    public function edit(int $plan)
    {
        return view('plataforma::planes.form', ['plan' => $this->erp->get("planes/{$plan}")['data'], 'productos' => $this->erp->catalogos()['productos'] ?? []]);
    }

    public function update(Request $request, int $plan)
    {
        $p = $this->erp->put("planes/{$plan}", $this->datos($request))['data'];

        return redirect()->route('plataforma.planes.index')->with('status', "Plan {$p['nombre']} actualizado. Los límites aplican de inmediato a sus empresas.");
    }

    public function destroy(int $plan)
    {
        $this->erp->delete("planes/{$plan}");

        return redirect()->route('plataforma.planes.index')->with('status', 'Plan eliminado.');
    }

    /** Límite vacío = ilimitado; precio vacío = esa periodicidad no se ofrece */
    private function datos(Request $request): array
    {
        $d = $request->only(['producto', 'codigo', 'nombre', 'descripcion', 'max_comprobantes_mes', 'max_productos', 'max_usuarios', 'max_almacenes', 'max_empresas', 'dias_prueba', 'orden']);
        $d['activo'] = $request->boolean('activo');
        $d['precios'] = collect(config('plataforma.periodicidades'))->keys()
            ->map(fn ($p) => ['periodicidad' => $p, 'precio' => $request->input("precios.{$p}")])->all();
        // Casillas de funcionalidades opcionales (solo las del sistema del plan): sin marcar = ninguna
        if ($request->has('modulos_enviados')) {
            $d['modulos'] = array_values((array) $request->input('modulos.' . $request->input('producto', 'erp'), []));
        }

        return $d;
    }
}
