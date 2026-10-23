{{-- TurnoCajaController@cierre: $sesion, $resumen, $denominaciones [billetes, monedas], $tolerancia --}}
@extends('layouts.app')

@section('title', 'Cerrar caja')

@section('content')
    <x-page-header title="Cerrar turno de caja" :migas="['Caja' => route('caja.index'), 'Cerrar turno' => null]"
        subtitle="Cuenta el efectivo del cajón por billetes y monedas. El sistema compara lo contado con lo esperado." />

    <x-flash />

    <form method="POST" action="{{ route('caja.cerrar') }}" data-arqueo class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        @csrf

        <div class="space-y-5">
            <x-card title="Arqueo de efectivo" icon="banknote" subtitle="Escribe cuántas unidades tienes de cada denominación">
                @foreach (['billetes' => 'Billetes', 'monedas' => 'Monedas'] as $grupo => $titulo)
                    <p class="mb-2 mt-1 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $titulo }}</p>
                    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($denominaciones[$grupo] as $valor)
                            @php $clave = (string) $valor; @endphp
                            <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-2.5">
                                <span class="w-16 shrink-0 text-sm font-semibold">S/ {{ $valor < 1 ? number_format($valor, 2) : $valor }}</span>
                                <span class="text-slate-400">×</span>
                                <input type="number" name="denominaciones[{{ $clave }}]" min="0" step="1" inputmode="numeric" value="{{ old('denominaciones.' . $clave) }}"
                                    data-valor="{{ $valor }}" aria-label="Cantidad de S/ {{ $valor }}" class="field h-9 w-20 text-right">
                                <span data-subtotal class="ml-auto text-xs text-slate-500">—</span>
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </x-card>

            <x-card title="Observación del cierre" icon="file-text">
                <x-textarea name="observacion" label="Observación" rows="3" maxlength="1000"
                    :hint="'Obligatoria si la diferencia supera S/ ' . number_format($tolerancia, 2) . '.'" placeholder="Ej. Faltante por vuelto mal entregado" />
            </x-card>
        </div>

        <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
            <x-card title="Resultado" icon="check-circle">
                <dl class="divide-y divide-slate-100 text-sm">
                    <x-dato label="Efectivo esperado"><span class="font-semibold"><x-money :value="$resumen['efectivo']" /></span></x-dato>
                    <x-dato label="Efectivo contado"><span data-contado class="font-semibold">S/ 0.00</span></x-dato>
                    <x-dato label="Diferencia"><span data-diferencia class="font-bold">—</span></x-dato>
                </dl>
                <p data-aviso class="mt-3 hidden rounded-lg px-3 py-2 text-xs"></p>

                <button type="submit" class="btn btn-primary mt-4 w-full"><x-icon name="lock" /> Cerrar turno</button>
                <a href="{{ route('caja.index') }}" class="btn btn-secondary mt-2 w-full">Volver a la caja</a>
            </x-card>

            @include('caja.partials.resumen')
        </aside>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-arqueo]');
            const esperado = @json((float) $resumen['efectivo']);
            const tolerancia = @json($tolerancia);
            const nf = new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const money = (n) => 'S/ ' + nf.format(n);

            const calcular = () => {
                let total = 0;
                form.querySelectorAll('[data-valor]').forEach((input) => {
                    const sub = (parseInt(input.value, 10) || 0) * parseFloat(input.dataset.valor);
                    total += sub;
                    input.parentElement.querySelector('[data-subtotal]').textContent = sub ? money(sub) : '—';
                });
                total = Math.round(total * 100) / 100;
                const dif = Math.round((total - esperado) * 100) / 100;

                form.querySelector('[data-contado]').textContent = money(total);
                const elDif = form.querySelector('[data-diferencia]');
                elDif.textContent = Math.abs(dif) < 0.005 ? 'Cuadra' : `${dif > 0 ? 'Sobrante' : 'Faltante'} ${money(Math.abs(dif))}`;
                elDif.className = `font-bold ${Math.abs(dif) < 0.005 ? 'text-emerald-700' : (dif > 0 ? 'text-amber-700' : 'text-red-600')}`;

                const aviso = form.querySelector('[data-aviso]');
                const grande = Math.abs(dif) > tolerancia;
                aviso.classList.toggle('hidden', !grande);
                aviso.className = `mt-3 rounded-lg px-3 py-2 text-xs ${grande ? 'bg-amber-50 text-amber-800' : 'hidden'}`;
                aviso.textContent = grande ? 'La diferencia supera la tolerancia: explica el motivo en la observación.' : '';
            };
            form.addEventListener('input', calcular);
            calcular();
        })();
    </script>
@endpush
