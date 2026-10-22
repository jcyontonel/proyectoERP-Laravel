// Comportamiento del layout principal: barra lateral, menús desplegables y atajos.

const raiz = document.documentElement;
const sidebar = () => document.querySelector('[data-sidebar]');
const fondo = () => document.querySelector('[data-sidebar-fondo]');

/* ───── Barra lateral en móvil (cajón) ───── */
function abrirCajon() {
    sidebar()?.setAttribute('data-abierto', '');
    if (fondo()) fondo().hidden = false;
    document.body.classList.add('overflow-hidden', 'lg:overflow-auto');
}

function cerrarCajon() {
    sidebar()?.removeAttribute('data-abierto');
    if (fondo()) fondo().hidden = true;
    document.body.classList.remove('overflow-hidden', 'lg:overflow-auto');
}

/* ───── Barra lateral colapsada en escritorio (se recuerda en localStorage) ───── */
function abrirSecciones() {
    // Colapsada solo muestra íconos: todas las secciones deben estar abiertas
    document.querySelectorAll('details[data-seccion]').forEach((d) => d.setAttribute('open', ''));
}

function alternarMini() {
    const mini = raiz.classList.toggle('sidebar-mini');
    try { localStorage.setItem('sidebar', mini ? 'mini' : 'normal'); } catch (e) {}
    if (mini) abrirSecciones();
}

/* ───── Menús <details data-dropdown>: cerrar al hacer clic fuera o con Esc ───── */
function cerrarMenus(e) {
    document.querySelectorAll('details[data-dropdown][open]').forEach((d) => {
        if (!e || e.type === 'keydown' || !d.contains(e.target)) d.removeAttribute('open');
    });
}

document.addEventListener('click', (e) => {
    if (e.target.closest('[data-sidebar-abrir]')) return abrirCajon();
    if (e.target.closest('[data-sidebar-cerrar]') || e.target.closest('[data-sidebar-fondo]')) return cerrarCajon();
    if (e.target.closest('[data-sidebar-mini]')) return alternarMini();
    cerrarMenus(e);
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        cerrarMenus(e);
        cerrarCajon();
    }

    // Ctrl/⌘ + K: buscador global
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        const buscador = document.querySelector('[data-buscador]');
        if (buscador) {
            e.preventDefault();
            buscador.focus();
            buscador.select();
        }
    }
});

// Abrir un menú desplegable cierra los demás
document.addEventListener('toggle', (e) => {
    const d = e.target;
    if (d.matches?.('details[data-dropdown]') && d.open) {
        document.querySelectorAll('details[data-dropdown][open]').forEach((o) => o !== d && o.removeAttribute('open'));
    }
}, true);

document.addEventListener('DOMContentLoaded', () => {
    if (raiz.classList.contains('sidebar-mini')) abrirSecciones();
});
