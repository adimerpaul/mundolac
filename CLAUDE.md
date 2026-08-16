# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Proyecto

**Mundolac** — sistema de compra/venta e inventario para una distribuidora de lácteos (leches, yogures, jugos) en Oruro, Bolivia. Monorepo con dos aplicaciones independientes:

- `back/` — API REST en Laravel 13 (PHP 8.3) + una landing pública renderizada con Blade.
- `front/` — SPA en Quasar v2 / Vue 3 que consume la API.

Todo el dominio, la UI, los mensajes y los nombres de permisos están **en español**. Mantener esa convención (`Producto`, `Venta`, `Compra`, `Pedido`, `Cliente`, `Proveedor`, `Lote`, `Configuracion`).

> `AGENTS.md` está desactualizado: describe `back/` y `front/` como instalaciones vacías y menciona un directorio `ejemplo/` que ya no existe. Preferir este archivo.

## Comandos

### Backend (`cd back`)

```bash
composer run setup            # install + .env + key + migrate + npm build (primera vez)
php artisan serve             # API en http://localhost:8000
composer run dev              # serve + queue + pail + vite en paralelo
php artisan migrate           # migraciones (también crean permisos y datos iniciales)
php artisan test              # PHPUnit (SQLite en memoria, ver phpunit.xml)
php artisan test --filter=NombreDelTest
composer run test             # config:clear + artisan test
vendor/bin/pint               # formateo PSR-12 (Laravel Pint)
```

Requiere MySQL corriendo (XAMPP en `/c/xampp8.2`), base `mundolac` en `127.0.0.1:3306`, usuario `root` sin contraseña. Usuario semilla: `admin` / `admin` (con todos los permisos).

### Frontend (`cd front`)

```bash
npm run dev                   # quasar dev (http://localhost:9000, router en modo history)
npm run build                 # quasar build → front/dist
```

`npm run test` es un placeholder; no hay tests de frontend.

`.env.development` apunta a `http://localhost:8000/api`; `.env.production` a `https://bmundolac.tuprogam.com/api`. Ambos están en `.gitignore` (usar `.env.example` como plantilla). **`front/dist/` sí se versiona** (la línea `/dist` está comentada en `front/.gitignore`) — el build se commitea para el despliegue.

## Arquitectura

### Autenticación y permisos

- Login por `username` + `password` (`POST /api/login`) → token Sanctum. No hay endpoint de registro.
- Todas las rutas de negocio viven bajo `auth:sanctum` en `back/routes/api.php`. Excepciones públicas: `/api/login` y `/api/configuracion`.
- **No hay roles**: se usan permisos directos de Spatie asignados usuario a usuario. Cada controlador declara su propio helper privado `authorizeAction()` / `requirePermission()` que hace `abort_unless($request->user()?->hasPermissionTo($perm), 403)`. No hay middleware de permisos ni Policies — al añadir un endpoint hay que llamar al helper explícitamente.
- Los permisos se crean con `Permission::firstOrCreate()` **dentro de las migraciones**, no en seeders. Un módulo nuevo añade sus permisos en su propia migración. Conjunto actual: `Ver Panel` (dashboard de inicio), `Ver/Crear/Editar/Eliminar Usuarios`, `Gestionar Permisos`, `Ver/Crear/Editar/Eliminar Productos`, `Ver/Crear/Anular Ventas`, `Ver/Crear Compras`, `Ver Clientes`, `Gestionar Clientes`, `Ver/Crear Pedidos`, `Gestionar Configuración`, `Ver Reportes`.
- La tabla `permissions` tiene tres columnas propias del proyecto: `modulo` (grupo mostrado en la pantalla de usuarios, default `Otros`), `etiqueta` (texto del checkbox) y `orden` (posición del grupo, default `99`). La pantalla de permisos se arma **solo con esos campos**, así que cualquier permiso nuevo aparece siempre; al crearlo conviene fijarlos: `Permission::firstOrCreate(['name' => 'Ver Reportes', 'guard_name' => 'web'], ['modulo' => 'Reportes', 'etiqueta' => 'Ver', 'orden' => 8])`.
- El login devuelve `must_change_password: true` cuando la contraseña sigue siendo `123456`.

En el frontend el espejo de esto es el store Pinia `useCounterStore` (`src/stores/example-store.js`, nombre heredado del template): guarda `isLogged`, `user`, `permissions` (array de strings) y `company`. `$store.hasPermission('Ver Ventas')` filtra los enlaces del drawer en `MainLayout.vue` y debe usarse también para ocultar botones de acción.

### Inventario: `stock_inicial` + lotes

Punto crítico y contraintuitivo:

