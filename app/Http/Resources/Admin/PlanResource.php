<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Plan */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'codigo'               => $this->codigo,
            'nombre'               => $this->nombre,
            'descripcion'          => $this->descripcion,
            'max_comprobantes_mes' => $this->max_comprobantes_mes,
            'max_productos'        => $this->max_productos,
            'max_usuarios'         => $this->max_usuarios,
            'max_almacenes'        => $this->max_almacenes,
            'dias_prueba'          => $this->dias_prueba,
            'activo'               => $this->activo,
            'orden'                => $this->orden,
            'precios'              => $this->whenLoaded('precios', fn () => $this->precios->map(fn ($p) => [
                'id' => $p->id, 'periodicidad' => $p->periodicidad, 'meses' => $p->meses, 'precio' => $p->precio, 'activo' => $p->activo,
            ])->values()),
            'empresas'             => $this->when(isset($this->suscripciones_vigentes_count), fn () => (int) $this->suscripciones_vigentes_count),
        ];
    }
}
