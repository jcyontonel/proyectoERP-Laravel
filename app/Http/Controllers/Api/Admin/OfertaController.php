<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\OfertaResource;
use App\Models\Oferta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Ofertas (descuentos) sobre las suscripciones */
class OfertaController extends AdminController
{
    public function index()
    {
        return OfertaResource::collection(Oferta::query()->with('plan')->orderByDesc('activo')->orderByDesc('desde')->get());
    }

    public function show(Oferta $oferta)
    {
        return new OfertaResource($oferta->load('plan'));
    }

    public function store(Request $request)
    {
        $oferta = Oferta::create($this->validar($request));

        return (new OfertaResource($oferta->load('plan')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Oferta $oferta)
    {
        $oferta->update($this->validar($request, $oferta));

        return new OfertaResource($oferta->refresh()->load('plan'));
    }

    /** Solo si nunca se usó: si no, se desactiva */
    public function destroy(Oferta $oferta)
    {
        if ($oferta->usos > 0) {
            return response()->json(['message' => "La oferta «{$oferta->nombre}» ya se usó: desactívala en lugar de eliminarla."], 422);
        }
        $oferta->delete();

        return response()->noContent();
    }

    private function validar(Request $request, ?Oferta $oferta = null): array
    {
        $request->merge(['codigo' => filled($request->input('codigo')) ? strtoupper(trim($request->input('codigo'))) : null]);

        $d = $request->validate([
            'nombre'           => ['required', 'string', 'max:80'],
            'codigo'           => ['nullable', 'string', 'max:30', 'alpha_dash', Rule::unique('ofertas', 'codigo')->ignore($oferta?->id)],
            'plan_id'          => ['nullable', 'integer', Rule::exists('planes', 'id')],
            'periodicidad'     => ['nullable', Rule::in(array_keys(config('suscripciones.periodicidades')))],
            'tipo'             => ['required', Rule::in([Oferta::PORCENTAJE, Oferta::MONTO])],
            'valor'            => ['required', 'numeric', 'min:0.01', $request->input('tipo') === Oferta::PORCENTAJE ? 'max:100' : 'max:999999'],
            'desde'            => ['required', 'date'],
            'hasta'            => ['nullable', 'date', 'after_or_equal:desde'],
            'max_usos'         => ['nullable', 'integer', 'min:1'],
            'solo_primer_pago' => ['boolean'],
            'activo'           => ['boolean'],
        ], ['codigo.unique' => 'Ya existe una oferta con ese código.', 'valor.max' => 'Un porcentaje no puede pasar de 100.']);

        return $d + ['solo_primer_pago' => true, 'activo' => true];
    }
}
