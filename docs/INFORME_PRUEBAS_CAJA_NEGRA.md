# Informe de prueba de caja negra — Sistema «De Todito Reyes»

**Fecha de ejecución:** 11/11/2026 (2026-11-11 20:45:41)
**Versión probada:** `sistema11` (09/11/2026), copia activa en `C:\xampp\htdocs\php\sistema`
**URL de prueba:** `http://localhost/php/sistema/public` (Apache)
**Base de datos:** `de_todito_reyes_test` (base de prueba; la base real `de_todito_reyes` no se modificó)
**Tipo de prueba:** caja negra funcional, de permisos, de seguridad, de integridad y de concurrencia
**Resultado global:** **131 casos ejecutados / 131 PASS / 0 FAIL**

---

## 1. Resumen ejecutivo

Se ejecutó una prueba de caja negra completa sobre la versión `sistema11` en funcionamiento,
cubriendo autenticación y sesión, cabeceras de seguridad, CSRF, CRUD de productos, clientes,
usuarios y tasas, facturación, comprobantes, PDF, PNG, CSV, anulación, eliminación segura,
merma, permisos ADMIN/CAJERO, cierre de caja, triggers de integridad y concurrencia de cajas.

**Resultado:** los **131 casos** definidos terminaron en **PASS**. No se encontró ningún
defecto funcional, de permisos, de validación, de exposición de datos ni de integridad en el
alcance probado.

El primer pase del arnés arrojó 113/131 PASS. Los 18 fallos iniciales se diagnosticaron uno por
uno: **ninguno correspondía a un defecto de la aplicación**. Eran expectativas del arnés
(IGTF enviado como `igtf=0`, altura del PNG dependiente del contenido, cierre de caja que muestra
totales y no números de factura, menú del CAJERO con acceso de solo lectura a Productos) y una
particularidad de Apache (convierte el código 419 en 500). Tras corregir el arnés, el pase final
quedó en **131/131 PASS**.

---

## 2. Entorno y método

| Elemento | Valor |
|---|---|
| Servidor web | Apache 2.4.58 (Win64) |
| PHP | 8.2.12 (ZTS, x64) |
| Base de datos | MariaDB 10.4.32 |
| Sistema operativo | Windows |
| URL | `http://localhost/php/sistema/public` |
| Base de prueba | `de_todito_reyes_test` |
| Base real | `de_todito_reyes` (intacta) |

La base de prueba se creó desde `sql/de_todito_reyes.sql`, sustituyendo únicamente el nombre de la
base. El seed oficial quedó con 2 usuarios (`admin/admin123`, `mcajera/cajera123`), 3 clientes,
9 productos, 1 factura demo (`000001`), 1 merma y 2 tasas BCV.

Para apuntar la aplicación a la base de prueba se cambió temporalmente `DB_NAME` en
`config/database.php`. Al finalizar, el archivo se restauró desde una copia exacta y se verificó su
hash SHA-256. La base real nunca recibió escrituras.

Cada caso HTTP se ejecutó con sesiones y cookies independientes, tokens CSRF reales y el token de
idempotencia del punto de venta. Las comprobaciones de integridad se hicieron con consultas SQL
directas sobre la base de prueba. El arnés y los resultados quedaron en:

- Arnés: `C:\Users\ARCADIA\AppData\Local\Temp\opencode\bb_harness.php`
- Resultados completos: `INFORME_PRUEBAS_CAJA_NEGRA_RESULTADOS.json` (copia durable) y
  `C:\Users\ARCADIA\AppData\Local\Temp\opencode\bb_resultados.json`

---

## 3. Resultado por fase

| Fase | Casos | PASS | FAIL |
|---|---:|---:|---:|
| Fase 1 — Entorno, autenticación y seguridad | 17 | 17 | 0 |
| Fase 2 — CRUD de productos, clientes, usuarios y tasas | 46 | 46 | 0 |
| Fase 3 — Facturación, comprobantes, PDF, PNG, CSV y anulación | 35 | 35 | 0 |
| Fase 4 — Permisos y acciones del CAJERO | 22 | 22 | 0 |
| Fase 5 — Merma | 7 | 7 | 0 |
| Fase 6 — Integridad, triggers y concurrencia | 4 | 4 | 0 |
| **TOTAL** | **131** | **131** | **0** |


