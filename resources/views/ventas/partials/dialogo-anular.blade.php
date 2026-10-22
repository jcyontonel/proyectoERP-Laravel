<dialog id="dialogo-anular" aria-labelledby="titulo-anular" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-xl p-0 text-ink shadow-xl backdrop:bg-slate-900/40">
    <form method="POST" action="" class="p-5">
        @csrf
        @method('PATCH')

        <h2 id="titulo-anular" class="text-base font-semibold">Anular comprobante</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            ¿Seguro que deseas anular <strong data-numero class="text-ink"></strong>?
            Esta acción no se puede deshacer.
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
            const dialogo = document.getElementById('dialogo-anular');
            document.addEventListener('click', (e) => {
                const abrir = e.target.closest('[data-anular]');
                if (abrir) {
                    dialogo.querySelector('form').action = abrir.dataset.anular;
                    dialogo.querySelector('[data-numero]').textContent = abrir.dataset.numero;
                    dialogo.showModal();
                } else if (e.target.closest('[data-cerrar]') || e.target === dialogo) {
                    dialogo.close();
                }
            });
        })();
    </script>
@endpush
