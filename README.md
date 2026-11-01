# De Todito Reyes — Sistema de Gestión y Facturación

Módulo MVC en PHP + MySQL para administrar inventario, clientes y facturación fiscal
en bolívares con tasa BCV, IVA e IGTF.

La estructura de datos se tomó del modelo `version 4.mwb` y quedó exportada en
`sql/de_todito_reyes.sql` (12 tablas y 4 vistas).

---

## 1. Requisitos

- PHP 7.4 o superior (probado con PHP 8 de XAMPP).
- MySQL / MariaDB (probado con MariaDB 10.4 de XAMPP).
- Extensiones PHP: `pdo_mysql`, `mbstring`, `json` (vienen activas en XAMPP).

---

## 2. Instalación

1. Copia la carpeta `sistema/` donde prefieras (por ejemplo `C:\xampp\htdocs\`).
   La **raíz web es `sistema/public/`**: solo esa carpeta debe quedar expuesta.
   `app/`, `config/`, `sql/` y `tools/` quedan fuera del alcance del navegador.
2. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
3. Importa la base de datos:
   - Opción A (phpMyAdmin): crea/abre la base `de_todito_reyes` e importa
     `sql/de_todito_reyes.sql`.
   - Opción B (consola):
     ```bat
     C:\xampp\mysql\bin\mysql.exe -u root < "ruta\sistema\sql\de_todito_reyes.sql"
     ```
4. Revisa la conexión en `config/database.php`:
   ```php
   const DB_HOST = 'localhost';
   const DB_NAME = 'de_todito_reyes';
   const DB_USER = 'root';
   const DB_PASS = '';
   ```
5. Ajusta los datos fiscales de la empresa en `config/empresa.php`
   (nombre, RIF, dirección y teléfono que salen impresos en la factura).

---

## 3. Cómo ejecutarlo

**Con Apache:** el `DocumentRoot` debe apuntar a la carpeta `public/`.

- Si dejas el proyecto en `htdocs`, abre:
  `http://localhost/sistema/public/`
- Para una URL limpia (`http://localhost/`), configura un VirtualHost con
  `DocumentRoot "C:/xampp/htdocs/sistema/public"`.

> No publiques la carpeta `sistema/` completa: `config/`, `app/` y `tools/`
> contienen código interno y el script de recuperación.

**Con el servidor embebido de PHP** (sin Apache):

```bat
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t "ruta\sistema\public" "ruta\sistema\public\index.php"
```

El último argumento es el **enrutador**: sin él, las URLs limpias
(`/productos`, `/facturacion/nueva`) devolverían 404.

y visita `http://127.0.0.1:8000/`. La raíz muestra el login; el panel está en
`/panel`.

---

## 4. Acceso al sistema

El sistema exige iniciar sesión. La raíz `/` muestra siempre el inicio de sesión;
el panel está en `/panel` y no es accesible sin sesión activa.

### Credenciales de demostración

| Usuario | Contraseña | Rol |
|---|---|---|
| `admin` | `admin123` | ADMIN |
| `mcajera` | `cajera123` | CAJERO |

> Cambia estas claves desde **Usuarios** antes de usar el sistema en producción.

### Roles y permisos

| Acción | ADMIN | CAJERO |
|---|:---:|:---:|
| Ver Panel | ✔ | ✔ |
| Consultar Productos y Clientes | ✔ | ✔ |
| Crear / editar / desactivar productos y clientes | ✔ | ✘ |
| Emitir facturas en el POS | ✔ | ✔ |
| Consultar facturas y comprobantes | ✔ | ✔ |
| Anular facturas | ✔ | ✘ |
| Gestionar usuarios | ✔ | ✘ |

Las restricciones se aplican en el servidor (no solo ocultando botones): un POST
de CAJERO a productos, clientes, anulación o usuarios se rechaza con un mensaje.

### Sesión

- Contraseñas guardadas con `password_hash()` (bcrypt) y verificadas con
  `password_verify()`; se rehashean solas si cambia el algoritmo de PHP.
- `session_regenerate_id(true)` al iniciar sesión y token CSRF nuevo.
- Cierre de sesión por inactividad a los **30 minutos**.
- Botón **Cerrar sesión** en la barra lateral.
- Eventos `LOGIN`, `LOGIN_FALLIDO` y `LOGOUT` se registran en
  `bitacora_auditoria`.
- Protecciones de administración: no se puede desactivar la propia cuenta ni
  dejar el sistema sin al menos un ADMIN activo.

### Recuperación de acceso (¿olvidaste la contraseña o el usuario?)

Si nadie puede entrar como ADMIN, usa el script de consola
`tools/crear_admin.php`. **No funciona desde el navegador** (la carpeta `tools/`
está fuera de `public/`), solo desde la terminal del servidor:

```bat
cd "ruta\sistema"

:: Ver los administradores y recordar el usuario
C:\xampp\php\php.exe tools\crear_admin.php --listar

:: Crear un ADMIN nuevo
C:\xampp\php\php.exe tools\crear_admin.php --crear --cedula=V-99999999 --nombre="Admin Respaldo" --usuario=admin2 --clave=ClaveSegura123 --si

:: Restablecer la contraseña de un ADMIN existente
C:\xampp\php\php.exe tools\crear_admin.php --resetear --usuario=admin --clave=NuevaClave123 --si

:: También se puede usar el ID
C:\xampp\php\php.exe tools\crear_admin.php --resetear --id=1 --clave=NuevaClave123 --si
```

Sin argumentos abre un menú interactivo (crear, resetear, listar). Las claves de
ADMIN deben tener al menos 8 caracteres y los cambios quedan en
`bitacora_auditoria` (`CREAR_ADMIN`, `RESET_ADMIN`).

> El script está en `tools/`, fuera de la raíz web, así que el navegador no puede
> ejecutarlo; además conserva un `.htaccess` como respaldo. Si publicas el sistema
> en internet, elimina el script cuando termines la recuperación.

---

## 5. Módulos incluidos

| Módulo | Ruta | Qué hace |
|---|---|---|
| Login | `/` | Inicio de sesión con usuario y contraseña. |
| Panel | `/panel` | Resumen de productos, clientes, facturas, tasa BCV, inventario a costo/venta, total vendido, stock bajo y últimas facturas. |
| Productos | `/productos` | Alta, edición, búsqueda, filtro por categoría, filtro de stock bajo y activación/desactivación. |
| Clientes | `/clientes` | Alta, edición, búsqueda y eliminación (bloqueada si el cliente tiene facturas). |
| Facturación | `/facturacion` | Listado de facturas, filtro por estado/búsqueda, punto de venta y comprobante imprimible. |
| Usuarios | `/usuarios` | Solo ADMIN: crear, editar, resetear contraseña y activar/desactivar cuentas. |

### URLs

| Acción | URL |
|---|---|
| Panel | `/panel` |
| Login / logout | `/` (también `/login`) · `/logout` (POST) |
| Productos | `/productos`, `/productos?editar=ID` |
| Clientes | `/clientes`, `/clientes?editar=ID` |
| Facturación | `/facturacion` |
| Nueva venta (POS) | `/facturacion/nueva` |
| Comprobante | `/facturacion/ver/ID` |
| Usuarios | `/usuarios`, `/usuarios?editar=ID` |

Las URLs antiguas (`productos.php`, `facturacion.php?accion=ver&id=1`, etc.)
siguen funcionando: el enrutador las redirige a la ruta limpia equivalente.

### Punto de venta (POS)

`/facturacion/nueva` muestra catálogo y carrito en una sola pantalla:

1. Se agregan productos con el botón **Agregar** o se buscan por nombre/código.
2. La cantidad se edita en el carrito y no puede superar el stock disponible.
3. Se elige cliente, método de pago y, si aplica, **Pago en divisa (IGTF 3 %)**.
4. Al emitir, el servidor recalcula todo y descuenta el stock en una transacción.

### Comprobante

`/facturacion/ver/ID` muestra el comprobante con botón
**Imprimir / Guardar PDF** y **Anular factura** (solo ADMIN y solo si sigue emitida).

---

## 6. Reglas de negocio implementadas

- **Consecutivo automático:** `MAX(nro_factura_consecutivo) + 1`, con 6 dígitos
  (`000001`) y número de control fiscal `00-000001`.
- **Recálculo en servidor:** el navegador solo envía `id_producto` y `cantidad`.
  Precio, IVA, IGTF y totales se leen/calculan con los datos de la base.
- **IVA por producto:** se usa `alicuota_iva` de cada producto; los exentos van a
  `subtotal_exento_ves`.
- **IGTF 3 %:** solo se aplica si el usuario marca la casilla «Pago en divisa».
- **Stock:** se descuenta en la misma transacción de la factura con
  `SELECT … FOR UPDATE` y validación `stock_actual >= cantidad`. Si falla, se
  revierte todo.
- **Concurrencia:** hasta 3 reintentos si dos cajas generan el mismo consecutivo.
- **Anulación:** cambia `estado` a `ANULADA` y devuelve las cantidades al stock.
  El trigger de la base registra el cambio en `bitacora_auditoria`.
- **Productos:** se desactivan (`activo = 0`), no se borran.
- **Clientes:** no se pueden eliminar si tienen facturas asociadas.
- **Usuario de la factura:** se toma de la sesión activa (`id_usuario`), por eso
  el cajero que emite queda registrado en la factura y en la bitácora.

---

## 7. Estructura de carpetas

```
sistema/
├── public/                       ← raíz web (lo único publicado)
│   ├── index.php                 Punto de entrada único + enrutador
│   ├── .htaccess                 Reescribe todo a index.php (Apache)
│   └── assets/
│       ├── css/estilos.css       Estilos propios (sin CDN)
│       └── js/                   app.js y facturacion.js
├── app/                          ← lógica interna (no accesible por HTTP)
│   ├── controladores/
│   │   ├── DashboardController.php
│   │   ├── LoginController.php
│   │   ├── LogoutController.php
│   │   ├── ProductosController.php
│   │   ├── ClientesController.php
│   │   ├── FacturacionController.php
│   │   └── UsuariosController.php
│   ├── modelos/
│   │   ├── DashboardModel.php
│   │   ├── ProductoModel.php
│   │   ├── ClienteModel.php
│   │   ├── FacturaModel.php      Emisión/anulación transaccional
│   │   └── UsuarioModel.php      Autenticación, CRUD y bitácora
│   └── vistas/
│       ├── layout/               header.php, footer.php
│       ├── auth/                 login.php
│       ├── errores/              404.php
│       ├── dashboard/
│       ├── productos/
│       ├── clientes/
│       ├── usuarios/             index (formulario + listado)
│       └── facturacion/          index, pos, ver (comprobante)
├── config/
│   ├── bootstrap.php             Carga helpers, base de datos y empresa
│   ├── database.php              Conexión PDO (singleton)
│   ├── empresa.php               Datos fiscales de la empresa
│   └── helpers.php               e(), money(), url(), flash, CSRF, sesión y roles
├── tools/
│   ├── crear_admin.php           Recuperación de acceso (solo consola)
│   └── .htaccess                 Niega el acceso web (respaldo)
├── sql/de_todito_reyes.sql       Esquema + datos de ejemplo
└── README.md
```

---

## 8. Decisiones técnicas

- **MVC simple sin framework:** `app/` agrupa `controladores/`, `modelos/` y
  `vistas/`; `public/` es la única carpeta expuesta y contiene un **único punto
  de entrada** (`index.php`) que enruta las URLs limpias hacia los controladores.
- **URLs limpias:** `/productos`, `/clientes`, `/facturacion/nueva`,
  `/facturacion/ver/1`, `/usuarios`. Los helpers `base_url()` y `url()` generan
  enlaces que funcionan tanto en la raíz como en una subcarpeta de Apache.
- **Compatibilidad:** las URLs antiguas con `.php` se redirigen a su equivalente
  limpio, así los enlaces guardados o favoritos siguen funcionando.
- **PDO con consultas preparadas reales** (`ATTR_EMULATE_PREPARES = false`).
  Por eso no se repite un mismo placeholder con nombre en una consulta.
- **Transacciones** para emitir y anular facturas (todo o nada).
- **CSRF** en todos los formularios POST y escape de salida con `e()`.
- **Autenticación por sesión:** `$_SESSION['usuario']` con id, nombre, login y rol;
  helpers `require_login()` y `require_admin()` protegen cada entrada.
- **Contraseñas:** `password_hash()` / `password_verify()` con rehash automático.
- **CSS propio:** no depende de internet ni de CDN.
- **Ajuste de zona horaria** `-04:00` en la conexión para que `NOW()` coincida con
  Venezuela.

---

## 9. Datos de ejemplo

El script incluye:

- 9 productos, 4 categorías y stock inicial.
- 3 clientes (Juan Perez, Consumidor Final, Bodega La Esquina).
- 1 factura emitida (`000001`) con su detalle.
- 2 usuarios: `admin` / `admin123` (ADMIN) y `mcajera` / `cajera123` (CAJERO),
  con hashes reales listos para usar.
- Una tasa BCV vigente (Bs 36,5000).

Puedes borrar los datos de prueba y dejar tus propios registros; la numeración de
facturas continúa desde el último consecutivo existente.

---

## 10. Verificación realizada

- Los 29 archivos PHP pasan `php -l` sin errores.
- Panel, Productos, Clientes, Facturación, POS, comprobante, Login y Usuarios
  responden 200 sin errores de PHP ni de consola.
- La raíz `/` muestra el login (incluso con sesión activa) y `/panel` exige
  sesión; login correcto e incorrecto probados.
- Login de usuario inactivo rechazado; logout con mensaje de confirmación.
- Creación, edición, cambio de contraseña, desactivación y reactivación de
  usuarios probados.
- Protección del último ADMIN activo probada (no permite degradarlo).
- CAJERO: no ve Usuarios, `/usuarios` lo redirige, Productos y Clientes en
  modo consulta, POST de creación bloqueado y anulación de factura bloqueada.
- POS como CAJERO: carrito, IVA, IGTF y totales se recalculan sin errores.
- Recuperación de acceso probada: `--listar`, `--crear` y `--resetear` funcionan
  por consola; el script no es accesible por el navegador.
- Reestructuración a `public/` + `app/` probada: login, panel, productos,
  clientes, facturación, POS, comprobante y usuarios responden 200, los assets
  cargan y `tools/` devuelve 404 por HTTP.
- Enrutador único probado: URLs limpias (`/productos`, `/facturacion/nueva`,
  `/facturacion/ver/1`, `/usuarios`), 404 para rutas inexistentes y redirección
  de las URLs antiguas `.php` a su equivalente limpio.
- Emisión de factura probada: totales correctos, consecutivo `000002`, control
  `00-000002` y descuento de stock (45 → 43).
- Anulación probada: estado `ANULADA`, stock devuelto (43 → 45) y registro en
  bitácora.
- Alta de producto, alta y eliminación de cliente probadas.
- Búsquedas y filtros probados en los módulos.
