import { createApp } from 'vue';
import App from './App.vue';

// Tienda pública: la app recibe las URLs de su API desde el cascarón (data-*) y pide todo de una vez
const raiz = document.getElementById('tienda');
if (raiz) {
    createApp(App, {
        apiDatos: raiz.dataset.api,
        apiPedidos: raiz.dataset.pedidos,
        slug: raiz.dataset.slug,
    }).mount(raiz);
}
