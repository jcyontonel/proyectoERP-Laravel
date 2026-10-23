@php $igvPct = $igv ?? 18; @endphp

<aside class="space-y-4 lg:col-start-3 lg:row-span-2 lg:row-start-1 lg:self-start lg:sticky lg:top-24">
    <x-card title="Resumen de venta" icon="file-text">
        <dl class="space-y-3 text-sm">
            <div class="flex items-center justify-between">
                <dt class="text-slate-600">Subtotal</dt>
                <dd id="res-subtotal" class="font-medium">S/ 0.00</dd>
            </div>

            <div class="flex items-center justify-between">
                <dt class="text-slate-600"><label for="descuento">Descuento</label></dt>
                <dd class="flex h-9 w-20 items-center rounded-lg border border-slate-200 bg-white pr-2 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20">
                    <input id="descuento" name="descuento" type="number" min="0" max="100" step="0.01" inputmode="decimal"
                        value="{{ old('descuento', $venta?->descuento_porcentaje ?? 0) }}"
                        class="w-full border-0 bg-transparent px-2 text-right text-sm focus:outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                    <span class="text-xs text-slate-500">%</span>
                </dd>
            </div>

            <div class="flex items-center justify-between text-emerald-700">
                <dt id="res-descuento-label">Descuento aplicado (0 %)</dt>
                <dd id="res-descuento" class="font-medium">− S/ 0.00</dd>
            </div>

            <div class="space-y-3 border-t border-slate-200 pt-3">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Base imponible</dt>
                    <dd id="res-base" class="font-medium">S/ 0.00</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">IGV ({{ $igvPct }} %)</dt>
                    <dd id="res-igv" class="font-medium">S/ 0.00</dd>
                </div>
            </div>
        </dl>

        <div class="mt-4 rounded-lg border border-brand-100 bg-brand-50 p-4">
            <p class="text-xs font-medium text-brand-700">Total a pagar</p>
            <p id="res-total" class="mt-1 text-3xl font-bold tracking-tight text-brand-700">S/ 0.00</p>
            <p class="mt-1 text-[11px] text-slate-500">Moneda: soles (PEN) · Incluye IGV</p>
        </div>

        {{-- Condición de pago: contado (se cobra ahora) o crédito (queda en Cuentas por cobrar) --}}
        @php $condicion = old('condicion_pago', $venta?->condicion_pago ?? 'contado'); @endphp
        <fieldset class="mt-4 border-t border-slate-200 pt-4" data-condicion>
            <legend class="sr-only">Condición de pago</legend>
            <div class="grid grid-cols-2 gap-2">
                @foreach (['contado' => ['Contado', 'banknote'], 'credito' => ['Crédito', 'calendar-clock']] as $valor => [$etiqueta, $icono])
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium has-checked:border-brand-500 has-checked:bg-brand-50 has-checked:text-brand-700">
                        <input type="radio" name="condicion_pago" value="{{ $valor }}" class="sr-only" @checked($condicion === $valor)>
                        <x-icon :name="$icono" /> {{ $etiqueta }}
                    </label>
                @endforeach
            </div>

            <div data-bloque-credito @if ($condicion !== 'credito') hidden @endif class="mt-3 space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <x-input name="fecha_vencimiento" label="Vence el" type="date"
                        :value="old('fecha_vencimiento', $venta?->fecha_vencimiento?->format('Y-m-d') ?? now()->addDays(30)->format('Y-m-d'))" />
                    <x-input name="adelanto" label="Adelanto (S/)" type="number" step="0.01" min="0" inputmode="decimal" :value="old('adelanto', 0)" />
                </div>
                <p class="flex items-center justify-between rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <span>Queda por cobrar</span>
                    <strong data-saldo-credito>S/ 0.00</strong>
                </p>
                <p class="text-[11px] text-slate-500">Requiere cliente. El adelanto se cobra con el método de pago elegido; el saldo se cobra desde Finanzas → Cuentas por cobrar.</p>
            </div>
        </fieldset>

        <p id="estado-listo" hidden class="mt-4 flex items-center gap-2 text-xs font-medium text-emerald-700">
            <x-icon name="check-circle" /> Todo listo para registrar la venta
        </p>
        <p id="estado-pendiente" class="mt-4 flex items-center gap-2 text-xs font-medium text-amber-700">
            <x-icon name="info" /> Agrega al menos un producto para registrar
        </p>

        <div class="mt-3 space-y-2.5">
            <button type="submit" id="btn-registrar" name="accion" value="registrar" disabled class="btn btn-primary w-full disabled:pointer-events-none disabled:opacity-50">
                <x-icon name="check" /> Registrar venta
            </button>
            <button type="submit" name="accion" value="borrador" class="btn btn-secondary w-full">
                <x-icon name="save" /> Guardar borrador
            </button>
        </div>

        <p class="mt-4 text-center text-[11px] leading-snug text-slate-500">
            Al registrar, se emitirá el comprobante y se actualizará el stock.
        </p>
        <a href="{{ route('ventas.index') }}" data-cancelar class="mt-3 block text-center text-xs text-slate-500 hover:text-ink">Cancelar venta</a>
    </x-card>

    {{-- Totales calculados en el navegador (el servidor debe recalcularlos) --}}
    <input type="hidden" name="subtotal" id="h-subtotal" value="0">
    <input type="hidden" name="descuento_monto" id="h-descuento-monto" value="0">
    <input type="hidden" name="base_imponible" id="h-base" value="0">
    <input type="hidden" name="igv" id="h-igv" value="0">
    <input type="hidden" name="total" id="h-total" value="0">

    <div class="px-1 text-xs text-slate-500">
        <p class="flex items-center gap-1.5 font-semibold text-ink"><x-icon name="info" class="size-3.5" /> Tu venta, bajo control</p>
        <p class="mt-1.5 leading-relaxed">Puedes guardar un borrador y continuar más tarde sin afectar el stock.</p>
    </div>
