# RicoPet: arquitectura de la plataforma

Una sola aplicación Laravel 12 con varios productos para las empresas (ERP y Página web) y la administración
global de la plataforma. Cada área vive en su módulo, en `app/Modulos/`.

## Módulos

| Módulo | Qué contiene | Depende de |
|---|---|---|
| `Nucleo` | Usuarios, empresas, roles por empresa (Spatie *teams*), auditoría, menú, layout, convenciones de rutas | — |
| `Catalogo` | Productos, categorías, unidades | Núcleo |
| `Erp` | Ventas, caja, clientes (propios de cada empresa), compras, inventario, finanzas, reportes, configuración | Núcleo, Catálogo |
| `Facturacion` | Comprobantes electrónicos: firma con Greenter, envío a SUNAT, bajas, API v1 para sistemas externos | Núcleo |
| `Suscripciones` | Productos de la plataforma (ERP, Página web), planes y sus módulos, titulares, suscripciones, pagos | Núcleo, Facturación |
| `Plataforma` | Panel global (`/plataforma`) y API de administración (`/api/admin`) | Suscripciones |
| `Web` | Tienda pública en Vue, pedidos, zonas de envío, Culqi y el bloque «Página web» del ERP | Catálogo, Erp, Suscripciones |

Cada módulo tiene `Providers/` (extiende `Nucleo\Providers\ProveedorModulo`), `routes/`, `resources/views/` y
`database/migrations/`. Los nombres de vista no llevan prefijo (`ventas.index`), salvo Plataforma (`plataforma::`).
Las dependencias entre módulos van en una sola dirección; donde el ERP necesita avisar a otro módulo usa eventos
(`Erp\Events\VentaGuardada` → Web marca el pedido convertido; `Facturacion\Events\EstadoDocumentoCambiado`).

Un negocio nuevo (restaurante, canchas…) es un módulo más con su producto en `productos_plataforma`, sus planes
y, si hace falta, su contexto en `config/modulos.php`.

## Contextos y dominios (`config/modulos.php`)

| Contexto | Desarrollo | Producción |
|---|---|---|
| `web` (pública) | `/` (directorio) y `/{slug}` (cada tienda) | `ricopet.com` y `ricopet.com/{slug}` (`WEB_DOMINIO`) |
| `erp` | `/erp` (login en `/erp/login`) | `erp.ricopet.com` (`ERP_DOMINIO`, `ERP_PREFIJO=`) |
| `plataforma` | `/plataforma` | `admin.ricopet.com` (`PLATAFORMA_DOMINIO`, `PLATAFORMA_PREFIJO=`) |
| `suscripciones` | `/suscripciones` (portal) | `subs.ricopet.com` (`SUSCRIPCIONES_DOMINIO`, `SUSCRIPCIONES_PREFIJO=`) |
| `api` | `/api` | `API_DOMINIO` o `/api` |

Con subdominios, `SESSION_DOMAIN=.ricopet.com` para compartir la sesión entre ERP y Plataforma.

## Productos, planes y suscripciones

- `productos_plataforma`: `erp` y `web`. Cada plan es de un producto.
  - ERP: **Básico**, **Pro**, **Total**.
  - Página web: **Catálogo** (muestrario y WhatsApp) y **Tienda** (carrito, pedidos, envíos, Culqi).
- Módulos por plan: `config/suscripciones.php` (`modulos` del ERP y `modulos_web`). Una ruta de un módulo que el
  plan no incluye no se abre y su ítem del menú no aparece (`Suscripciones\Support\Modulos`, `VerificarSuscripcion`).
- Una empresa tiene a lo más una suscripción vigente por producto. La paga un **titular** (RUC o DNI), a cuyo nombre
  salen los comprobantes; una suscripción puede cubrir varias empresas (`suscripcion_empresa`, hasta
  `planes.max_empresas`). Los demás límites (comprobantes, productos, usuarios, almacenes) son por empresa.
