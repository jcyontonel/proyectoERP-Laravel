<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PagoSuscripcion */
class PagoSuscripcionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'empresa_id'     => $this->empresa_id,
            'empresa'        => $this->whenLoaded('empresa', fn () => $this->empresa->nombre_comercial ?: $this->empresa->razon_social),
            'suscripcion_id' => $this->suscripcion_id,
            'plan'           => $this->whenLoaded('suscripcion', fn () => $this->suscripcion->plan?->nombre),
            'periodicidad'   => $this->whenLoaded('suscripcion', fn () => $this->suscripcion->periodicidad),
            'oferta'         => $this->whenLoaded('oferta', fn () => $this->oferta?->nombre),
            'periodo_desde'  => $this->periodo_desde?->toDateString(),
            'periodo_hasta'  => $this->periodo_hasta?->toDateString(),
            'meses'          => $this->meses,
            'monto_bruto'    => $this->monto_bruto,
            'descuento'      => $this->descuento,
            'monto'          => $this->monto,
            'estado'         => $this->estado,
            'metodo'         => $this->metodo,
            'referencia'     => $this->referencia,
            'fecha_pago'     => $this->fecha_pago?->toIso8601String(),
            'registrado_por' => $this->registrado_por,
            'notas'          => $this->notas,
            'creado'         => $this->created_at?->toIso8601String(),
        ];
    }
}
