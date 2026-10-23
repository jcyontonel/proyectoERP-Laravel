{{-- Confirmación para anular un cobro o pago. Botones: data-anular-pago="{{ url PATCH }}" data-numero="Cobro de S/ 50.00 (B001-12)" --}}
<dialog id="dialogo-anular-pago" aria-labelledby="titulo-anular-pago" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-xl p-0 text-ink shadow-xl backdrop:bg-slate-900/40">
    <form method="POST" action="" class="p-5">
        @csrf
        @method('PATCH')

        <h2 id="titulo-anular-pago" class="text-base font-semibold">Anular movimiento</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            ¿Seguro que deseas anular el <strong data-numero class="text-ink"></strong>?
            El saldo del documento vuelve a quedar pendiente. Si fue en efectivo, sale del arqueo de tu turno de caja.
        </p>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" data-cerrar class="btn btn-secondary">Cancelar</button>
            <button type="submit" class="btn bg-red-600 text-white hover:bg-red-700">Sí, anular</button>
        </div>
    </form>
</dialog>

@push('scripts')
    <script>
        (() => {
            const dialogo = document.getElementById('dialogo-anular-pago');
            document.addEventListener('click', (e) => {
                const abrir = e.target.closest('[data-anular-pago]');
                if (abrir) {
                    dialogo.querySelector('form').action = abrir.dataset.anularPago;
                    dialogo.querySelector('[data-numero]').textContent = abrir.dataset.numero;
                    dialogo.showModal();
                } else if (e.target.closest('[data-cerrar]') && dialogo.open || e.target === dialogo) {
                    dialogo.close();
                }
            });
        })();
    </script>
@endpush
