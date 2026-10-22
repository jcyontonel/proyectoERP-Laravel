<?php

namespace App\Models;

use App\Models\Concerns\EsActivable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Catálogo ISO 4217 (PEN, USD). Los documentos guardan el código en su columna `moneda` */
class Moneda extends Model
{
    use HasFactory, EsActivable;

    protected $fillable = ['codigo', 'nombre', 'simbolo', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
