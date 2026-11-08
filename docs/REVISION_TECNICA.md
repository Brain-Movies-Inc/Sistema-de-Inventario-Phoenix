# Revisión técnica — Sistema De Todito Reyes

**Proyecto:** Sistema de gestión y facturación “De Todito Reyes”  
**Ubicación revisada:** `C:\xampp\htdocs\revisionSistema`  
**Fecha de revisión:** 6 de noviembre de 2026  
**Tipo de revisión:** estática (código, base de datos, configuración y documentación)  
**Entorno:** Windows, XAMPP, PHP 8.3.36, MariaDB/MySQL de XAMPP  
**Documentos relacionados:** [Manual de usuario](MANUAL_USUARIO.md) · [Manual de instalación](MANUAL_INSTALACION.md) · [Diagramas de casos de uso](casos-de-uso/)

---

## 1. Resumen ejecutivo

El sistema está construido como una aplicación MVC en PHP sin framework, con PDO y MySQL/MariaDB. La separación entre `public/` (raíz web) y `app/`, `config/`, `sql/` y `tools/` es correcta y reduce la exposición de código interno. La lógica de facturación es la parte más cuidada: usa transacciones, bloqueos `FOR UPDATE`, `GET_LOCK` para el consecutivo, recálculo de precios en el servidor y auditoría mediante triggers.

La revisión encontró **dos riesgos críticos** y varios riesgos altos que deben atenderse antes de usar el sistema en producción:

1. Las credenciales de demostración siguen activas y la conexión usa `root` sin contraseña.
2. El script de instalación elimina las 12 tablas existentes (`DROP TABLE`) sin confirmación.

Además, el sistema **no fuerza HTTPS**, la cookie de sesión no usa `Secure`, no hay límite de intentos de login, la auditoría solo cubre autenticación y facturas, y la documentación contiene afirmaciones desactualizadas (por ejemplo, dice que hay 34 archivos PHP cuando hay 40, y que el token CSRF nunca se conserva cuando tres controladores sí lo guardan en sesión).

La calidad interna del código es buena para un proyecto académico/pequeño: no se encontraron inyecciones SQL ni XSS evidentes, y las reglas de autorización se aplican en el servidor, no solo en la interfaz. Con las correcciones indicadas, el sistema puede considerarse una base sólida para un negocio pequeño.

### Valoración global

| Dimensión | Valoración | Comentario |
|---|---|---|
| Arquitectura y organización | Buena | MVC claro, raíz web aislada, autoload simple. |
| Seguridad de aplicación | Aceptable con reservas | Buenas bases (PDO, CSRF, escape, roles), pero faltan controles de producción. |
| Seguridad de infraestructura | Insuficiente para producción | Sin HTTPS, `root` sin clave, credenciales demo. |
| Consistencia funcional | Buena | Reglas de negocio coherentes y transaccionales. |
| Documentación | Buena pero desactualizada | Muy completa, con desfases concretos. |
| Pruebas | Limitada | Solo `php -l`; no hay pruebas automatizadas ni CI. |
| Mantenibilidad | Media | Código legible, pero sin framework, tests ni migraciones formales. |

---

## 2. Alcance y metodología

### 2.1 Alcance

Se revisaron:

- Los 40 archivos PHP del proyecto (controladores, modelos, vistas, configuración, herramientas y punto de entrada).
- El esquema SQL `sql/de_todito_reyes.sql` y la migración `sql/actualizacion_codigo_verificacion.sql`.
- Los archivos `.htaccess` de `public/`, `app/`, `config/`, `sql/` y `tools/`.
- El JavaScript de `public/assets/js/` y la hoja de estilos.
- El `README.md` del proyecto.
- La configuración de PHP y extensiones del entorno XAMPP.

### 2.2 Metodología

1. Lectura completa del código y del esquema.
2. Verificación de sintaxis con `php -l` sobre los 40 archivos.
3. Verificación de extensiones PHP cargadas.
4. Comprobación del estado del servidor MySQL.
5. Revisión manual de controles de seguridad: autenticación, sesión, CSRF, autorización, validación de entrada, escape de salida, consultas SQL, manejo de errores, auditoría, archivos y configuración.
6. Contraste entre lo que afirma el `README.md` y lo que hace el código.

### 2.3 Limitaciones

- **MySQL no estaba en ejecución** durante la revisión (`Can't connect to server on 'localhost' (10061)`), por lo que no se pudieron repetir pruebas de integración, concurrencia ni flujos completos en navegador.
- No se dispone de un entorno de staging ni de datos reales.
- No se hicieron pruebas de carga, penetración externa ni análisis de dependencias (el proyecto no usa Composer).
- Las afirmaciones del `README.md` sobre pruebas realizadas no pudieron reproducirse en este entorno; se contrastaron con el código, no con ejecución real.

