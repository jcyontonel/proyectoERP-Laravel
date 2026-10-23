<?php

namespace App\Modulos\Plataforma\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modulos\Nucleo\Models\Empresa */
class EmpresaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'ruc'              => $this->ruc,
            'razon_social'     => $this->razon_social,
            'nombre_comercial' => $this->nombre_comercial,
            'direccion'        => $this->direccion,
            'ubigeo'           => $this->ubigeo,
            'departamento'     => $this->departamento,
            'provincia'        => $this->provincia,
            'distrito'         => $this->distrito,
            'telefono'         => $this->telefono,
            'email'            => $this->email,
            'activo'           => $this->activo,
            'sunat_modo'       => $this->sunat_modo,
            'creada'           => $this->created_at?->toIso8601String(),
            'usuarios'         => $this->when(isset($this->usuarios_count), fn () => (int) $this->usuarios_count),
            'suscripcion'      => $this->whenLoaded('suscripcion', fn () => $this->suscripcion ? new SuscripcionResource($this->suscripcion) : null),
        ];
    }
}
