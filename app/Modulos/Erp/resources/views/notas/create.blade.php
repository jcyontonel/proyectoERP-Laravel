{{--
    NotaController@create
      $venta (cliente, detalles.producto, notas emitidas)  $tipo 07|08  $motivos [codigo => [descripción, alcance]]
      $pendientes [detalle_id => cantidad que aún se puede devolver]  $disponible (importe que queda para crédito)
      $conCredito (ya tiene NC)  $saldo (deuda si es al crédito)  $metodos  $cajaAbierta
--}}
@extends('layouts.app')

@php
    $esCredito = $tipo === \App\Modulos\Erp\Models\Nota::CREDITO;
    $titulo = $esCredito ? 'Nota de crédito' : 'Nota de débito';
    $motivoActual = old('motivo_codigo', $esCredito ? ($conCredito ? '07' : '01') : '01');
    $cant = fn ($x) => rtrim(rtrim(number_format((float) $x, 4, '.', ''), '0'), '.');
@endphp

@section('title', $titulo . ' sobre ' . $venta->numero_completo)

@section('content')
    <x-page-header :title="$titulo" :migas="['Ventas' => route('ventas.index'), $venta->numero_completo => route('ventas.show', $venta), $titulo => null]"
        :subtitle="'Sobre ' . $venta->tipo_etiqueta . ' ' . $venta->numero_completo . ' · ' . ($venta->cliente?->nombre_completo ?? 'Cliente varios') . ' · total S/ ' . number_format($venta->total, 2)" />

    <x-flash />

    <form method="POST" action="{{ route('ventas.notas.store', $venta) }}" class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]" id="form-nota">
        @csrf
        <input type="hidden" name="tipo" value="{{ $tipo }}">

        <div class="space-y-5">
            <x-card title="Motivo" icon="file-minus" :subtitle="$esCredito ? 'Catálogo SUNAT 09' : 'Catálogo SUNAT 10'">
                <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Motivo">
                    @foreach ($motivos as $codigo => [$descripcion, $alcance])
                        @php $bloqueado = $alcance === 'total' && $conCredito; @endphp
                        <label @class(['flex items-start gap-3 rounded-lg border border-slate-200 p-3', 'cursor-pointer has-checked:border-brand-500 has-checked:bg-brand-50' => ! $bloqueado, 'cursor-not-allowed opacity-50' => $bloqueado])>
                            <input type="radio" name="motivo_codigo" value="{{ $codigo }}" data-alcance="{{ $alcance }}" class="mt-0.5 accent-brand-600" @checked($motivoActual === $codigo) @disabled($bloqueado)>
                            <span class="text-sm">
                                <span class="font-medium">{{ $codigo }} · {{ $descripcion }}</span>
                                <span class="block text-[11px] text-slate-500">
                                    {{ match ($alcance) { 'total' => 'Todo el comprobante; el stock vuelve al almacén', 'items' => 'Eliges productos y cantidades; el stock vuelve al almacén', default => 'Un importe, sin mover stock' } }}
                                    @if ($bloqueado) · ya tiene notas de crédito @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('motivo_codigo')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            </x-card>

            {{-- Por ítems --}}
            <x-card title="Productos devueltos" icon="package" data-seccion="items">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs text-slate-500">
                            <tr><th class="py-2 pr-3">Producto</th><th class="px-3 py-2 text-right">Vendido</th><th class="px-3 py-2 text-right">Puede devolver</th><th class="px-3 py-2 text-right">P. unit.</th><th class="py-2 pl-3 text-right">Devolver</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($venta->detalles as $d)
                                @php $max = $pendientes[$d->id] ?? 0; $unit = (float) $d->cantidad > 0 ? $d->total / $d->cantidad : 0; @endphp
                                <tr>
                                    <td class="py-2 pr-3">{{ $d->descripcion }}</td>
                                    <td class="px-3 py-2 text-right">{{ $cant($d->cantidad) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $cant($max) }}</td>
                                    <td class="px-3 py-2 text-right"><x-money :value="$unit" /></td>
                                    <td class="py-2 pl-3 text-right">
                                        <input type="number" name="items[{{ $d->id }}]" value="{{ old('items.' . $d->id, 0) }}" min="0" max="{{ $max }}" step="{{ $d->producto?->seVendeFraccionado() ? '0.001' : '1' }}"
                                            data-unitario="{{ $unit }}" aria-label="Cantidad a devolver de {{ $d->descripcion }}" @disabled($max <= 0)
                                            class="field !h-8 !w-24 text-right">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('items')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            </x-card>

            {{-- Por monto --}}
            <x-card title="Importe" icon="coins" data-seccion="monto">
                <div class="grid gap-4 sm:grid-cols-[12rem_minmax(0,1fr)]">
                    <x-input name="monto" label="Monto con IGV (S/) *" type="number" step="0.01" min="0.01" :max="$esCredito ? $disponible : null" inputmode="decimal" />
                    <x-input name="descripcion" label="Descripción" maxlength="255" :placeholder="$esCredito ? 'Descuento por pronto pago…' : 'Intereses por 15 días de mora…'" />
                </div>
                @if ($esCredito)<p class="mt-2 text-[11px] text-slate-500">Máximo S/ {{ number_format($disponible, 2) }} (lo que queda del comprobante).</p>@endif
            </x-card>

            {{-- Total --}}
            <x-card title="Todo el comprobante" icon="file-text" data-seccion="total">
                <p class="text-sm text-slate-600">Se emite por el total de <strong class="text-ink">S/ {{ number_format($venta->total, 2) }}</strong> y todos los productos vuelven al almacén.</p>
            </x-card>
        </div>

        <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
            <x-card :title="$esCredito ? 'Devolución del dinero' : 'Cobro'" icon="wallet">
                @if ($esCredito && $saldo > 0)
                    <p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">Venta al crédito con deuda de S/ {{ number_format($saldo, 2) }}: la nota primero reduce esa deuda; solo lo que exceda se devuelve.</p>
                @endif
                <x-select name="metodo" :label="$esCredito ? 'Cómo se devuelve' : 'Cómo se cobra'">
                    @if ($esCredito)<option value="ninguno" @selected(old('metodo') === 'ninguno')>No se devuelve (saldo a favor)</option>@endif
                    @foreach ($metodos as $v => $et)
                        <option value="{{ $v }}" @selected(old('metodo', 'efectivo') === $v)>{{ $et }}</option>
                    @endforeach
                </x-select>
                <p class="mt-2 text-[11px] text-slate-500">
                    @if ($cajaAbierta) Queda registrado en tu turno de caja ({{ $cajaAbierta->caja?->nombre }}). @else En efectivo necesitas tener tu turno de caja abierto. @endif
                </p>
                @error('metodo')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror

                <dl class="mt-4 space-y-1.5 border-t border-slate-100 pt-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-600">Total de la nota</dt><dd class="text-lg font-bold" data-total>S/ 0.00</dd></div>
                </dl>
                <div class="mt-3"><x-textarea name="observacion" label="Observaciones" rows="2" maxlength="1000" /></div>
                <button type="submit" class="btn btn-primary mt-4 w-full"><x-icon name="check" /> Emitir {{ strtolower($titulo) }}</button>
                <p class="mt-2 text-center text-[11px] text-slate-500">Consume un comprobante de tu plan y toma la serie {{ \Illuminate\Support\Str::substr($venta->serie, 0, 1) }}{{ $esCredito ? 'C' : 'D' }}.</p>
            </x-card>
            @error('venta')<x-alert tone="danger">{{ $message }}</x-alert>@enderror
            @error('suscripcion')<x-alert tone="danger">{{ $message }}</x-alert>@enderror
        </aside>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('form-nota');
            const nf = new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const totalVenta = @json((float) $venta->total);

            const pintar = () => {
                const alcance = form.querySelector('input[name="motivo_codigo"]:checked')?.dataset.alcance ?? 'monto';
                form.querySelectorAll('[data-seccion]').forEach((s) => { s.hidden = s.dataset.seccion !== alcance; });
                let total = 0;
                if (alcance === 'total') total = totalVenta;
                if (alcance === 'monto') total = parseFloat(form.monto.value) || 0;
                if (alcance === 'items') form.querySelectorAll('input[data-unitario]').forEach((i) => { total += (parseFloat(i.value) || 0) * parseFloat(i.dataset.unitario); });
                form.querySelector('[data-total]').textContent = 'S/ ' + nf.format(total);
            };
            form.addEventListener('input', pintar);
            form.addEventListener('change', pintar);
            pintar();
        })();
    </script>
@endpush