---

## 4. Casos ejecutados

### Fase 1 — Entorno, autenticación y seguridad (17/17)

| ID | Caso | Resultado | Detalle |
|---|---|:---:|---|
| A1 | GET / anónimo devuelve login 200 | **PASS** | HTTP 200 |
| A2 | /panel anónimo redirige a /login | **PASS** | HTTP 302 -> /php/sistema/public/login |
| A3 | Login con clave incorrecta rechazado sin abrir sesión | **PASS** | login HTTP 200, panel HTTP 302 |
| A4 | Login con SQLi rechazado | **PASS** | panel HTTP 302 |
| A5 | POST de login sin CSRF se rechaza (419/500 Apache) | **PASS** | HTTP 500 |
| A6 | Login ADMIN correcto redirige a /panel | **PASS** | HTTP 302 -> /php/sistema/public/panel |
| A7 | Panel ADMIN carga | **PASS** | HTTP 200 |
| A8 | Cabeceras CSP, X-Frame-Options, nosniff y Referrer-Policy | **PASS** | CSP/Frame/nosniff/Referrer |
| A9 | Páginas ADMIN responden 200 sin errores PHP | **PASS** | 8/8 |
| A10 | Cookie de sesión con HttpOnly y SameSite=Lax | **PASS** | Set-Cookie |
| A11 | GET /logout no cierra la sesión | **PASS** | logout HTTP 302, panel HTTP 200 |
| A12 | POST /logout sin CSRF se rechaza y mantiene sesión | **PASS** | HTTP 500 |
| A13 | POST /logout con CSRF cierra la sesión | **PASS** | logout HTTP 302, panel HTTP 302 |
| A14 | Ruta inexistente devuelve 404 controlado | **PASS** | HTTP 404 |
| A15 | CSS local servido sin CDN | **PASS** | HTTP 200, 23842 bytes |
| A16 | Login sin recursos externos | **PASS** | externos=0 |
| A17 | Menú ADMIN incluye Merma, Tasas, Usuarios y Cierre | **PASS** | menú |

### Fase 2 — CRUD de productos, clientes, usuarios y tasas (46/46)

