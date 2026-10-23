/**
 * Culqi Checkout v4: la tarjeta se escribe en el formulario de Culqi (nunca pasa por la tienda) y se obtiene un
 * token de un solo uso; el servidor crea el cargo con la llave secreta de la tienda.
 * Resuelve con el token o rechaza con el mensaje de Culqi. Si el comprador cierra el formulario la promesa queda
 * pendiente (la pantalla no se bloquea: puede volver a pulsar «Pagar», que abre un formulario nuevo).
 */
const SCRIPT = 'https://checkout.culqi.com/js/v4';

function cargarScript() {
    if (window.Culqi) return Promise.resolve();
    return new Promise((resolver, rechazar) => {
        const s = document.createElement('script');
        s.src = SCRIPT;
        s.onload = () => resolver();
        s.onerror = () => rechazar(new Error('No se pudo cargar el formulario de pago. Revisa tu conexión.'));
        document.head.appendChild(s);
    });
}

export async function pagarConCulqi(llavePublica, { titulo, monto, correo }) {
    await cargarScript();
    const Culqi = window.Culqi;
    Culqi.publicKey = llavePublica;
    Culqi.settings({ title: titulo, currency: 'PEN', amount: Math.round(monto * 100) });
    Culqi.options({ lang: 'es', installments: false, paymentMethods: { tarjeta: true, yape: false, bancaMovil: false, agente: false, billetera: false, cuotealo: false } });
    if (correo) Culqi.options({ customerEmail: correo });

    return new Promise((resolver, rechazar) => {
        window.culqi = () => {
            if (Culqi.token) {
                const token = Culqi.token.id;
                Culqi.close();
                resolver(token);
            } else if (Culqi.error) {
                rechazar(new Error(Culqi.error.user_message || 'El pago no fue aceptado.'));
            }
        };
        Culqi.open();
    });
}
