{{-- NotaController@show: $nota (venta.cliente, detalles, usuario, devolucion) --}}
@extends('layouts.app')

@php $v = $nota->venta; @endphp

@section('title', $nota->tipo_etiqueta . ' ' . $nota->numero_completo)

@section('content')
    <x-page-header :title="$nota->numero_completo" :migas="['Notas' => route('notas.index'), $nota->numero_completo => null]"
        :subtitle="$nota->tipo_etiqueta . ' sobre ' . $v->tipo_etiqueta . ' ' . $v->numero_completo . ' · ' . $nota->fecha_emision->translatedFormat('d M Y, h:i A') . ($nota->usuario ? ' · por ' . $nota->usuario->name : '')">
        <x-slot:badge>
            <x-badge :tone="$nota->esAnulada() ? 'neutral' : ($nota->esCredito() ? 'info' : 'warning')" dot>{{ $nota->esAnulada() ? 'Anulada' : 'Emitida' }}</x-badge>
        </x-slot:badge>
        <x-slot:acciones>
            <a href="{{ route('notas.ticket', $nota) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icon name="printer" class="text-brand-600" /> Imprimir</a>
            <a href="{{ route('ventas.show', $v) }}" class="btn btn-secondary"><x-icon name="file-text" class="text-brand-600" /> Ver {{ $v->numero_completo }}</a>
            @unless ($nota->esAnulada())
                <button type="button" data-anular="{{ route('notas.anular', $nota) }}" data-numero="{{ $nota->numero_completo }}" class="btn btn-secondary text-red-600 hover:bg-red-50"><x-icon name="x-circle" /> Anular</button>
            @endunless
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <x-card title="Detalle" icon="file-minus">
            <p class="mb-3 text-sm"><span class="text-slate-500">Motivo:</span> <strong>{{ $nota->motivo_codigo }} · {{ $nota->motivo_descripcion }}</strong></p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-slate-500">
                        <tr><th class="py-2 pr-3">Descripción</th><th class="px-3 py-2 text-right">Cant.</th><th class="px-3 py-2 text-right">Base</th><th class="px-3 py-2 text-right">IGV</th><th class="py-2 pl-3 text-right">Total</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($nota->detalles as $d)
                            <tr>
                                <td class="py-2 pr-3">{{ $d->descripcion }}</td>
                                <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format($d->cantidad, 4, '.', ''), '0'), '.') }}</td>
                                <td class="px-3 py-2 text-right"><x-money :value="$d->subtotal" /></td>
                                <td class="px-3 py-2 text-right"><x-money :value="$d->total_impuestos" /></td>
                                <td class="py-2 pl-3 text-right font-medium"><x-money :value="$d->total" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($nota->devolucion)
                <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                    <x-icon name="package" class="size-3.5" /> Devolución {{ $nota->devolucion->serie }}-{{ \App\Modulos\Erp\Models\Correlativo::formatear($nota->devolucion->numero) }}:
                    el stock volvió a {{ $nota->devolucion->almacen?->nombre ?? 'el almacén' }}{{ $nota->devolucion->estado === 'anulada' ? ' (revertida al anular la nota)' : '' }}.
                </p>
            @endif
        </x-card>

        <aside class="space-y-5">
            <x-card title="Importes" icon="coins">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-600">Base imponible</dt><dd><x-money :value="$nota->subtotal" /></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">IGV</dt><dd><x-money :value="$nota->total_impuestos" /></dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2"><dt class="font-semibold">Total</dt><dd @class(['text-lg font-bold', 'line-through opacity-60' => $nota->esAnulada()])><x-money :value="$nota->total" /></dd></div>
                </dl>
            </x-card>
            @include('sunat.partials.estado', ['sunat' => $nota->sunat])
            <x-card title="Cliente" icon="user">
                <p class="font-medium">{{ $v->cliente?->nombre_completo ?? 'Cliente varios' }}</p>
                <p class="text-xs text-slate-500">{{ $v->cliente?->documento_etiqueta }}</p>
            </x-card>
            @if ($nota->observacion)
                <x-card title="Observaciones" icon="info">
                    <p class="whitespace-pre-line text-sm text-slate-600">{{ $nota->observacion }}</p>
                </x-card>
            @endif
        </aside>
    </div>

    @unless ($nota->esAnulada())
        <dialog id="dialogo-anular" aria-labelledby="titulo-anular" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-xl p-0 text-ink shadow-xl backdrop:bg-slate-900/40">
            <form method="POST" action="" class="p-5">
                @csrf @method('PATCH')
                <h2 id="titulo-anular" class="text-base font-semibold">Anular nota</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                    ¿Anular <strong data-numero class="text-ink"></strong>? Se revierten el stock devuelto, la rebaja de la deuda y el dinero reembolsado o cobrado.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" data-cerrar class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn bg-red-600 text-white hover:bg-red-700">Sí, anular</button>
                </div>
            </form>
        </dialog>
        @push('scripts')
            <script>
                (() => {
                    const d = document.getElementById('dialogo-anular');
                    document.addEventListener('click', (e) => {
                        const a = e.target.closest('[data-anular]');
                        if (a) { d.querySelector('form').action = a.dataset.anular; d.querySelector('[data-numero]').textContent = a.dataset.numero; d.showModal(); }
                        else if (e.target.closest('[data-cerrar]') || e.target === d) d.close();
                    });
                })();
            </script>
        @endpush
    @endunless
@endsection
