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
| Ver el panel administrativo (inventario, costos, métricas globales) | ✔ | ✘ |
| Ver su panel personal de ventas | ✔ | ✔ |
| Consultar y crear clientes | ✔ | ✔ |
| Editar o eliminar clientes | ✔ | ✘ |
| Consultar el módulo de productos | ✔ | ✘ |
| Crear / editar / desactivar / eliminar productos | ✔ | ✘ |
| Emitir facturas en el POS | ✔ | ✔ |
| Consultar facturas y comprobantes | ✔ | ✔ |
| Consultar la tasa BCV y su historial | ✔ | ✔ |
| Descargar el historial de ventas (CSV) | ✔ | ✔ |
| Registrar o corregir la tasa BCV | ✔ | ✘ |
| Anular o eliminar facturas anuladas | ✔ | ✘ |
| Reportar mermas | ✔ | ✔ |
| Gestionar usuarios | ✔ | ✘ |

El menú lateral se construye según el rol. El CAJERO ve **Mi panel**, **Clientes**,
**Facturación**, **Merma** y **Nueva venta**; no ve Productos, Tasas ni Usuarios.
Las restricciones se aplican en el servidor (no solo ocultando botones):
`/productos` y `/usuarios` redirigen al CAJERO a su panel con un aviso, y un POST
de CAJERO para editar o eliminar clientes, o para anular/eliminar facturas, se
rechaza. En `/mermas` el CAJERO puede registrar bajas, pero no ve los costos.

### Sesión

- Contraseñas guardadas con `password_hash()` (bcrypt) y verificadas con
  `password_verify()`; se rehashean solas si cambia el algoritmo de PHP.
- `session_regenerate_id(true)` al iniciar sesión y token CSRF nuevo.
- `session.use_strict_mode = 1`: se rechazan IDs de sesión no emitidos por el
  servidor (protección contra fijación de sesión).
- Cierre de sesión por inactividad a los **30 minutos**.
- **Revalidación en cada petición:** la cuenta se consulta de nuevo en la base;
  si fue desactivada o eliminada, la sesión se cierra al instante. Si un ADMIN
  cambia el rol o el nombre, la sesión refleja el cambio sin volver a entrar.
- **Sin caché:** las páginas autenticadas envían `Cache-Control: no-store`, de
  modo que el botón «Atrás» no muestra el panel después de cerrar sesión.
- **Login sin atajos:** `/` siempre pide usuario y contraseña. Si hay una sesión
  abierta en el navegador, se cierra al entrar al login y se exige la clave de
  nuevo; no existe un enlace directo al panel.
- Botón **Cerrar sesión** en la barra lateral.
- Eventos `LOGIN`, `LOGIN_FALLIDO` y `LOGOUT` se registran en
  `bitacora_auditoria`.
- Protecciones de administración: no se puede desactivar la propia cuenta ni
  dejar el sistema sin al menos un ADMIN activo. Si un ADMIN se quita su propio
  rol, se le redirige al panel y pierde el acceso al módulo de usuarios.
- Los formularios rechazados conservan los datos escritos, pero **nunca** la
  contraseña ni el token CSRF.

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
| Panel ADMIN | `/panel` | Resumen completo: productos, clientes, facturas, tasa BCV, inventario a costo/venta, total vendido, stock bajo, panel de merma (mermas del mes, pérdida acumulada y últimos movimientos) y últimas facturas. |
| Panel CAJERO | `/panel` | Vista propia del cajero: sus facturas de hoy, vendido del día/mes, tasa BCV, accesos rápidos, panel de merma sin costos y sus últimas facturas. Sin inventario ni métricas globales. |
| Productos | `/productos` | Alta, edición, búsqueda, filtro por categoría, filtro de stock bajo, activación/desactivación y eliminación (solo si el producto no tiene ventas, compras ni mermas). Muestra el costo y el precio de venta en USD y su equivalente en Bs calculado con la tasa BCV vigente. |
| Clientes | `/clientes` | Alta, edición, búsqueda y eliminación (bloqueada si el cliente tiene facturas). El CAJERO puede registrar clientes nuevos; editar y eliminar queda para el ADMIN. |
| Merma | `/mermas` | Registro de bajas de inventario por vencimiento, daño, pérdida, robo o ajuste. Descuenta stock en una transacción y guarda el costo del momento. Visible para ADMIN y CAJERO. |
| Facturación | `/facturacion` | Historial de ventas con filtros por fecha, estado y búsqueda, punto de venta y comprobante imprimible. Las facturas anuladas se pueden eliminar (solo ADMIN). |
| Tasas BCV | `/tasas` | Tasa vigente, aviso cuando no es del día, historial de cambios y alta/corrección (solo ADMIN). |
| Usuarios | `/usuarios` | Solo ADMIN: crear, editar, resetear contraseña, activar/desactivar y eliminar cuentas sin movimientos asociados. |

