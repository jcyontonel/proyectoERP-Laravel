{{-- Deuda agrupada por cliente/proveedor ($filas: objetos de CuentasController::porTercero) --}}
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="border-y border-slate-200 bg-slate-50/70 text-left text-xs font-medium text-slate-500">
            <tr>
                <th class="py-3 pl-5 pr-3">{{ $c['tercero'] }}</th>
                <th class="px-3 py-3 text-center">Documentos</th>
                <th class="px-3 py-3">Próximo vencimiento</th>
                <th class="px-3 py-3 text-right">Vencido</th>
                <th class="px-3 py-3 text-right">Saldo total</th>
                <th class="py-3 pl-3 pr-5 text-center">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($filas as $f)
                <tr class="hover:bg-slate-50/60">
                    <td class="py-3 pl-5 pr-3">
                        @if ($f->tercero)
                            <a href="{{ $ruta('cuenta', $f->tercero) }}" class="font-semibold hover:text-brand-600">{{ ($c['nombre'])($f->tercero) }}</a>
                            <p class="text-xs text-slate-500">{{ ($c['documento'])($f->tercero) }}</p>
                        @else
                            <span class="text-slate-400">Sin {{ Str::lower($c['tercero']) }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-3 text-center">{{ $f->documentos }}</td>
                    <td class="whitespace-nowrap px-3 py-3">
                        {{ $f->proximo?->translatedFormat('d M Y') ?? '—' }}
                        @if ($f->max_atraso > 0)
                            <x-badge tone="danger" shape="box" class="ml-1">hasta {{ $f->max_atraso }} d. de atraso</x-badge>
                        @endif
                    </td>
                    <td @class(['px-3 py-3 text-right', 'font-semibold text-red-600' => $f->vencido > 0, 'text-slate-400' => $f->vencido <= 0])><x-money :value="$f->vencido" /></td>
                    <td class="px-3 py-3 text-right font-semibold"><x-money :value="$f->saldo" /></td>
                    <td class="py-3 pl-3 pr-5">
                        @if ($f->tercero)
                            <div class="flex items-center justify-center gap-1">
                                <button type="button" class="btn btn-secondary !h-8 !px-2.5 text-xs"
                                    data-pagar="{{ $ruta('abonar', $f->tercero) }}" data-titulo="Abono de {{ ($c['nombre'])($f->tercero) }}"
                                    data-detalle="Se aplica del documento más antiguo al más reciente" data-saldo="{{ number_format($f->saldo, 2, '.', '') }}">
                                    <x-icon name="coins" class="text-brand-600" /> Abonar
                                </button>
                                <a href="{{ $ruta('cuenta', $f->tercero) }}" title="Estado de cuenta" class="grid size-8 place-items-center rounded-md text-brand-600 hover:bg-brand-50"><x-icon name="file-text" /></a>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