- `productos.stock_inicial` **no es el stock inicial, es el stock actual**. Se incrementa en compras y se decrementa en ventas (`$product->decrement('stock_inicial', $cantidad)`), siempre dentro de `DB::transaction` con `lockForUpdate()`.
- En paralelo, cada detalle de compra genera un registro en `lotes` (`cantidad_inicial`, `cantidad_disponible`, `fecha_vencimiento`). Las ventas consumen lotes en orden **FEFO** (vencimiento más próximo primero, nulos al final) y registran el reparto en la tabla pivote `venta_detalle_lotes`.
- Anular una venta revierte ambas cosas: repone `stock_inicial` y devuelve las cantidades a cada lote según el pivote.
- Las páginas *Por vencer* / *Vencidos* leen de `lotes` (`CompraController::vencimientos`), no de `productos`.

Cualquier operación nueva que mueva stock debe actualizar **las dos** estructuras de forma consistente.

### Ventas y compras

- Numeración generada tras el insert: `V-00000001` / `C-00000001` (`str_pad` sobre el id).
- Los detalles **desnormalizan** el producto en el momento de la venta (`codigo`, `nombre`, `categoria`, `unidad`, `foto`, `precio_compra`, `precio_venta`), de modo que los reportes históricos no cambian si el producto se edita después.
- `tipo_pago` es `EFECTIVO | QR | COMBINADO`; se valida que `monto_efectivo + monto_qr == total` (tolerancia 0.009).
- El descuento se prorratea por línea y el residuo se asigna a la última línea para que la suma cuadre al céntimo.
- `estado` es `COMPLETADA | ANULADA`; nunca se borra una venta. Los modelos de negocio usan `SoftDeletes` + `OwenIt\Auditing`, así que las consultas crudas con `DB::table()` deben filtrar `whereNull('deleted_at')` a mano (ver `VentaController::dashboard`).
- La ganancia se calcula como `(precio_venta - precio_compra) * cantidad - descuento` sobre `venta_detalles`.

### Exportaciones e impresión

- Excel vía `maatwebsite/excel` (clases en `app/Exports/`), PDF vía `barryvdh/laravel-dompdf` con vistas Blade en `resources/views/ventas|pedidos/reporte.blade.php`.
- La impresión de tickets es del lado del cliente: `src/addons/ventaPrint.js`, `compraPrint.js`, `pedidoPrint.js` usan `printd`.

### Imágenes

Se suben por endpoints dedicados (`POST /productos/{id}/foto`, `/clientes/{id}/foto`, `/users/{id}/avatar`, `/configuracion/logo`), se convierten a **WebP con GD** y se guardan en `back/public/images/...`. El frontend las resuelve con `$imgBase` (= `VITE_API_BACK` sin el sufijo `/api`) + `/images/`.

### Landing pública

`routes/web.php` → `WebsiteController@index` → `resources/views/website/index.blade.php`: catálogo público con "más vendidos" (agregado de `venta_detalles`) y "más cotizados" (agregado de `pedido_detalles`), textos configurables desde la tabla `configuraciones` (`titulo_web`, `whatsapp`). Es Blade + Tailwind v4, no comparte nada con la SPA.

### Convenciones del frontend

- Rutas declaradas a mano en `src/router/routes.js` (no hay file-based routing). Guard global: sin `tokenMundolac` en localStorage → redirige a `/login`.
- `src/boot/axios.js` expone en `globalProperties`: `$axios` (con `baseURL = VITE_API_BACK`), `$alert`, `$store`, `$url`, `$imgBase`, `$version`. Los componentes usan `getCurrentInstance().proxy` con `<script setup>`.
- localStorage: `tokenMundolac`, `permissionsMundolac`, `user`. El boot restaura permisos cacheados antes de que responda `/me` y limpia todo si `/me` falla.
- Notificaciones y diálogos siempre por `Alert` (`src/addons/Alert.js`): `$alert.success/warning/error/info/dialog`, nunca `Notify`/`Dialog` directos.
- Directiva `v-uppercase` (`src/boot/uppercase.js`) para inputs de texto; el backend además guarda varios campos en mayúsculas.
- Cada módulo es una carpeta en `src/pages/<modulo>/` con `IndexPage.vue` (listado + filtros) y, si aplica, `NuevaPage.vue`/`CrearPage.vue`.
- Extras ya instalados: `apexcharts` (dashboard), `leaflet` (mapa de clientes y geolocalización de pedidos, campos `latitud`/`longitud`).
- El scaffolding PWA existe en `src-pwa/` pero `pwa: false` en `quasar.config.js`.

## Añadir un módulo nuevo

1. Migración que cree la(s) tabla(s) **y** registre sus permisos con `Permission::firstOrCreate()`, indicando `modulo`, `etiqueta` y `orden`.
2. Modelo con `SoftDeletes` + `Auditable` si es entidad de negocio.
3. Controlador con helper privado `authorizeAction()` y llamada en cada método; respuestas JSON, paginación con `per_page` (tope 500).
4. Rutas dentro del grupo `auth:sanctum` de `routes/api.php`.
5. Página(s) en `src/pages/<modulo>/`, ruta en `routes.js` y enlace en `links` de `MainLayout.vue` con su `can`.
