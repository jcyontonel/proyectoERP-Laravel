{{-- Notas de crédito y débito del comprobante ($venta->notas) --}}
@if ($venta->notas->isNotEmpty())
    <x-card title="Notas de crédito y débito" icon="file-minus" class="mt-5">
        <ul class="divide-y divide-slate-100">
            @foreach ($venta->notas->sortByDesc('id') as $n)
                <li class="flex items-start justify-between gap-3 py-2.5 text-sm">
                    <div class="min-w-0">
                        <a href="{{ route('notas.show', $n) }}" @class(['font-semibold text-brand-600 hover:text-brand-700', 'line-through opacity-60' => $n->esAnulada()])>{{ $n->numero_completo }}</a>
                        <p class="text-xs text-slate-500">{{ $n->tipo_etiqueta }} · {{ $n->motivo_descripcion }} · {{ $n->fecha_emision->format('d/m/Y') }} @if ($n->esAnulada()) · anulada @endif</p>
                    </div>
                    <p @class(['shrink-0 font-semibold', 'text-emerald-700' => ! $n->esCredito() && ! $n->esAnulada(), 'line-through text-slate-400' => $n->esAnulada()])>
                        {{ $n->esCredito() ? '−' : '+' }} <x-money :value="$n->total" />
                    </p>
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
