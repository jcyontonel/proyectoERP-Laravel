<script setup>
import { computed, reactive, ref } from 'vue';
import { enviar } from '../api';
import { soles } from '../carrito';
import { pagarConCulqi } from '../culqi';

const props = defineProps({ carrito: Object, compra: Object, tienda: Object, apiPedidos: String });
const emit = defineEmits(['cerrar']);

const paso = ref('carrito');   // carrito → datos → listo
const enviando = ref(false);
const error = ref('');
const errores = ref({});
const resultado = ref(null);

const zonas = computed(() => props.compra?.zonas || []);
const metodos = computed(() => props.compra?.metodos_pago || []);
const recojo = computed(() => props.compra?.recojo);

const f = reactive({
    nombre: '', telefono: '', correo: '', tipo_doc: '', documento: '',
    entrega: zonas.value.length ? 'envio' : 'recojo',
    zona_envio_id: zonas.value[0]?.id || null, direccion: '', referencia: '',
    metodo_pago: metodos.value[0]?.clave || '', notas: '',
});

const zona = computed(() => (f.entrega === 'envio' ? zonas.value.find((z) => z.id === f.zona_envio_id) : null));
const envio = computed(() => zona.value?.costo || 0);
const total = computed(() => Math.round((props.carrito.subtotal.value + envio.value) * 100) / 100);
const faltaMinimo = computed(() => Math.max(0, (props.compra?.pedido_minimo || 0) - props.carrito.subtotal.value));
const err = (campo) => errores.value[campo]?.[0];

async function confirmar() {
    error.value = '';
    errores.value = {};
    if (f.metodo_pago === 'tarjeta' && !f.correo) {
        errores.value = { 'cliente.correo': ['Para pagar con tarjeta escribe tu correo (ahí te llega la constancia).'] };
        return;
    }
    try {
        // Tarjeta: primero el formulario de Culqi (la pantalla no se bloquea mientras está abierto)
        let culqiToken = null;
        if (f.metodo_pago === 'tarjeta') {
            culqiToken = await pagarConCulqi(props.compra.culqi.llave_publica, { titulo: props.tienda.nombre, monto: total.value, correo: f.correo });
        }
        enviando.value = true;
        resultado.value = await enviar(props.apiPedidos, {
            cliente: { nombre: f.nombre, telefono: f.telefono, correo: f.correo || null, tipo_doc: f.documento ? f.tipo_doc || (f.documento.length === 11 ? '6' : '1') : null, documento: f.documento || null },
            entrega: f.entrega, zona_envio_id: f.entrega === 'envio' ? f.zona_envio_id : null,
            direccion: f.entrega === 'envio' ? f.direccion : null, referencia: f.entrega === 'envio' ? f.referencia : null,
            metodo_pago: f.metodo_pago, culqi_token: culqiToken, notas: f.notas || null,
            items: props.carrito.lineas.value.map((l) => ({ id: l.producto.id, cantidad: l.cantidad })),
        });
        resultado.value.lineas = props.carrito.lineas.value.map((l) => `${l.cantidad} × ${l.producto.nombre}`);
        props.carrito.vaciar();
        paso.value = 'listo';
    } catch (e) {
        error.value = e.message;
        errores.value = e.errores || {};
    } finally {
        enviando.value = false;
    }
}

const whatsappPedido = computed(() => {
    if (!props.tienda.whatsapp || !resultado.value) return null;
    const texto = `Hola ${props.tienda.nombre}, hice el pedido ${resultado.value.codigo} por ${soles(resultado.value.total)}:\n${resultado.value.lineas.join('\n')}`;
    return `https://wa.me/${props.tienda.whatsapp}?text=${encodeURIComponent(texto)}`;
});
</script>

