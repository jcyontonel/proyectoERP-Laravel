{{-- $porVencer: producto_id, producto, lote, vence, dias, cantidad --}}
<x-card title="Lotes por vencer" icon="calendar-clock" :subtitle="'Próximos ' . \App\Services\NotificacionService::DIAS_POR_VENCER . ' días, con stock'">
    @forelse ($porVencer as $l)
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2.5 text-sm last:border-0">
            <div class="min-w-0">
                <a href="{{ route('productos.show', $l->producto_id) }}" class="block truncate font-medium hover:text-brand-600">{{ $l->producto }}</a>
                <p class="text-[11px] text-slate-500">Lote {{ $l->lote }} · {{ $l->cantidad }} unid.</p>
            </div>
            @if ($l->dias < 0)
                <x-badge tone="danger" shape="box">Vencido</x-badge>
            @else
                <x-badge :tone="$l->dias <= 7 ? 'danger' : 'warning'" shape="box">{{ $l->dias === 0 ? 'Hoy' : $l->dias . ' días' }}</x-badge>
            @endif
        </div>
    @empty
        <p class="rounded-lg bg-emerald-50 px-3 py-3 text-xs text-emerald-700">Ningún lote con stock vence pronto.</p>
    @endforelse
</x-card>
