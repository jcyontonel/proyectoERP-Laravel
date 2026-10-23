<?php

namespace App\Modulos\Plataforma\Http\Resources;

use App\Modulos\Plataforma\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modulos\Plataforma\Models\Plan */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'producto'             => $this->producto?->codigo,
            'producto_nombre'      => $this->producto?->nombre,
            'codigo'               => $this->codigo,
            'nombre'               => $this->nombre,
            'descripcion'          => $this->descripcion,
            'max_comprobantes_mes' => $this->max_comprobantes_mes,
            'max_productos'        => $this->max_productos,
            'max_usuarios'         => $this->max_usuarios,
            'max_almacenes'        => $this->max_almacenes,
            'max_empresas'         => $this->max_empresas,
            'dias_prueba'          => $this->dias_prueba,
            'activo'               => $this->activo,
            'orden'                => $this->orden,
            'modulos'              => $this->modulos ?? Plan::opcionalesDe($this->codigoProducto()),   // opcionales elegidas (null = todas)
            'funcionalidades'      => $this->funcionalidades(),   // base del sistema + opcionales
            'precios'              => $this->whenLoaded('precios', fn () => $this->precios->map(fn ($p) => [
                'id' => $p->id, 'periodicidad' => $p->periodicidad, 'meses' => $p->meses, 'precio' => $p->precio, 'activo' => $p->activo,
            ])->values()),
            'empresas'             => $this->when(isset($this->suscripciones_vigentes_count), fn () => (int) $this->suscripciones_vigentes_count),
        ];
    }
}
