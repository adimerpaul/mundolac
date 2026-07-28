# AGENTS.md — Mundolac

> Guía para agentes de código que trabajen en este proyecto. Se asume que el lector no conoce nada de la aplicación.

---

## 1. Visión general del proyecto

**Mundolac** es una aplicación web compuesta por un backend en **Laravel 13** y un frontend en **Quasar Framework v2** (Vue 3). En su estado actual, los directorios activos (`/back` y `/front`) son instalaciones recientes y casi vacías. La implementación funcional de referencia vive en `/ejemplo` y corresponde a un sistema de gestión clínica/farmacéutica llamado **Urme** (pacientes, internaciones, farmacia, compras, ventas, laboratorio, usuarios/permisos).

Cuando el proyecto requiera nuevas funcionalidades, es muy probable que deban replicarse o adaptarse patrones del directorio `ejemplo/`.

### Estructura de directorios

```
C:/proyectos/mundolac
├── back/                  # Laravel 13 (proyecto activo, instalación base)
├── front/                 # Quasar v2 + Vue 3 (proyecto activo, instalación base)
├── ejemplo/               # Implementación de referencia "Urme"
│   ├── back/              # Laravel funcional con API completa
│   └── front/             # Quasar funcional con páginas y componentes
├── .gitignore             # Ignora node_modules, .quasar, /dist, .idea, .env.local*
└── README.md              # README plantilla de Quasar
```

### Stack tecnológico

| Capa | Tecnología | Versión / detalle |
|------|------------|-------------------|
| Backend | PHP | ^8.3 |
| Backend | Laravel Framework | ^13.0 (actualmente 13.23.0) |
| Backend | Base de datos | **MySQL** (`DB_CONNECTION=mysql`, base `mundolac` en `127.0.0.1:3306`, XAMPP en `/c/xampp8.2`) |
| Backend | CSS | Tailwind CSS v4 con Vite |
| Frontend | Framework | Vue 3 + Quasar v2 |
| Frontend | Build tool | Vite (vía `@quasar/app-vite`) |
| Frontend | Estado | Pinia v3 |
| Frontend | Router | Vue Router 4 |
| Frontend | HTTP | Axios |
| Node | Node.js / npm | v22.14.0 / 11.6.2 (comprobado en el entorno) |

---

## 2. Directorio activo: `/back` (Laravel)

### Archivos clave

- `composer.json` — dependencias PHP y scripts.
- `package.json` — dependencias Node para el build de assets (Vite + Tailwind).
- `vite.config.js` — configura Laravel Vite Plugin + Tailwind CSS.
- `.env.example` — plantilla de variables de entorno.
- `phpunit.xml` — configuración de pruebas PHPUnit.
- `artisan` — CLI de Laravel.

### Organización del código

```
back/
├── app/
│   ├── Http/Controllers/    # Controladores (actualmente solo Controller.php base)
│   ├── Models/              # Modelos Eloquent (actualmente solo User.php)
│   └── Providers/           # Service providers
├── config/                  # Configuración de Laravel
├── database/
│   ├── factories/           # Factories para tests/seeders
│   ├── migrations/          # Migraciones (tablas users, cache, jobs por defecto)
│   └── seeders/             # Seeders
├── public/                  # Document root
├── resources/
│   ├── css/app.css          # Entrada CSS para Vite
│   ├── js/app.js            # Entrada JS para Vite
│   └── views/welcome.blade.php
├── routes/
│   ├── web.php              # Única ruta: `/` devuelve welcome
│   └── console.php          # Comandos de consola personalizados
├── storage/                 # Logs, caché, sesiones, uploads
└── tests/
    ├── Feature/             # Tests de integración
    ├── Unit/                # Tests unitarios
    └── TestCase.php
```

### Dependencias principales

- Producción: `laravel/framework`, `laravel/tinker`.
- Desarrollo: `fakerphp/faker`, `laravel/pail`, `laravel/pint`, `mockery/mockery`, `nunomaduro/collision`, `phpunit/phpunit`.

### Convenciones

