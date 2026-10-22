{{-- $item: clave, label, icon, url, activo, pronto, contador (App\Support\Menu) --}}
<li>
    <a href="{{ $item->url }}" title="{{ $item->label }}{{ $item->pronto ? ' (próximamente)' : '' }}" @if ($item->activo) aria-current="page" @endif
        @class([
            'relative flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium transition lg:mini:justify-center lg:mini:px-0',
            'bg-brand-50 text-brand-700' => $item->activo,
            'text-slate-600 hover:bg-slate-100 hover:text-ink' => ! $item->activo && ! $item->pronto,
            'text-slate-400 hover:bg-slate-50 hover:text-slate-600' => ! $item->activo && $item->pronto,
        ])>
        <x-icon :name="$item->icon" @class(['size-[18px]', 'text-brand-600' => $item->activo]) />
        <span class="min-w-0 flex-1 truncate lg:mini:hidden">{{ $item->label }}</span>

        @if ($item->contador > 0)
            <span class="rounded-full bg-amber-100 px-1.5 text-[10px] font-semibold leading-4 text-amber-700 lg:mini:absolute lg:mini:right-1 lg:mini:top-1">{{ $item->contador > 99 ? '99+' : $item->contador }}</span>
        @elseif ($item->pronto)
            <span class="rounded bg-slate-100 px-1.5 text-[10px] font-medium leading-4 text-slate-400 lg:mini:hidden">Pronto</span>
        @endif
    </a>
</li>
