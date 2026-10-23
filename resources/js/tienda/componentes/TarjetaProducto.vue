<script setup>
import { soles } from '../carrito';

defineProps({ producto: Object, esTienda: Boolean, enCarrito: Number });
defineEmits(['ver', 'agregar']);
</script>

<template>
    <article class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition hover:shadow-md">
        <button type="button" class="relative block aspect-square overflow-hidden bg-slate-100" :aria-label="`Ver ${producto.nombre}`" @click="$emit('ver')">
            <img v-if="producto.imagen" :src="producto.imagen" :alt="producto.nombre" loading="lazy" class="size-full object-cover transition group-hover:scale-105">
            <span v-else class="grid size-full place-items-center text-4xl font-bold text-slate-300">{{ producto.nombre.charAt(0) }}</span>
            <span v-if="producto.agotado" class="absolute left-2 top-2 rounded-full bg-slate-900/80 px-2 py-0.5 text-[11px] font-semibold text-white">Agotado</span>
            <span v-else-if="producto.destacado" class="absolute left-2 top-2 rounded-full bg-marca px-2 py-0.5 text-[11px] font-semibold text-white">Destacado</span>
        </button>
        <div class="flex flex-1 flex-col p-3">
            <h3 class="line-clamp-2 text-sm font-medium leading-snug">{{ producto.nombre }}</h3>
            <p v-if="producto.stock != null && !producto.agotado" class="mt-0.5 text-[11px] text-slate-500">{{ producto.stock }} disponibles</p>
            <div class="mt-auto flex items-center justify-between gap-2 pt-2">
                <span class="text-base font-bold">{{ soles(producto.precio) }}</span>
                <button v-if="esTienda && !producto.agotado" type="button" class="grid size-9 place-items-center rounded-xl bg-marca text-white transition hover:brightness-110"
                    :aria-label="`Agregar ${producto.nombre} al carrito`" @click="$emit('agregar')">
                    <span v-if="enCarrito" class="text-sm font-bold">{{ enCarrito }}</span>
                    <svg v-else class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                </button>
            </div>
        </div>
    </article>
</template>
