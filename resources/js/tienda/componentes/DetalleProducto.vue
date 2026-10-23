<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { soles } from '../carrito';

const props = defineProps({ producto: Object, esTienda: Boolean, enCarrito: Number, whatsapp: String });
const emit = defineEmits(['cerrar', 'agregar']);

const cantidad = ref(1);
const maximo = props.producto.maximo != null ? Math.max(0, Math.floor(props.producto.maximo) - (props.enCarrito || 0)) : 99;

const alTeclear = (e) => e.key === 'Escape' && emit('cerrar');
onMounted(() => document.addEventListener('keydown', alTeclear));
onUnmounted(() => document.removeEventListener('keydown', alTeclear));
</script>

<template>
    <div class="fixed inset-0 z-40 flex items-end justify-center bg-slate-900/50 sm:items-center sm:p-6" role="dialog" aria-modal="true" :aria-label="producto.nombre" @click.self="$emit('cerrar')">
        <div class="max-h-[92dvh] w-full max-w-2xl overflow-y-auto rounded-t-3xl bg-white sm:rounded-3xl">
            <div class="grid sm:grid-cols-2">
                <div class="relative aspect-square bg-slate-100">
                    <img v-if="producto.imagen" :src="producto.imagen" :alt="producto.nombre" class="size-full object-cover">
                    <span v-else class="grid size-full place-items-center text-6xl font-bold text-slate-300">{{ producto.nombre.charAt(0) }}</span>
                    <button type="button" class="absolute right-3 top-3 grid size-9 place-items-center rounded-full bg-white/90 text-slate-700 shadow" aria-label="Cerrar" @click="$emit('cerrar')">✕</button>
                </div>
                <div class="flex flex-col p-5">
                    <h2 class="text-lg font-bold leading-snug">{{ producto.nombre }}</h2>
                    <p class="mt-1 text-2xl font-bold text-marca">{{ soles(producto.precio) }}</p>
                    <p v-if="producto.descripcion" class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ producto.descripcion }}</p>
                    <p v-if="producto.stock != null && !producto.agotado" class="mt-2 text-xs text-slate-500">{{ producto.stock }} disponibles</p>

                    <div class="mt-auto pt-5">
                        <p v-if="producto.agotado" class="rounded-xl bg-slate-100 p-3 text-center text-sm font-medium text-slate-600">Agotado por ahora</p>
                        <template v-else-if="esTienda">
                            <div v-if="maximo > 0" class="flex items-center gap-3">
                                <div class="flex items-center rounded-xl ring-1 ring-slate-300">
                                    <button type="button" class="px-3 py-2 text-lg" aria-label="Menos" :disabled="cantidad <= 1" @click="cantidad--">−</button>
                                    <span class="w-8 text-center font-semibold">{{ cantidad }}</span>
                                    <button type="button" class="px-3 py-2 text-lg" aria-label="Más" :disabled="cantidad >= maximo" @click="cantidad++">+</button>
                                </div>
                                <button type="button" class="btn-marca flex-1" @click="$emit('agregar', cantidad)">Agregar · {{ soles(producto.precio * cantidad) }}</button>
                            </div>
                            <p v-else class="text-sm text-slate-500">Ya tienes en el carrito todo lo disponible.</p>
                        </template>
                        <a v-else-if="whatsapp" :href="whatsapp" target="_blank" rel="noopener" class="btn-marca w-full">Consultar por WhatsApp</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
