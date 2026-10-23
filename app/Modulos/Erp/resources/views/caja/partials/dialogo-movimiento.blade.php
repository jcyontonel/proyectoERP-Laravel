{{-- Diálogo para registrar un ingreso o egreso. Botones que lo abren: data-movimiento="ingreso|egreso". $conceptos, $metodos --}}
<dialog id="dialogo-movimiento" aria-labelledby="titulo-movimiento" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl p-0 text-ink shadow-xl backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('caja.movimientos.store') }}" class="p-5">
        @csrf
        <input type="hidden" name="tipo" value="egreso" data-tipo>

        <h2 id="titulo-movimiento" class="text-base font-semibold" data-titulo>Registrar egreso</h2>
        <p class="mt-1 text-xs text-slate-500" data-ayuda>Gastos menores, retiros a banco, movilidad…</p>

        <div class="mt-4 space-y-4">
            <div>
                <label for="mov-concepto" class="field-label">Concepto *</label>
                <input id="mov-concepto" name="concepto" list="conceptos-sugeridos" required maxlength="150" class="field" autocomplete="off">
                <datalist id="conceptos-sugeridos"></datalist>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="mov-monto" class="field-label">Monto S/ *</label>
                    <input id="mov-monto" name="monto" type="number" step="0.01" min="0.01" required inputmode="decimal" class="field text-right">
                </div>
                <x-select name="metodo_pago" label="Método">
                    @foreach ($metodos as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected($valor === 'efectivo')>{{ $etiqueta }}</option>
                    @endforeach
                </x-select>
            </div>
            <div>
                <label for="mov-obs" class="field-label">Observación</label>
                <input id="mov-obs" name="observacion" maxlength="500" class="field" placeholder="Ej. N.º de boleta del gasto">
            </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" data-cerrar-movimiento class="btn btn-secondary">Cancelar</button>
            <button type="submit" class="btn btn-primary"><x-icon name="save" /> Registrar</button>
        </div>
    </form>
</dialog>

@push('scripts')
    <script>
        (() => {
            const dialogo = document.getElementById('dialogo-movimiento');
            const conceptos = @json($conceptos);
            const textos = {
                ingreso: ['Registrar ingreso', 'Fondo adicional, cobro de una deuda anterior…'],
                egreso: ['Registrar egreso', 'Gastos menores, retiros a banco, movilidad…'],
            };
            document.addEventListener('click', (e) => {
                const abrir = e.target.closest('[data-movimiento]');
                if (abrir) {
                    const tipo = abrir.dataset.movimiento;
                    dialogo.querySelector('form').reset();
                    dialogo.querySelector('[data-tipo]').value = tipo;
                    dialogo.querySelector('[data-titulo]').textContent = textos[tipo][0];
                    dialogo.querySelector('[data-ayuda]').textContent = textos[tipo][1];
                    dialogo.querySelector('#conceptos-sugeridos').innerHTML = (conceptos[tipo] || []).map((c) => `<option value="${c}">`).join('');
                    dialogo.showModal();
                    dialogo.querySelector('#mov-concepto').focus();
                } else if (e.target.closest('[data-cerrar-movimiento]') || e.target === dialogo) {
                    dialogo.close();
                }
            });
        })();
    </script>
@endpush
