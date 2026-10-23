@props(['title', 'icon' => null, 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5']) }}>
    <header @class(['mb-4 flex justify-between gap-3', 'items-start' => $subtitle, 'items-center' => ! $subtitle])>
        <div class="min-w-0">
            <h2 class="flex items-center gap-2 text-base font-semibold text-ink">
                @if ($icon)
                    <x-icon :name="$icon" class="size-[18px] text-brand-600" />
                @endif
                {{ $title }}
            </h2>
            @if ($subtitle)
                <p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($aside)
            <div class="shrink-0 text-xs text-slate-500">{{ $aside }}</div>
        @endisset
    </header>

    {{ $slot }}
</section>
