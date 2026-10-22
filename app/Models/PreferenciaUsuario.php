<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Preferencias personales del usuario (una fila por usuario, se crea al guardar por primera vez) */
class PreferenciaUsuario extends Model
{
    protected $table = 'preferencias_usuario';

    protected $fillable = ['user_id', 'empresa_inicio_id', 'ticket_ancho', 'imprimir_al_vender', 'alertas_ocultas'];

    /** Valores por defecto cuando el usuario aún no guardó nada */
    protected $attributes = ['ticket_ancho' => '80', 'imprimir_al_vender' => false];

    protected function casts(): array
    {
        return ['imprimir_al_vender' => 'boolean', 'alertas_ocultas' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** ¿El usuario ocultó esta alerta? */
    public function ocultaAlerta(string $clave): bool
    {
        return in_array($clave, $this->alertas_ocultas ?? [], true);
    }
}