### 2.4 Verificaciones ejecutadas

| Verificación | Resultado |
|---|---|
| Archivos PHP analizados | **40** |
| `php -l` | **40/40 sin errores de sintaxis** |
| PHP | **8.3.36** |
| `pdo_mysql` | Habilitada |
| `mbstring` | Habilitada |
| `json` | Habilitada |
| `fileinfo` | Habilitada |
| `openssl` | Habilitada |
| `gd` | **Deshabilitada** (`;extension=gd` en `php.ini`) |
| Servidor MySQL | **Detenido** durante la revisión |
| Pruebas automatizadas | No existen |
| CI/CD | No existe |

---

## 3. Arquitectura y stack

### 3.1 Stack

- **Lenguaje:** PHP 8.x (probado con 8.3.36). El README declara 7.4, pero el uso de `match` exige **PHP 8.0+** (ver AL-07).
- **Base de datos:** MySQL 8.0+ / MariaDB 10.4+.
- **Acceso a datos:** PDO con consultas preparadas reales (`ATTR_EMULATE_PREPARES = false`).
- **Frontend:** HTML, CSS y JavaScript propios, sin CDN ni frameworks.
- **Servidor web:** Apache con `mod_rewrite` o el servidor embebido de PHP para desarrollo.
- **Generación de documentos:** PDF nativo (`PdfFactura`) y PNG con GD (`ImagenFactura`).

### 3.2 Estructura

```
revisionSistema/
├── public/          ← única raíz web
│   ├── index.php    ← front controller y enrutador
│   ├── .htaccess    ← reescritura a index.php
│   └── assets/      ← CSS y JS
├── app/
│   ├── core/        ← BaseController, PdfFactura, ImagenFactura, fuentes
│   ├── controladores/
│   ├── modelos/
│   └── vistas/
├── config/          ← bootstrap, PDO, empresa, helpers
├── sql/             ← esquema, datos demo y migración
├── tools/           ← recuperación de ADMIN por consola
└── docs/            ← esta revisión y los manuales
```

### 3.3 Flujo de una petición

1. Apache reescribe la URL a `public/index.php` (o el servidor embebido usa `index.php` como router).
2. `config/bootstrap.php` carga helpers, base de datos y datos de empresa.
3. El enrutador resuelve la ruta y llama al método del controlador.
4. El controlador exige sesión/rol, valida CSRF y delega en el modelo.
5. El modelo usa PDO y transacciones cuando corresponde.
6. La vista escapa la salida con `e()` y se renderiza dentro del layout.

### 3.4 Modelo de datos

El esquema contiene **12 tablas** y **4 vistas**:

- Tablas: `categorias`, `proveedores`, `usuarios`, `clientes`, `productos`, `compras_proveedor`, `detalle_compra`, `tasas_cambio_bcv`, `facturas`, `detalle_factura`, `merma_ajustes`, `bitacora_auditoria`.
- Vistas: `v_tasa_vigente`, `v_stock_bajo`, `v_libro_ventas`, `v_movimientos_producto`.
- Triggers: auditoría de facturas (`INSERT`/`UPDATE`), bloqueo de stock negativo y validación de merma.

Las relaciones usan `InnoDB`, `utf8mb4` y claves foráneas con `RESTRICT` en los historiales, lo que protege la trazabilidad.

---

## 4. Fortalezas encontradas

