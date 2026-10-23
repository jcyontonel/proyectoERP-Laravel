<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\EsActivable;
use App\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Correlativo extends Model
{
    use Auditable, EsActivable, EsDeEmpresa, SoftDeletes;

    /** correlativos.modulo: el formulario de ventas solo usa las series de este módulo */
    public const MODULO_VENTA = 'venta';

    /**
     * Series que se pueden configurar: modulo => [tipo => [descripción, patrón de la serie, ejemplo]].
     * Facturas y boletas siguen el formato SUNAT (F### / B###); las notas, el del comprobante que modifican.
     */
    public const TIPOS = [
        'venta' => [
            '01' => ['Factura', '/^F[A-Z0-9]{3}$/', 'F001'],
            '03' => ['Boleta', '/^B[A-Z0-9]{3}$/', 'B001'],
            '00' => ['Ticket', '/^[A-Z0-9]{4}$/', 'T001'],
        ],
        'nota' => [
            '07' => ['Nota de crédito', '/^[FB][A-Z0-9]{3}$/', 'FC01'],
            '08' => ['Nota de débito', '/^[FB][A-Z0-9]{3}$/', 'FD01'],
        ],
        'ajuste'          => ['AJ' => ['Ajuste de inventario', '/^[A-Z0-9]{2,10}$/', 'AJ01']],
        'transferencia'   => ['TR' => ['Transferencia', '/^[A-Z0-9]{2,10}$/', 'TR01']],
        'devolucion'      => ['DV' => ['Devolución', '/^[A-Z0-9]{2,10}$/', 'DV01']],
        'fraccionamiento' => ['FR' => ['Fraccionamiento', '/^[A-Z0-9]{2,10}$/', 'FR01']],
        'cotizacion'      => ['CT' => ['Cotización', '/^[A-Z0-9]{2,10}$/', 'CT01']],
        'orden_compra'    => ['OC' => ['Orden de compra', '/^[A-Z0-9]{2,10}$/', 'OC01']],
        // Comprobantes de los pagos de suscripción al ERP (solo en la empresa emisora de la plataforma)
        'suscripcion' => [
            '01' => ['Factura de suscripción', '/^F[A-Z0-9]{3}$/', 'F900'],
            '03' => ['Boleta de suscripción', '/^B[A-Z0-9]{3}$/', 'B900'],
        ],
    ];

    public const MODULOS = [
        'venta'           => 'Ventas',
        'nota'            => 'Notas de crédito y débito',
        'ajuste'          => 'Ajustes de inventario',
        'transferencia'   => 'Transferencias',
        'devolucion'      => 'Devoluciones',
        'fraccionamiento' => 'Fraccionamientos',
        'cotizacion'      => 'Cotizaciones',
        'orden_compra'    => 'Órdenes de compra',
        'suscripcion'     => 'Suscripciones (plataforma)',
    ];

    protected $fillable = ['empresa_id', 'modulo', 'tipo', 'destipo', 'serie', 'numero', 'activo'];

    protected function casts(): array
    {
        return ['numero' => 'integer', 'activo' => 'boolean'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** ¿Este comprobante exige cliente con RUC? (facturas) */
    public function requiereRuc(): bool
    {
        $texto = Str::lower($this->tipo . ' ' . $this->destipo);

        return Str::contains($texto, 'factura') || $this->tipo === '01';
    }

    /** 129 → "000129" (dígitos según config/ventas.php) */
    public static function formatear(int $numero): string
    {
        return str_pad((string) $numero, (int) config('ventas.numero_pad', 6), '0', STR_PAD_LEFT);
    }

    /** Sin documentos emitidos: aún se puede cambiar la serie o eliminarla */
    public function sinUso(): bool
    {
        return (int) $this->numero === 0;
    }

    /** Número que se emitirá a continuación (solo informativo para el formulario) */
    public function siguiente(): string
    {
        return self::formatear($this->numero + 1);
    }

    /**
     * Avanza el correlativo y devuelve el número emitido.
     * `numero` guarda el último emitido. Llamar SIEMPRE dentro de una transacción
     * con la fila bloqueada (lockForUpdate).
     */
    public function emitir(): string
    {
        $this->numero = $this->numero + 1;
        $this->saveQuietly();   // sin auditoría: cada documento emitido ya queda registrado

        return self::formatear($this->numero);
    }
}
