{{-- $compras: últimas 5 compras (proveedor, pagado) --}}
<x-card title="Últimas compras" icon="truck">
    <x-slot:aside>
        <a href="{{ route('compras.index') }}" class="inline-flex items-center gap-1 font-semibold text-brand-600 hover:text-brand-700">Ver todas <x-icon name="arrow-right" class="size-3.5" /></a>
    </x-slot:aside>

    @forelse ($compras as $c)
        <a href="{{ route('compras.show', $c) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 py-2.5 text-sm last:border-0 hover:text-brand-600">
            <span class="min-w-0">
                <span class="block truncate font-medium">{{ $c->proveedor?->nombre }}</span>
                <span class="block text-[11px] text-slate-500">{{ $c->numero_completo }} · {{ $c->fecha_emision->translatedFormat('d M') }}</span>
            </span>
            <span class="shrink-0 text-right">
                <span class="block font-semibold"><x-money :value="$c->total" /></span>
                @if ($c->esAnulada())
                    <x-estado-compra estado="anulada" />
                @else
                    <x-estado-pago :estado="$c->estadoPago()" />
                @endif
            </span>
        </a>
    @empty
        <p class="text-sm text-slate-500">Aún no hay compras registradas.</p>
    @endforelse
</x-card>