- Suspendida o cancelada = solo lectura en ese producto.

## Facturación electrónica

- Contrato `Facturacion\Contracts\EmisorComprobantes`, implementado por `MotorFacturacion` (Greenter + SUNAT).
- El emisor es la empresa: modo beta/producción, usuario SOL y certificado `.pfx` desde Configuración → SUNAT.
  En beta sin certificado se firma con uno de prueba autogenerado.
- Tabla `documentos`: facturas, boletas y notas (con su origen: venta, nota o pago de suscripción) y las bajas
  RA/RC como documentos propios (`afectado_id` / `baja_id`).
- Emitir firma en el momento y deja el envío en cola (job `EnviarDocumento`). `facturacion:procesar` (cada minuto)
  reenvía pendientes con espera creciente, consulta tickets de bajas y entrega webhooks.
- API v1 para sistemas de fuera: `/api/v1/comprobantes` con token por empresa
  (`php artisan facturacion:sistema {ruc} "Nombre" --webhook=URL`) y webhooks firmados (`X-Facturador-Firma`).

## Página web (tiendas)

- `tiendas` (una por empresa): slug, textos, contacto, entrega, métodos de pago y Culqi propio (llaves cifradas,
  modo prueba o producción; en prueba usa las llaves `CULQI_*` de la plataforma si la tienda no tiene las suyas).
- Solo se ven los productos con `publicar_web` (nunca todo el catálogo).
- La página es una app Vue 3 (`resources/js/tienda`) que pide **todo en una sola llamada** (`GET /api/tienda/{slug}`).
  El carrito vive en el navegador; al enviar el pedido el servidor recalcula precios, stock y envío.
- Con tarjeta, Culqi Checkout tokeniza la tarjeta en el navegador y el servidor crea el cargo; si se rechaza, no
  queda pedido.
- En el ERP: Página web → Mi tienda, Productos publicados, Pedidos, Zonas de envío, Ver mi tienda. Un pedido se
  convierte en venta desde su ficha (abre «Nueva venta» llenada).

## Plataforma (administración global)

- Permiso global `users.administra_plataforma` (no asignable en masa). Se da o quita solo por consola:
  `php artisan plataforma:asignar-admin {correo}` (`--quitar`).
- Las pantallas usan la API de administración dentro de la aplicación (`Plataforma\Support\ApiAdmin`), sin HTTP ni
  token. `/api/admin` sigue disponible para clientes externos con `ERP_ADMIN_TOKEN`.

## Procesos en segundo plano

`php artisan schedule:work` (o `schedule:run` cada minuto desde el Programador de tareas o cron) ejecuta:

- `facturacion:procesar`, cada minuto;
- `queue:work --stop-when-empty`, cada minuto: los envíos a SUNAT en cola;
- `suscripciones:procesar`, a diario: los cobros de renovación.

## Pruebas

- `php artisan test`: SQLite en memoria; SUNAT siempre simulado (`tests/Fakes/PasarelaFalsa`) y disco local falso.
- Contra SUNAT beta de verdad: `SUNAT_BETA_REAL=1 php artisan test --filter=FacturadorRealTest`.
- Contra MariaDB: `DB_CONNECTION=mysql DB_DATABASE=proyectoerp_pruebas php artisan test`. Las pruebas se niegan
  a correr en una base que no sea de pruebas (`*_pruebas`, `*_test` o en memoria).

## Comandos

| Comando | Para qué |
|---|---|
| `plataforma:asignar-admin {correo}` | Dar o quitar el permiso global |
| `facturacion:procesar` | Timer de SUNAT (también lo corre el programador) |
| `facturacion:sistema {ruc} "Nombre"` | Token de la API v1 para un sistema externo |
| `facturacion:importar-facturador` | Importó los comprobantes del facturador separado (ya hecho) |
| `suscripciones:procesar` | Cobros de renovación |
