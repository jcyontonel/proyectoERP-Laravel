<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Correlativo extends Model
{
    protected $table = 'correlativos';
    protected $fillable = ['empresa_id', 'tipo', 'serie', 'numero'];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
