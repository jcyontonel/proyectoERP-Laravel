{{--
    Confirmación de eliminación reutilizable. Los botones que la abren llevan:
      data-eliminar="{{ route('x.destroy', $m) }}" data-nombre="{{ $m->nombre }}"
--}}
@props(['titulo' => 'Eliminar registro', 'mensaje' => 'Esta acción no se puede deshacer.'])

<dialog id="dialogo-eliminar" aria-labelledby="titulo-eliminar" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-xl p-0 text-ink shadow-xl backdrop:bg-slate-900/40">
    <form method="POST" action="" class="p-5">
        @csrf
        @method('DELETE')

        <div class="flex items-start gap-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-red-50 text-red-600"><x-icon name="trash" class="size-5" /></span>
            <div>
                <h2 id="titulo-eliminar" class="text-base font-semibold">{{ $titulo }}</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600">
                    ¿Seguro que deseas eliminar <strong data-nombre class="text-ink"></strong>? {{ $mensaje }}
                </p>
            </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" data-cerrar class="btn btn-secondary">Cancelar</button>
            <button type="submit" class="btn btn-danger">Sí, eliminar</button>
        </div>
    </form>
</dialog>

@push('scripts')
    <script>
        (() => {
            const dialogo = document.getElementById('dialogo-eliminar');
            document.addEventListener('click', (e) => {
                const abrir = e.target.closest('[data-eliminar]');
                if (abrir) {
                    dialogo.querySelector('form').action = abrir.dataset.eliminar;
                    dialogo.querySelector('[data-nombre]').textContent = abrir.dataset.nombre;
                    dialogo.showModal();
                } else if (e.target.closest('[data-cerrar]') || e.target === dialogo) {
                    dialogo.close();
                }
            });
        })();
    </script>
@endpush
