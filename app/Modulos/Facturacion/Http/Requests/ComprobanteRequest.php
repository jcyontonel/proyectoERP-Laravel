<?php

namespace App\Modulos\Facturacion\Http\Requests;

use App\Modulos\Facturacion\Models\Documento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** API v1: factura (01), boleta (03), nota de crédito (07) o de débito (08) de la empresa del token */
class ComprobanteRequest extends FormRequest
{
    // Catálogo 09 (NC) y 10 (ND)
    public const MOTIVOS_NC = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'];
    public const MOTIVOS_ND = ['01', '02', '03', '11'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fecha_emision'        => $this->input('fecha_emision', now()->toDateTimeString()),
            'moneda'               => strtoupper((string) $this->input('moneda', 'PEN')),
            'igv_porcentaje'       => $this->input('igv_porcentaje', config('facturacion.igv')),
            'precios_incluyen_igv' => $this->boolean('precios_incluyen_igv', true),
            'serie'                => strtoupper((string) $this->input('serie')),
        ]);
        // Boleta sin cliente identificado
        if ($this->input('tipo') === Documento::BOLETA && blank($this->input('cliente.numero'))) {
            $this->merge(['cliente' => ['tipo_doc' => '0', 'numero' => '00000000', 'nombre' => $this->input('cliente.nombre') ?: 'CLIENTES VARIOS'] + (array) $this->input('cliente', [])]);
        }
    }

    public function rules(): array
    {
        $tipo = $this->input('tipo');
        $esNota = in_array($tipo, [Documento::NOTA_CREDITO, Documento::NOTA_DEBITO], true);

        return [
            'emisor'               => ['nullable', 'digits:11'],   // opcional: el emisor es la empresa del token
            'tipo'                 => ['required', Rule::in([Documento::FACTURA, Documento::BOLETA, Documento::NOTA_CREDITO, Documento::NOTA_DEBITO])],
            'serie'                => ['required', 'string', match ($tipo) {
                Documento::FACTURA => 'regex:/^F[A-Z0-9]{3}$/',
                Documento::BOLETA  => 'regex:/^B[A-Z0-9]{3}$/',
                default            => 'regex:/^[FB][A-Z0-9]{3}$/',
            }],
            'numero'               => ['required', 'integer', 'between:1,99999999'],
            'fecha_emision'        => ['required', 'date', 'before_or_equal:' . now()->addMinutes(10)->toDateTimeString()],
            'moneda'               => ['required', Rule::in(['PEN', 'USD'])],
            'igv_porcentaje'       => ['required', 'numeric', Rule::in([18, 10, 10.5])],
            'precios_incluyen_igv' => ['boolean'],
            'observaciones'        => ['nullable', 'string', 'max:500'],
            'referencia'           => ['nullable', 'string', 'max:100'],
            'webhook_url'          => ['nullable', 'url', 'max:255'],
            'asincrono'            => ['boolean'],

            'cliente'              => ['required', 'array'],
            'cliente.tipo_doc'     => ['required', Rule::in(['0', '1', '4', '6', '7', 'A', '-'])],
            'cliente.numero'       => ['required', 'string', 'max:20'],
            'cliente.nombre'       => ['required', 'string', 'max:255'],
            'cliente.direccion'    => ['nullable', 'string', 'max:255'],
            'cliente.email'        => ['nullable', 'email', 'max:255'],

            'items'                => ['required', 'array', 'min:1', 'max:500'],
            'items.*.codigo'       => ['nullable', 'string', 'max:30'],
            'items.*.descripcion'  => ['required', 'string', 'max:500'],
            'items.*.unidad'       => ['nullable', 'string', 'max:5'],
            'items.*.cantidad'     => ['required', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.descuento'    => ['nullable', 'numeric', 'min:0'],
            'items.*.afectacion'   => ['nullable', Rule::in(['10', '20', '30', '40'])],
            'items.*.valor_venta'  => ['nullable', 'numeric', 'min:0', 'required_with:items.*.igv'],
            'items.*.igv'          => ['nullable', 'numeric', 'min:0', 'required_with:items.*.valor_venta'],

            'forma_pago'           => ['nullable', 'array'],
            'forma_pago.tipo'      => ['required_with:forma_pago', Rule::in(['contado', 'credito'])],
            'forma_pago.cuotas'    => ['required_if:forma_pago.tipo,credito', 'array', 'min:1'],
            'forma_pago.cuotas.*.monto' => ['required', 'numeric', 'gt:0'],
            'forma_pago.cuotas.*.fecha' => ['required', 'date', 'after:fecha_emision'],

            'afectado'             => [Rule::requiredIf($esNota), 'array'],
            'afectado.tipo'        => [Rule::requiredIf($esNota), Rule::in([Documento::FACTURA, Documento::BOLETA])],
            'afectado.serie'       => [Rule::requiredIf($esNota), 'string', 'regex:/^[FB][A-Z0-9]{3}$/'],
            'afectado.numero'      => [Rule::requiredIf($esNota), 'integer', 'min:1'],
            'motivo'               => [Rule::requiredIf($esNota), 'array'],
            'motivo.codigo'        => [Rule::requiredIf($esNota), Rule::in($tipo === Documento::NOTA_DEBITO ? self::MOTIVOS_ND : self::MOTIVOS_NC)],
            'motivo.descripcion'   => [Rule::requiredIf($esNota), 'string', 'max:250'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $d = $this->all();
            if (($d['tipo'] ?? null) === Documento::FACTURA && ($d['cliente']['tipo_doc'] ?? null) !== '6' && ! collect($d['items'] ?? [])->contains('afectacion', '40')) {
                $v->errors()->add('cliente.tipo_doc', 'La factura exige el RUC del cliente (tipo_doc 6).');
            }
            if (($d['cliente']['tipo_doc'] ?? null) === '6' && ! preg_match('/^(10|15|17|20)\d{9}$/', (string) ($d['cliente']['numero'] ?? ''))) {
                $v->errors()->add('cliente.numero', 'El RUC del cliente no es válido.');
            }
            if (($d['cliente']['tipo_doc'] ?? null) === '1' && ! preg_match('/^\d{8}$/', (string) ($d['cliente']['numero'] ?? ''))) {
                $v->errors()->add('cliente.numero', 'El DNI debe tener 8 dígitos.');
            }
            // La nota sigue la serie del comprobante que modifica (F… para facturas, B… para boletas)
            if (isset($d['afectado']['serie'], $d['serie']) && $d['serie'][0] !== $d['afectado']['serie'][0]) {
                $v->errors()->add('serie', 'La serie de la nota debe empezar con la misma letra que la del comprobante afectado (' . $d['afectado']['serie'] . ').');
            }
            if (isset($d['afectado']['tipo'], $d['afectado']['serie']) && ($d['afectado']['tipo'] === Documento::FACTURA) !== str_starts_with($d['afectado']['serie'], 'F')) {
                $v->errors()->add('afectado.serie', 'Las facturas usan series F… y las boletas B….');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'serie.regex'     => 'La serie no es válida: F### para facturas (y sus notas), B### para boletas (y sus notas).',
            'items.required'  => 'Envía al menos un ítem.',
            'forma_pago.cuotas.required_if' => 'Una venta al crédito debe indicar sus cuotas (monto y fecha).',
            'fecha_emision.before_or_equal' => 'La fecha de emisión no puede ser futura.',
        ];
    }
}
