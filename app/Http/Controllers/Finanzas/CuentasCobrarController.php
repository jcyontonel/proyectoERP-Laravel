<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Requests\Finanzas\RegistrarPagoRequest;
use App\Models\Cliente;
use App\Models\Venta;
use App\Services\CuentasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Cuentas por cobrar: ventas al crédito con saldo */
class CuentasCobrarController extends CuentasController
{
    protected function config(): array
    {
        return [
            'modo'         => CuentasService::COBRAR,
            'titulo'       => 'Cuentas por cobrar',
            'subtitulo'    => 'Ventas al crédito pendientes de cobro: vencimientos, antigüedad de la deuda y cobranza.',
            'tercero'      => 'Cliente',
            'terceros'     => 'Clientes',
            'relacion'     => 'cliente',
            'prefijo'      => 'cuentas-cobrar',
            'verbo'        => 'Cobrar',
            'accion'       => 'cobro',
            'documento_label' => 'Venta',
            'metodos'      => config('ventas.metodos_pago'),
            'ruta_doc'     => 'ventas.show',
            'ruta_tercero' => 'clientes.show',
            'permiso_tercero' => null,
            'nombre'       => fn (Cliente $c) => $c->nombre_completo,
            'documento'    => fn (Cliente $c) => $c->documento_etiqueta,
        ];
    }

    public function cuenta(Request $request, Cliente $cliente)
    {
        Gate::authorize('view', $cliente);

        return $this->estadoCuenta($request, $cliente,
            Venta::query()->deEmpresa($this->empresaId($request))->where('cliente_id', $cliente->id)
                ->where('condicion_pago', Venta::CREDITO)->whereIn('estado', [Venta::COMPLETADA, Venta::ANULADA]));
    }

    public function registrar(RegistrarPagoRequest $request, Venta $venta)
    {
        return $this->registrarEn($request, $venta);
    }

    public function abonar(RegistrarPagoRequest $request, Cliente $cliente)
    {
        Gate::authorize('view', $cliente);

        return $this->abonarA($request, $cliente);
    }
}
