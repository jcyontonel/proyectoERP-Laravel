<?php

namespace App\Modulos\Nucleo\Models;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsActivable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Tipos de afectación tributaria (catálogo SUNAT 07): gravado, exonerado, inafecto, exportación */
class Impuesto extends Model
{
    use Auditable, HasFactory, EsActivable;

    /**
     * Catálogo SUNAT 07 (tipo de afectación) => [descripción, código de tributo del catálogo 05, lleva porcentaje].
     * 1000 IGV · 1016 IVAP · 9995 exportación · 9996 gratuito · 9997 exonerado · 9998 inafecto
     */
    public const AFECTACIONES = [
        '10' => ['Gravado - Operación onerosa', '1000', true],
        '11' => ['Gravado - Retiro por premio', '9996', true],
        '12' => ['Gravado - Retiro por donación', '9996', true],
        '13' => ['Gravado - Retiro', '9996', true],
        '14' => ['Gravado - Retiro por publicidad', '9996', true],
        '15' => ['Gravado - Bonificaciones', '9996', true],
        '16' => ['Gravado - Retiro por entrega a trabajadores', '9996', true],
        '17' => ['Gravado - IVAP', '1016', true],
        '20' => ['Exonerado - Operación onerosa', '9997', false],
        '21' => ['Exonerado - Transferencia gratuita', '9996', false],
        '30' => ['Inafecto - Operación onerosa', '9998', false],
        '31' => ['Inafecto - Retiro por bonificación', '9996', false],
        '32' => ['Inafecto - Retiro', '9996', false],
        '33' => ['Inafecto - Retiro por muestras médicas', '9996', false],
        '34' => ['Inafecto - Retiro por convenio colectivo', '9996', false],
        '35' => ['Inafecto - Retiro por premio', '9996', false],
        '36' => ['Inafecto - Retiro por publicidad', '9996', false],
        '37' => ['Inafecto - Transferencia gratuita', '9996', false],
        '40' => ['Exportación de bienes o servicios', '9995', false],
    ];

    protected $fillable = ['codigo', 'nombre', 'tipo_afectacion', 'codigo_tributo', 'porcentaje', 'activo'];

    protected function casts(): array
    {
        return ['porcentaje' => 'float', 'activo' => 'boolean'];
    }
}
