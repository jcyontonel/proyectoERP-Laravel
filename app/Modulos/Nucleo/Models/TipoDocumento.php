<?php

namespace App\Modulos\Nucleo\Models;

use App\Modulos\Erp\Models\Cliente;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo SUNAT 06: codigo 1 = DNI, 6 = RUC, 4 = CE, 7 = Pasaporte… */
class TipoDocumento extends Model
{
    use HasFactory;

    protected $table = 'tipo_documentos';

    protected $fillable = ['codigo', 'abreviatura', 'descripcion'];

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'tipo_documento_id');
    }
}
