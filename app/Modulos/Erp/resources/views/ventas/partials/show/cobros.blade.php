{{-- Venta al crédito: vencimiento, cobros (pagos.usuario cargados) y saldo --}}
@php
    $metodos = config('ventas.metodos_pago');
    $atraso = $venta->diasVencido();
@endphp

<x-card title="Crédito y cobros" icon="coins" class="mt-5">
    <x-slot:aside>
        @if ($venta->saldo() <= 0)
            <x-badge tone="success" shape="box">Cancelada</x-badge>
        @elseif ($atraso > 0)
            <x-badge tone="danger" shape="box">{{ $atraso }} d. de atraso</x-badge>
        @else
            <x-badge tone="warning" shape="box">Por cobrar</x-badge>
        @endif
    </x-slot:aside>

    <dl class="space-y-2 text-sm">
        <div class="flex justify-between"><dt class="text-slate-600">Vence el</dt><dd class="font-medium">{{ $venta->fecha_vencimiento?->translatedFormat('d M Y') ?? '—' }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-600">Cobrado</dt><dd class="font-medium text-emerald-700"><x-money :value="$venta->montoPagado()" /></dd></div>
        <div class="flex justify-between border-t border-slate-200 pt-2"><dt class="font-semibold">Saldo</dt><dd class="font-bold"><x-money :value="$venta->saldo()" /></dd></div>
    </dl>

    @if ($venta->pagos->isNotEmpty())
        <ul class="mt-3 divide-y divide-slate-100 border-t border-slate-200">
            @foreach ($venta->pagos->sortByDesc('fecha_pago') as $pago)
                @php $anulado = $pago->estado !== 'registrado'; @endphp
                <li class="flex items-start justify-between gap-3 py-2.5 text-sm">
                    <div class="min-w-0">
                        <p @class(['font-medium', 'line-through text-slate-400' => $anulado])>{{ $metodos[$pago->metodo_pago] ?? Str::headline($pago->metodo_pago) }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $pago->fecha_pago->translatedFormat('d M Y') }}
                            @if ($pago->referencia) · Op. {{ $pago->referencia }} @endif
                            @if ($pago->usuario) · {{ $pago->usuario->name }} @endif
                            @if ($anulado) · anulado @endif
                        </p>
                    </div>
                    <p @class(['shrink-0 font-semibold', 'line-through text-slate-400' => $anulado])><x-money :value="$pago->monto" /></p>
                </li>
            @endforeach
        </ul>
    @endif

    @can('registrar-pagos')
        @if ($venta->puedeRegistrarCobro())
            <button type="button" class="btn btn-primary mt-3 w-full"
                data-pagar="{{ route('cuentas-cobrar.registrar', $venta) }}" data-titulo="Cobrar {{ $venta->numero_completo }}"
                data-detalle="{{ $venta->cliente?->nombre_completo ?? $venta->numero_completo }}" data-saldo="{{ number_format($venta->saldo(), 2, '.', '') }}">
                <x-icon name="coins" /> Registrar cobro
            </button>
        @endif
        @if ($venta->cliente)
            <a href="{{ route('cuentas-cobrar.cuenta', $venta->cliente) }}" class="mt-2 block text-center text-xs font-semibold text-brand-600 hover:text-brand-700">Ver estado de cuenta del cliente</a>
        @endif
    @endcan
</x-card>
