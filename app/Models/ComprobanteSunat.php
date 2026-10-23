<?php

namespace App\Models;

use App\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Estado en SUNAT de una venta (factura/boleta) o nota, según el facturador electrónico.
 * facturador_id null = todavía no llegó al facturador (sin conexión): lo reintenta facturacion:sincronizar.
 */
class ComprobanteSunat extends Model
{
    use EsDeEmpresa;

    public const PENDIENTE = 'pendiente';
    public const ENVIADO = 'enviado';
    public const ACEPTADO = 'aceptado';
    public const OBSERVADO = 'observado';
    public const RECHAZADO = 'rechazado';
    public const ERROR = 'error';
    public const ANULADO = 'anulado';

    /** estado => [etiqueta, tono del badge] */
    public const ESTADOS = [
        self::PENDIENTE => ['Pendiente de envío', 'warning'],
        self::ENVIADO   => ['Enviado, en proceso', 'info'],
        self::ACEPTADO  => ['Aceptado por SUNAT', 'success'],
        self::OBSERVADO => ['Aceptado con observaciones', 'success'],
        self::RECHAZADO => ['Rechazado por SUNAT', 'danger'],
        self::ERROR     => ['Error de envío', 'danger'],
        self::ANULADO   => ['Anulado en SUNAT', 'neutral'],
    ];

    protected $table = 'comprobantes_sunat';

    protected $fillable = [
        'empresa_id', 'comprobante_type', 'comprobante_id', 'facturador_id', 'tipo', 'serie', 'numero', 'nombre_archivo', 'hash',
        'estado', 'codigo_respuesta', 'mensaje_respuesta', 'observaciones', 'ticket', 'baja_estado', 'intentos', 'proximo_intento_at',
        'enviado_at', 'respondido_at',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer', 'observaciones' => 'array', 'intentos' => 'integer', 'proximo_intento_at' => 'datetime',
            'enviado_at' => 'datetime', 'respondido_at' => 'datetime',
        ];
    }

    public function comprobante(): MorphTo
    {
        return $this->morphTo();
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function fueAceptado(): bool
    {
        return in_array($this->estado, [self::ACEPTADO, self::OBSERVADO], true);
    }

    /** Puede reenviarse a mano (o corregirse y reenviarse) */
    public function esReenviable(): bool
    {
        return in_array($this->estado, [self::PENDIENTE, self::RECHAZADO, self::ERROR], true);
    }

    public function etiqueta(): string
    {
        return self::ESTADOS[$this->estado][0] ?? $this->estado;
    }

    public function tono(): string
    {
        return self::ESTADOS[$this->estado][1] ?? 'neutral';
    }

    public function numeroCompleto(): string
    {
        return $this->serie . '-' . $this->numero;
    }

    /** Filtros: estado, tipo, q (serie-número), desde, hasta */
    public function scopeFiltrar(Builder $q, array $f): void
    {
        $q->when(filled($f['estado'] ?? null), fn (Builder $w) => $w->where('estado', $f['estado']))
            ->when(filled($f['tipo'] ?? null), fn (Builder $w) => $w->where('tipo', $f['tipo']))
            ->when(filled($f['q'] ?? null), function (Builder $w) use ($f) {
                $partes = explode('-', strtoupper(trim($f['q'])));
                $w->whereLike('serie', '%' . $partes[0] . '%')
                    ->when(isset($partes[1]) && ctype_digit($partes[1]), fn (Builder $n) => $n->where('numero', (int) $partes[1]));
            })
            ->when(filled($f['desde'] ?? null), fn (Builder $w) => $w->whereDate('created_at', '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn (Builder $w) => $w->whereDate('created_at', '<=', $f['hasta']));
    }
}