En el formulario de productos, **Costo en USD** y **Precio de venta en USD** son
dos conceptos distintos: lo que cuesta adquirir el producto y lo que se cobra al
venderlo. Al escribir cualquiera de ellos, su campo **en Bs (BCV)** se actualiza
automáticamente usando la tasa BCV vigente. El valor que se guarda sigue siendo
USD; el POS aplica la tasa del momento de la venta.

### URLs

| Acción | URL |
|---|---|
| Panel | `/panel` |
| Login / logout | `/` (también `/login`) · `/logout` (POST) |
| Productos | `/productos`, `/productos?editar=ID` |
| Clientes | `/clientes`, `/clientes?editar=ID` (editar solo ADMIN) |
| Merma | `/mermas` |
| Facturación | `/facturacion` |
| Facturación filtrada | `/facturacion?desde=2026-10-01&hasta=2026-10-31&estado=EMITIDA&q=texto` |
| Exportar resumen CSV | `/facturacion/exportar?tipo=resumen` |
| Exportar detalle CSV | `/facturacion/exportar?tipo=detalle` |
| Nueva venta (POS) | `/facturacion/nueva` |
| Comprobante | `/facturacion/ver/ID` |
| Descargar PDF | `/facturacion/pdf/ID` |
| Abrir PDF para imprimir | `/facturacion/pdf/ID?ver=1` |
| Descargar imagen PNG | `/facturacion/imagen/ID` |
| Tasas BCV | `/tasas`, `/tasas?editar=ID` |
| Usuarios | `/usuarios`, `/usuarios?editar=ID` |

Las URLs antiguas (`productos.php`, `facturacion.php?accion=ver&id=1`, etc.)
siguen funcionando: el enrutador las redirige a la ruta limpia equivalente.

### Punto de venta (POS)

`/facturacion/nueva` muestra catálogo y carrito en una sola pantalla:

1. Se agregan productos con el botón **Agregar** o se buscan por nombre/código.
2. La cantidad se edita en el carrito y no puede superar el stock disponible.
   Las cantidades se muestran sin ceros innecesarios (`44`, no `44,000`); solo
   usan hasta tres decimales cuando el producto se vende por peso o volumen.
3. Se elige cliente, método de pago y, si aplica, **Pago en divisa (IGTF 3 %)**.
4. Al emitir, el servidor recalcula todo y descuenta el stock en una transacción.

### Comprobante

`/facturacion/ver/ID` muestra el comprobante con los botones **Descargar PDF**,
**Descargar imagen**, **Imprimir**, **Anular factura** (solo ADMIN y solo si
sigue emitida) y **Eliminar factura** (solo ADMIN y solo si ya está anulada).

El número de factura y el número de control siguen siendo **consecutivos**
(requisito fiscal del SENIAT). Además, cada factura recibe un **código aleatorio
de verificación** de 8 caracteres (por ejemplo `RJKRMJC2`) que se muestra en el
comprobante, el PDF y el PNG, y se puede usar en la búsqueda de facturación para
localizar el documento. El código no reemplaza la numeración consecutiva: solo
sirve para comprobar que el comprobante fue emitido por el sistema.