1. **Raíz web aislada.** Solo `public/` se publica; `app/`, `config/`, `sql/` y `tools/` quedan fuera del alcance HTTP y además tienen `.htaccess` de respaldo.
2. **Consultas preparadas reales.** `PDO::ATTR_EMULATE_PREPARES = false` y marcadores nombrados; no se concatena entrada del usuario en SQL.
3. **CSRF correcto.** `csrf_verify()` rechaza tokens vacíos, ausentes o manipulados con `hash_equals`; el login sin token devuelve 419.
4. **Contraseñas seguras.** `password_hash()` / `password_verify()` con `PASSWORD_DEFAULT` y rehash automático.
5. **Sesión endurecida.** `session.use_strict_mode`, regeneración de ID al iniciar sesión, `HttpOnly`, `SameSite=Lax`, cierre por inactividad a los 30 minutos y revalidación de la cuenta en cada petición.
6. **Autorización en servidor.** `require_login()` y `require_admin()` protegen cada módulo; el CAJERO no puede editar/eliminar clientes, anular/eliminar facturas, gestionar productos, tasas ni usuarios aunque manipule la URL.
7. **Escape de salida sistemático.** Las vistas usan `e()` (`htmlspecialchars` con `ENT_QUOTES`) para datos de usuario.
8. **Cabeceras de seguridad.** CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin` y `Cache-Control: no-store` en páginas autenticadas.
9. **Facturación transaccional.** Recálculo en servidor, `SELECT ... FOR UPDATE`, descuento de stock en la misma transacción, `GET_LOCK` para el consecutivo y reintentos ante colisión.
10. **Auditoría de facturas.** Triggers que registran emisión y anulación, incluido el usuario que anula mediante `@auditoria_usuario`.
11. **Exportación CSV robusta.** BOM UTF-8, separador `;`, formato venezolano y neutralización de fórmulas para Excel.
12. **Documentos sin dependencias.** PDF y PNG se generan en el servidor, sin CDN ni librerías externas.
13. **Formato local correcto.** Zona horaria `America/Caracas`, `SET time_zone = '-04:00'`, moneda y cantidades con formato venezolano.
14. **Recuperación de acceso controlada.** `tools/crear_admin.php` solo funciona por CLI y conserva `.htaccess` de respaldo.

---

## 5. Hallazgos por severidad

> **Criterio de severidad**
> - **Crítico:** permite comprometer el sistema o perder datos de forma inmediata.
> - **Alto:** riesgo relevante de seguridad, integridad o continuidad; debe corregirse antes de producción.
> - **Medio:** debilidad que aumenta la superficie de ataque o dificulta la operación.
> - **Bajo:** mejora de calidad, documentación o mantenibilidad.

### 5.1 Resumen

| ID | Severidad | Hallazgo | Ubicación principal |
|---|---|---|---|
| CR-01 | Crítico | Credenciales demo activas y MySQL `root` sin contraseña | `sql/de_todito_reyes.sql`, `config/database.php` |
| CR-02 | Crítico | Script de instalación destructivo (`DROP TABLE`) | `sql/de_todito_reyes.sql` |
| AL-01 | Alto | Sin HTTPS, sin `Secure` en cookie y sin HSTS | `config/helpers.php`, despliegue |
| AL-02 | Alto | Sin límite de intentos ni bloqueo de login | `LoginController.php` |
| AL-03 | Alto | Auditoría incompleta fuera de login y facturas | controladores y modelos |
| AL-04 | Alto | Eliminación física de facturas anuladas | `FacturaModel::eliminar()` |
| AL-05 | Alto | GD deshabilitada: PNG del comprobante falla | `php.ini`, `ImagenFactura.php` |
| AL-06 | Alto | Documentación desactualizada en puntos de seguridad | `README.md` |
| AL-07 | Alto | El README declara PHP 7.4, pero el código usa `match` (PHP 8.0+) | `README.md`, controladores |
| ME-01 | Medio | `old_producto`, `old_cliente` y `old_tasa` guardan el token CSRF | controladores |
| ME-02 | Medio | Política de contraseñas débil (mínimo 6, sin complejidad) | `UsuariosController.php` |
| ME-03 | Medio | El mensaje de login permite enumerar usuarios inactivos | `LoginController.php` |
| ME-04 | Medio | Corregir una tasa no actualiza el usuario que la registró | `TasaModel::actualizar()` |
| ME-05 | Medio | Sin pruebas automatizadas ni CI | proyecto |
| ME-06 | Medio | Sin estrategia de respaldos automatizados | operación |
| ME-07 | Medio | CSP permite estilos inline y no hay `upgrade-insecure-requests` | `helpers.php` |
| BA-01 | Bajo | La tasa “vigente” puede incluir una tasa futura por tolerancia de 5 min | `TasaModel::vigente()` |
| BA-02 | Bajo | El servidor embebido de PHP no es apto para producción | documentación |
| BA-03 | Bajo | Se mantienen URLs legacy y código de vista no usado | router y vistas |
| BA-04 | Bajo | Conteo de archivos PHP y nombres de usuarios desactualizados | `README.md` |
| BA-05 | Bajo | `casos_uso_general.puml` quedó como artefacto alternativo | `docs/casos-de-uso/` |

---

### 5.2 Detalle de hallazgos

#### CR-01 — Credenciales de demostración activas y conexión como `root` sin contraseña

**Evidencia**

- `sql/de_todito_reyes.sql` crea `admin` / `admin123` y `mcajera` / `cajera123`.
- `config/database.php` usa:
  ```php
  const DB_USER = 'root';
  const DB_PASS = '';
  ```
- El README recomienda cambiarlas, pero no hay un cambio forzado ni una advertencia bloqueante al primer inicio.

**Impacto**

Cualquier persona con acceso a la URL puede entrar como ADMIN con credenciales públicas. Si MySQL está expuesto en la red, cualquiera puede conectarse como `root` sin contraseña y leer, modificar o borrar toda la base.

**Recomendación**

1. Cambiar las contraseñas de `admin` y `mcajera` antes de publicar.
2. Eliminar o desactivar las cuentas demo que no se usen.
3. Crear un usuario MySQL dedicado (`de_todito_reyes_app`) con permisos solo sobre la base del sistema.
4. No usar `root` en la aplicación.
5. Añadir una comprobación de primer inicio que obligue a cambiar la contraseña demo.

**Prioridad:** inmediata.

---

#### CR-02 — Script de instalación destructivo

**Evidencia**

`sql/de_todito_reyes.sql` contiene:

```sql
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS bitacora_auditoria;
DROP TABLE IF EXISTS merma_ajustes;
...
DROP TABLE IF EXISTS categorias;
SET FOREIGN_KEY_CHECKS = 1;
```

**Impacto**

Si se ejecuta sobre una base existente para “actualizar” el sistema, borra todos los datos sin confirmación. Es un riesgo alto de pérdida de información en producción.

**Recomendación**

1. Separar el script en:
   - `crear_esquema.sql` (solo `CREATE TABLE IF NOT EXISTS`).
   - `datos_demo.sql` (opcional).
   - `migraciones/` para cambios incrementales.
2. Añadir una confirmación explícita o un modo `--force` en la herramienta de instalación.
3. Documentar con letra grande que el script actual es solo para una instalación nueva.
4. Automatizar un respaldo antes de cualquier importación.

**Prioridad:** inmediata.

---

#### AL-01 — Sin HTTPS, sin `Secure` en la cookie y sin HSTS

**Evidencia**

`config/helpers.php` configura la cookie así:

```php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
```

No se define `'secure' => true`, no se fuerza la redirección a HTTPS y no se envía `Strict-Transport-Security`.

**Impacto**

En una red no confiable, las credenciales y la cookie de sesión pueden interceptarse. Un atacante podría secuestrar la sesión y operar como el usuario.

**Recomendación**

1. Publicar el sistema únicamente detrás de HTTPS (certificado válido, idealmente Let's Encrypt).
2. Añadir `'secure' => true` cuando `HTTPS` esté activo.
3. Redirigir HTTP → HTTPS a nivel de Apache o balanceador.
4. Enviar `Strict-Transport-Security: max-age=31536000; includeSubDomains`.
5. Mantener `HttpOnly` y `SameSite=Lax` (o `Strict` si la operación lo permite).

**Prioridad:** antes de producción.

---

#### AL-02 — Sin límite de intentos ni bloqueo de login

**Evidencia**

`LoginController::index()` valida usuario y contraseña, pero no cuenta intentos fallidos, no aplica retraso, no bloquea la cuenta ni la IP y no usa CAPTCHA. `LOGIN_FALLIDO` se registra en la bitácora, pero no se consulta para limitar.

**Impacto**

Permite ataques de fuerza bruta o *password spraying* contra `admin` y otros usuarios. La contraseña mínima de 6 caracteres agrava el riesgo.

**Recomendación**

1. Limitar intentos por combinación IP + usuario (por ejemplo, 5 intentos en 15 minutos).
2. Aplicar retraso exponencial o bloqueo temporal.
3. Alertar al ADMIN tras varios fallos.
4. Considerar CAPTCHA tras 3 fallos.
5. Registrar y monitorizar los eventos `LOGIN_FALLIDO`.

**Prioridad:** alta.

---

#### AL-03 — Auditoría incompleta

**Evidencia**

- `UsuarioModel::registrarBitacora()` se usa para `LOGIN`, `LOGIN_FALLIDO`, `LOGOUT`, `CREAR_ADMIN` y `RESET_ADMIN`.
- Los triggers de `facturas` registran emisión y anulación.
- No hay registros equivalentes para altas/ediciones/bajas de productos, clientes, usuarios, tasas o mermas.

**Impacto**

Ante un incidente o un error operativo no se puede saber quién cambió un precio, eliminó un cliente, desactivó un usuario o corrigió una tasa.

**Recomendación**

1. Crear un servicio de auditoría único (`Auditoria::registrar()`).
2. Registrar en cada operación CRUD: usuario, acción, entidad, ID, datos anteriores y nuevos (sin secretos), IP y fecha.
3. Añadir triggers o tablas de historial para precios y tasas.
4. Incluir un visor de bitácora para el ADMIN.

**Prioridad:** alta.

---

#### AL-04 — Eliminación física de facturas anuladas

**Evidencia**

`FacturaModel::eliminar()` borra la factura y sus líneas:

```php
DELETE FROM detalle_factura WHERE id_factura = :id
DELETE FROM facturas WHERE id_factura = :id
```

Solo permite facturas `ANULADA`, pero las elimina definitivamente.

**Impacto**

Un documento fiscal anulado sigue siendo parte de la historia fiscal. Borrarlo puede dificultar una fiscalización, una auditoría interna o una conciliación. Aunque el consecutivo no se reutiliza (se conserva en la bitácora), el comprobante y su detalle desaparecen.

**Recomendación**

1. Sustituir el borrado físico por borrado lógico (`eliminada = 1`, `fecha_eliminacion`, `id_usuario_elimina`) o por archivado.
2. Restringir la eliminación a un rol superior y exigir motivo.
3. Conservar el detalle y el PDF/PNG de la factura anulada.
4. Definir una política de retención legal con asesoría fiscal.

**Prioridad:** alta.

---

#### AL-05 — GD deshabilitada: el PNG del comprobante falla

**Evidencia**

- `php -i` / `extension_loaded('gd')` devuelve `false`.
- `C:\xampp\php\php.ini` tiene `;extension=gd`.
- `ImagenFactura::__construct()` lanza una excepción si no existen `imagettftext` o `imagepng`.

**Impacto**

El botón **Descargar imagen** no funciona; el usuario recibe un aviso de error. El PDF y la impresión siguen funcionando.

**Recomendación**

1. Habilitar `extension=gd` en `php.ini`.
2. Reiniciar Apache.
3. Verificar con `php -m | findstr gd`.
4. Añadir una comprobación en el panel de salud o en la instalación.
5. Si no se puede habilitar GD, ocultar el botón y documentar la limitación.

**Prioridad:** alta (funcionalidad anunciada).

---

#### AL-06 — Documentación desactualizada en puntos de seguridad

**Evidencia**

- El `README.md` dice “Los 34 archivos PHP pasan `php -l`”; el conteo real es **40**.
- El README afirma que los formularios rechazados “nunca” conservan el token CSRF; sin embargo:
  - `ProductosController` guarda `$_POST` completo en `old_producto`.
  - `ClientesController` guarda `$_POST` completo en `old_cliente`.
  - `TasasController` guarda `$_POST` completo en `old_tasa`.
- El README menciona usuarios `mcajeraw`, `mcajera` y `cajero2`; el SQL solo crea `admin` y `mcajera`.

**Impacto**

La documentación transmite una garantía de seguridad que el código no cumple y dificulta la operación (conteos y cuentas incorrectas).

**Recomendación**

1. Corregir el conteo y los nombres.
2. Ajustar la afirmación sobre CSRF o corregir el código (ver ME-01).
3. Añadir una sección “Última verificación” con fecha, entorno y comandos.
4. Revisar el README en cada release.

**Prioridad:** alta (por la afirmación de seguridad).

---

#### AL-07 — Compatibilidad de PHP declarada incorrectamente

**Evidencia**

- El `README.md` indica “PHP 7.4 o superior”.
- `FacturacionController.php` y `UsuariosController.php` usan la expresión `match`, introducida en **PHP 8.0**.
- El proyecto se probó con PHP **8.3.36**.

**Impacto**

Un servidor con PHP 7.4 produciría un error de sintaxis al cargar los controladores y el sistema no funcionaría. La instalación podría fallar sin un mensaje claro.

**Recomendación**

1. Declarar PHP **8.0+** como requisito mínimo (recomendado 8.2/8.3).
2. Añadir una comprobación de versión en `config/bootstrap.php` o en el instalador.
3. Actualizar el README y el manual de instalación.
4. Si se necesita PHP 7.4, sustituir `match` por `switch`/`if`.

**Prioridad:** alta (bloquea instalaciones).

---

#### ME-01 — Los formularios guardan el token CSRF en sesión

**Evidencia**

```php
// ProductosController.php
$_SESSION['old_producto'] = $_POST;

