<?php

namespace App\Modulos\Web\Http\Controllers;

use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Erp\Services\ClienteService;
use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Nucleo\Models\TipoDocumento;
use App\Modulos\Web\Models\PedidoWeb;
use App\Modulos\Web\Models\Tienda;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Página web → Pedidos (plan Tienda): atenderlos y convertirlos en venta (factura o boleta) */
class PedidoController extends Controller
{
    use ResuelveEmpresa;

    public function index(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $f = $request->validate(['estado' => ['nullable', Rule::in(array_keys(PedidoWeb::ESTADOS))], 'q' => ['nullable', 'string', 'max:60']]);
        $base = PedidoWeb::query()->deEmpresa($empresaId);

        return view('web.pedidos.index', [
            'pedidos' => (clone $base)->with('zona')
                ->when(filled($f['estado'] ?? null), fn ($q) => $q->where('estado', $f['estado']))
                ->when(filled($f['q'] ?? null), fn ($q) => $q->where(fn ($w) => $w->whereLike('cliente_nombre', '%' . $f['q'] . '%')
                    ->orWhereLike('cliente_telefono', '%' . $f['q'] . '%')->orWhere('numero', (int) preg_replace('/\D/', '', $f['q']))))
                ->latest('id')->paginate(25)->withQueryString(),
            'conteo'  => (clone $base)->selectRaw('estado, COUNT(*) AS n')->groupBy('estado')->pluck('n', 'estado'),
        ]);
    }

    public function show(PedidoWeb $pedido)
    {
        return view('web.pedidos.show', ['pedido' => $pedido->load('detalles.producto', 'zona', 'venta'), 'metodos' => Tienda::METODOS_PAGO]);
    }

    public function estado(Request $request, PedidoWeb $pedido)
    {
        $d = $request->validate(['estado' => ['required', Rule::in(array_keys(PedidoWeb::ESTADOS))]]);
        if (! $pedido->puedePasarA($d['estado'])) {
            return back()->with('error', "El pedido está «{$pedido->etiqueta()}»: no puede pasar a «" . PedidoWeb::ESTADOS[$d['estado']][0] . '».');
        }
        $pedido->update(['estado' => $d['estado']]);

        return back()->with('status', "Pedido {$pedido->codigo()}: {$pedido->etiqueta()}.");
    }

    /** Abre «Nueva venta» con el cliente y los productos del pedido; al registrarla, el pedido queda con su venta */
    public function convertir(Request $request, PedidoWeb $pedido, ClienteService $clientes)
    {
        if (! $pedido->esConvertible()) {
            return back()->with('error', $pedido->venta_id ? 'El pedido ya tiene su comprobante.' : 'Un pedido cancelado no se convierte en venta.');
        }
        $pedido->load('detalles');

        return redirect()->route('ventas.create')->withInput([
            'pedido_id'     => $pedido->id,
            'pedido_ref'    => $pedido->codigo(),
            'cliente_id'    => $this->cliente($pedido, $clientes)?->id,
            'observaciones' => "Pedido web {$pedido->codigo()}" . ($pedido->entrega === 'envio' ? " · envío a {$pedido->direccion}" : ' · recojo en tienda'),
            'productos'     => $pedido->detalles->whereNotNull('producto_id')->map(fn ($d) => ['id' => $d->producto_id, 'cantidad' => $d->cantidad])->values()->all(),
        ]);
    }

    /** El cliente de la empresa con el documento del pedido (lo registra si no existe); sin documento, venta sin cliente */
    private function cliente(PedidoWeb $pedido, ClienteService $clientes): ?Cliente
    {
        if (blank($pedido->cliente_documento) || blank($pedido->cliente_tipo_doc)) {
            return null;
        }
        $tipoId = (int) TipoDocumento::where('codigo', $pedido->cliente_tipo_doc)->value('id');
        $existente = Cliente::query()->deEmpresa($pedido->empresa_id)->where('tipo_documento_id', $tipoId)->where('numero_documento', $pedido->cliente_documento)->first();
        if ($existente) {
            return $existente;
        }

        try {
            return $clientes->registrar([
                'tipo_documento_id' => $tipoId, 'numero_documento' => $pedido->cliente_documento, 'nombre' => $pedido->cliente_nombre,
                'telefono' => $pedido->cliente_telefono, 'correo' => $pedido->cliente_correo, 'direccion' => $pedido->direccion,
            ], $pedido->empresa_id);
        } catch (ValidationException) {
            return null;   // se elige el cliente a mano en la venta
        }
    }
}
