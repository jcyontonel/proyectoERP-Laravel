{{-- 4 indicadores: con ventas → ventas, transacciones, caja/inventario, stock; sin ventas (almacén) → inventario --}}
<section aria-label="Indicadores" class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @if ($widgets['ventas'])
        @php
            $v = $kpiVentas;
            $sube = ($v['variacion'] ?? 0) >= 0;
        @endphp
        <x-stat-card :title="$alcance === 'propio' ? 'Mis ventas del día' : 'Ventas del día'" icon="dollar-sign" :value="'S/ ' . number_format($v['total'], 2)"
            :href="route('ventas.index', ['desde' => $fecha->toDateString(), 'hasta' => $fecha->toDateString()])">
            @if ($v['variacion'] !== null)
                <x-badge :tone="$sube ? 'success' : 'danger'" shape="box">{{ $sube ? '+' : '' }}{{ $v['variacion'] }}%</x-badge>
                <span>vs. el día anterior</span>
                <x-icon :name="$sube ? 'trending-up' : 'trending-down'" :class="$sube ? 'size-3.5 text-emerald-600' : 'size-3.5 text-red-500'" />
            @else
                <span>Sin ventas el día anterior</span>
            @endif
        </x-stat-card>

        <x-stat-card title="Transacciones" icon="receipt" :value="number_format($v['cantidad'])">
            <span>ticket promedio <strong class="text-ink"><x-money :value="$v['ticket']" /></strong></span>
        </x-stat-card>
    @endif

    @if ($widgets['caja'])
        <x-stat-card title="Caja / turno" icon="wallet" :tone="$caja['abierta'] ? 'emerald' : 'slate'" :value="$caja['abierta'] ? 'S/ ' . number_format($caja['efectivo'], 2) : 'Cerrada'" :href="route('caja.index')">
            @if ($caja['abierta'])
                <x-badge tone="success" :dot="true">Abierta</x-badge>
                <span>desde las {{ $caja['desde']->format('H:i') }} · efectivo esperado</span>
            @else
                <span>Abre tu turno para registrar cobros</span>
            @endif
        </x-stat-card>
    @elseif ($widgets['ventas'])
        <x-stat-card title="Ventas del mes" icon="calendar" tone="slate" :value="'S/ ' . number_format($kpiVentas['mes'], 2)">
            <span>{{ str($fecha->translatedFormat('F'))->ucfirst() }} hasta el {{ $fecha->format('d') }}</span>
        </x-stat-card>
    @endif

    @if ($widgets['inventario'] && ! $widgets['ventas'])
        <x-stat-card title="Inventario valorizado" icon="pie-chart" tone="emerald" :value="'S/ ' . number_format($inventario['valor'], 2)">
            <span>{{ number_format($inventario['productos']) }} productos al costo</span>
        </x-stat-card>
        <x-stat-card title="Agotados" icon="x-circle" tone="red" :value="number_format($inventario['agotados'])" :href="route('productos.index', ['stock' => 'agotado'])">
            <span>sin stock en ningún almacén</span>
        </x-stat-card>
    @endif

    <x-stat-card title="Stock bajo" icon="alert-triangle" tone="amber" :value="number_format($stock['total'])" :href="route('productos.index', ['stock' => 'bajo'])">
        <x-badge tone="warning" shape="box">Alertas</x-badge>
        <span>producto-almacén bajo el mínimo</span>
    </x-stat-card>

    @if ($widgets['lotes'] && ! $widgets['ventas'])
        <x-stat-card title="Lotes por vencer" icon="calendar-clock" tone="amber" :value="number_format($porVencer->count())">
            <span>en los próximos {{ \App\Modulos\Erp\Services\NotificacionService::DIAS_POR_VENCER }} días</span>
        </x-stat-card>
    @endif
</section>
