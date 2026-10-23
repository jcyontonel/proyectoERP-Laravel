<?php

namespace App\Modulos\Web\Models;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Página web de una empresa: dirección pública (slug), textos, contacto, entrega y pagos (Culqi propio) */
class Tienda extends Model
{
    use Auditable;

    public const METODOS_PAGO = [
        'contra_entrega' => 'Pago contra entrega',
        'transferencia'  => 'Transferencia bancaria',
        'yape'           => 'Yape / Plin',
        'tarjeta'        => 'Tarjeta (Culqi)',
    ];

    /** Direcciones que no pueden ser de una tienda: en la raíz conviven con los otros módulos (/erp, /plataforma, /api…) */
    public const RESERVADOS = [
        'erp', 'plataforma', 'suscripciones', 'api', 'admin', 'login', 'tienda', 'web', 'www',
        'up', 'static', 'build', 'assets', 'storage', 'sanctum',
    ];

    protected $table = 'tiendas';

    protected $fillable = [
        'empresa_id', 'slug', 'publicada', 'titulo', 'lema', 'descripcion', 'whatsapp', 'correo', 'color', 'mostrar_stock',
        'recojo_en_tienda', 'direccion_recojo', 'pedido_minimo', 'metodos_pago', 'instrucciones_pago', 'culqi_modo',
        'culqi_llave_publica', 'culqi_llave_secreta',
    ];

    protected $hidden = ['culqi_llave_secreta'];

    protected function casts(): array
    {
        return [
            'publicada' => 'boolean', 'mostrar_stock' => 'boolean', 'recojo_en_tienda' => 'boolean', 'pedido_minimo' => 'float',
            'metodos_pago' => 'array', 'culqi_llave_secreta' => 'encrypted',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** La de la empresa (la crea con un slug a partir del nombre si no existe) */
    public static function deEmpresa(Empresa $empresa): self
    {
        return self::firstOrCreate(['empresa_id' => $empresa->id], [
            'slug' => self::slugLibre($empresa->nombre_comercial ?: $empresa->razon_social),
            'titulo' => $empresa->nombre_comercial ?: $empresa->razon_social,
            'metodos_pago' => ['contra_entrega', 'transferencia'],
        ]);
    }

    public static function slugLibre(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'tienda';
        $base = in_array($base, self::RESERVADOS, true) ? "{$base}-tienda" : $base;
        $slug = $base;
        for ($i = 2; self::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function nombre(): string
    {
        return $this->titulo ?: ($this->empresa->nombre_comercial ?: $this->empresa->razon_social);
    }

    public function urlPublica(): string
    {
        return route('tienda.publica', $this->slug);
    }

    /** Métodos de pago habilitados (tarjeta solo si hay llaves de Culqi para el modo actual) */
    public function metodosDisponibles(): array
    {
        return array_values(array_filter($this->metodos_pago ?? [], fn ($m) => isset(self::METODOS_PAGO[$m])
            && ($m !== 'tarjeta' || $this->culqi()['publica'])));
    }

    /**
     * Llaves de Culqi según el modo. En prueba, si la tienda aún no tiene las suyas, se usan las de prueba de
     * la plataforma (CULQI_LLAVE_PUBLICA / CULQI_LLAVE_SECRETA en .env, que deben ser pk_test/sk_test).
     *
     * @return array{modo: string, publica: ?string, secreta: ?string}
     */
    public function culqi(): array
    {
        $propias = filled($this->culqi_llave_publica) && filled($this->culqi_llave_secreta);
        if ($this->culqi_modo === 'produccion') {
            return ['modo' => 'produccion', 'publica' => $propias ? $this->culqi_llave_publica : null, 'secreta' => $propias ? $this->culqi_llave_secreta : null];
        }
        [$publica, $secreta] = $propias ? [$this->culqi_llave_publica, $this->culqi_llave_secreta] : [config('services.culqi.llave_publica'), config('services.culqi.llave_secreta')];
        $deprueba = str_starts_with((string) $publica, 'pk_test_') && str_starts_with((string) $secreta, 'sk_test_');

        return ['modo' => 'prueba', 'publica' => $deprueba ? $publica : null, 'secreta' => $deprueba ? $secreta : null];
    }
}
