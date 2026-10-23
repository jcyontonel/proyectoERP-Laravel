<script setup>
import { computed, onMounted, ref } from 'vue';
import { obtener } from './api';
import { soles, usarCarrito } from './carrito';
import TarjetaProducto from './componentes/TarjetaProducto.vue';
import DetalleProducto from './componentes/DetalleProducto.vue';
import PanelCarrito from './componentes/PanelCarrito.vue';

const props = defineProps({ apiDatos: String, apiPedidos: String, slug: String });

// Todo llega en una sola llamada asíncrona: tienda, empresa, categorías, productos, zonas y pagos
const datos = ref(null);
const error = ref('');
const cargando = ref(true);

const productos = computed(() => datos.value?.productos || []);
const tienda = computed(() => datos.value?.tienda || {});
const esTienda = computed(() => tienda.value.modalidad === 'tienda');
const carrito = usarCarrito(props.slug, productos);

const busqueda = ref('');
const categoria = ref(null);
const abierto = ref(null);       // producto en detalle
const verCarrito = ref(false);

const normalizar = (t) => (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

const filtrados = computed(() => {
    const q = normalizar(busqueda.value.trim());
    return productos.value.filter((p) => (!categoria.value || p.categoria_id === categoria.value)
        && (!q || normalizar(`${p.nombre} ${p.descripcion || ''}`).includes(q)));
});
const destacados = computed(() => productos.value.filter((p) => p.destacado).slice(0, 8));
const mostrarDestacados = computed(() => destacados.value.length > 0 && !busqueda.value && !categoria.value);

function whatsapp(texto) {
    const n = tienda.value.whatsapp;
    return n ? `https://wa.me/${n}?text=${encodeURIComponent(texto)}` : null;
}

async function cargar() {
    cargando.value = true;
    error.value = '';
    try {
        datos.value = await obtener(props.apiDatos);
        document.title = datos.value.tienda.nombre;
    } catch (e) {
        error.value = e.message;
    } finally {
        cargando.value = false;
    }
}

onMounted(cargar);
</script>

<template>
    <!-- Cargando -->
    <div v-if="cargando" class="mx-auto max-w-6xl px-4 py-10">
        <div class="h-10 w-48 animate-pulse rounded-lg bg-slate-200"></div>
        <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <div v-for="i in 8" :key="i" class="aspect-[3/4] animate-pulse rounded-2xl bg-slate-200"></div>
        </div>
    </div>

    <!-- Error al cargar -->
    <div v-else-if="error" class="grid min-h-dvh place-items-center px-4 text-center">
        <div>
            <p class="text-lg font-semibold">No pudimos cargar la tienda</p>
            <p class="mt-1 text-sm text-slate-500">{{ error }}</p>
            <button type="button" class="btn-marca mt-4" @click="cargar">Reintentar</button>
        </div>
    </div>

    <div v-else class="min-h-dvh pb-24">
        <!-- Cabecera -->
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3">
                <img v-if="tienda.logo" :src="tienda.logo" :alt="tienda.nombre" class="size-10 rounded-xl object-contain">
                <span v-else class="grid size-10 place-items-center rounded-xl bg-marca text-lg font-bold text-white">{{ tienda.nombre?.charAt(0) }}</span>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-bold sm:text-lg">{{ tienda.nombre }}</h1>
                    <p v-if="tienda.lema" class="truncate text-xs text-slate-500">{{ tienda.lema }}</p>
                </div>
                <button v-if="esTienda" type="button" class="relative rounded-xl p-2.5 text-slate-700 hover:bg-slate-100" aria-label="Ver carrito" @click="verCarrito = true">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1" /><circle cx="19" cy="21" r="1" /><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12" /></svg>
                    <span v-if="carrito.unidades.value" class="absolute -right-0.5 -top-0.5 grid min-w-5 place-items-center rounded-full bg-marca px-1 text-[11px] font-bold text-white">{{ carrito.unidades.value }}</span>
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4">
            <!-- Presentación -->
            <section v-if="datos.tienda.descripcion" class="mt-6 rounded-2xl bg-marca/10 p-5 text-sm leading-relaxed text-slate-700 whitespace-pre-line">{{ datos.tienda.descripcion }}</section>

            <!-- Búsqueda y categorías -->
            <div class="sticky top-[65px] z-20 -mx-4 mt-4 bg-slate-50/95 px-4 py-3 backdrop-blur">
                <input v-model="busqueda" type="search" class="campo" placeholder="Buscar productos…" aria-label="Buscar productos">
                <div v-if="datos.categorias.length" class="mt-3 flex gap-2 overflow-x-auto pb-1">
                    <button type="button" :class="['shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium', !categoria ? 'bg-marca text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200']" @click="categoria = null">Todo</button>
                    <button v-for="c in datos.categorias" :key="c.id" type="button"
                        :class="['shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium', categoria === c.id ? 'bg-marca text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200']"
                        @click="categoria = categoria === c.id ? null : c.id">{{ c.nombre }}</button>
                </div>
            </div>

            <!-- Destacados -->
            <section v-if="mostrarDestacados" class="mt-4">
                <h2 class="text-lg font-bold">Destacados</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <TarjetaProducto v-for="p in destacados" :key="'d' + p.id" :producto="p" :es-tienda="esTienda" :en-carrito="carrito.cantidad(p.id)"
                        @ver="abierto = p" @agregar="carrito.agregar(p.id)" />
                </div>
            </section>

            <!-- Catálogo -->
            <section class="mt-6">
                <h2 class="text-lg font-bold">{{ categoria ? datos.categorias.find((c) => c.id === categoria)?.nombre : 'Todos los productos' }}
                    <span class="text-sm font-normal text-slate-500">({{ filtrados.length }})</span></h2>
                <div v-if="filtrados.length" class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <TarjetaProducto v-for="p in filtrados" :key="p.id" :producto="p" :es-tienda="esTienda" :en-carrito="carrito.cantidad(p.id)"
                        @ver="abierto = p" @agregar="carrito.agregar(p.id)" />
                </div>
                <p v-else class="mt-6 rounded-2xl bg-white p-8 text-center text-sm text-slate-500 ring-1 ring-slate-200">No encontramos productos con esa búsqueda.</p>
            </section>
        </main>

        <!-- Pie con los datos de la empresa -->
        <footer class="mx-auto mt-12 max-w-6xl border-t border-slate-200 px-4 py-8 text-sm text-slate-500">
            <p class="font-semibold text-slate-700">{{ datos.empresa.razon_social }}</p>
            <p>RUC {{ datos.empresa.ruc }}<span v-if="datos.empresa.direccion"> · {{ datos.empresa.direccion }}</span></p>
            <p v-if="datos.empresa.telefono || datos.tienda.correo">{{ [datos.empresa.telefono, datos.tienda.correo].filter(Boolean).join(' · ') }}</p>
        </footer>

        <!-- Botón de WhatsApp -->
        <a v-if="tienda.whatsapp" :href="whatsapp(`Hola ${tienda.nombre}, quiero hacer una consulta.`)" target="_blank" rel="noopener"
            class="fixed bottom-5 left-5 z-30 grid size-14 place-items-center rounded-full bg-[#25d366] text-white shadow-lg" aria-label="Escríbenos por WhatsApp">
            <svg class="size-7" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1s-.5-.1-.7.1-.8 1-1 1.2-.4.2-.7.1a8 8 0 0 1-4-3.5c-.3-.5.3-.5.9-1.6.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6a1.2 1.2 0 0 0-.8.4 3.5 3.5 0 0 0-1.1 2.6 6 6 0 0 0 1.3 3.2 13.7 13.7 0 0 0 5.3 4.7c2 .8 2.8.9 3.8.7a3.2 3.2 0 0 0 2.1-1.5 2.6 2.6 0 0 0 .2-1.5c-.1-.1-.3-.2-.6-.3M12 21.8a9.9 9.9 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8M12 0a11.9 11.9 0 0 0-10.3 17.9L0 24l6.3-1.6A11.9 11.9 0 1 0 12 0" /></svg>
        </a>

        <!-- Barra del carrito (móvil) -->
        <button v-if="esTienda && carrito.unidades.value && !verCarrito" type="button"
            class="btn-marca fixed inset-x-4 bottom-5 z-30 py-3.5 text-base sm:left-auto sm:right-6 sm:w-80" @click="verCarrito = true">
            Ver carrito · {{ carrito.unidades.value }} · {{ soles(carrito.subtotal.value) }}
        </button>

        <DetalleProducto v-if="abierto" :producto="abierto" :es-tienda="esTienda" :en-carrito="carrito.cantidad(abierto.id)"
            :whatsapp="whatsapp(`Hola, me interesa: ${abierto.nombre} (${soles(abierto.precio)})`)"
            @cerrar="abierto = null" @agregar="(n) => { carrito.agregar(abierto.id, n); abierto = null; verCarrito = true; }" />

        <PanelCarrito v-if="esTienda && verCarrito" :carrito="carrito" :compra="datos.compra" :tienda="tienda" :api-pedidos="apiPedidos"
            @cerrar="verCarrito = false" />
    </div>
</template>
