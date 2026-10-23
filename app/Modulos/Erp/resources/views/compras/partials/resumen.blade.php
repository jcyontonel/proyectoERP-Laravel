@php $igvPct = $igv ?? 18; @endphp

<aside class="space-y-4 lg:col-start-3 lg:row-span-2 lg:row-start-1 lg:self-start lg:sticky lg:top-24">
    <x-card title="Resumen de compra" icon="file-text">
        {{-- Cómo se calcula el IGV --}}
        <div class="space-y-2.5 text-sm">
            <label class="flex cursor-pointer items-center gap-2.5">
                <input type="checkbox" id="afecto_igv" name="afecto_igv" value="1" @checked(old('afecto_igv', true)) class="size-4 rounded border-slate-300 accent-brand-600">
                Compra afecta a IGV ({{ $igvPct }} %)
            </label>
            <label class="flex cursor-pointer items-center gap-2.5">
                <input type="checkbox" id="precios_incluyen_igv" name="precios_incluyen_igv" value="1" @checked(old('precios_incluyen_igv', $preciosIncluyenIgv ?? false)) class="size-4 rounded border-slate-300 accent-brand-600">
                Los costos ingresados ya incluyen IGV
            </label>
        </div>

        <dl class="mt-4 space-y-3 border-t border-slate-200 pt-4 text-sm">
            <div class="flex items-center justify-between">
                <dt class="text-slate-600">Subtotal</dt>
                <dd id="res-subtotal" class="font-medium">S/ 0.00</dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-slate-600">Base imponible</dt>
                <dd id="res-base" class="font-medium">S/ 0.00</dd>
            </div>
            <div class="flex items-center justify-between">
                <dt id="res-igv-label" class="text-slate-600">IGV ({{ $igvPct }} %)</dt>
                <dd id="res-igv" class="font-medium">S/ 0.00</dd>
            </div>
        </dl>

        <div class="mt-4 rounded-lg border border-brand-100 bg-brand-50 p-4">
            <p class="text-xs font-medium text-brand-700">Total de la compra</p>
            <p id="res-total" class="mt-1 text-3xl font-bold tracking-tight text-brand-700">S/ 0.00</p>
            <p class="mt-1 text-[11px] text-slate-500">Moneda: soles (PEN)</p>
        </div>

        {{-- Pago --}}
        <div class="mt-4 space-y-3 border-t border-slate-200 pt-4">
            <x-select name="condicion_pago" label="Condición de pago">
                <option value="contado" @selected(old('condicion_pago', 'contado') === 'contado')>Contado (ya se pagó)</option>
                <option value="credito" @selected(old('condicion_pago') === 'credito')>Crédito (queda por pagar)</option>
            </x-select>

            <div id="bloque-credito" hidden>
                <x-input name="fecha_vencimiento" label="Vence el (pago al proveedor)" type="date" :value="old('fecha_vencimiento', now()->addDays(30)->format('Y-m-d'))" hint="Quedará en Cuentas por pagar con esta fecha." />
            </div>

            <div id="bloque-pago" class="space-y-3">
                <x-select name="metodo_pago" label="Método de pago">
                    @foreach ($metodosPago as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('metodo_pago', 'efectivo') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </x-select>
                <x-input name="referencia_pago" label="N.º de operación (opcional)" maxlength="100" autocomplete="off" />
                <p class="flex items-start gap-2 rounded-lg bg-slate-50 px-3 py-2 text-[11px] text-slate-600">
                    <x-icon name="wallet" class="mt-px size-3.5 shrink-0 text-slate-400" />
                    @if ($cajaAbierta ?? null)
                        Si pagas en efectivo, el monto saldrá de tu turno de caja «{{ $cajaAbierta->caja?->nombre }}».
                    @else
                        No tienes un turno de caja abierto: el pago en efectivo no se descontará de ninguna caja.
                    @endif
                </p>
            </div>
        </div>

        <p id="estado-listo" hidden class="mt-4 flex items-center gap-2 text-xs font-medium text-emerald-700">
            <x-icon name="check-circle" /> Todo listo para registrar la compra
        </p>
        <p id="estado-pendiente" class="mt-4 flex items-center gap-2 text-xs font-medium text-amber-700">
            <x-icon name="info" /> Agrega al menos un producto para registrar
        </p>

        <div class="mt-3">
            <button type="submit" id="btn-registrar" disabled class="btn btn-primary w-full disabled:pointer-events-none disabled:opacity-50">
                <x-icon name="check" /> Registrar compra
            </button>
        </div>

        <p class="mt-4 text-center text-[11px] leading-snug text-slate-500">
            Al registrar se ingresa el stock al almacén, se anota en el kardex y se actualiza el costo del producto.
        </p>
        <a href="{{ route('compras.index') }}" data-cancelar class="mt-3 block text-center text-xs text-slate-500 hover:text-ink">Cancelar compra</a>
    </x-card>
</aside>
