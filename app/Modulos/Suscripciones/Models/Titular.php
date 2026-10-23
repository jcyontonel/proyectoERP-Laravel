<?php

namespace App\Modulos\Suscripciones\Models;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Titular comercial: quien paga las suscripciones (RUC o DNI). Puede pagar varias, de varias empresas;
 * los comprobantes de sus pagos salen a su nombre. No da acceso a las empresas (eso es de los usuarios).
 */
class Titular extends Model
{
    use Auditable;

    protected $table = 'titulares';

    protected $fillable = ['tipo_documento', 'numero_documento', 'nombre', 'correo', 'telefono', 'direccion'];

    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class);
    }

    /** El titular con los datos fiscales de la empresa (lo crea si no existe) */
    public static function deEmpresa(Empresa $empresa): self
    {
        $doc = (string) $empresa->ruc;

        return self::firstOrCreate(['numero_documento' => $doc], [
            'tipo_documento' => strlen($doc) === 11 ? '6' : '1', 'nombre' => $empresa->razon_social,
            'correo' => $empresa->email, 'telefono' => $empresa->telefono, 'direccion' => $empresa->direccion,
        ]);
    }

    public function esRuc(): bool
    {
        return $this->tipo_documento === '6';
    }
}
