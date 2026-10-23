<?php

namespace App\Modulos\Facturacion\Models;

use App\Modulos\Facturacion\Support\Emisor;
use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use App\Modulos\Nucleo\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Comprobante electrónico (01 factura, 03 boleta, 07 NC, 08 ND) o documento de baja (RA comunicación
 * de baja, RC resumen diario de boletas) de una empresa, con su estado en SUNAT.
 *  - comprobante: lo que lo originó en la plataforma (venta, nota, pago de suscripción); null si llegó por la API.
 *  - datos: lo que se firmó (cliente, ítems ya calculados, totales); null = aún no se registró el contenido.
 *  - sistema_id: sistema externo que lo emitió por la API v1 (se le avisa por webhook).
 */
class Documento extends Model
{
    use EsDeEmpresa;

    public const FACTURA = '01';
    public const BOLETA = '03';
    public const NOTA_CREDITO = '07';
    public const NOTA_DEBITO = '08';
    public const BAJA = 'RA';
    public const RESUMEN = 'RC';

    public const COMPROBANTES = [self::FACTURA, self::BOLETA, self::NOTA_CREDITO, self::NOTA_DEBITO];

    public const TIPOS = [
        self::FACTURA => 'Factura', self::BOLETA => 'Boleta de venta', self::NOTA_CREDITO => 'Nota de crédito',
        self::NOTA_DEBITO => 'Nota de débito', self::BAJA => 'Comunicación de baja', self::RESUMEN => 'Resumen diario',
    ];

    public const PENDIENTE = 'pendiente';   // guardado; se envía (o reintenta) cuando hay conexión
    public const ENVIADO = 'enviado';       // SUNAT dio ticket (RA/RC); falta consultar el resultado
    public const ACEPTADO = 'aceptado';
    public const OBSERVADO = 'observado';   // aceptado con observaciones (códigos 4000+)
    public const RECHAZADO = 'rechazado';   // códigos 2000-3999: corregir y volver a enviar
    public const ERROR = 'error';           // no se puede enviar (datos, configuración, plazo vencido…)
    public const ANULADO = 'anulado';       // dado de baja por RA/RC aceptado (o anulado antes de enviarse)

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

    protected $table = 'documentos';

    protected $fillable = [
        'uuid', 'empresa_id', 'comprobante_type', 'comprobante_id', 'sistema_id', 'tipo', 'serie', 'numero', 'fecha_emision',
        'moneda', 'cliente_tipo_doc', 'cliente_num_doc', 'cliente_nombre', 'total', 'datos', 'afectado_id', 'baja_id', 'estado',
        'nombre_archivo', 'hash', 'xml_path', 'cdr_path', 'ticket', 'sunat_codigo', 'sunat_descripcion', 'sunat_observaciones',
        'intentos', 'proximo_intento_at', 'ultimo_error', 'enviado_at', 'respondido_at', 'referencia', 'webhook_url',
        'webhook_estado', 'webhook_intentos', 'webhook_proximo_at', 'webhook_ultimo_error',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer', 'fecha_emision' => 'datetime', 'total' => 'float', 'datos' => 'array', 'sunat_observaciones' => 'array',
            'intentos' => 'integer', 'proximo_intento_at' => 'datetime', 'enviado_at' => 'datetime', 'respondido_at' => 'datetime',
            'webhook_intentos' => 'integer', 'webhook_proximo_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $d) => $d->uuid ??= (string) Str::uuid());
    }

    /* ───────── Relaciones ───────── */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** Venta, nota o pago de suscripción que lo originó */
    public function comprobante(): MorphTo
    {
        return $this->morphTo();
    }

    public function sistema(): BelongsTo
    {
        return $this->belongsTo(SistemaExterno::class, 'sistema_id');
    }

    /** NC/ND o baja → comprobante original */
    public function afectado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'afectado_id');
    }

    /** Comprobante → su RA/RC de anulación (la última) */
    public function baja(): BelongsTo
    {
        return $this->belongsTo(self::class, 'baja_id');
    }

    public function emisor(): Emisor
    {
        return new Emisor($this->empresa);
    }

    /* ───────── Estado ───────── */

    public function esComprobante(): bool
    {
        return in_array($this->tipo, self::COMPROBANTES, true);
    }

    /** RA y RC se envían con sendSummary y responden con ticket */
    public function usaTicket(): bool
    {
        return in_array($this->tipo, [self::BAJA, self::RESUMEN], true);
    }

    /** Boletas y sus notas (serie B…): se anulan por resumen diario; el resto por comunicación de baja */
    public function esDeBoleta(): bool
    {
        return str_starts_with($this->serie, 'B');
    }

    public function fueAceptado(): bool
    {
        return in_array($this->estado, [self::ACEPTADO, self::OBSERVADO], true);
    }

    /** Se puede corregir y volver a enviar con el mismo número */
    public function esReemplazable(): bool
    {
        return in_array($this->estado, [self::PENDIENTE, self::RECHAZADO, self::ERROR], true);
    }

    /** Puede reenviarse a mano desde la plataforma */
    public function esReenviable(): bool
    {
        return in_array($this->estado, [self::PENDIENTE, self::RECHAZADO, self::ERROR], true)
            || in_array($this->baja?->estado, [self::PENDIENTE, self::ERROR], true);
    }

    /** Estado de la anulación en SUNAT (RA/RC): null si no se pidió */
    public function getBajaEstadoAttribute(): ?string
    {
        return $this->baja_id ? $this->baja?->estado : null;
    }

    /** Lo que SUNAT respondió o, si no llegó a responder, el último error del envío */
    public function mensaje(): ?string
    {
        return $this->sunat_descripcion ?: $this->ultimo_error;
    }

    public function tieneArchivos(): bool
    {
        return (bool) $this->xml_path;
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

    /* ───────── Scopes ───────── */

    /** Facturas, boletas y notas (sin los RA/RC de anulación) */
    public function scopeComprobantes(Builder $q): void
    {
        $q->whereIn('tipo', self::COMPROBANTES);
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
            ->when(filled($f['desde'] ?? null), fn (Builder $w) => $w->whereDate('fecha_emision', '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn (Builder $w) => $w->whereDate('fecha_emision', '<=', $f['hasta']));
    }
}
