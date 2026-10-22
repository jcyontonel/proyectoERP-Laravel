<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bitácora: quién hizo qué, cuándo y desde dónde. Solo se inserta (nunca se edita ni se borra) */
class AuditoriaEvento extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auditoria_eventos';

    protected $fillable = ['empresa_id', 'user_id', 'entidad', 'entidad_id', 'accion', 'datos_anteriores', 'datos_nuevos', 'ip', 'request_id'];

    protected function casts(): array
    {
        return ['datos_anteriores' => 'array', 'datos_nuevos' => 'array', 'created_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
