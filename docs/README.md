# Documentación — Sistema De Todito Reyes

Esta carpeta reúne la documentación funcional, técnica y visual del sistema **De Todito Reyes**. Está pensada para tres públicos:

- **Usuarios y cajeros:** manual de usuario con capturas reales de cada módulo.
- **Responsables de instalación y soporte:** manual de instalación con capturas de phpMyAdmin, login y verificación.
- **Auditores o responsables técnicos:** revisión técnica, hallazgos y diagramas de casos de uso.

Todos los documentos están en español y usan como referencia el entorno Windows + XAMPP descrito en el manual de instalación.

---

## 1. Contenido de la carpeta

| Archivo o carpeta | Tipo | Para qué sirve |
|---|---|---|
| [`MANUAL_USUARIO.md`](MANUAL_USUARIO.md) | Markdown | Operación diaria: acceso, roles, clientes, productos, POS, pago mixto, comprobantes, historial, tasas, mermas, períodos fiscales y usuarios. Incluye 18 figuras. |
| [`MANUAL_INSTALACION.md`](MANUAL_INSTALACION.md) | Markdown | Requisitos, instalación con XAMPP, importación de la base, configuración, publicación con Apache, migraciones, respaldos, seguridad y solución de problemas. Incluye 7 figuras. |
| [`REVISION_TECNICA.md`](REVISION_TECNICA.md) | Markdown | Revisión del código y la configuración: hallazgos por severidad, evidencias, fortalezas, hoja de ruta y conclusión. |
| [`index.html`](index.html) | HTML | Documento consolidado para lectura en pantalla e impresión. Incluye los tres manuales, las capturas y los cuatro diagramas SVG embebidos. |
| [`capturas/`](capturas/) | PNG | Capturas reales del sistema usadas en los manuales y en `index.html`. |
| [`casos-de-uso/`](casos-de-uso/) | SVG y Python | Diagramas de casos de uso y el script que los genera. |
| [`generar_capturas.py`](generar_capturas.py) | Python | Genera las capturas con Playwright y Google Chrome. |
| [`generar_html.py`](generar_html.py) | Python | Convierte los manuales Markdown y los diagramas SVG en `index.html`. |

### Documentos relacionados del proyecto

- `../README.md`: descripción general del sistema.
- `../sql/de_todito_reyes.sql`: esquema completo y datos de ejemplo (15 tablas, 4 vistas y 4 triggers).
- `../sql/migracion_f14_f16_f17_f21.sql`: migración para bases existentes (pagos, períodos, secuencias y anulación).
- `../config/database.php` y `../config/empresa.php`: configuración local.
- `../tools/crear_admin.php`: recuperación de ADMIN por consola.

---

## 2. Roles del sistema

Los manuales y los diagramas diferencian explícitamente los dos roles:

| Rol | Alcance | Capturas representativas |
|---|---|---|
| **ADMIN** | Control total: productos, precios, inventario, usuarios, tasas, períodos fiscales, anulación y eliminación de facturas, mermas y exportación CSV. | `01_panel_admin.png`, `02_productos_admin.png`, `09_usuarios_admin.png`, `21_periodos_admin.png` |
| **CAJERO** | Operación de caja: ventas, registro de clientes, consulta de sus propias facturas y productos en modo lectura. No registra mermas, no exporta CSV, no anula ni elimina facturas y no ve Períodos, Merma, Tasas ni Usuarios. | `10_panel_cajero.png`, `11_clientes_cajero.png`, `12_pos_cajero.png`, `19_productos_cajero.png` |

Las restricciones no dependen de ocultar botones: el servidor valida el rol en cada ruta y en cada acción sensible. Las capturas muestran esa diferencia en el menú y en los botones disponibles.

---

## 3. Capturas incluidas

Las capturas se generaron con datos de demostración y reflejan la interfaz real del sistema. La mayoría mide **1680 × 1000 px**; las páginas completas y los comprobantes tienen alturas distintas.

### 3.1 Acceso y paneles

| Archivo | Qué muestra | Se usa en |
|---|---|---|
| `00_login.png` | Formulario de inicio de sesión con usuario y contraseña. | Manual de usuario, figura 1; manual de instalación, figura 3. |
| `01_panel_admin.png` | Panel principal del ADMIN: ventas, inventario, stock bajo, mermas, tasa BCV y últimas facturas. | Manual de usuario, figura 2; manual de instalación, figura 5. |
| `10_panel_cajero.png` | Panel «Mi panel» del CAJERO, limitado a su actividad. | Manual de usuario, figura 3; manual de instalación, figura 6. |

### 3.2 Módulos operativos

