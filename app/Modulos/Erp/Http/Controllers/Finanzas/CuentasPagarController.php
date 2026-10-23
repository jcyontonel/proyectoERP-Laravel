<?php

namespace App\Modulos\Erp\Http\Controllers\Finanzas;

use App\Modulos\Erp\Http\Requests\Finanzas\RegistrarPagoRequest;
use App\Modulos\Erp\Models\Compra;
use App\Modulos\Erp\Models\Proveedor;
use App\Modulos\Erp\Services\CuentasService;
use Illuminate\Http\Request;

/** Cuentas por pagar: compras registradas con saldo pendiente con el proveedor */
class CuentasPagarController extends CuentasController
{
    protected function config(): array
    {
        return [
            'modo'         => CuentasService::PAGAR,
            'titulo'       => 'Cuentas por pagar',
            'subtitulo'    => 'Compras pendientes de pago a proveedores: vencimientos, antigüedad y pagos.',
            'tercero'      => 'Proveedor',
            'terceros'     => 'Proveedores',
            'relacion'     => 'proveedor',
            'prefijo'      => 'cuentas-pagar',
            'verbo'        => 'Pagar',
            'accion'       => 'pago',
            'documento_label' => 'Compra',
            'metodos'      => config('compras.metodos_pago'),
            'ruta_doc'     => 'compras.show',
            'ruta_tercero' => 'proveedores.edit',
            'permiso_tercero' => 'gestionar-proveedores',
            'nombre'       => fn (Proveedor $p) => $p->nombre,
            'documento'    => fn (Proveedor $p) => $p->documento_etiqueta,
        ];
    }

    public function cuenta(Request $request, Proveedor $proveedor)
    {
        return $this->estadoCuenta($request, $proveedor,
            Compra::query()->deEmpresa($proveedor->empresa_id)->where('proveedor_id', $proveedor->id));
    }

    public function registrar(RegistrarPagoRequest $request, Compra $compra)
    {
        return $this->registrarEn($request, $compra);
    }

    public function abonar(RegistrarPagoRequest $request, Proveedor $proveedor)
    {
        return $this->abonarA($request, $proveedor);
    }
}
