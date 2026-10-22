<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\PagoSuscripcionResource;
use App\Http\Resources\Admin\SuscripcionResource;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Services\SuscripcionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Plan de cada empresa: asignar o cambiar, suspender, reactivar, cancelar y generar cobros */
class SuscripcionController extends AdminController
{
    public function __construct(private SuscripcionService $suscripciones)
    {
    }

    /** Asigna (o cambia) el plan de la empresa */
    public function store(Request $request, Empresa $empresa)
    {
        $d = $request->validate([
            'plan_id'               => ['required', 'integer', Rule::exists('planes', 'id')],
            'periodicidad'          => ['required', Rule::in(array_keys(config('suscripciones.periodicidades')))],
            'prueba'                => ['boolean'],
            'pagado'                => ['boolean'],
            'oferta'                => ['nullable', 'string', 'max:30'],
            'metodo'                => ['nullable', Rule::in(array_keys(config('suscripciones.metodos_pago')))],
            'referencia'            => ['nullable', 'string', 'max:100'],
            'renovacion_automatica' => ['boolean'],
            'notas'                 => ['nullable', 'string', 'max:1000'],
        ]);

        $s = $this->suscripciones->asignar($empresa, Plan::findOrFail($d['plan_id']), $d, $this->operador($request));

        return (new SuscripcionResource($s->load('plan', 'oferta')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Suscripcion $suscripcion)
    {
        $suscripcion->update($request->validate([
            'renovacion_automatica' => ['sometimes', 'boolean'],
            'notas'                 => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]));

        return new SuscripcionResource($suscripcion->refresh()->load('plan', 'oferta'));
    }

    public function suspender(Request $request, Suscripcion $suscripcion)
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'max:255']]);
        $this->vigente($suscripcion);
        $this->suscripciones->suspender($suscripcion, $d['motivo']);

        return new SuscripcionResource($suscripcion->refresh()->load('plan', 'oferta'));
    }

    public function reactivar(Suscripcion $suscripcion)
    {
        $this->vigente($suscripcion);
        $this->suscripciones->reactivar($suscripcion);

        return new SuscripcionResource($suscripcion->refresh()->load('plan', 'oferta'));
    }

    public function cancelar(Request $request, Suscripcion $suscripcion)
    {
        $d = $request->validate(['motivo' => ['nullable', 'string', 'max:255']]);
        $this->vigente($suscripcion);
        $this->suscripciones->cancelar($suscripcion, $d['motivo'] ?? null);

        return new SuscripcionResource($suscripcion->refresh()->load('plan', 'oferta'));
    }

    /** Genera (o devuelve) el cobro pendiente del siguiente periodo */
    public function cobro(Suscripcion $suscripcion)
    {
        $this->vigente($suscripcion);

        return new PagoSuscripcionResource($this->suscripciones->generarCobro($suscripcion->load('plan', 'oferta')));
    }

    private function vigente(Suscripcion $s): void
    {
        abort_if($s->estado === Suscripcion::CANCELADA, 422, 'La suscripción está cancelada.');
    }
}