| Archivo | Qué muestra | Se usa en |
|---|---|---|
| `02_productos_admin.png` | Formulario de alta y listado de productos con costo, precio, IVA y stock. | Manual de usuario, figura 6. |
| `03_clientes_admin.png` | Clientes vistos por el ADMIN, con botones Editar y Eliminar. | Manual de usuario, figura 4. |
| `04_historial_ventas.png` | Historial de ventas del ADMIN con tarjetas, filtros y exportaciones. | Manual de usuario, figura 13. |
| `05_pos.png` | Punto de venta del ADMIN con catálogo paginado, carrito y resumen de totales. | Manual de usuario, figura 8. |
| `06_comprobante_admin.png` | Comprobante fiscal con desglose de pagos, PDF, imagen, impresión y anulación con motivo. | Manual de usuario, figura 11. |
| `07_tasas_admin.png` | Tasas BCV con formulario de registro, historial y acciones de corrección. | Manual de usuario, figura 15; manual de instalación, figura 7. |
| `08_mermas_admin.png` | Merma del ADMIN con costo unitario y pérdida. | Manual de usuario, figura 16. |
| `09_usuarios_admin.png` | Gestión de usuarios, roles, contraseñas y estados. | Manual de usuario, figura 18; manual de instalación, figura 4. |
| `20_pos_mixto.png` | Pago mixto: porción en divisa, IGTF calculado y resto en bolívares. | Manual de usuario, figura 10. |
| `21_periodos_admin.png` | Períodos fiscales con un mes cerrado, su usuario de cierre y la acción Reabrir. | Manual de usuario, figura 17. |

### 3.3 Restricciones del CAJERO

| Archivo | Qué muestra | Se usa en |
|---|---|---|
| `11_clientes_cajero.png` | Clientes sin botones de edición ni eliminación. | Manual de usuario, figura 5. |
| `12_pos_cajero.png` | El mismo POS operado por un CAJERO. | Manual de usuario, figura 9. |
| `13_comprobante_cajero.png` | Comprobante sin botones de anulación ni eliminación. | Manual de usuario, figura 12. |
| `14_historial_cajero.png` | Historial limitado a las facturas propias y sin exportación CSV. | Manual de usuario, figura 14. |
| `19_productos_cajero.png` | Productos en modo consulta: sin costo ni botones de administración. | Manual de usuario, figura 7. |

### 3.4 Instalación y base de datos

| Archivo | Qué muestra | Se usa en |
|---|---|---|
| `17_phpmyadmin_importar.png` | Pestaña Importar de phpMyAdmin con la base seleccionada. | Manual de instalación, figura 1. |
| `18_phpmyadmin_estructura.png` | Estructura de la base con las 15 tablas y las 4 vistas creadas por el script. | Manual de instalación, figura 2. |

> Las capturas contienen datos de demostración (`admin`, `mcajera`, «Bodega La Esquina, C.A.», «Juan Pérez»). Si la documentación se va a distribuir fuera del negocio, regenéralas con datos anonimizados.

---

## 4. Cómo regenerar los entregables

### 4.1 Requisitos

- Windows con XAMPP instalado en `C:\xampp`.
- MySQL/MariaDB iniciado y la base `de_todito_reyes` importada.
- PHP 8.0 o superior.
- Python 3.10 o superior.
- Para las capturas: Playwright para Python y Google Chrome instalados.
- Para las capturas de phpMyAdmin: Apache iniciado en el puerto 80.

Instala Playwright si aún no está disponible:

```bat
python -m pip install playwright
python -m playwright install chrome
```

### 4.2 Levantar el sistema para las capturas

En dos terminales distintas:

```bat
:: Terminal 1: MySQL (si no está iniciado desde el Panel de XAMPP)
C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone

:: Terminal 2: servidor PHP de desarrollo
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t "C:\xampp\htdocs\revisionSistema\public"
```

Comprueba que responde:

```text
http://127.0.0.1:8000/
```

Para las capturas de phpMyAdmin, inicia Apache desde el Panel de XAMPP y abre:

```text
http://localhost/phpmyadmin/
```

### 4.3 Regenerar las capturas

Desde la carpeta `docs/`:

```bat
cd /d "C:\xampp\htdocs\revisionSistema\docs"
python generar_capturas.py
```

El script:

1. Abre Chrome en modo headless con un viewport de **1680 × 1000**.
2. Inicia sesión como `admin` / `admin123` y captura los módulos del ADMIN.
3. Prepara un carrito con dos productos y captura el POS normal y el **pago mixto**.
4. Abre la factura `000002` (emitida con pago mixto) para mostrar el desglose de pagos y la anulación con motivo.
5. Cierra enero en **Períodos** para capturar el estado `Cerrado` y el botón **Reabrir**; después lo reabre para no dejar el mes bloqueado.
6. Cierra la sesión, inicia como `mcajera` / `cajera123` y captura las vistas del CAJERO.
7. Captura la importación y la estructura de la base en phpMyAdmin.
8. Guarda todo en `docs/capturas/`.

Notas técnicas:

