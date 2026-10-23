{{--
    Diálogo único para cobrar/pagar un documento o abonar a un cliente/proveedor.
    Los botones que lo abren llevan:
      data-pagar="{{ url del POST }}" data-titulo="Registrar cobro" data-detalle="B001-00000012 · Juan Pérez" data-saldo="120.50"
    Variables: $metodos [valor => etiqueta], $metodoDefecto
    Si el servidor devuelve errores, se reabre con lo enviado (campos ocultos _accion, _titulo, _detalle, _saldo).
--}}
@php
    $abrirAlCargar = old('_accion') && $errors->hasAny(['monto', 'metodo_pago', 'fecha_pago', 'referencia', 'observacion']);
@endphp

<dialog id="dialogo-pago" aria-labelledby="titulo-pago" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl p-0 text-ink shadow-xl backdrop:bg-slate-900/40">
    <form method="POST" action="{{ old('_accion') }}" class="p-5">
        @csrf
        <input type="hidden" name="_accion" value="{{ old('_accion') }}">
        <input type="hidden" name="_titulo" value="{{ old('_titulo') }}">
        <input type="hidden" name="_detalle" value="{{ old('_detalle') }}">
        <input type="hidden" name="_saldo" value="{{ old('_saldo') }}">

        <h2 id="titulo-pago" class="text-base font-semibold" data-titulo>{{ old('_titulo', 'Registrar pago') }}</h2>
        <p class="mt-1 text-xs text-slate-500">
            <span data-detalle>{{ old('_detalle') }}</span> · saldo <strong class="text-ink" data-saldo-texto>S/ {{ number_format((float) old('_saldo', 0), 2) }}</strong>
        </p>

        @if ($abrirAlCargar)
            <div role="alert" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
            </div>
        @endif

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div>
                <x-input name="monto" label="Monto (S/)" type="number" step="0.01" min="0.01" inputmode="decimal" required />
                <button type="button" data-todo class="mt-1 text-[11px] font-semibold text-brand-600 hover:text-brand-700">Todo el saldo</button>
            </div>
            <x-input name="fecha_pago" label="Fecha" type="date" :value="now()->format('Y-m-d')" max="{{ now()->format('Y-m-d') }}" required />
            <x-select name="metodo_pago" label="Método de pago">
                @foreach ($metodos as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(old('metodo_pago', $metodoDefecto ?? 'efectivo') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </x-select>
            <x-input name="referencia" label="N.º de operación" maxlength="100" autocomplete="off" />
        </div>
        <div class="mt-3">
            <x-input name="observacion" label="Observaciones (opcional)" maxlength="1000" />
        </div>
        <p class="mt-3 text-[11px] leading-snug text-slate-500">
            El efectivo queda en tu turno de caja abierto y entra en el arqueo.
        </p>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" data-cerrar-pago class="btn btn-secondary">Cancelar</button>
            <button type="submit" class="btn btn-primary"><x-icon name="check" /> Registrar</button>
        </div>
    </form>
</dialog>

@push('scripts')
    <script>
        (() => {
            const dialogo = document.getElementById('dialogo-pago');
            const form = dialogo.querySelector('form');
            const monto = form.querySelector('#monto');
            const nf = new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const preparar = (d) => {
                form.action = d.pagar;
                form._accion.value = d.pagar;
                form._titulo.value = d.titulo;
                form._detalle.value = d.detalle;
                form._saldo.value = d.saldo;
                dialogo.querySelector('[data-titulo]').textContent = d.titulo;
                dialogo.querySelector('[data-detalle]').textContent = d.detalle;
                dialogo.querySelector('[data-saldo-texto]').textContent = 'S/ ' + nf.format(parseFloat(d.saldo) || 0);
                monto.max = d.saldo;
                monto.value = parseFloat(d.saldo).toFixed(2);
                dialogo.querySelector('[role="alert"]')?.remove();
            };

            document.addEventListener('click', (e) => {
                const abrir = e.target.closest('[data-pagar]');
                if (abrir) {
                    preparar(abrir.dataset);
                    dialogo.showModal();
                    monto.select();
                } else if (e.target.closest('[data-todo]')) {
                    monto.value = parseFloat(form._saldo.value || 0).toFixed(2);
                } else if (e.target.closest('[data-cerrar-pago]') || e.target === dialogo) {
                    dialogo.close();
                }
            });

            @if ($abrirAlCargar)
                monto.max = form._saldo.value;
                dialogo.showModal();   // el servidor devolvió errores: se reabre con lo enviado
            @endif
        })();
    </script>
@endpush
