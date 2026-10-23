<?php

namespace App\Modulos\Catalogo\Http\Requests;

use App\Modulos\Catalogo\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;

/** Filtros del listado de productos (GET) */
class FiltrarProductosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Producto::class);
    }

    public function rules(): array
    {
        return [
            'q'            => ['nullable', 'string', 'max:100'],
            'categoria_id' => ['nullable', 'integer'],
            'tipo'         => ['nullable', 'in:producto,servicio'],
            'estado'       => ['nullable', 'in:activos,inactivos'],
            'stock'        => ['nullable', 'in:bajo,agotado'],
            'orden'        => ['nullable', 'in:nombre,precio,stock,recientes'],
            'page'         => ['nullable', 'integer', 'min:1'],
        ];
    }
}