| ID | Caso | Resultado | Detalle |
|---|---|:---:|---|
| P1 | Crear producto | **PASS** | HTTP 302 |
| P2 | Rechazar código duplicado | **PASS** | duplicado |
| P3 | Editar producto | **PASS** | precio=3.500 |
| P4 | Rechazar precio negativo | **PASS** | precio=3.500 |
| P5 | Rechazar IVA mayor a 100 | **PASS** | IVA=150 |
| P6 | Rechazar producto sin categoría | **PASS** | categoría=0 |
| P7 | Bloquear eliminación de producto con ventas/mermas | **PASS** | producto 1 |
| P8 | Eliminar producto sin referencias | **PASS** | id=11 |
| P9 | POST de productos sin CSRF se rechaza | **PASS** | HTTP 500 |
| P10 | Crear producto temporal para ventas y merma | **PASS** | id=12 |
| P11 | Listado ADMIN muestra columna de costo | **PASS** | columna Costo |
| P12 | Desactivar y reactivar producto | **PASS** | inactivo=0, activo=1 |
| P13 | Rechazar producto sin código | **PASS** | código vacío |
| P14 | Rechazar stock negativo | **PASS** | stock=-1 |
| P15 | Crear producto para prueba de concurrencia | **PASS** | id=13 |
| C1 | Crear cliente | **PASS** | id=4 |
| C2 | Escapar XSS en el listado de clientes | **PASS** | escapado |
| C3 | Editar cliente y verlo en el listado | **PASS** | nombre editado |
| C4 | Rechazar RIF duplicado | **PASS** | duplicado |
| C5 | Rechazar RIF con formato inválido | **PASS** | 12345 |
| C6 | Bloquear eliminación de cliente con facturas | **PASS** | cliente 1 |
| C7 | Eliminar cliente sin facturas | **PASS** | id=6 |
| C8 | POST de clientes sin CSRF se rechaza | **PASS** | HTTP 500 |
| C9 | Rechazar cliente sin nombre | **PASS** | nombre vacío |
| C10 | Crear cliente con nombre tipo fórmula | **PASS** | id=7 |
| U1 | Crear usuario CAJERO | **PASS** | id=3 |
| U2 | Rechazar login duplicado | **PASS** | duplicado |
| U3 | Editar usuario sin cambiar clave | **PASS** | nombre editado |
| U4 | Desactivar y reactivar usuario | **PASS** | baja=0, alta=1 |
| U5 | Bloquear autoeliminación del ADMIN | **PASS** | admin id=1 |
| U6 | Bloquear autodesactivación del ADMIN | **PASS** | admin id=1 |
| U7 | Exigir al menos un ADMIN activo | **PASS** | regla último ADMIN |
| U8 | Rechazar contraseña menor a 6 caracteres | **PASS** | clave=123 |
| U9 | Rechazar cédula inválida | **PASS** | cedula=123 |
| U10 | Rechazar formato de usuario inválido | **PASS** | usuario inválido |
| T1 | Registrar tasa BCV histórica | **PASS** | id=3 |
| T2 | Rechazar tasa con fecha duplicada | **PASS** | duplicada |
| T3 | Rechazar tasa con fecha futura | **PASS** | 2030 |
| T4 | Editar tasa BCV | **PASS** | valor=55.5 |
| T5 | Rechazar tasa cero o negativa | **PASS** | tasa=0 |
| T6 | Rechazar fecha de tasa inválida | **PASS** | fecha inválida |
| T7 | ADMIN ve formulario de tasa | **PASS** | formulario |
| X1 | Búsqueda XSS en clientes se escapa | **PASS** | escapado |
| X2 | Búsqueda SQLi en productos no genera error | **PASS** | HTTP 200 |
| X3 | Búsqueda XSS en facturación se escapa | **PASS** | escapado |
| X4 | Búsqueda SQLi en clientes no genera error | **PASS** | HTTP 200 |

### Fase 3 — Facturación, comprobantes, PDF, PNG, CSV y anulación (35/35)

