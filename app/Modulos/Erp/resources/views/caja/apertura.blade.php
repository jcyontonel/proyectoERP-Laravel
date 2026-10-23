{{-- TurnoCajaController@actual (sin turno abierto): $cajas (con sesionAbierta.usuario), $ultimo (último turno cerrado del usuario) --}}
@extends('layouts.app')

@section('title', 'Abrir caja')

@section('content')
    <x-page-header title="Abrir caja" :migas="['Caja' => null, 'Abrir caja' => null]"
        subtitle="Inicia tu turno con el efectivo que hay en el cajón. Las ventas, cobros y gastos del turno quedarán registrados para el arqueo del cierre.">
        <x-slot:acciones>
            <a href="{{ route('caja.turnos.index') }}" class="btn btn-secondary"><x-icon name="history" class="text-brand-600" /> Historial de turnos</a>
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <x-card title="Apertura de turno" icon="wallet">
            @if ($cajas->isEmpty())
                <x-empty-state icon="wallet" title="No hay cajas activas" description="Un administrador debe crear al menos una caja en Configuración.">
                    @can('gestionar-empresas')
                        <a href="{{ route('cajas.create') }}" class="btn btn-primary"><x-icon name="plus" /> Crear caja</a>
                    @endcan
                </x-empty-state>
            @else
                <form method="POST" action="{{ route('caja.abrir') }}" class="space-y-5">
                    @csrf
                    <fieldset>
                        <legend class="field-label">Caja *</legend>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($cajas as $c)
                                @php $ocupada = $c->sesionAbierta; @endphp
                                <label @class([
                                    'flex items-start gap-3 rounded-lg border p-3.5 transition has-checked:border-brand-500 has-checked:bg-brand-50',
                                    'cursor-pointer border-slate-200 hover:border-brand-200' => ! $ocupada,
                                    'cursor-not-allowed border-slate-200 bg-slate-50 opacity-70' => $ocupada,
                                ])>
                                    <input type="radio" name="caja_id" value="{{ $c->id }}" class="mt-1 accent-brand-600" @checked(old('caja_id', $cajas->firstWhere(fn ($x) => ! $x->sesionAbierta)?->id) == $c->id) @disabled($ocupada)>
                                    <span class="min-w-0 leading-tight">
                                        <span class="block text-sm font-semibold">{{ $c->nombre }}</span>
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $c->codigo }}{{ $c->almacen ? ' · ' . $c->almacen->nombre : '' }}</span>
                                        @if ($ocupada)
                                            <span class="mt-1.5 inline-block text-[11px] font-medium text-amber-700">En uso por {{ $ocupada->usuario?->nombre_completo }} desde las {{ $ocupada->fecha_apertura->format('H:i') }}</span>
                                        @else
                                            <span class="mt-1.5 inline-block text-[11px] font-medium text-emerald-700">Disponible</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('caja_id') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </fieldset>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input name="monto_apertura" label="Efectivo inicial (S/) *" type="number" step="0.01" min="0" :value="old('monto_apertura', $ultimo?->monto_contado ?? 0)" required
                            hint="El sencillo con el que empiezas el turno." />
                        <x-input name="observacion" label="Observación" maxlength="500" placeholder="Opcional" />
                    </div>

                    <button type="submit" class="btn btn-primary w-full sm:w-auto"><x-icon name="lock" /> Abrir turno</button>
                </form>
            @endif
        </x-card>

        <aside class="space-y-5">
            <x-card title="Cómo funciona" icon="info">
                <ol class="space-y-3 text-sm text-slate-600">
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">1</span> Abre el turno con el efectivo inicial.</li>
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">2</span> Cada venta se cobra en tu turno; registra aquí gastos, retiros y fondos adicionales.</li>
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">3</span> Al cierre cuenta el efectivo por billetes y monedas: el sistema calcula la diferencia.</li>
                </ol>
            </x-card>

            @if ($ultimo)
                <x-card title="Tu último turno" icon="history">
                    <dl class="divide-y divide-slate-100 text-sm">
                        <x-dato label="Caja">{{ $ultimo->caja?->nombre }}</x-dato>
                        <x-dato label="Cerrado">{{ $ultimo->fecha_cierre?->format('d/m/Y H:i') }}</x-dato>
                        <x-dato label="Efectivo contado"><x-money :value="$ultimo->monto_contado" /></x-dato>
                    </dl>
                    <a href="{{ route('caja.turnos.show', $ultimo) }}" class="mt-1 flex items-center justify-between border-t border-slate-200 pt-3 text-xs font-semibold text-brand-600 hover:text-brand-700">Ver arqueo <x-icon name="arrow-right" class="size-3.5" /></a>
                </x-card>
            @endif
        </aside>
    </div>
@endsection
