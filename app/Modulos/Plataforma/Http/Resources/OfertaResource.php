<?php

namespace App\Modulos\Plataforma\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modulos\Plataforma\Models\Oferta */
class OfertaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'nombre'           => $this->nombre,
            'codigo'           => $this->codigo,
            'plan_id'          => $this->plan_id,
            'plan'             => $this->whenLoaded('plan', fn () => $this->plan?->nombre),
            'periodicidad'     => $this->periodicidad,
            'tipo'             => $this->tipo,
            'valor'            => $this->valor,
            'desde'            => $this->desde?->toDateString(),
            'hasta'            => $this->hasta?->toDateString(),
            'max_usos'         => $this->max_usos,
            'usos'             => $this->usos,
            'solo_primer_pago' => $this->solo_primer_pago,
            'activo'           => $this->activo,
            'vigente'          => $this->activo && $this->desde->lte(today()) && (! $this->hasta || $this->hasta->gte(today()))
                && ($this->max_usos === null || $this->usos < $this->max_usos),
        ];
    }
}