// ClientesController.php
$_SESSION['old_cliente'] = $_POST;

// TasasController.php
$_SESSION['old_tasa'] = $_POST;
```

`$_POST` incluye `csrf`. `UsuariosController` sí lo elimina:

```php
unset($old['password'], $old['csrf']);
```

**Impacto**

El token CSRF queda almacenado en la sesión del servidor y se reinyecta en el formulario. No es una vulnerabilidad crítica por sí sola, pero contradice la documentación, aumenta la exposición innecesaria y dificulta la rotación de tokens.

**Recomendación**

Guardar solo los campos del formulario, con una lista blanca:

```php
$_SESSION['old_producto'] = [
    'codigo_qr_barras' => $_POST['codigo_qr_barras'] ?? '',
    'nombre_producto'  => $_POST['nombre_producto'] ?? '',
    // ...
];
```

Nunca guardar `csrf`, contraseñas ni tokens.

**Prioridad:** media.

---

#### ME-02 — Política de contraseñas débil

**Evidencia**

- `UsuariosController::validarClave()` exige mínimo **6** caracteres y máximo 72, sin complejidad.
- `tools/crear_admin.php` exige mínimo **8** para ADMIN.
- El login no exige cambio de contraseña en el primer ingreso.

**Impacto**

Contraseñas como `123456` son aceptadas. Combinado con la ausencia de rate limiting, el riesgo de fuerza bruta aumenta.

**Recomendación**

1. Exigir mínimo 12 caracteres para ADMIN y 10 para CAJERO.
2. Validar contra listas de contraseñas comunes.
3. Permitir frases de paso y no imponer reglas de composición obsoletas.
4. Forzar cambio en el primer inicio y tras un reseteo.
5. Considerar MFA para ADMIN.

**Prioridad:** media.

---

#### ME-03 — El mensaje de login permite enumerar usuarios inactivos

**Evidencia**

`LoginController.php`:

```php
'Usuario o contraseña incorrectos, o el usuario está inactivo.'
```

**Impacto**

Un atacante puede distinguir si un usuario existe pero está inactivo, o al menos obtener una pista adicional. No revela la contraseña, pero facilita la enumeración.

**Recomendación**

Usar un mensaje genérico: “Usuario o contraseña incorrectos.” y registrar el motivo real solo en la bitácora.

**Prioridad:** media.

---

#### ME-04 — Corregir una tasa no actualiza el usuario que la registró

**Evidencia**

`TasaModel::actualizar()` actualiza `tasa_bcv_ves`, `fecha_vigencia` y `observacion`, pero no `id_usuario`. La pantalla muestra “Registrada por” con el usuario original.

**Impacto**

La atribución de una corrección es incorrecta; no se sabe quién modificó la tasa. Es un problema de auditoría.

**Recomendación**

Añadir `id_usuario_actualiza` y `fecha_actualizacion`, o registrar la corrección en la bitácora con el usuario de la sesión.

**Prioridad:** media.

---

#### ME-05 — Sin pruebas automatizadas ni CI

**Evidencia**

No hay carpeta `tests/`, `phpunit.xml`, `composer.json` ni pipeline. La única verificación es `php -l`.

**Impacto**

Los cambios en facturación, stock o permisos pueden romper reglas críticas sin detección temprana. El README describe muchas pruebas manuales, pero no son reproducibles.

**Recomendación**

1. Añadir PHPUnit para modelos y helpers.
2. Crear pruebas de integración con una base de datos de prueba.
3. Probar: emisión, anulación, stock, IVA/IGTF, permisos, CSRF, login, CSV.
4. Configurar un pipeline (GitHub Actions u otro) con `php -l` y pruebas.
5. Añadir pruebas de regresión para los hallazgos corregidos.

**Prioridad:** media.

---

#### ME-06 — Sin estrategia de respaldos automatizados

**Evidencia**

No hay scripts de respaldo, tareas programadas ni documentación operativa de restauración.

**Impacto**

Un fallo de disco, un error humano o el script destructivo pueden causar pérdida total de datos.

**Recomendación**

1. Programar `mysqldump` diario con retención (7 diarios, 4 semanales, 12 mensuales).
2. Respaldar también `config/` y los archivos de facturas si se conservan.
3. Probar la restauración periódicamente.
4. Guardar copias fuera del servidor.
5. Documentar el procedimiento en el manual de instalación.

**Prioridad:** media.

---

#### ME-07 — CSP permite estilos inline y no hay `upgrade-insecure-requests`

**Evidencia**

```php
"style-src 'self' 'unsafe-inline'; script-src 'self'"
```

**Impacto**

`'unsafe-inline'` en estilos reduce la protección de la CSP, aunque el riesgo principal sería un ataque de inyección de estilos. No hay `upgrade-insecure-requests`, útil cuando se migre a HTTPS.

**Recomendación**

1. Mover los estilos inline a `estilos.css`.
2. Eliminar `'unsafe-inline'` de `style-src`.
3. Añadir `upgrade-insecure-requests` al migrar a HTTPS.
4. Mantener `script-src 'self'` sin `unsafe-inline`.

**Prioridad:** media.

---

#### BA-01 — La tasa “vigente” puede incluir una tasa futura

**Evidencia**

`TasaModel::vigente()` ordena por `fecha_vigencia DESC` sin filtrar `fecha_vigencia <= NOW()`. La validación permite hasta 5 minutos en el futuro (`now +5 minutes`).

**Impacto**

Una tasa registrada para dentro de 3 minutos se usa antes de tiempo. El impacto es menor, pero puede confundir la operación.

**Recomendación**

Añadir `WHERE fecha_vigencia <= NOW()` a `vigente()` y eliminar la tolerancia de 5 minutos o reducirla.

**Prioridad:** baja.

---

#### BA-02 — El servidor embebido de PHP no es apto para producción

**Evidencia**

El README documenta `php -S` para desarrollo. Es correcto, pero no se advierte con suficiente claridad que es monohilo y no debe usarse en producción.

**Impacto**

Si se publica con `php -S`, el rendimiento y la concurrencia serán limitados.

**Recomendación**

Usar Apache/Nginx con PHP-FPM en producción y dejar `php -S` solo para desarrollo.

**Prioridad:** baja.

---

#### BA-03 — URLs legacy y código de vista no usado

**Evidencia**

- El router mantiene redirecciones para `productos.php`, `facturacion.php?accion=...`, etc.
- `app/vistas/productos/index.php` tiene una rama `if (!is_admin())` que nunca se ejecuta porque `ProductosController` exige ADMIN.

**Impacto**

Deuda técnica y confusión; no es un riesgo funcional.

**Recomendación**

Mantener las redirecciones mientras haya enlaces externos, pero documentar su fecha de retiro. Eliminar o simplificar el código muerto.

**Prioridad:** baja.

---

#### BA-04 — Conteo y nombres desactualizados en el README

**Evidencia**

- “34 archivos PHP” vs. 40 reales.
- Usuarios mencionados que no existen en el SQL.
- “CSRF nunca se conserva” vs. tres controladores que lo conservan.

**Impacto**

Documentación engañosa.

**Recomendación**

Actualizar el README o reemplazarlo por los manuales de `docs/`.

**Prioridad:** baja.

---

#### BA-05 — Artefacto PlantUML alternativo

**Evidencia**

`docs/casos-de-uso/casos_uso_general.puml` se generó como alternativa, pero los entregables finales son los SVG generados por `generar_diagramas.py`.

**Impacto**

Puede confundir sobre cuál es la fuente de verdad.

**Recomendación**

Eliminarlo o moverlo a una carpeta `borradores/` con una nota.

**Prioridad:** baja.

---

## 6. Revisión de controles de seguridad

| Control | Estado | Observación |
|---|---|---|
| Autenticación | Correcta | `password_hash`/`password_verify`, rehash, cuentas activas. |
| Autorización | Correcta | `require_login` / `require_admin` en servidor. |
| Sesión | Buena con reservas | `strict_mode`, regeneración, `HttpOnly`, `SameSite`; falta `Secure`. |
| CSRF | Correcta | Token por sesión, `hash_equals`, rechazo de vacío. |
| XSS | Correcta | Escape con `e()` en vistas; CSP sin scripts inline. |
| Inyección SQL | Correcta | PDO preparado, sin emulación. |
| Validación de entrada | Buena | Reglas por entidad; algunas longitudes y formatos. |
| Manejo de errores | Correcto | Errores técnicos al log, mensaje genérico al usuario. |
| Auditoría | Parcial | Login, logout, admin CLI y facturas; faltan CRUD. |
| Rate limiting | Ausente | Sin límite de intentos. |
| HTTPS | Ausente | Sin TLS, HSTS ni cookie `Secure`. |
| Archivos internos | Protegidos | `public/` como raíz y `.htaccess` en carpetas internas. |
| Respaldos | No documentados | Sin automatización. |
| Dependencias | Mínimas | Sin Composer/CDN; fuentes locales. |
| Pruebas | Insuficientes | Solo lint. |

---

## 7. Plan de acción recomendado

### 7.1 Inmediato (0–7 días)

1. Cambiar las contraseñas de `admin` y `mcajera`; desactivar cuentas demo.
2. Crear un usuario MySQL dedicado y retirar `root` sin contraseña.
3. Publicar detrás de HTTPS y añadir `Secure` + HSTS.
4. Habilitar `extension=gd` y probar la descarga PNG.
5. Programar un respaldo completo antes de cualquier cambio.
6. Corregir el README en los puntos de seguridad y conteo.

### 7.2 Corto plazo (1–4 semanas)

1. Implementar rate limiting y bloqueo temporal de login.
2. Centralizar la auditoría CRUD.
3. Sustituir el borrado físico de facturas por borrado lógico/archivado.
4. Guardar solo campos permitidos en `old_*` y eliminar el CSRF de la sesión.
5. Endurecer la política de contraseñas y forzar cambio inicial.
6. Usar un mensaje de login genérico.
7. Registrar el usuario que corrige una tasa.

### 7.3 Medio plazo (1–3 meses)

1. Añadir PHPUnit y pruebas de integración.
2. Configurar CI con lint + pruebas.
3. Automatizar respaldos y probar restauración.
4. Añadir MFA para ADMIN.
5. Revisar la CSP y mover estilos inline.
6. Definir política de retención fiscal con asesoría.
7. Añadir monitorización de errores y alertas.

### 7.4 Largo plazo (3–12 meses)

1. Migraciones formales de base de datos.
2. Observabilidad (logs estructurados, métricas, trazas).
3. Plan de continuidad y recuperación ante desastres.
4. Revisión de seguridad externa antes de crecer.
5. Evaluar un framework o una capa de seguridad mantenida si el sistema crece.

---

## 8. Conclusión

El sistema tiene una base técnica ordenada y varias decisiones acertadas: raíz web aislada, PDO preparado, CSRF, escape de salida, sesión estricta, autorización en servidor y facturación transaccional. Para un proyecto pequeño es un punto de partida sólido.

Sin embargo, **no está listo para producción tal como se entrega**: las credenciales demo, la conexión como `root` sin contraseña, la ausencia de HTTPS y de rate limiting, la auditoría parcial y el script SQL destructivo son riesgos que deben resolverse antes de exponerlo. La documentación también debe alinearse con el código.

Con las acciones inmediatas y de corto plazo de este informe, el sistema puede operar con un nivel de riesgo aceptable para un negocio pequeño, siempre con respaldos, HTTPS y una política de acceso disciplinada.

---

## 9. Anexos

### 9.1 Comandos usados en la revisión

```powershell
# Conteo y lint de archivos PHP
$files = Get-ChildItem -Recurse -Filter *.php "C:\xampp\htdocs\revisionSistema"
$files.Count