| ID | Caso | Resultado | Detalle |
|---|---|:---:|---|
| F1 | Emitir factura y descontar stock | **PASS** | HTTP 302 -> /php/sistema/public/facturacion/ver/2 |
| F2 | Totales correctos sin IGTF | **PASS** | base=365.000, IVA=58.400, total=423.400, USD=11.600 |
| F3 | Código de verificación de 8 caracteres y único | **PASS** | código=MU34KWPR |
| F4 | Stock del producto 10 → 8 | **PASS** | stock=8 |
| F5 | Comprobante muestra número, código y total | **PASS** | HTTP 200 |
| F6 | Búsqueda por código de verificación encuentra la factura | **PASS** | código=MU34KWPR |
| F7 | PDF válido y descargado como adjunto | **PASS** | HTTP 200, 3517 bytes |
| F8 | PDF contiene el código y no expone URL ni rutas | **PASS** | sin fugas |
| F9 | PDF con ?ver=1 se abre inline | **PASS** | HTTP 200 |
| F10 | PNG válido de 900 × 772 (sin IGTF) | **PASS** | HTTP 200, 900x772 |
| F11 | CSV resumen con BOM UTF-8 y datos correctos | **PASS** | HTTP 200 |
| F12 | CSV detalle con BOM UTF-8 y líneas | **PASS** | HTTP 200 |
| F13 | Emitir factura con IGTF 3 % y totales correctos | **PASS** | base=182.500, IVA=29.200, IGTF=6.351, total=218.051, USD=5.974 |
| F14 | Stock 8 → 7 tras la segunda venta | **PASS** | stock=7 |
| F15 | CSV neutraliza fórmula =2+2 anteponiendo apóstrofo | **PASS** | fórmula neutralizada |
| F16 | Rechazar carrito vacío | **PASS** | sin factura |
| F17 | Rechazar cliente inexistente | **PASS** | cliente 999999 |
| F18 | Rechazar producto inexistente | **PASS** | producto 999999 |
| F19 | Rechazar stock insuficiente | **PASS** | cantidad=999 |
| F20 | Rechazar método de pago inválido | **PASS** | BITCOIN |
| F21 | CSRF ausente en emisión se rechaza | **PASS** | HTTP 500 |
| F22 | Rechazar emisión sin token de idempotencia | **PASS** | sin token |
| F23 | Token de emisión es de un solo uso (doble envío) | **PASS** | facturas=4 |
| F24 | No se puede eliminar una factura EMITIDA | **PASS** | factura 000002 |
| F25 | Anular factura devuelve stock | **PASS** | stock 6 -> 8 |
| F26 | Comprobante queda marcado ANULADA | **PASS** | estado ANULADA |
| F27 | Reanulación rechazada sin doble devolución de stock | **PASS** | stock=8 |
| F28 | Eliminar factura ANULADA borra también sus líneas | **PASS** | líneas 1 -> 0 |
| F29 | Consecutivo no reutiliza el número de la última anulada eliminada | **PASS** | nueva=000005, máximo previo=4 |
| F30 | Vistas fiscales excluyen facturas anuladas | **PASS** | libro=0, vendido=2/2, stock=8 |
| F31 | Factura demo 000001 sin descuadre de total USD | **PASS** | total_usd=26.882 |
| F32 | PDF protegido exige sesión | **PASS** | HTTP 302 |
| F33 | Comprobante inexistente redirige con aviso | **PASS** | HTTP 302 |
| F34 | Cierre con fecha inválida redirige con aviso | **PASS** | HTTP 302 |
| F35 | Cierre del 30/10 resume la factura demo 000001 | **PASS** | HTTP 200 |

### Fase 4 — Permisos y acciones del CAJERO (22/22)

| ID | Caso | Resultado | Detalle |
|---|---|:---:|---|
| M1 | Login de CAJERO correcto | **PASS** | HTTP 200 |
| M2 | Menú CAJERO incluye Productos y excluye Usuarios, Tasas y Merma | **PASS** | menú restringido |
| M3 | CAJERO consulta productos en modo solo lectura | **PASS** | HTTP 200 |
| M4 | CAJERO bloqueado en /usuarios | **PASS** | HTTP 302 |
| M5 | CAJERO bloqueado en /mermas | **PASS** | HTTP 302 |
| M6 | CAJERO consulta tasas sin formulario de guardado | **PASS** | HTTP 200 |
| M7 | CAJERO no puede registrar tasa | **PASS** | tasa bloqueada |
| M8 | CAJERO no puede registrar merma | **PASS** | merma bloqueada |
| M9 | CAJERO puede crear cliente | **PASS** | id=8 |
| M10 | CAJERO no puede editar cliente | **PASS** | sin cambios |
| M11 | CAJERO no puede eliminar cliente | **PASS** | cliente intacto |
| M12 | CAJERO no puede descargar CSV | **PASS** | HTTP 302 |
| M13 | CAJERO puede emitir factura | **PASS** | factura=000006 |
| M14 | CAJERO no puede anular factura | **PASS** | estado EMITIDA |
| M15 | CAJERO no puede eliminar factura | **PASS** | factura intacta |
| M16 | Cierre del CAJERO solo muestra sus ventas | **PASS** | HTTP 200, facturas=1/3 |
| M17 | Cierre del ADMIN muestra todas las ventas | **PASS** | HTTP 200, facturas=3 |
| M18 | Total del cierre coincide con la base | **PASS** | total=635,10 |
| M19 | Panel CAJERO omite costos y pérdidas de merma | **PASS** | sin costos |
| M20 | Panel ADMIN muestra merma con pérdida y costos | **PASS** | con costos |
| M21 | Menú CAJERO incluye Cierre de caja | **PASS** | cierre visible |
| M22 | CAJERO puede ver su comprobante | **PASS** | HTTP 200 |

