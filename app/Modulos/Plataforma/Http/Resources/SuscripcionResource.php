<?php

namespace App\Modulos\Plataforma\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modulos\Plataforma\Models\Suscripcion */
class SuscripcionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'empresa_id'            => $this->empresa_id,
            'plan'                  => $this->whenLoaded('plan', fn () => ['id' => $this->plan->id, 'codigo' => $this->plan->codigo, 'nombre' => $this->plan->nombre]),
            'periodicidad'          => $this->periodicidad,
            'precio'                => $this->precio,
            'oferta'                => $this->whenLoaded('oferta', fn () => $this->oferta ? ['id' => $this->oferta->id, 'nombre' => $this->oferta->nombre] : null),
            'estado'                => $this->estado,
            'estado_efectivo'       => $this->estadoEfectivo(),
            'inicio'                => $this->inicio?->toDateString(),
            'vence_el'              => $this->vence_el?->toDateString(),
            'dias_restantes'        => $this->diasRestantes(),
            'renovacion_automatica' => $this->renovacion_automatica,
            'motivo_suspension'     => $this->motivo_suspension,
            'cancelada_el'          => $this->cancelada_el?->toIso8601String(),
            'notas'                 => $this->notas,
        ];
    }
}