# Extensiones
C:\xampp\php\php.exe -m

# Estado de MySQL
C:\xampp\mysql\bin\mysqladmin.exe -u root ping
```

### 9.2 Archivos clave revisados

| Archivo | Rol |
|---|---|
| `public/index.php` | Front controller y rutas |
| `config/helpers.php` | Sesión, CSRF, roles, formato y cabeceras |
| `config/database.php` | Conexión PDO |
| `config/empresa.php` | Datos fiscales de la empresa |
| `app/controladores/LoginController.php` | Autenticación |
| `app/controladores/FacturacionController.php` | POS, historial, PDF/PNG, anulación |
| `app/modelos/FacturaModel.php` | Emisión, consecutivo, stock y anulación |
| `app/controladores/ProductosController.php` | Inventario |
| `app/controladores/ClientesController.php` | Clientes |
| `app/controladores/MermasController.php` | Mermas |
| `app/controladores/TasasController.php` | Tasa BCV |
| `app/controladores/UsuariosController.php` | Usuarios y roles |
| `sql/de_todito_reyes.sql` | Esquema, vistas, triggers y datos demo |
| `tools/crear_admin.php` | Recuperación de ADMIN por consola |

### 9.3 Documentos generados

- [Manual de usuario](MANUAL_USUARIO.md)
- [Manual de instalación](MANUAL_INSTALACION.md)
- [Índice de documentación](README.md)
- [Diagrama general de casos de uso](casos-de-uso/casos_uso_general.svg)
- [Casos de uso del ADMIN](casos-de-uso/casos_uso_admin.svg)
- [Casos de uso del CAJERO](casos-de-uso/casos_uso_cajero.svg)
- [Proceso de facturación](casos-de-uso/casos_uso_facturacion.svg)

---

*Revisión realizada sin ejecución del servidor MySQL. Las conclusiones funcionales se basan en lectura de código y esquema; las conclusiones de seguridad, en análisis estático y configuración del entorno.*