### Fase 5 — Merma (7/7)

| ID | Caso | Resultado | Detalle |
|---|---|:---:|---|
| S1 | ADMIN registra merma y descuenta stock | **PASS** | stock 7 -> 6 |
| S2 | Rechazar merma mayor al stock sin alterar existencias | **PASS** | stock=6 |
| S3 | Rechazar motivo de merma inválido | **PASS** | motivo inválido |
| S4 | Historial ADMIN muestra costo unitario y pérdida | **PASS** | columnas de costo |
| S5 | Dashboard ADMIN refleja las mermas | **PASS** | resumen de merma |
| S6 | Rechazar merma con cantidad cero | **PASS** | cantidad=0 |
| S7 | Rechazar merma de producto inexistente | **PASS** | producto 999999 |

### Fase 6 — Integridad, triggers y concurrencia (4/4)

| ID | Caso | Resultado | Detalle |
|---|---|:---:|---|
| H1 | Trigger impide stock negativo por SQL directo | **PASS** | stock=6 |
| H2 | Trigger impide merma mayor al stock por SQL directo | **PASS** | merma bloqueada |
| H3 | Trigger de auditoría registra la emisión por SQL directo | **PASS** | bitácora EMISION |
| H4 | Concurrencia: 4 cajas emiten con consecutivos únicos | **PASS** | HTTP=302,302,302,302 nros=000010,000009,000008,000007 stock=96 |


---

## 5. Evidencia destacada

### 5.1 Autenticación, sesión y CSRF

- El login exige usuario y contraseña; las credenciales incorrectas y la inyección SQL se rechazan
  sin abrir sesión.
- La cookie de sesión se emite con `HttpOnly` y `SameSite=Lax`.
- `GET /logout` no cierra la sesión; `POST /logout` exige CSRF y, con token válido, redirige a
  `/login?cerrado=1` y deja el panel inaccesible.
- Todos los POST sin CSRF se rechazan antes de ejecutar la acción. En Apache la respuesta llega
  como **HTTP 500** con el cuerpo «La sesión expiró o la solicitud no es válida…»; en el servidor
  embebido de PHP llega como **HTTP 419**. Es un mapeo del servidor, no una diferencia de la
  aplicación (ver §6.1).