- Namespaces PSR-4: `App\` → `app/`, `Database\Factories\` → `database/factories/`, `Database\Seeders\` → `database/seeders/`, `Tests\` → `tests/`.
- El código de ejemplo en `ejemplo/back` sigue el estilo Laravel estándar, con algunas convenciones propias:
  - Atributos en mayúsculas usando `mb_strtoupper` para campos de texto (nombres, direcciones, estados).
  - Control de permisos manual con `Spatie\Permission` (ver `UserController` en `ejemplo/`).
  - Respuestas JSON para la API.
  - Uso de `SoftDeletes` y `OwenIt\Auditing\Auditable` en modelos del ejemplo.

---

## 3. Directorio activo: `/front` (Quasar + Vue 3)

### Archivos clave

- `package.json` — scripts y dependencias.
- `quasar.config.js` — configuración de Quasar CLI.
- `jsconfig.json` — extiende `./.quasar/tsconfig.json`.
- `postcss.config.js` — autoprefixer.
- `index.html` — plantilla HTML con variables de Quasar.

### Organización del código

```
front/
├── src/
│   ├── App.vue                  # Componente raíz
│   ├── assets/                  # Imágenes, logos
│   ├── boot/
│   │   ├── axios.js             # Configuración global de Axios
│   │   └── .gitkeep
│   ├── components/              # Componentes reutilizables
│   ├── css/
│   │   ├── app.scss             # Estilos globales
│   │   └── quasar.variables.scss # Variables de color de Quasar
│   ├── layouts/
│   │   └── MainLayout.vue       # Layout con drawer
│   ├── pages/
│   │   ├── IndexPage.vue
│   │   └── ErrorNotFound.vue
│   ├── router/
│   │   ├── index.js             # Creación del router
│   │   └── routes.js            # Definición explícita de rutas
│   └── stores/
│       ├── index.js             # Instancia de Pinia
│       └── example-store.js     # Store de ejemplo (counter)
├── public/                      # Archivos estáticos (favicon, icons)
└── index.html
```

### Convenciones

- Router en modo **hash** (`vueRouterMode: 'hash'` en `quasar.config.js`).
- Rutas definidas manualmente en `src/router/routes.js`.
- Componentes, layouts y páginas se importan con alias de Quasar (`layouts/`, `pages/`, `components/`, `assets/`).
- El store usa Pinia con HMR (`acceptHMRUpdate`).
- El boot `axios.js` expone `$axios` y `$api` en `globalProperties`.

### Diferencias con `ejemplo/front`

El frontend de ejemplo usa:
- `@quasar/app-vite` v3 (casi RC) y **file-based routing** (`filenameBasedRouting: true`).
- Router en modo **history**.
- Quasar en español (`lang: 'es'`).
- Plugins `Loading`, `Notify`, `Dialog`.
- Boot `uppercase.js` para forzar mayúsculas en inputs.
- Addons personalizados (`src/addons/`) para alertas, impresión de tickets, fechas bolivianas.
- Variables de entorno `.env.development` / `.env.production` con `VITE_API_BACK`.

Si se migra o replica funcionalidad del ejemplo, se debe decidir si se mantiene el routing manual actual o se adopta el file-based routing.

---

## 4. Comandos de build y desarrollo

### Backend (`/back`)

```bash
cd back
composer install              # Instalar dependencias PHP
npm install                   # Instalar dependencias Node (Vite + Tailwind)
npm run dev                   # Vite dev server para assets
npm run build                 # Build de assets para producción
php artisan serve             # Servidor de desarrollo Laravel (http://localhost:8000)
php artisan migrate --force   # Ejecutar migraciones
php artisan db:seed           # Ejecutar seeders
composer run setup            # Instala todo, genera .env/key, migra y build
composer run dev              # Inicia serve + queue + pail + vite en paralelo
composer run test             # Limpia caché y ejecuta php artisan test
php artisan test              # Ejecutar tests PHPUnit
```

### Frontend (`/front`)

```bash
cd front
npm install                   # Instalar dependencias
npm run dev                   # quasar dev (hot reload, abre navegador)
npm run build                 # quasar build (genera /dist)
npm run test                  # Actualmente: "No test specified"
```

### Variables de entorno importantes (backend)

- `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`
- `DB_CONNECTION=mysql` con `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=mundolac`, `DB_USERNAME=root`, `DB_PASSWORD=` (vacío). El servidor MySQL corre con XAMPP (`/c/xampp8.2`); hay que arrancarlo antes de usar el backend.
- `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`
- `VITE_APP_NAME` (usada por el plugin de Laravel).

### Variables de entorno importantes (frontend de referencia en `ejemplo/front`)

- `VITE_API_BACK` — URL base de la API Laravel (`http://localhost:8000/api` en dev).
- `VITE_VERSION` — versión visible de la app.

