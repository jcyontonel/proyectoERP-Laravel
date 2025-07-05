<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'empresa_id'=> $this->empresa_id,
            'name'      => $this->name,
            'apellido'  => $this->apellido,
            'telefono'  => $this->telefono,
            'email'     => $this->email,
            'rol'       => $this->getRoleNames()->first() ?? null,
            'created_at'=> $this->created_at,
            'updated_at'=> $this->updated_at,
        ];
    }
}