</aside>

@push('scripts')
    <script>
        // Condición de pago: muestra vencimiento/adelanto y valida el crédito antes de enviar
        (() => {
            const form = document.getElementById('venta-form');
            const bloque = form.querySelector('[data-bloque-credito]');
            const adelanto = document.getElementById('adelanto');
            const nf = new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const esCredito = () => form.querySelector('input[name="condicion_pago"]:checked')?.value === 'credito';
            const total = () => parseFloat(document.getElementById('h-total').value) || 0;

            const pintar = () => {
                bloque.hidden = !esCredito();
                const saldo = Math.max(total() - (parseFloat(adelanto.value) || 0), 0);
                form.querySelector('[data-saldo-credito]').textContent = 'S/ ' + nf.format(saldo);
            };
            // Los totales los recalcula scripts.blade.php en el mismo evento: se lee después
            ['input', 'change', 'click'].forEach((ev) => form.addEventListener(ev, () => setTimeout(pintar)));
            pintar();

            // Fase de captura: corre antes que el envío principal y lo detiene si el crédito no es válido
            form.addEventListener('submit', (e) => {
                if ((e.submitter?.value || 'registrar') !== 'registrar' || !esCredito()) return;
                const errores = [];
                if (!document.getElementById('cliente_id').value) errores.push('Para vender al crédito selecciona el cliente.');
                if ((parseFloat(adelanto.value) || 0) >= total() && total() > 0) errores.push('El adelanto debe ser menor al total: si paga todo, registra la venta al contado.');
                if (!document.getElementById('fecha_vencimiento').value) errores.push('Indica la fecha de vencimiento del crédito.');
                if (!errores.length) return;

                e.preventDefault();
                e.stopImmediatePropagation();
                const caja = document.getElementById('form-errors');
                caja.innerHTML = '<ul class="list-disc pl-5">' + errores.map((m) => `<li>${m}</li>`).join('') + '</ul>';
                caja.hidden = false;
                caja.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, true);
        })();
    </script>
@endpush
