@php
    $metodosPago = config('compras.metodos_pago');
    $abrirAlCargar = $errors->has('monto') || $errors->has('metodo_pago') || $errors->has('fecha_pago') || $errors->has('referencia');
@endphp

<dialog id="dialogo-pago" aria-labelledby="titulo-pago" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl p-0 text-ink shadow-xl backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('compras.pagos.store', $compra) }}" class="p-5">
        @csrf

        <h2 id="titulo-pago" class="text-base font-semibold">Registrar pago</h2>
        <p class="mt-1 text-xs text-slate-500">
            {{ $compra->numero_completo }} · saldo por pagar <strong class="text-ink"><x-money :value="$compra->saldo()" /></strong>
        </p>

        @if ($errors->any() && $abrirAlCargar)
            <div role="alert" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
            </div>
        @endif

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <x-input name="monto" label="Monto (S/)" type="number" step="0.01" min="0.01" max="{{ $compra->saldo() }}" :value="number_format($compra->saldo(), 2, '.', '')" inputmode="decimal" />
            <x-input name="fecha_pago" label="Fecha de pago" type="date" :value="now()->format('Y-m-d')" max="{{ now()->format('Y-m-d') }}" />
            <x-select name="metodo_pago" label="Método de pago">
                @foreach ($metodosPago as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(old('metodo_pago', 'transferencia') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </x-select>
            <x-input name="referencia" label="N.º de operación" maxlength="100" autocomplete="off" />
        </div>
        <div class="mt-3">
            <x-input name="observacion" label="Observaciones (opcional)" maxlength="1000" />
        </div>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" data-cerrar-pago class="btn btn-secondary">Cancelar</button>
            <button type="submit" class="btn btn-primary"><x-icon name="check" /> Registrar pago</button>
        </div>
    </form>
</dialog>

@push('scripts')
    <script>
        (() => {
            const dialogo = document.getElementById('dialogo-pago');
            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-abrir-pago]')) dialogo.showModal();
                else if (e.target.closest('[data-cerrar-pago]') || e.target === dialogo) dialogo.close();
            });
            @if ($abrirAlCargar)
                dialogo.showModal();   // el servidor devolvió errores del pago: se reabre el formulario
            @endif
        })();
    </script>
@endpush