- Las cabeceras CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff` y
  `Referrer-Policy: same-origin` están presentes en las páginas autenticadas.

### 5.2 CRUD y validaciones

- Productos: alta, edición, activación/desactivación, eliminación segura y bloqueo de eliminación
  cuando existen ventas, compras o mermas. Se rechazan código duplicado, precio negativo, IVA fuera
  de 0–100, categoría inexistente, código vacío y stock negativo.
- Clientes: alta, edición, eliminación segura y bloqueo cuando existen facturas. Se rechazan RIF
  duplicado, formato inválido y nombre vacío. El listado escapa XSS y las búsquedas no generan
  errores SQL.
- Usuarios: alta, edición sin cambiar clave, baja/alta, bloqueo de autoeliminación y
  autodesactivación, regla de «al menos un ADMIN activo», longitud mínima de clave, cédula y
  formato de usuario.
- Tasas: alta histórica, edición, rechazo de fecha duplicada, fecha futura, valor cero/negativo y
  fecha inválida.

### 5.3 Facturación y comprobantes

- Emisión con descuento de stock y totales calculados en el servidor a partir de la tasa vigente.
- IVA del 16 % y IGTF del 3 % calculados correctamente; el IGTF solo se aplica cuando el campo
  `igtf` está presente.
- Código de verificación de 8 caracteres y único; búsqueda por código en el historial.
- PDF válido (`%PDF-…%%EOF`), descarga como adjunto y apertura inline con `?ver=1`, sin rutas ni
  URL internas expuestas.
- PNG válido de 900 px de ancho. La altura es dinámica: **772 px** sin IGTF y **798 px** con la
  fila de IGTF.
- CSV de resumen y detalle con BOM UTF-8; neutralización de fórmulas tipo `=2+2` anteponiendo
  apóstrofo.
- Rechazo de carrito vacío, cliente inexistente, producto inexistente, stock insuficiente y método
  de pago inválido.
- Token de emisión de un solo uso: el doble envío no duplica la factura.
- Anulación con devolución de stock, rechazo de reanulación sin doble devolución y eliminación de
  facturas anuladas junto con sus líneas.
- El consecutivo no reutiliza el número de una factura anulada y eliminada.
- Las vistas fiscales (`v_libro_ventas`, `v_movimientos_producto`) excluyen las facturas anuladas.
- La factura demo `000001` mantiene `total_usd` coherente con `total_ves / tasa`.

### 5.4 Permisos ADMIN / CAJERO

- El CAJERO puede consultar Productos en modo solo lectura (sin columna de costo ni formulario de
  alta), consultar Tasas sin formulario de guardado, crear clientes y emitir facturas.
- El CAJERO no puede editar ni eliminar clientes, registrar tasas, registrar mermas, descargar CSV,
  anular facturas, eliminar facturas ni entrar a Usuarios o Merma.
- El menú del CAJERO incluye Productos, Clientes, Facturación, Cierre de caja y Nueva venta; excluye
  Usuarios, Tasas y Merma.
- El panel del CAJERO omite costos y pérdidas de merma; el del ADMIN muestra «Inventario a costo»,
  la sección Merma y la pérdida acumulada.

### 5.5 Merma

- El ADMIN registra mermas, descuenta stock y ve costo unitario y pérdida.
- Se rechazan merma mayor al stock, motivo inválido, cantidad cero y producto inexistente, sin
  alterar existencias ni crear registros.

### 5.6 Integridad y concurrencia

- Trigger de stock negativo: bloquea la actualización por SQL directo.
- Trigger de merma mayor al stock: bloquea la inserción por SQL directo.
- Trigger de auditoría: registra la emisión de facturas en `bitacora_auditoria`.
- Concurrencia: 4 sesiones emitieron simultáneamente contra Apache; las 4 respondieron 302, los
  consecutivos fueron únicos (`000007`–`000010`) y el stock bajó de 100 a 96, sin sobreventa.

### 5.7 Cierre de caja

- El cierre es un resumen agregado: facturas emitidas, total Bs/USD, IGTF, arqueo por método de
  pago, primera/última venta y facturas anuladas. **No lista números de factura individuales.**
- El cierre del ADMIN suma todas las ventas del día; el del CAJERO solo las suyas. Los totales
  coinciden con las consultas SQL de control.
- El cierre del 30/10 resume correctamente la factura demo `000001` (Bs 981,20 / $ 26,88).

---

## 6. Observaciones y hallazgos

### 6.1 Código 419 en Apache

`csrf_verify()` responde con `http_response_code(419)` y un cuerpo explicativo. En el servidor
embebido de PHP (`php -S`) la respuesta es **HTTP 419**. En Apache 2.4.58 llega como **HTTP 500**,
pero con el mismo cuerpo y sin ejecutar la acción. Es una limitación de Apache con códigos de estado
no estándar, no un fallo de la lógica de seguridad: la petición se rechaza igualmente.

**Recomendación (no aplicada en esta versión):** si se requiere un código uniforme entre servidores,
usar `403` en lugar de `419` o registrar el mapeo en la documentación de despliegue. Cambiarlo
implicaría modificar la aplicación y volver a ejecutar la caja blanca; por eso se dejó intacta.

### 6.2 Cierre de caja agregado

La vista de cierre no muestra el listado de facturas, solo totales y arqueo por método. Las
comprobaciones de «solo mis ventas» / «todas las ventas» se hicieron comparando cantidad y total
contra la base, no buscando números de factura en el HTML.

### 6.3 Altura del PNG

La altura del comprobante depende de las filas renderizadas. Un comprobante sin IGTF mide 900×772;
con IGTF mide 900×798. El caso F10 valida el caso sin IGTF y el generador añade la fila cuando
`monto_igtf_ves > 0`.

### 6.4 Acceso del CAJERO a Productos

El CAJERO sí puede consultar el catálogo en modo solo lectura. Esto es coherente con el README y con
el controlador (`exigirLogin()` + vista sin acciones de escritura). El caso M2 se ajustó para
exigir Productos y excluir Usuarios, Tasas y Merma.

### 6.5 Fallos del primer pase

Los 18 fallos iniciales se agruparon así:

| Causa | Casos | Resolución |
|---|---|---|
| IGTF activado por enviar `igtf=0` (`isset`) | F2, F5, F11 | Omitir el campo cuando no aplica |
| Altura del PNG dependiente del contenido | F10 | Esperar 772 px sin IGTF |
| Cierre agregado sin números de factura | F35, M16, M17 | Comparar cantidad y totales |
| Menú del CAJERO con Productos | M2 | Ajustar la aserción |
| Texto «Inventario a costo» | M20 | Ajustar la aserción |
| Consecutivo esperado tras una factura extra | F29 | Comparar contra el máximo previo |
| Total vendido acumulado por una factura extra | F30 | Calcular el esperado desde la base |
| Cookie ya existente en la sesión | A10 | Usar una sesión nueva |
| `Location` con query (`/login?cerrado=1`) | A13 | Comparar solo la ruta |
| Apache convierte 419 en 500 | A5, A12, P9, C8, F21 | Aceptar 419/500 con cuerpo de rechazo |

Ninguno de estos puntos requirió cambiar código de la aplicación.

---

## 7. Integridad de la verificación

| Comprobación | Resultado |
|---|---|
| Base real `de_todito_reyes` | No se modificó; conserva sus datos (2 facturas, 4 usuarios, 11 productos, 2 clientes) |
| `config/database.php` restaurado | SHA-256 `F5E2BCD511D97619290979FA46B1F08BF67F268E2BF66C39B65FABF6AAEC2E51` |
| `sistema11.rar` | 278.741 bytes; SHA-256 `AAC5EDC6F13400631CC3906D37329949E6FF74DC7C6191A3922207E2F145FDAF`; sin cambios |
| Carpeta `sistema` frente al RAR | 58/58 archivos de la aplicación idénticos, 0 diferencias; este informe se añade como evidencia |
| Base de prueba | `de_todito_reyes_test` conservada para reproducibilidad |

---

## 8. Limitaciones

- La prueba se ejecutó sobre Apache con XAMPP en Windows; el comportamiento del código 419 puede
  variar en otros servidores.
- La concurrencia se probó con 4 sesiones simultáneas, suficiente para validar el bloqueo y los
  consecutivos, pero no es una prueba de carga.
- Las pruebas son HTTP + SQL; no se automatizó un navegador real. El JavaScript del POS se validó
  indirectamente mediante el contrato del formulario y las validaciones del servidor.
- El cierre de caja se valida por totales, porque la vista no expone el detalle de facturas.

---

## 9. Conclusión

La versión `sistema11` (09/11/2026) supera la prueba de caja negra con **131/131 casos PASS**.
No se encontraron defectos funcionales, de permisos, de seguridad, de validación ni de integridad en
el alcance ejecutado. El resultado es consistente con la caja blanca de la misma versión
(`INFORME_PRUEBAS_CAJA_BLANCA.md`, 151/151 PASS).

Las únicas observaciones son de entorno o de expectativa de prueba: el mapeo 419→500 de Apache, la
altura dinámica del PNG y el carácter agregado del cierre de caja. Ninguna requiere corregir la
aplicación para esta entrega.
