<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'empresa_id',
        'user_id',
        'serie',
        'numero',
        'fecha_emision',
        'tipo',
        'estado',
        'subtotal',
        'total_impuestos',
        'total',
        'metodo_pago',
        'observaciones',
    ];

    // Relación con cliente
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    // Relación con empresa
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    // Relación con usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relación con detalles de venta
    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class);
    }
}