<?php

namespace App\Models;

use App\Models\Concerns\EsActivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Precio de un plan para una periodicidad (mensual, trimestral, anual) */
class PlanPrecio extends Model
{
    use EsActivable;

    protected $table = 'plan_precios';

    protected $fillable = ['plan_id', 'periodicidad', 'meses', 'precio', 'activo'];

    protected function casts(): array
    {
        return ['meses' => 'integer', 'precio' => 'float', 'activo' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