El botón **Descargar PDF** genera el archivo en el servidor
(`/facturacion/pdf/ID`) con las fuentes Helvetica, sin librerías externas. El
PDF descargado no incluye la URL de la página ni los encabezados/pies que Chrome
agrega al usar «Imprimir / Guardar como PDF», así que no aparece la ruta del
sistema al final del documento. El botón **Imprimir** abre ese mismo PDF en una
pestaña nueva (`?ver=1`, con `Content-Disposition: inline`): ya no usa
`window.print()`, de modo que el papel tampoco lleva la URL ni la fecha que
Chrome agrega al pie de una página HTML.

El botón **Descargar imagen** entrega la misma información como PNG
(`/facturacion/imagen/ID`), útil para enviarla por WhatsApp o correo. La imagen
se dibuja con la extensión GD de PHP y las fuentes Roboto incluidas en
`app/core/fuentes` (licencia Apache 2.0), también sin CDN ni librerías externas.
Requiere `extension=gd` habilitada en `C:\xampp\php\php.ini`.

### Historial de ventas y descargas

`/facturacion` funciona como historial de ventas:

- Filtros por **rango de fechas** (`desde` / `hasta`), estado (emitidas/anuladas)
  y búsqueda por número de factura, control fiscal, cliente o RIF/cédula.
- Botón **Resumen CSV**: una fila por factura con cliente, cajero, método de
  pago, estado, subtotal exento, base imponible, IVA, IGTF, totales en Bs/USD y
  la tasa aplicada.
- Botón **Detalle CSV**: una fila por producto vendido con factura, cliente,
  código, producto, unidad, cantidad/peso, precios, alícuota, IVA y subtotal.
- Las descargas respetan los filtros activos y usan **UTF-8 con BOM**,
  separador `;`, decimales con coma y punto de miles (formato venezolano), para
  abrirlas directamente en Excel sin que las cantidades se multipliquen.
- El nombre del archivo incluye la fecha y hora de la descarga.

### Tasa BCV

`/tasas` muestra el valor del dólar vigente (Bs por 1 USD), quién lo registró y
el historial completo. Todos los usuarios autenticados pueden consultarla; solo
un **ADMIN** puede registrar o corregir valores.

- Si la tasa vigente es de un día anterior, el Panel, el POS, Facturación y la
  propia pantalla de tasas muestran un aviso con enlace para actualizarla.
- La tasa se muestra sin ceros sobrantes (`Bs 36,5`, `Bs 900`), no como
  `36,5000`, para que no se confunda con un número de miles.
