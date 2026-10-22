@php $metodos = config('compras.metodos_pago'); @endphp

<x-card title="Pagos" icon="wallet">
    @if ($compra->pagos->isEmpty())
        <p class="rounded-lg border border-dashed border-slate-300 p-3.5 text-center text-xs text-slate-500">
            Sin pagos registrados: la compra está a crédito.
        </p>
    @else
        <ul class="divide-y divide-slate-100">
            @foreach ($compra->pagos->sortByDesc('fecha_pago') as $pago)
                <li class="flex items-start justify-between gap-3 py-2.5 text-sm">
                    <div class="min-w-0">
                        <p @class(['font-medium', 'line-through text-slate-400' => $pago->estado === 'anulado'])>
                            {{ $metodos[$pago->metodo_pago] ?? \Illuminate\Support\Str::headline($pago->metodo_pago) }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ $pago->fecha_pago->translatedFormat('d M Y') }}
                            @if ($pago->referencia) · Op. {{ $pago->referencia }} @endif
                            @if ($pago->usuario) · {{ $pago->usuario->name }} @endif
                            @if ($pago->estado === 'anulado') · anulado @endif
                        </p>
                    </div>
                    <p @class(['shrink-0 font-semibold', 'line-through text-slate-400' => $pago->estado === 'anulado'])><x-money :value="$pago->monto" /></p>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($compra->puedeRegistrarPago())
        <button type="button" data-abrir-pago class="btn btn-secondary mt-3 w-full">
            <x-icon name="plus" class="text-brand-600" /> Registrar pago
        </button>
    @endif
</x-card>