<template>
    <div class="fixed inset-0 z-40 flex justify-end bg-slate-900/50" role="dialog" aria-modal="true" aria-label="Carrito" @click.self="$emit('cerrar')">
        <aside class="flex h-full w-full max-w-md flex-col bg-white shadow-xl">
            <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-bold">{{ paso === 'carrito' ? 'Tu carrito' : paso === 'datos' ? 'Completa tu pedido' : 'Pedido recibido' }}</h2>
                <button type="button" class="grid size-9 place-items-center rounded-full hover:bg-slate-100" aria-label="Cerrar" @click="$emit('cerrar')">✕</button>
            </header>

            <!-- 1. Carrito -->
            <template v-if="paso === 'carrito'">
                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <p v-if="!carrito.lineas.value.length" class="py-16 text-center text-sm text-slate-500">Tu carrito está vacío.</p>
                    <ul class="divide-y divide-slate-100">
                        <li v-for="l in carrito.lineas.value" :key="l.producto.id" class="flex gap-3 py-3">
                            <img v-if="l.producto.imagen" :src="l.producto.imagen" alt="" class="size-16 rounded-xl object-cover">
                            <span v-else class="grid size-16 place-items-center rounded-xl bg-slate-100 text-xl font-bold text-slate-300">{{ l.producto.nombre.charAt(0) }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2 text-sm font-medium">{{ l.producto.nombre }}</p>
                                <p class="text-xs text-slate-500">{{ soles(l.producto.precio) }} c/u</p>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <div class="flex items-center rounded-lg ring-1 ring-slate-300">
                                        <button type="button" class="px-2.5 py-1" aria-label="Menos" @click="carrito.poner(l.producto.id, l.cantidad - 1)">−</button>
                                        <span class="w-7 text-center text-sm font-semibold">{{ l.cantidad }}</span>
                                        <button type="button" class="px-2.5 py-1" aria-label="Más" :disabled="l.producto.maximo != null && l.cantidad >= l.producto.maximo" @click="carrito.poner(l.producto.id, l.cantidad + 1)">+</button>
                                    </div>
                                    <button type="button" class="text-xs text-slate-500 underline" @click="carrito.quitar(l.producto.id)">Quitar</button>
                                </div>
                            </div>
                            <p class="text-sm font-semibold">{{ soles(l.total) }}</p>
                        </li>
                    </ul>
                </div>
                <footer v-if="carrito.lineas.value.length" class="border-t border-slate-200 p-5">
                    <div class="flex justify-between text-base font-bold"><span>Subtotal</span><span>{{ soles(carrito.subtotal.value) }}</span></div>
                    <p v-if="faltaMinimo > 0" class="mt-2 text-xs text-amber-700">El pedido mínimo es {{ soles(compra.pedido_minimo) }}: te faltan {{ soles(faltaMinimo) }}.</p>
                    <button type="button" class="btn-marca mt-3 w-full py-3" :disabled="faltaMinimo > 0 || !metodos.length" @click="paso = 'datos'">Continuar</button>
                    <p v-if="!metodos.length" class="mt-2 text-xs text-slate-500">Esta tienda aún no configuró sus métodos de pago.</p>
                </footer>
            </template>

            <!-- 2. Datos, entrega y pago -->
            <form v-else-if="paso === 'datos'" class="flex flex-1 flex-col overflow-hidden" @submit.prevent="confirmar">
                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div>
                        <label class="etiqueta" for="t-nombre">Nombre *</label>
                        <input id="t-nombre" v-model="f.nombre" class="campo" required maxlength="150" autocomplete="name">
                        <p v-if="err('cliente.nombre')" class="mt-1 text-xs text-red-600">{{ err('cliente.nombre') }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="etiqueta" for="t-tel">Teléfono *</label>
                            <input id="t-tel" v-model="f.telefono" class="campo" required maxlength="20" inputmode="tel" autocomplete="tel">
                            <p v-if="err('cliente.telefono')" class="mt-1 text-xs text-red-600">{{ err('cliente.telefono') }}</p>
                        </div>
                        <div>
                            <label class="etiqueta" for="t-correo">Correo</label>
                            <input id="t-correo" v-model="f.correo" type="email" class="campo" maxlength="150" autocomplete="email">
                            <p v-if="err('cliente.correo')" class="mt-1 text-xs text-red-600">{{ err('cliente.correo') }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="etiqueta" for="t-doc">DNI o RUC (para tu comprobante)</label>
                        <input id="t-doc" v-model="f.documento" class="campo" maxlength="11" inputmode="numeric" placeholder="Opcional">
                    </div>

                    <fieldset>
                        <legend class="etiqueta">Entrega</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <label v-if="zonas.length" :class="['cursor-pointer rounded-xl p-3 text-sm ring-1', f.entrega === 'envio' ? 'bg-marca/10 ring-marca' : 'ring-slate-200']">
                                <input v-model="f.entrega" type="radio" value="envio" class="sr-only"> Envío a domicilio
                            </label>
                            <label v-if="recojo" :class="['cursor-pointer rounded-xl p-3 text-sm ring-1', f.entrega === 'recojo' ? 'bg-marca/10 ring-marca' : 'ring-slate-200']">
                                <input v-model="f.entrega" type="radio" value="recojo" class="sr-only"> Recojo en tienda
                            </label>
                        </div>
                        <p v-if="f.entrega === 'recojo' && recojo?.direccion" class="mt-2 text-xs text-slate-500">Recoges en: {{ recojo.direccion }}</p>
                    </fieldset>

                    <template v-if="f.entrega === 'envio'">
                        <div>
                            <label class="etiqueta" for="t-zona">Zona *</label>
                            <select id="t-zona" v-model="f.zona_envio_id" class="campo">
                                <option v-for="z in zonas" :key="z.id" :value="z.id">{{ z.nombre }} — {{ z.costo > 0 ? soles(z.costo) : 'gratis' }}{{ z.tiempo_entrega ? ' · ' + z.tiempo_entrega : '' }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="etiqueta" for="t-dir">Dirección *</label>
                            <input id="t-dir" v-model="f.direccion" class="campo" required maxlength="255" autocomplete="street-address">
                            <p v-if="err('direccion')" class="mt-1 text-xs text-red-600">{{ err('direccion') }}</p>
                        </div>
                        <div>
                            <label class="etiqueta" for="t-ref">Referencia</label>
                            <input id="t-ref" v-model="f.referencia" class="campo" maxlength="255" placeholder="Frente al parque, puerta verde…">
                        </div>
                    </template>

                    <fieldset>
                        <legend class="etiqueta">Pago</legend>
                        <div class="space-y-2">
                            <label v-for="m in metodos" :key="m.clave" :class="['flex cursor-pointer items-center gap-2 rounded-xl p-3 text-sm ring-1', f.metodo_pago === m.clave ? 'bg-marca/10 ring-marca' : 'ring-slate-200']">
                                <input v-model="f.metodo_pago" type="radio" :value="m.clave" class="accent-[var(--marca)]"> {{ m.nombre }}
                            </label>
                        </div>
                        <p v-if="compra.culqi?.modo === 'prueba' && f.metodo_pago === 'tarjeta'" class="mt-2 text-xs text-amber-700">Modo de prueba: usa una tarjeta de prueba de Culqi (no se cobra dinero real).</p>
                    </fieldset>

                    <div>
                        <label class="etiqueta" for="t-notas">Notas</label>
                        <textarea id="t-notas" v-model="f.notas" class="campo" rows="2" maxlength="500"></textarea>
                    </div>
                </div>

                <footer class="border-t border-slate-200 p-5">
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between"><span>Subtotal</span><span>{{ soles(carrito.subtotal.value) }}</span></div>
                        <div v-if="f.entrega === 'envio'" class="flex justify-between"><span>Envío</span><span>{{ envio > 0 ? soles(envio) : 'Gratis' }}</span></div>
                        <div class="flex justify-between text-base font-bold"><span>Total</span><span>{{ soles(total) }}</span></div>
                    </div>
                    <p v-if="error" class="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-700" role="alert">{{ error }}</p>
                    <div class="mt-3 flex gap-2">
                        <button type="button" class="btn-suave" @click="paso = 'carrito'">Atrás</button>
                        <button type="submit" class="btn-marca flex-1 py-3" :disabled="enviando">
                            {{ enviando ? 'Enviando…' : f.metodo_pago === 'tarjeta' ? `Pagar ${soles(total)}` : 'Confirmar pedido' }}
                        </button>
                    </div>
                </footer>
            </form>

            <!-- 3. Confirmación -->
            <div v-else class="flex-1 overflow-y-auto px-5 py-8 text-center">
                <div class="mx-auto grid size-16 place-items-center rounded-full bg-emerald-100 text-3xl text-emerald-600">✓</div>
                <p class="mt-4 text-lg font-bold">Pedido {{ resultado.codigo }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ resultado.mensaje }}</p>
                <p class="mt-3 text-2xl font-bold">{{ soles(resultado.total) }}</p>
                <p v-if="resultado.pagado" class="mt-1 text-sm font-medium text-emerald-700">Pago con tarjeta aprobado.</p>
                <p v-else-if="compra.instrucciones_pago" class="mt-4 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-left text-sm text-slate-700">{{ compra.instrucciones_pago }}</p>
                <a v-if="whatsappPedido" :href="whatsappPedido" target="_blank" rel="noopener" class="btn-marca mt-6 w-full">Avisar por WhatsApp</a>
                <button type="button" class="btn-suave mt-2 w-full" @click="$emit('cerrar')">Seguir comprando</button>
            </div>
        </aside>
    </div>
</template>
