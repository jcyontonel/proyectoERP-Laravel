{{-- $cuentas: cobrar / pagar [saldo, documentos, vencidas] --}}
<x-card title="Cuentas pendientes" icon="coins">
    <div class="grid grid-cols-2 gap-3">
        @foreach (['cobrar' => ['Por cobrar', 'cuentas-cobrar', 'text-emerald-700'], 'pagar' => ['Por pagar', 'cuentas-pagar', 'text-red-600']] as $clave => [$titulo, $modulo, $color])
            @php $c = $cuentas[$clave]; @endphp
            <a href="{{ route($modulo . '.index') }}" class="rounded-lg border border-slate-200 p-3 hover:border-brand-200 hover:bg-slate-50">
                <p class="text-xs text-slate-500">{{ $titulo }}</p>
                <p class="mt-1 text-lg font-bold {{ $c['saldo'] > 0 ? $color : 'text-ink' }}"><x-money :value="$c['saldo']" /></p>
                <p class="mt-0.5 text-[11px] text-slate-500">{{ $c['documentos'] }} {{ $c['documentos'] === 1 ? 'documento' : 'documentos' }}</p>
                @if ($c['vencidas'] > 0)
                    <x-badge tone="danger" shape="box" class="mt-1.5">{{ $c['vencidas'] }} {{ $c['vencidas'] === 1 ? 'vencida' : 'vencidas' }}</x-badge>
                @endif
            </a>
        @endforeach
    </div>
</x-card>
