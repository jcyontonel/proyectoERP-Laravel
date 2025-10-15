<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    /** @use HasFactory<\Database\Factories\EmpresaFactory> */
    use HasFactory;

    protected $hidden = ['pivot'];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function correlativos()
    {
        return $this->hasMany(Correlativo::class, 'empresa_id', 'id');
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'empresa_id', 'id');
    }

    public function productos()
    {
        return $this->hasMany(Producto::class, 'empresa_id', 'id');
    }

    public function clientes()
    {
        return $this->belongsToMany(Cliente::class, 'cliente_empresa')
                    ->withTimestamps();
    }


}
