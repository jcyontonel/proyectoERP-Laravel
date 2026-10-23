{{-- Página web → Pedidos (PedidoController@index): $pedidos (paginador), $conteo [estado => n] --}}
@extends('layouts.app')

@php use App\Modulos\Web\Models\PedidoWeb; @endphp

@section('title', 'Pedidos web')

@section('content')
    <x-page-header title="Pedidos web" :migas="['Página web' => route('web.tienda.edit'), 'Pedidos' => null]"
        subtitle="Pedidos de tu tienda en línea. Confírmalos, coordina la entrega y conviértelos en venta para emitir el comprobante." />

    <x-flash />

    <nav class="mt-6 flex flex-wrap gap-2" aria-label="Filtrar por estado">
        <a href="{{ route('web.pedidos.index') }}" @class(['rounded-full px-3 py-1.5 text-sm font-medium', 'bg-brand-600 text-white' => ! request('estado'), 'bg-white text-slate-600 ring-1 ring-slate-200' => request('estado')])>Todos</a>
        @foreach (PedidoWeb::ESTADOS as $clave => [$etiqueta])
            <a href="{{ route('web.pedidos.index', ['estado' => $clave]) }}" @class(['rounded-full px-3 py-1.5 text-sm font-medium', 'bg-brand-600 text-white' => request('estado') === $clave, 'bg-white text-slate-600 ring-1 ring-slate-200' => request('estado') !== $clave])>
                {{ $etiqueta }} <span class="opacity-70">{{ $conteo[$clave] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <section class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
                <tr><th class="py-3 pl-5 pr-3">Pedido</th><th class="px-3 py-3">Cliente</th><th class="px-3 py-3">Entrega</th><th class="px-3 py-3">Pago</th><th class="px-3 py-3 text-right">Total</th><th class="px-3 py-3">Estado</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($pedidos as $p)
                    <tr class="hover:bg-slate-50/60">
                        <td class="py-3 pl-5 pr-3">
                            <a href="{{ route('web.pedidos.show', $p) }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $p->codigo() }}</a>
                            <p class="text-xs text-slate-500">{{ $p->created_at->format('d/m/Y H:i') }}</p>
                        </td>
                        <td class="px-3 py-3"><p>{{ $p->cliente_nombre }}</p><p class="text-xs text-slate-500">{{ $p->cliente_telefono }}</p></td>
                        <td class="px-3 py-3 text-xs">{{ $p->entrega === 'envio' ? 'Envío · ' . ($p->zona?->nombre ?? '') : 'Recojo en tienda' }}</td>
                        <td class="px-3 py-3 text-xs">{{ \App\Modulos\Web\Models\Tienda::METODOS_PAGO[$p->metodo_pago] ?? $p->metodo_pago }}
                            @if ($p->pago_estado === 'pagado')<x-badge tone="success">Pagado</x-badge>@endif</td>
                        <td class="px-3 py-3 text-right font-semibold"><x-money :value="$p->total" /></td>
                        <td class="px-3 py-3"><x-badge :tone="$p->tono()" dot>{{ $p->etiqueta() }}</x-badge>@if ($p->venta_id)<x-badge tone="info">Con venta</x-badge>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="shopping-cart" title="Sin pedidos" description="Cuando tus clientes compren en tu página web, sus pedidos aparecerán aquí." /></td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($pedidos->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $pedidos->links() }}</div>@endif
    </section>
@endsection