- Las capturas de página completa aplican temporalmente `.sidebar { position: static; height: auto; }` para que la barra lateral acompañe todo el contenido.
- La captura del login se recorta al componente `.login-card` para evitar una imagen con demasiado espacio vacío.
- Los comprobantes se capturan desde el elemento `main` para incluir las acciones y el documento fiscal.
- El script acepta los diálogos `window.confirm` de las acciones destructivas y del cierre de período.
- La captura `21_periodos_admin.png` requiere que la factura `000002` exista y que el período de enero pueda cerrarse y reabrirse. Si el entorno no lo permite, el script guarda la vista sin el estado cerrado y lo informa en la consola.
- Si cambias los datos de demostración, las capturas cambiarán. Revisa que sigan siendo representativas.

### 4.4 Regenerar los diagramas de casos de uso

Desde la carpeta `docs/`:

```bat
python casos-de-uso\generar_diagramas.py
```

Genera o actualiza:

- `casos-de-uso/casos_uso_general.svg`
- `casos-de-uso/casos_uso_admin.svg`
- `casos-de-uso/casos_uso_cajero.svg`
- `casos-de-uso/casos_uso_facturacion.svg`

Los diagramas usan azul para el ADMIN, verde para el CAJERO, violeta para `<<include>>` y naranja para `<<extend>>`.

### 4.5 Regenerar `index.html`

Desde la carpeta `docs/`:

```bat
python generar_html.py
```

El generador:

- Lee `MANUAL_USUARIO.md`, `MANUAL_INSTALACION.md` y `REVISION_TECNICA.md`.
- Convierte encabezados, tablas, listas, bloques de código, citas y enlaces.
- Convierte las imágenes `![...](capturas/...)` en figuras con pie de foto.
- Embebe cada PNG y cada SVG como **data URI** en base64, de modo que `index.html` funciona sin la carpeta `capturas/` al lado.
- Genera el índice lateral, la portada y las secciones imprimibles.

El archivo resultante pesa varios megabytes por las imágenes embebidas. Es normal.

### 4.6 Generar un PDF para imprimir

Con Google Chrome instalado:

```bat
"C:\Program Files\Google\Chrome\Application\chrome.exe" --headless=new --disable-gpu --no-pdf-header-footer --print-to-pdf="C:\xampp\htdocs\revisionSistema\docs\_impresion.pdf" "file:///C:/xampp/htdocs/revisionSistema/docs/index.html"
```

El CSS de impresión usa A4, saltos de página antes de cada documento y de cada diagrama, y evita cortar tablas, bloques de código y figuras. Las capturas se ajustan al ancho de la página y conservan su pie de foto.

---

## 5. Notas y limitaciones

- **GD desactivada:** el botón **Descargar imagen** del comprobante no funciona hasta habilitar `extension=gd` en `C:\xampp\php\php.ini`. El manual de instalación explica cómo activarla. Las capturas del comprobante se tomaron desde la vista HTML, no desde el PNG.
- **Objetos en phpMyAdmin:** el esquema actual tiene **15 tablas y 4 vistas** (19 objetos). La captura `18_phpmyadmin_estructura.png` muestra el estado real de la base de demostración. Si tu base conserva vistas de versiones anteriores, el número puede ser mayor; no afecta al sistema.
- **Tasa desactualizada en las capturas:** las imágenes muestran la tasa vigente del entorno de demostración y la fecha en que se generaron. Es el estado real de ese entorno; no es un error del manual.
- **Datos de ejemplo:** las facturas, clientes y mermas visibles son datos de demostración. No incluyen información real de clientes.
- **Numeración de figuras:** cada manual numera sus figuras de forma independiente. Si agregas o quitas una captura, actualiza los números y las referencias del texto.
- **Capturas y cambios de interfaz:** si se modifica el diseño, los textos o los botones del sistema, vuelve a ejecutar `generar_capturas.py` y `generar_html.py` para que la documentación no quede desactualizada.
- **Capturas obsoletas:** las vistas de Merma y Tasas para CAJERO se retiraron porque esos módulos ya no están disponibles para ese rol. No las vuelvas a agregar sin cambiar antes los permisos del sistema.

---

## 6. Estructura de la carpeta

```text
docs/
├── README.md                  ← este índice
├── MANUAL_USUARIO.md
├── MANUAL_INSTALACION.md
├── REVISION_TECNICA.md
├── index.html                 ← documento consolidado
├── generar_capturas.py
├── generar_html.py
├── capturas/
│   ├── 00_login.png
│   ├── 01_panel_admin.png
│   ├── ...
│   ├── 20_pos_mixto.png
│   └── 21_periodos_admin.png
└── casos-de-uso/
    ├── generar_diagramas.py
    ├── casos_uso_general.svg
    ├── casos_uso_admin.svg
    ├── casos_uso_cajero.svg
    └── casos_uso_facturacion.svg
```

---

*Documentación del Sistema De Todito Reyes. Para dudas sobre la instalación, consulta el manual de instalación; para la operación diaria, el manual de usuario.*
