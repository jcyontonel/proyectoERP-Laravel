<?php

namespace App\Models\Concerns;

use App\Services\AuditoriaService;

/** Registra en auditoria_eventos las altas, cambios (solo lo que cambió) y bajas del modelo */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => app(AuditoriaService::class)->modelo($m, 'creado'));
        static::updated(fn ($m) => app(AuditoriaService::class)->modelo($m, 'actualizado'));
        static::deleted(fn ($m) => app(AuditoriaService::class)->modelo($m, 'eliminado'));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($m) => app(AuditoriaService::class)->registrar('restaurado', class_basename($m), $m->getKey()));
        }
    }
}