---

## 5. Instrucciones de testing

- Backend: PHPUnit configurado en `phpunit.xml`.
  - Suites: `Unit` (`tests/Unit`) y `Feature` (`tests/Feature`).
  - Entorno de testing: `APP_ENV=testing`, base de datos SQLite en memoria (`:memory:`).
  - Comando: `php artisan test` o `composer run test`.
- Frontend: no hay tests configurados. El script `test` es un placeholder.

---

## 6. Proceso de despliegue

Actualmente **no existen archivos de despliegue** (Dockerfile, docker-compose, CI/CD, scripts de deploy, nginx, etc.). El proyecto está preparado para desarrollo local.

Para desplegar manualmente se seguiría el flujo típico:

1. Backend:
   - `composer install --no-dev --optimize-autoloader`
   - `npm ci && npm run build`
   - Configurar `.env` en producción.
   - `php artisan key:generate`, `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`.
   - Asegurar permisos de `storage/` y `bootstrap/cache/`.
   - Configurar servidor web (Apache/Nginx) con `public/` como document root.

2. Frontend:
   - `npm ci && npm run build`
   - Servir el contenido de `/dist` con un servidor estático o integrarlo en Laravel copiando los assets.

---

## 7. Consideraciones de seguridad

- **No versionar `.env`**: tanto `back/.env` como `ejemplo/back/.env` y `ejemplo/front/.env.*` contienen credenciales o URLs sensibles. Están ignorados en `.gitignore`.
- **Contraseñas**: en el ejemplo, las contraseñas se hashean con `bcrypt()`. El flujo de login detecta si la contraseña sigue siendo la por defecto (`123456`) y fuerza el cambio.
- **Autenticación API**: el ejemplo usa **Laravel Sanctum** (`auth:sanctum`) con tokens Bearer.
- **Autorización**: control de permisos granular con **Spatie Laravel Permission**. Los nombres de permiso están en español (p. ej. `"Ver Pacientes"`, `"Crear Ventas"`).
- **Auditoría**: el ejemplo usa **OwenIt Laravel Auditing** para registrar cambios en modelos clave.
- **Subida de archivos**: el ejemplo procesa avatares redimensionándolos a WebP con GD y guardándolos en `public/images/`.
- **SQL Injection**: el ejemplo usa Eloquent/Query Builder; evitar concatenar strings directamente en consultas.
- **XSS**: en el frontend se usan plantillas Vue que escapan por defecto; no insertar HTML sin sanitizar.

---

## 8. Notas para agentes

- Antes de agregar una funcionalidad, revisar si ya existe una implementación equivalente en `ejemplo/back` y `ejemplo/front`.
- El backend activo (`/back`) no tiene instalados aún Sanctum, Spatie Permission ni los paquetes de exportación PDF/Excel que usa el ejemplo. Si se requieren, instalarlos vía Composer.
- El frontend activo (`/front`) usa Quasar app-vite v2 y routing manual; el ejemplo usa v3 con file-based routing. No mezclar ambos estilos en el mismo directorio sin actualizar `quasar.config.js`.
- Los textos de UI, nombres de permisos y comentarios en el código de ejemplo están en **español**. Mantener esa convención para mantener consistencia con la implementación de referencia.
- El proyecto no es un repositorio Git inicializado; no hay historial de commits ni CI configurado.

---

## 9. Recursos útiles

- Laravel 13 docs: https://laravel.com/docs
- Quasar v2 docs: https://v2.quasar.dev
- Configuración Quasar CLI Vite: https://v2.quasar.dev/quasar-cli-vite/quasar-config-file
- Routing basado en archivos (usado en ejemplo): https://v2.quasar.dev/quasar-cli-vite/page-routing-with-vue-router#filename-based-routing