- La actualización es manual: consulta el valor oficial en
  [bcv.org.ve](https://www.bcv.org.ve/) y regístralo con su fecha de vigencia.
- No se permite duplicar la misma fecha/hora ni registrar fechas futuras.
- Cada factura guarda la tasa aplicada al momento de la venta; corregir una tasa
  histórica **no** altera las facturas ya emitidas.

---

## 6. Reglas de negocio implementadas

- **Consecutivo automático:** `MAX(nro_factura_consecutivo) + 1`, con 6 dígitos
  (`000001`) y número de control fiscal `00-000001`. También se considera el
  máximo registrado en la bitácora para no reutilizar un número al eliminar una
  factura anulada.
- **Código de verificación:** cada emisión genera un código aleatorio de 8
  caracteres (alfabeto sin `O/0`, `I/1`, `L`, `S/5`) con índice `UNIQUE`. Es
  adicional al consecutivo fiscal y se puede usar para buscar el comprobante.
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
  El trigger de la base registra el cambio en `bitacora_auditoria` con el
  **usuario que anula** (variable `@auditoria_usuario`), no con el emisor
  original de la factura.
- **Productos:** se pueden eliminar solo si no tienen ventas, compras ni mermas;
  si ya tienen historial se desactivan (`activo = 0`) para no romper la
  trazabilidad.
- **Clientes:** no se pueden eliminar si tienen facturas asociadas. El CAJERO
  puede registrar clientes nuevos; editar y eliminar sigue reservado al ADMIN.
- **Usuarios:** se pueden eliminar solo si no tienen facturas, tasas, mermas ni
  compras. Nunca se puede borrar la propia cuenta ni el último ADMIN activo.
- **Facturas:** solo se eliminan las **ANULADAS**; se borran la factura y sus
  líneas en una transacción. Una factura emitida debe anularse primero.
- **Merma:** cada registro descuenta `stock_actual` y guarda el
  `costo_unitario_usd` del momento. La cantidad no puede superar el stock
  disponible ni ser cero; el motivo se guarda como enum.
- **Usuario de la factura:** se toma de la sesión activa (`id_usuario`), por eso
  el cajero que emite queda registrado en la factura y en la bitácora.
- **Tasa BCV:** la tasa vigente es la de mayor `fecha_vigencia`; si su fecha es
  anterior a hoy, el sistema la marca como desactualizada y avisa en las
  pantallas de trabajo. Solo ADMIN puede registrarla o corregirla.
- **Tasa histórica:** cada factura conserva `tasa_bcv_aplicada`; editar el
  historial de tasas no modifica los totales de facturas ya emitidas.
- **Exportación CSV:** resumen y detalle respetan los filtros aplicados, usan
  BOM UTF-8, separador `;`, decimales con coma y punto de miles para que Excel
  en español no interprete los decimales como miles.
- **PDF nativo:** el comprobante se genera en el servidor con `PdfFactura`
  (Helvetica y codificación Windows-1252), sin depender del diálogo de impresión
  ni de librerías externas. Así el archivo no hereda la URL ni los encabezados
  que Chrome agrega al guardar una página como PDF.
- **Imagen PNG:** `ImagenFactura` dibuja el mismo comprobante con GD y las
  fuentes Roboto locales. La imagen crece en alto según las líneas de la factura
  (no se corta) y mantiene los márgenes en todos los casos probados.

---

## 7. Estructura de carpetas

```
sistema/
├── public/                       ← raíz web (lo único publicado)
│   ├── index.php                 Punto de entrada único + enrutador
│   ├── .htaccess                 Reescribe todo a index.php (Apache)
│   └── assets/
│       ├── css/estilos.css       Estilos propios (sin CDN)
│       └── js/                   app.js, facturacion.js y productos.js
├── app/                          ← lógica interna (no accesible por HTTP)
│   ├── core/
│   │   ├── BaseController.php    Render, redirecciones y errores seguros
│   │   ├── PdfFactura.php        Comprobante PDF generado en el servidor
│   │   ├── ImagenFactura.php     Comprobante PNG generado en el servidor
│   │   └── fuentes/              Roboto Regular y Bold (Apache 2.0)
│   ├── controladores/
│   │   ├── DashboardController.php
│   │   ├── LoginController.php
│   │   ├── LogoutController.php
│   │   ├── ProductosController.php
│   │   ├── ClientesController.php
│   │   ├── FacturacionController.php
│   │   ├── MermasController.php      Registro y consulta de mermas
│   │   ├── TasasController.php       Tasa BCV (consulta, alta y corrección)
│   │   └── UsuariosController.php
│   ├── modelos/
│   │   ├── DashboardModel.php
│   │   ├── ProductoModel.php
│   │   ├── ClienteModel.php
│   │   ├── FacturaModel.php      Emisión/anulación transaccional y exportación
│   │   ├── MermaModel.php        Bajas de inventario y ajuste de stock
│   │   ├── TasaModel.php         Persistencia de la tasa BCV
│   │   └── UsuarioModel.php      Autenticación, CRUD y bitácora
│   └── vistas/
│       ├── layout/               header.php, footer.php
│       ├── auth/                 login.php
│       ├── errores/              404.php, 500.php
│       ├── dashboard/
│       ├── productos/
│       ├── clientes/
│       ├── mermas/               index (formulario + historial)
│       ├── usuarios/             index (formulario + listado)
│       ├── tasas/                index (tasa actual, formulario e historial)
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
├── sql/actualizacion_codigo_verificacion.sql  Migración para bases existentes
└── README.md
```

---

## 8. Decisiones técnicas

- **MVC sin framework, con POO:** `app/` agrupa `core/`, `controladores/`,
  `modelos/` y `vistas/`; `public/` es la única carpeta expuesta y contiene un
  **único punto de entrada** (`index.php`). Cada controlador es una clase que
  extiende `BaseController` y el enrutador solo instancia la clase y llama al
  método de la ruta; no ejecuta scripts secuenciales.
- **URLs limpias:** `/productos`, `/clientes`, `/facturacion/nueva`,
  `/facturacion/ver/1`, `/usuarios`. Los helpers `base_url()` y `url()` generan
  enlaces que funcionan tanto en la raíz como en una subcarpeta de Apache.
- **Compatibilidad:** las URLs antiguas con `.php` se redirigen a su equivalente
  limpio, así los enlaces guardados o favoritos siguen funcionando.
- **PDO con consultas preparadas reales** (`ATTR_EMULATE_PREPARES = false`).
  Por eso no se repite un mismo placeholder con nombre en una consulta. Los
  `LIMIT` también se enlazan con `PDO::PARAM_INT`.
- **Transacciones** para emitir y anular facturas (todo o nada). El consecutivo
  fiscal se genera bajo un bloqueo `GET_LOCK` de MySQL para que dos cajas no
  lean el mismo número base.
- **CSRF una sola vez por POST** y escape de salida con `e()`.
- **Errores seguros:** las excepciones de validación muestran su mensaje; los
  fallos técnicos (PDO, errores de PHP) se registran en el servidor y el usuario
  recibe un mensaje genérico. Nunca se muestran nombres de esquema ni SQL.
- **Autenticación por sesión:** `$_SESSION['usuario']` con id, nombre, login y rol;
  helpers `require_login()` y `require_admin()` protegen cada entrada. En cada
  petición autenticada se revalida la cuenta contra la base para aplicar de
  inmediato desactivaciones y cambios de rol.
- **Contraseñas:** `password_hash()` / `password_verify()` con rehash automático.
- **Tasa BCV manual y transparente:** no se hace scraping del BCV; el ADMIN
  registra el valor oficial con su fecha. El helper `tasa_desactualizada()`
  compara la vigencia con el día actual y alimenta los avisos de la interfaz.
- **Exportación sin dependencias:** el CSV se genera en streaming con
  `fputcsv`, BOM UTF-8 y `;` como separador; no requiere librerías externas.
  Los textos que empiezan con `=`, `+`, `-`, `@`, tabulador o retorno de carro
  se neutralizan para que Excel no los ejecute como fórmulas.
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
- Dos tasas BCV de ejemplo (Bs 36,2000 y Bs 36,5000); si la fecha ya pasó, el
  sistema mostrará el aviso de tasa desactualizada hasta que registres la del día.

Puedes borrar los datos de prueba y dejar tus propios registros; la numeración de
facturas continúa desde el último consecutivo existente.

---

## 10. Verificación realizada

- Los 34 archivos PHP pasan `php -l` sin errores.
- Panel, Productos, Clientes, Merma, Facturación, Tasas, POS, comprobante, Login
  y Usuarios responden 200 sin errores de PHP ni de consola.
- La raíz `/` muestra el login (incluso con sesión activa) y `/panel` exige
  sesión; login correcto e incorrecto probados.
- Login de usuario inactivo rechazado; logout con mensaje de confirmación.
- Creación, edición, cambio de contraseña, desactivación y reactivación de
  usuarios probados.
- Protección del último ADMIN activo probada (no permite degradarlo).
- CAJERO: `/usuarios` y `/productos` lo redirigen; en `/clientes` puede consultar
  y crear, pero editar/eliminar se bloquea en el servidor; anulación/eliminación
  de facturas bloqueadas; `/mermas` permite registrar bajas sin ver costos.
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
- Refactorización POO verificada: el enrutador instancia controladores, el login
  conserva el aviso de sesión activa sin consultar la sesión desde la vista y
  los errores de validación no se duplican.
- Integridad de clientes: el botón Eliminar queda deshabilitado cuando el
  cliente tiene facturas y el modelo rechaza el borrado con un mensaje claro.
- Errores técnicos: una emisión inválida y un cliente inválido muestran un solo
  aviso amigable, sin `SQLSTATE`, `PDOException` ni detalles internos.
- Tasas BCV: `/tasas` responde 200, muestra la tasa vigente, el estado
  «Desactualizada/Al día» y el historial de cambios.
- Avisos de tasa: Panel, POS, Facturación y Tasas muestran el aviso cuando la
  vigencia es anterior a hoy, con enlace a `/tasas` para el ADMIN.
- Validaciones de tasa: CSRF inválido devuelve 419; valor cero y fecha duplicada
  redirigen con mensaje claro sin insertar registros.
- Historial de ventas: filtros por `desde`, `hasta`, `estado` y `q` responden
  200; una fecha inválida redirige a `/facturacion` con aviso.
- Exportación CSV: resumen y detalle se descargan con BOM `EF BB BF`, separador
  `;`, decimales con coma y cantidades sin ceros sobrantes; el tipo inválido
  redirige sin generar archivo.
- Revalidación de sesión: al desactivar una cuenta, su petición siguiente
  redirige a `/login`; al cambiar el rol en la base, la sesión lo aplica sin
  volver a iniciar sesión.
- Edición del propio usuario: un ADMIN que se degrada a CAJERO ve su nombre y rol
  actualizados y `/usuarios` lo redirige a `/panel`.
- CSRF: un POST con token inválido devuelve **419**, incluso con sesión activa.
- Auditoría de anulación: con `@auditoria_usuario = 1` el registro usa el actor
  que anula y no el emisor de la factura (prueba con rollback).
- Formularios fallidos: conservan los datos escritos y no guardan la contraseña
  ni el token CSRF en la sesión.
- Consistencia de usuarios: una contraseña nueva inválida no deja a medias la
  edición del nombre, cédula, login o rol.
- CSV seguro: los textos que empiezan con `=`, `+`, `-`, `@`, tabulador o retorno
  de carro se exportan neutralizados; los números no se alteran.
- Avisos: solo los mensajes flash se ocultan solos; el aviso de tasa
  desactualizada permanece visible.
- Base intacta tras las pruebas: no quedaron productos, clientes, usuarios,
  facturas ni mermas temporales; el stock se restauró en cada prueba.
- PDF del comprobante: `/facturacion/pdf/ID` descarga un archivo `%PDF-1.4`
  válido (xref y offsets comprobados), con los datos de la factura y sin la URL
  ni rutas del sistema. La alineación derecha del encabezado y de los totales se
  verificó con un volcado de coordenadas: ningún texto termina más allá del
  margen derecho (x ≤ 555).
- Impresión: el botón **Imprimir** abre `/facturacion/pdf/ID?ver=1` en una
  pestaña nueva con `Content-Disposition: inline`; no se usa `window.print()`,
  así que no aparece la URL ni la fecha que Chrome agrega al pie.
- Imagen del comprobante: `/facturacion/imagen/ID` descarga un PNG válido de
  900 px de ancho, con alto variable según el número de líneas. Se comprobó que
  el contenido queda dentro de los márgenes en facturas de 1, 2 y 5 líneas, y
  que un CAJERO puede descargarla sin ver el botón **Anular factura**.
- Eliminar productos: un producto temporal se creó y eliminó correctamente; un
  producto con merma registrada fue rechazado con un aviso claro y permaneció
  en la base.
- Eliminar usuarios: se rechazó la propia cuenta y un usuario con facturas; un
  CAJERO temporal sin movimientos se eliminó correctamente.
- Eliminar facturas: una factura ANULADA de prueba se eliminó con sus líneas;
  una EMITIDA fue rechazada con el mensaje «Solo se pueden eliminar facturas
  anuladas».
- Merma: un CAJERO registró una merma de 0.5 unidades, el stock bajó de 125.000
  a 124.500 y el movimiento quedó con su `id_usuario`. Después se revirtió la
  prueba para dejar la base como estaba.
- Clientes como CAJERO: `/clientes` responde 200 con el formulario de alta; el
  cliente temporal se creó y luego se eliminó. Un intento de editar un cliente
  existente fue rechazado en el servidor.

---

## 11. Seguridad: pruebas de intrusión y endurecimiento

Se hicieron pruebas reales contra la instancia local (`http://127.0.0.1:8000`)
intentando saltarse el login y ejecutar scripts. Resultado y medidas aplicadas:

### 11.1 Inyección SQL en el login

Se probaron credenciales como `admin' OR '1'='1' --`, `admin' OR 1=1 #`,
`' OR 1=1 --`, `admin'--` y `admin' UNION SELECT ... --`.

- Todas fallaron: la pantalla devolvió “Usuario o contraseña incorrectos” y
  `/panel` siguió redirigiendo a `/login` (sin sesión).
- La causa es el uso de **consultas preparadas reales** con
  `PDO::ATTR_EMULATE_PREPARES = false`; el valor del usuario nunca se concatena
  al SQL. Las búsquedas y los filtros también usan marcadores nombrados.

### 11.2 XSS reflejado

Se enviaron cargas `<script>alert(1)</script>`, `"><svg onload=alert(1)>` y
`'><img src=x onerror=alert(1)>` en el parámetro `q` de `/facturacion`,
`/productos`, `/clientes` y `/usuarios`.

- En todos los casos la respuesta fue HTTP 200, sin etiquetas ejecutables
  (`raw=0`): el texto apareció escapado (`&lt;script&gt;`, `&lt;svg`, `&lt;img`).
- Toda salida de datos de usuario pasa por `e()` (`htmlspecialchars` con
  `ENT_QUOTES`), incluidos atributos `value`, `data-*` y mensajes flash.

### 11.3 XSS almacenado

Se creó un cliente temporal con `<script>alert(...)</script>`,
`<svg onload=...>` y `<img onerror=...>` en nombre, dirección y teléfono.

- La vista `/clientes` y el POS (`/facturacion/nueva`) mostraron el texto
  escapado, sin `rawScript` ni `rawSvg`.
- El cliente de prueba (`V-88888888`) se eliminó al terminar; la base quedó sin
  registros de prueba.
- El JavaScript del POS (`facturacion.js`) construye el carrito con
  `textContent`/`createElement` y no usa `innerHTML`, `eval` ni `document.write`
  con datos del usuario.

### 11.4 Fallo CSRF encontrado y corregido

La prueba de un `POST /login` **sin token y sin sesión** reveló un fallo real:
`csrf_verify()` comparaba `$_SESSION['csrf_token'] ?? ''` con `$_POST['csrf'] ?? ''`
y, al ser ambos `''`, `hash_equals('', '')` devolvía `true`. La petición pasaba
la validación (302) en lugar de rechazarse.

Corrección aplicada en `config/helpers.php`:

```php
if (!is_string($token)
    || !is_string($esperado)
    || $token === ''
    || $esperado === ''
    || !hash_equals($esperado, $token)
) {
    http_response_code(419);
    exit('La sesión expiró o la solicitud no es válida. ...');
}
```

Verificación posterior:

| Caso | Resultado esperado | Resultado |
|---|---|---|
| `POST /login` sin token CSRF | 419 | **419** |
| `POST /login` con token inventado | 419 | **419** |
| `POST /login` con token válido | 302 a `/panel` | **302 a `/panel`** |
| `/panel` con la sesión creada | 200 | **200** |

### 11.5 Cabeceras de seguridad y CSP

`security_headers()` se ejecuta al cargar los helpers y añade a todas las
respuestas:

- `Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'`
- `X-Frame-Options: DENY` (no se puede incrustar el sistema en un iframe).
- `X-Content-Type-Options: nosniff`.
- `Referrer-Policy: same-origin`.

La CSP solo permite scripts locales: aunque una salida quedara sin escapar, un
`<script>` inyectado no se ejecutaría. No hay JavaScript inline ni CDN; los dos
archivos usados son `assets/js/app.js` y `assets/js/facturacion.js`. Las páginas
autenticadas y el login envían además `Cache-Control: no-store, no-cache,
must-revalidate, max-age=0`.

### 11.6 Otras comprobaciones

- IDs manipulados (`?editar=1 OR 1=1`, `/facturacion/ver/999999`) se convierten
  a entero o devuelven 404/redirección, sin consultas dinámicas.
- `GET /logout` no cierra la sesión: el cierre real es por `POST` con CSRF.
- Los errores técnicos (PDO, SQL) se registran en el servidor y el navegador solo
  recibe un mensaje genérico.
- Tras las pruebas se confirmó que no quedaron usuarios ni clientes temporales:
  siguen solo los usuarios reales (`admin` y `mcajeraw` activos; `mcajera` y
  `cajero2` inactivos) y los clientes reales.
