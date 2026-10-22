<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\PlanResource;
use App\Models\Plan;
use App\Models\Suscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Planes de suscripción con sus límites y sus precios por periodicidad */
class PlanController extends AdminController
{
    public function index()
    {
        return PlanResource::collection(
            Plan::query()->with('precios')
                ->withCount(['suscripciones as suscripciones_vigentes_count' => fn ($q) => $q->where('estado', '!=', Suscripcion::CANCELADA)])
                ->orderBy('orden')->orderBy('id')->get()
        );
    }

    public function show(Plan $plan)
    {
        return new PlanResource($plan->load('precios'));
    }

    public function store(Request $request)
    {
        $plan = DB::transaction(function () use ($request) {
            $d = $this->validar($request);
            $plan = Plan::create(collect($d)->except('precios')->all());
            $this->guardarPrecios($plan, $d['precios'] ?? []);

            return $plan;
        });

        return (new PlanResource($plan->load('precios')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Plan $plan)
    {
        DB::transaction(function () use ($request, $plan) {
            $d = $this->validar($request, $plan);
            $plan->update(collect($d)->except('precios')->all());
            $this->guardarPrecios($plan, $d['precios'] ?? []);
        });

        return new PlanResource($plan->refresh()->load('precios'));
    }

    /** Solo si ninguna empresa lo tuvo: si no, se desactiva */
    public function destroy(Plan $plan)
    {
        if ($plan->suscripciones()->exists()) {
            return response()->json(['message' => "El plan {$plan->nombre} tiene o tuvo suscripciones: desactívalo en lugar de eliminarlo."], 422);
        }
        $plan->delete();

        return response()->noContent();
    }

    private function validar(Request $request, ?Plan $plan = null): array
    {
        $periodicidades = array_keys(config('suscripciones.periodicidades'));

        $d = $request->validate([
            'codigo'               => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('planes', 'codigo')->ignore($plan?->id)],
            'nombre'               => ['required', 'string', 'max:60'],
            'descripcion'          => ['nullable', 'string', 'max:255'],
            'max_comprobantes_mes' => ['nullable', 'integer', 'min:0'],
            'max_productos'        => ['nullable', 'integer', 'min:0'],
            'max_usuarios'         => ['nullable', 'integer', 'min:1'],
            'max_almacenes'        => ['nullable', 'integer', 'min:1'],
            'dias_prueba'          => ['nullable', 'integer', 'min:0', 'max:90'],
            'activo'               => ['boolean'],
            'orden'                => ['nullable', 'integer', 'min:0'],
            'precios'              => ['nullable', 'array'],
            'precios.*.periodicidad' => ['required', Rule::in($periodicidades)],
            'precios.*.precio'     => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'precios.*.activo'     => ['boolean'],
        ], ['codigo.unique' => 'Ya existe un plan con ese código.']);

        $d['dias_prueba'] = (int) ($d['dias_prueba'] ?? 0);
        $d['orden'] = (int) ($d['orden'] ?? 0);

        return $d;
    }

    /** Precio vacío = esa periodicidad no se ofrece (se desactiva si ya existía) */
    private function guardarPrecios(Plan $plan, array $precios): void
    {
        foreach ($precios as $p) {
            $meses = config('suscripciones.periodicidades')[$p['periodicidad']][1];
            if (($p['precio'] ?? null) === null || $p['precio'] === '') {
                $plan->precios()->where('periodicidad', $p['periodicidad'])->update(['activo' => false]);
                continue;
            }
            $plan->precios()->updateOrCreate(
                ['periodicidad' => $p['periodicidad']],
                ['meses' => $meses, 'precio' => $p['precio'], 'activo' => (bool) ($p['activo'] ?? true)]
            );
        }
    }
}
