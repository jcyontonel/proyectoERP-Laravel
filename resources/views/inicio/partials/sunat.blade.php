{{-- $sunat: aceptados, pendientes, observados, rechazados --}}
<x-card title="Facturación electrónica" icon="upload-cloud" subtitle="Estado de envíos a SUNAT">
    <div class="grid grid-cols-2 gap-2 text-center">
        @foreach ([
            ['Aceptados', $sunat['aceptados'], 'text-emerald-700 bg-emerald-50'],
            ['Pendientes', $sunat['pendientes'], 'text-slate-600 bg-slate-50'],
            ['Observados', $sunat['observados'], 'text-amber-700 bg-amber-50'],
            ['Rechazados', $sunat['rechazados'], 'text-red-600 bg-red-50'],
        ] as [$etiqueta, $valor, $tono])
            <div class="rounded-lg px-3 py-2.5 {{ $tono }}">
                <p class="text-lg font-bold">{{ number_format($valor) }}</p>
                <p class="text-[11px]">{{ $etiqueta }}</p>
            </div>
        @endforeach
    </div>
    <a href="{{ route('pronto', 'sunat') }}" class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3 text-xs font-semibold text-brand-600 hover:text-brand-700">
        Ver envíos <x-icon name="arrow-right" class="size-3.5" />
    </a>
</x-card>
