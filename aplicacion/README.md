# Mundolac Ventas (app móvil)

App Flutter para vendedores: **sólo hace ventas**. Inicia sesión contra la API de
`back/`, guarda la sesión y el catálogo en **SQLite** y registra las ventas contra
`POST /api/ventas`.

## Arquitectura (MVVM)

```
lib/
├─ core/                     Tema y utilidades de formato (Bs, fechas, parseo)
├─ modelos/                  Modelo: Producto, Venta, ItemCarrito, Usuario
├─ datos/
│  ├─ servicios/             ApiServicio (HTTP) y BaseDatos (SQLite)
│  └─ repositorios/          Fuente de verdad: sesión, productos, carrito, ventas
├─ vistamodelos/             ViewModel (ChangeNotifier): estado + reglas de UI
└─ vistas/                   View: widgets, sin lógica de negocio
```

Reglas del patrón que se respetan en todo el código:

- **View** (`vistas/`) sólo dibuja y llama métodos del ViewModel. No conoce
  `http`, ni `sqflite`, ni los repositorios.
- **ViewModel** (`vistamodelos/`) extiende `VistaModeloBase`, que centraliza
  `cargando` / `error` y el helper `ejecutar()`. No importa `package:flutter/material.dart`.
- **Model** (`modelos/` + `datos/`) no sabe que existe la UI. Los repositorios
  deciden cuándo usar la API y cuándo el cache local.
- El cableado de dependencias está en `main.dart` con `provider`
  (servicios → repositorios → ViewModels).

## Pantallas

| Pantalla | Vista | ViewModel |
| --- | --- | --- |
| Login (con URL del servidor configurable) | `vistas/login/login_vista.dart` | `SesionVistaModelo` |
| Punto de venta (buscador, escáner, carrito) | `vistas/venta/venta_vista.dart` | `VentaVistaModelo` |
| Revisar orden / cobro | `vistas/venta/cobro_vista.dart` | `CobroVistaModelo` |
| Comprobante de la venta | `vistas/venta/venta_registrada_vista.dart` | — |
| Listado de ventas + detalle | `vistas/ventas/` | `VentasVistaModelo` |
| Catálogo de productos | `vistas/productos/productos_vista.dart` | `ProductosVistaModelo` |
| Mi cuenta (permisos, sincronizar, salir) | `vistas/cuenta/cuenta_vista.dart` | `SesionVistaModelo` |

El menú inferior (`vistas/principal/principal_vista.dart`) tiene cuatro opciones
—Vender, Ventas, Productos, Cuenta— y el **botón circular central**, que lleva al
punto de venta y abre/cierra el escáner de códigos de barras; muestra un globo con
la cantidad de artículos del carrito.

## Base de datos local (SQLite)

`mundolac.db`, creada en `datos/servicios/base_datos.dart`:

| Tabla | Para qué |
| --- | --- |
| `ajustes` | URL de la API y fecha de la última sincronización |
| `sesion` | token Sanctum, usuario y permisos (una sola fila) |
| `productos` | catálogo cacheado para buscar sin internet |
| `carrito` | venta en curso (una fila por línea); sobrevive al cierre de la app |
| `ventas` | últimas ventas descargadas, para verlas sin conexión |

## Permisos

Se respetan los mismos permisos de Spatie que el backend: `Crear Ventas` habilita
el punto de venta, `Ver Ventas` el listado y `Ver Productos` el catálogo. Si el
usuario no los tiene, la pestaña muestra "Acceso restringido".

## Correr la app

```bash
flutter pub get
flutter run                 # dispositivo o emulador Android conectado
flutter build apk --release
```

Sólo se soporta Android (la carpeta `ios/` fue eliminada del proyecto).

## Entornos (`.env`)

Igual que en `front/`, la URL de la API se define por archivo de entorno
(cargados con `flutter_dotenv`, ver `lib/core/entorno.dart`):

| Archivo | Cuándo se usa | Valor |
| --- | --- | --- |
| `.env.development` | `flutter run` / `--debug` / `--profile` | `http://192.168.1.7:8000/api` |
| `.env.production` | `flutter build apk --release` | `https://bmundolac.tuprogam.com/api` |

Ambos están declarados como `assets` en `pubspec.yaml`, así que **se versionan**
(sin ellos el build falla). `.env.example` sirve de plantilla. Si cambia la IP de
la PC, se edita `.env.development` y se vuelve a correr la app.

Dentro de la app, el login tiene un botón con la URL activa para apuntar a otro
servidor sin recompilar; ese valor manual queda guardado en SQLite y tiene
prioridad sobre el `.env`.

Usuario de prueba del backend: `admin` / `admin`.

## Notas

- En el carrito se puede tocar la fila (o el número) para **escribir la cantidad**
  con el teclado, en vez de pulsar «+» varias veces; el botón «Quitar» del mismo
  diálogo saca el producto y dejar el campo vacío equivale a cancelar.
- Cada vez que se agrega un producto se crea una **línea nueva**, aunque ya esté
  en el carrito. El control de stock suma todas las líneas del mismo producto.
- Las cantidades son enteras porque `venta_detalles.cantidad` es `unsignedInteger`
  en el backend.
- El stock que se muestra viene del cache; se descuenta al vender y se corrige al
  sincronizar (`Productos → botón de sincronizar`).
- Registrar una venta **requiere conexión**: la API es la que descuenta stock y
  reparte lotes FEFO. Sin internet se puede armar el carrito, pero no cobrar.

## Icono

El icono sale de `assets/icono/logo.png` (logo Mundilac, el mismo de `front/`).
Para regenerarlo tras cambiar la imagen:

```bash
dart run flutter_launcher_icons
```

Genera los `mipmap-*` y el icono adaptativo (`adaptive_icon_background` blanco,
el logo con 20% de margen). La pantalla de arranque (`launch_background.xml`)
y el login usan el mismo logo.
