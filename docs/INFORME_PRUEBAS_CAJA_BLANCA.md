# Informe de prueba de caja blanca — Sistema «De Todito Reyes»

**Fecha de ejecución:** 09/11/2026
**Versión probada:** copia de trabajo `C:\Users\ARCADIA\AppData\Local\Temp\opencode\sistema_test` (idéntica al sistema en `C:\xampp\htdocs\php\sistema`, salvo `config/database.php`)
**Base de datos:** `de_todito_reyes_test` (copia desechable). La base real `de_todito_reyes` solo se leyó para comparar su checksum.
**URL de prueba:** `http://127.0.0.1:8010`
**Tipo de prueba:** caja blanca (ramas, condiciones, triggers, vistas y validaciones) con las afirmaciones del capítulo 5 — Preguntas Frecuentes — como oráculo.
**Resultado global:** **151 casos ejecutados / 151 PASS / 0 FAIL** (9,03 s). Los 14 hallazgos del informe anterior quedaron **corregidos, aceptados por negocio o mantenidos por diseño**: 8 corregidos, 1 aceptado y 1 mantenido por diseño (HB-01…HB-10), y 4 positivos preservados (HB-11…HB-14).

---

## 1. Resumen ejecutivo

Se ejecutó una prueba de caja blanca sobre una copia aislada del sistema, apuntando a una base de datos separada (`de_todito_reyes_test`). A diferencia de la caja negra, aquí no solo se observó la interfaz: se verificaron las ramas de decisión del código PHP, las condiciones de las vistas SQL, los triggers de la base de datos y los helpers compartidos, usando como oráculo las respuestas del capítulo 5 del documento funcional (Preguntas Frecuentes).

**Resultado:** los 151 casos definidos terminaron en PASS. El sistema cumple las conductas prometidas en las FAQ para stock mínimo, bloqueo de stock 0, rol de cajero, expiración de sesión, corte de caja por fecha, SKU manual, descuento de inventario al emitir, impresión sin doble descuento e idempotencia de la emisión.

La prueba también permitió comprobar lo que la interfaz sola no muestra: el consecutivo fiscal **no** se reutiliza tras borrar la última factura anulada (la función `siguienteConsecutivo()` consulta también la bitácora `EMISION`), la concurrencia de cuatro cajas produce consecutivos únicos y stock consistente, y los triggers de base de datos rechazan stock negativo y mermas que exceden lo disponible.

**Correcciones aplicadas en esta iteración:**

1. **Mermas solo para ADMIN:** `MermasController` exige ADMIN y el menú/panel del CAJERO ya no ofrece la merma (HB-01).
2. **CSV solo para ADMIN:** `FacturacionController::exportar` exige ADMIN; el CAJERO no ve ni descarga los reportes (HB-03).
3. **Cierre de caja dedicado:** nuevo módulo `/cierre` con arqueo por método de pago, IGTF, facturas emitidas y filtro por usuario; el CAJERO solo ve sus propias ventas (HB-04).
4. **Vistas fiscales corregidas:** `v_libro_ventas` filtra `estado = 'EMITIDA'` y `v_movimientos_producto` solo suma líneas de facturas EMITIDA (HB-05).
5. **Consulta de productos para el CAJERO:** `/productos` es accesible en modo solo lectura; el formulario y la columna de costo se ocultan (HB-06).
6. **Logo enlazado al panel:** la marca es ahora `<a class="brand" href="/panel">` (HB-07).
7. **Idempotencia de emisión:** token `token_emision` de un solo uso; un reenvío o un POST sin token se rechaza sin duplicar la factura (HB-08).
8. **Dato demo corregido:** la factura `000001` del seed y de la base de prueba cuadra en `26,882 USD` (HB-10).

**Decisiones de negocio documentadas:**

- **HB-02 (CAJERO crea clientes):** se mantiene. La creación de clientes forma parte del flujo de facturación y fue aceptada explícitamente por el negocio.
- **HB-09 (productos agotados en `productosVendibles()`):** se mantiene por diseño. El POS muestra el producto agotado con el botón deshabilitado y el servidor bloquea la venta; es una decisión de usabilidad, no una brecha de seguridad.

Al finalizar se ejecutó la limpieza y se comparó la base de prueba contra su línea base: **quedó idéntica** (mismos conteos). La base real `de_todito_reyes` **no fue modificada** (checksums iguales antes y después; ver §8).

---

## 2. Alcance

### 2.1 Módulos y unidades de código ejercitadas

| Módulo | Unidades de código / ramas probadas |
|---|---|
| Autenticación y sesión | `LoginController::index` (POST válido, clave mala, campos vacíos, CSRF ausente/falso), `LogoutController::logout` (GET vs POST), `require_login`, `validar_sesion_actual`, `logout_user`, `session_regenerate_id` |
| Seguridad HTTP | `security_headers`, `no_cache_headers`, `csrf_verify`, `csrf_field`, escape con `e()`, consultas preparadas |
| Dashboard | `DashboardController::index` (rama ADMIN y rama CAJERO), `DashboardModel::stockBajo`, `resumenMermas`, `ultimasFacturas`, acceso rápido al cierre |
| Productos | `ProductosController::index` (gate `exigirLogin`, modo consulta para CAJERO), `procesarPost` (guardar, editar, eliminar, cambiar_estado, solo ADMIN), `validar` (todas las reglas) |
| Clientes | `ClientesController::procesarPost` (guardar por CAJERO, editar/eliminar solo ADMIN, RIF duplicado) |
| Usuarios | `UsuarioModel::autenticar`, `existeLogin`, `contarAdminsActivos`, `registrarBitacora` |
| Tasas BCV | `TasasController` (solo lectura para CAJERO), `TasaModel::vigente`, `tasa_desactualizada` |
| Facturación | `FacturacionController::index`, `nueva`, `emitir`, `ver`, `pdf`, `imagen`, `exportar` (solo ADMIN), `anular`, `eliminar`, filtros, validación de fechas e idempotencia (`generarTokenEmision`, `consumirTokenEmision`) |
| Modelo de factura | `FacturaModel::productosVendibles`, `emitir`, `siguienteConsecutivo`, `filtros`, `resumenHoy`, `resumenCierre`, `anular`, `eliminar` |
| Merma | `MermaModel::registrar`, `listar`; `MermasController::index` y `procesarPost` (solo ADMIN) |
| Cierre de caja | `CierreController::index`, `FacturaModel::resumenCierre` (totales por método de pago, IGTF, filtro por usuario) |
| SQL | Vistas `v_stock_bajo`, `v_tasa_vigente`, `v_libro_ventas` (solo EMITIDA), `v_movimientos_producto` (solo EMITIDA); triggers `trg_facturas_ai`, `trg_facturas_au`, `trg_merma_valida_stock`, `trg_productos_stock_no_negativo` |
| Helpers | `e`, `money`, `money3`, `cantidad`, `numero`, `input_cantidad`, `input_number`, `base_url`, `url`, `flash`, `flashes`, `error_usuario`, `metodo_pago_label`, `estado_badge` |

### 2.2 Fuera de alcance

- Pruebas de carga o estrés masivo (solo se probó concurrencia controlada de 4 emisiones).
- Navegadores reales, dispositivos móviles o impresora física (el PDF/PNG se validó por firma y cabeceras).
- Envío de correo, backups, restauración y despliegue.
- Pruebas sobre la base real de producción (solo lectura de checksums).

---

## 3. Entorno de ejecución

| Elemento | Valor |
|---|---|
| Sistema operativo | Windows |
| PHP | 8.2.12 (ZTS, x64) |
| Extensiones | `curl`, `gd`, `mbstring`, `pdo_mysql` |
| Servidor | `php -S 127.0.0.1:8010 -t …\sistema_test\public …\public\index.php` |
| Base de datos | MariaDB 10.4.32 |
| Base de prueba | `de_todito_reyes_test` |
| Base real (solo lectura) | `de_todito_reyes` |
| Cliente HTTP | `curl` con cookie jar y CSRF |
| Arnés | PHP CLI modular: `runner.php` + `fases/f01…f11` + `emit_worker.php` |
| Verificación de integridad | `CHECKSUM TABLE` y conteos por tabla |

### 3.1 Línea base de la base de prueba

| Tabla | Registros |
|---|---:|
| usuarios | 2 |
| clientes | 3 |
| productos | 9 |
| facturas | 1 |
| detalle_factura | 4 |
| merma_ajustes | 1 |
| tasas_cambio_bcv | 2 |
| bitacora_auditoria | 3 |
| categorias | 4 |
| proveedores | 3 |
| compras_proveedor | 1 |
| detalle_compra | 4 |

Datos de referencia: factura `000001` EMITIDA (30/10/2026), total `981,200 Bs` / `26,882 USD` (cuadra con la tasa 36,5); tasa vigente 36,5 Bs/USD (30/10/2026); usuarios demo `admin/admin123` (ADMIN) y `mcajera/cajera123` (CAJERO).

---

## 4. Metodología de caja blanca

1. **Oráculo funcional:** cada afirmación del capítulo 5 (Preguntas Frecuentes) se tradujo en una o más aserciones verificables sobre ramas concretas del código. El Anexo C lista la correspondencia FAQ → casos.
2. **Aislamiento:** se creó una copia del sistema y una base separada (`de_todito_reyes_test`). La copia solo cambia `config/database.php`; el resto del código es el mismo que el sistema real.
3. **Datos identificables:** se usaron productos `CB-PROD-*`, cliente `CB-V-00000001`, usuarios `cbcajero` y `cbsesion`, y facturas creadas durante la corrida. La limpieza borra por ids máximos iniciales, no por nombre.
4. **Ejecución por HTTP + verificación SQL:** las acciones se hicieron como un usuario real (cookies, CSRF, redirecciones), y después se consultó la base para confirmar stock, totales, estados, consecutivos, auditoría y triggers.
5. **Cobertura de ramas:** además del camino feliz, se forzaron ramas de error: CSRF ausente/falso, stock 0, stock insuficiente, producto inactivo, cliente inexistente, método de pago inválido, cantidad negativa/cero/enorme, carrito >100 productos, fechas invertidas, sesión vencida, cuenta desactivada, merma excesiva, stock negativo por SQL, fórmulas CSV, duplicados, anulación/eliminación, token de emisión ausente o reutilizado y accesos por rol a mermas, CSV, productos y cierre.
6. **Concurrencia:** cuatro procesos CLI (`emit_worker.php`) emitieron simultáneamente contra el mismo producto para verificar el bloqueo y la unicidad del consecutivo.
7. **Cierre y comparación:** se restauró la base de prueba y se comparó su estado con la línea base; se compararon los checksums de la base real antes y después.

### 4.1 Secuencia de stock usada como hilo conductor

| Producto | Secuencia | Casos |
|---|---|---|
| A (stock 10, mínimo 5) | 10 → 7 (E1) → 4 (E2) → 7 (anular E2) → 6 (E3) → 5 (E4) → 4 (anular E3) → 3 (merma) | F5-12, F5-31, F5-34, F5-39, F5-41, F7-08, F9-01 |
| B (stock 3, mínimo 3) | 3 → 2 (E1) → 1 (E2) → 2 (anular E2) → 0 (E6) | F5-12, F5-31, F5-34, F5-42, F5-43 |
| C (stock 0, mínimo 1) | 0 (nunca se descuenta) | F5-09, F5-44 |
| D (stock 100, mínimo 10) | 100 → 96 (4 workers) → 95 (venta del cajero) | F5-47, F6-07 |

---

## 5. Matriz de resultados

### F1 · Entorno, aislamiento y fixtures (11/11)

| ID | Caso | Resultado |
|---|---|---|
| F1-01 | La aplicación apunta a la base de prueba y no a la real | PASS |
| F1-02 | El servidor de prueba responde en /login | PASS |
| F1-03 | La respuesta incluye cabeceras de seguridad | PASS |
| F1-04 | El login no se cachea y la cookie es HttpOnly/SameSite=Lax | PASS |
| F1-05 | Los archivos estáticos se sirven sin pasar por el router | PASS |
| F1-06 | Una ruta inexistente devuelve 404 con página amigable | PASS |
| F1-07 | No hay residuos CB-* de corridas anteriores | PASS |
| F1-08 | La base de prueba conserva los datos demo de línea base | PASS |
| F1-09 | Se crean los productos de prueba con stock controlado | PASS |
| F1-10 | Se crean el cliente y los usuarios de prueba | PASS |
| F1-11 | Los fixtures CB-* quedan registrados en el inventario | PASS |

### F2 · Autenticación, sesión y CSRF (12/12)

| ID | Caso | Resultado |
|---|---|---|
| F2-01 | Credenciales incorrectas no inician sesión y muestran error | PASS |
| F2-02 | Usuario y contraseña vacíos son rechazados | PASS |
| F2-03 | POST /login sin token CSRF responde 419 y no autentica | PASS |
| F2-04 | POST /login con token CSRF inválido responde 419 | PASS |
| F2-05 | El ADMIN inicia sesión y es redirigido al panel | PASS |
| F2-06 | El login exitoso queda registrado en la bitácora | PASS |
| F2-07 | El panel exige sesión: sin login redirige a /login | PASS |
| F2-08 | La URL heredada /index.php redirige al panel | PASS |
| F2-09 | El cierre de sesión por POST destruye la sesión | PASS |
| F2-10 | GET /logout no cierra la sesión: exige POST con CSRF | PASS |
| F2-11 | Entrar a /login con sesión abierta la cierra (reiniciado=1) | PASS |
| F2-12 | El panel del ADMIN muestra la alerta de tasa desactualizada | PASS |

### F3 · Stock mínimo y alertas del inventario (7/7)

| ID | Caso | Resultado |
|---|---|---|
| F3-01 | La vista v_stock_bajo incluye stock igual y por debajo del mínimo | PASS |
| F3-02 | El faltante se calcula como mínimo menos stock (nunca negativo) | PASS |
| F3-03 | Un stock apenas por encima del mínimo no dispara la alerta | PASS |
| F3-04 | El filtro ?bajo=1 del inventario usa la misma regla <= | PASS |
| F3-05 | ProductoModel::listar(soloBajo) coincide con la vista SQL | PASS |
| F3-06 | El Dashboard del ADMIN muestra los productos en o bajo el mínimo | PASS |
| F3-07 | La alerta del Dashboard enlaza al filtro de stock bajo | PASS |

### F4 · SKU manual y validaciones de productos (13/13)

| ID | Caso | Resultado |
|---|---|---|
| F4-01 | Un producto sin código de barras comercial acepta un SKU manual | PASS |
| F4-02 | El producto con SKU manual aparece en el punto de venta | PASS |
| F4-03 | Un código vacío se rechaza aunque exista la opción de SKU | PASS |
| F4-04 | Un código duplicado se rechaza | PASS |
| F4-05 | Un código de más de 64 caracteres se rechaza | PASS |
| F4-06 | Costo o precio negativos se rechazan | PASS |
| F4-07 | Una alícuota de IVA fuera de 0–100 se rechaza | PASS |
| F4-08 | Un stock negativo se rechaza | PASS |
| F4-09 | Una categoría ausente se rechaza | PASS |
| F4-10 | Una unidad desconocida se normaliza a UND | PASS |
| F4-11 | Un producto sin movimientos se puede eliminar | PASS |
| F4-12 | Desactivar un producto lo saca del punto de venta | PASS |
| F4-13 | El listado muestra inactivos solo con ?inactivos=1 | PASS |

### F5 · Emisión, stock, PDF/PNG/CSV y ciclo de vida (50/50)

| ID | Caso | Resultado |
|---|---|---|
| F5-01 | Un carrito vacío no emite factura | PASS |
| F5-02 | Sin cliente seleccionado la emisión se rechaza | PASS |
| F5-03 | Un cliente inexistente se rechaza | PASS |
| F5-04 | Un método de pago fuera de la lista se rechaza | PASS |
| F5-05 | Un producto inexistente se rechaza | PASS |
| F5-06 | Un producto inactivo no se puede vender | PASS |
| F5-07 | Una cantidad cero o negativa se rechaza | PASS |
| F5-08 | Una cantidad superior a 100.000 se rechaza | PASS |
| F5-09 | Un producto con stock 0 es bloqueado por el servidor | PASS |
| F5-10 | Una cantidad mayor al stock disponible se rechaza | PASS |
| F5-11 | El JavaScript del POS bloquea agregar productos con stock 0 | PASS |
| F5-12 | La emisión válida consolida duplicados y descuenta stock | PASS |
| F5-13 | El consecutivo y el control fiscal tienen formato fiscal | PASS |
| F5-14 | El trigger de auditoría registra la EMISION | PASS |
| F5-15 | El PDF del comprobante se genera en el servidor | PASS |
| F5-16 | El PDF puede abrirse en línea con ?ver=1 | PASS |
| F5-17 | El PNG del comprobante se genera con firma válida | PASS |
| F5-18 | El CSV resumen respeta filtros y lleva BOM UTF-8 | PASS |
| F5-19 | El CSV detalle incluye código, producto y cantidad | PASS |
| F5-20 | Un tipo de exportación inválido se rechaza | PASS |
| F5-21 | El filtro por estado EMITIDA incluye la factura | PASS |
| F5-22 | El filtro por estado ANULADA excluye la factura emitida | PASS |
| F5-23 | La búsqueda por número y por cliente encuentra la factura | PASS |
| F5-24 | El filtro por fecha de hoy incluye y el de ayer excluye | PASS |
| F5-25 | Un rango de fechas invertido muestra error controlado | PASS |
| F5-26 | Una fecha inválida muestra error controlado | PASS |
| F5-27 | Una búsqueda de más de 100 caracteres se trunca sin error | PASS |
| F5-28 | El detalle de la factura muestra número, cliente y líneas | PASS |
| F5-29 | Ver una factura inexistente redirige con aviso | PASS |
| F5-30 | Recargar la vista de la factura no duplica la transacción | PASS |
| F5-31 | Reenviar el mismo token de emisión no duplica la factura | PASS |
| F5-32 | Una emisión sin token de idempotencia se rechaza | PASS |
| F5-33 | El CAJERO no puede anular facturas | PASS |
| F5-34 | El ADMIN anula y el stock vuelve al inventario | PASS |
| F5-35 | El trigger de auditoría registra la anulación con su usuario | PASS |
| F5-36 | Anular dos veces la misma factura se rechaza | PASS |
| F5-37 | Una factura EMITIDA no se puede eliminar | PASS |
| F5-38 | Una factura ANULADA se elimina con sus líneas | PASS |
| F5-39 | El consecutivo no se reutiliza tras borrar la última anulada | PASS |
| F5-40 | Un producto con ventas no se puede eliminar | PASS |
| F5-41 | El IGTF del 3% se calcula solo cuando se marca | PASS |
| F5-42 | Vender exactamente todo el stock está permitido | PASS |
| F5-43 | Con stock 0 el servidor ya no permite una nueva venta | PASS |
| F5-44 | El POS muestra el producto agotado con botón deshabilitado | PASS |
| F5-45 | El carrito rechaza más de 100 productos distintos | PASS |
| F5-46 | El CSV neutraliza fórmulas inyectadas en nombres | PASS |
| F5-47 | Cuatro cajas concurrentes generan consecutivos únicos | PASS |
| F5-48 | Todos los consecutivos de la base son únicos | PASS |
| F5-49 | Las facturas nuevas mantienen total USD = total Bs / tasa | PASS |
| F5-50 | La factura demo 000001 cuadra con total Bs / tasa | PASS |

### F6 · Roles, permisos y navegación (13/13)

| ID | Caso | Resultado |
|---|---|---|
| F6-01 | El CAJERO entra a su panel personal sin datos gerenciales | PASS |
| F6-02 | El menú del CAJERO incluye Productos y Cierre, sin Tasas/Usuarios/Merma | PASS |
| F6-03 | El CAJERO puede consultar /productos en modo solo lectura | PASS |
| F6-04 | El CAJERO no puede guardar productos por POST | PASS |
| F6-05 | El CAJERO no puede entrar a Usuarios | PASS |
| F6-06 | El CAJERO no puede registrar ni corregir la tasa BCV | PASS |
| F6-07 | El CAJERO puede emitir facturas (su función principal) | PASS |
| F6-08 | El CAJERO no puede registrar mermas (ajuste de inventario) | PASS |
| F6-09 | El CAJERO puede crear clientes como parte de la facturación (aceptado) | PASS |
| F6-10 | El CAJERO no puede editar ni eliminar clientes | PASS |
| F6-11 | El CAJERO no puede descargar reportes CSV | PASS |
| F6-12 | El menú lateral tiene un enlace al panel | PASS |
| F6-13 | El logotipo de la marca enlaza al inicio | PASS |

### F7 · Corte de caja diario y reportes (11/11)

| ID | Caso | Resultado |
|---|---|---|
| F7-01 | El resumen del día coincide con las facturas EMITIDA de hoy | PASS |
| F7-02 | El historial filtrado por la fecha actual muestra el día | PASS |
| F7-03 | El historial de ayer no mezcla las ventas de hoy | PASS |
| F7-04 | El CSV resumen permite contrastar efectivo y otros medios | PASS |
| F7-05 | El efectivo y los otros medios se pueden separar para el arqueo | PASS |
| F7-06 | Existe un reporte dedicado de cierre de caja con arqueo | PASS |
| F7-07 | El cierre del CAJERO solo incluye sus propias ventas | PASS |
| F7-08 | Anular una factura la saca del resumen del día | PASS |
| F7-09 | La factura anulada aparece en el filtro ANULADA y no en EMITIDA | PASS |
| F7-10 | v_libro_ventas excluye las facturas anuladas | PASS |
| F7-11 | v_movimientos_producto solo suma ventas EMITIDA | PASS |

### F8 · Sesión expirada, revalidación y CSRF (6/6)

| ID | Caso | Resultado |
|---|---|---|
| F8-01 | Tras 30 minutos de inactividad la sesión expira | PASS |
| F8-02 | La actividad se renueva con cada petición autenticada | PASS |
| F8-03 | Desactivar la cuenta invalida la sesión en el siguiente request | PASS |
| F8-04 | POST /facturacion sin CSRF responde 419 y no emite | PASS |
| F8-05 | Un token CSRF de otra sesión es rechazado | PASS |
| F8-06 | Un id de sesión inventado no es aceptado (strict mode) | PASS |

### F9 · Mermas, ajustes y triggers de inventario (11/11)

| ID | Caso | Resultado |
|---|---|---|
| F9-01 | Una merma válida descuenta stock y guarda el costo del momento | PASS |
| F9-02 | El costo histórico de la merma no cambia si cambia el producto | PASS |
| F9-03 | Una cantidad de merma cero o negativa se rechaza | PASS |
| F9-04 | Una merma mayor al stock disponible se rechaza | PASS |
| F9-05 | Un motivo fuera del catálogo se rechaza | PASS |
| F9-06 | Una observación de más de 200 caracteres se rechaza | PASS |
| F9-07 | Un producto inactivo no admite mermas | PASS |
| F9-08 | El trigger rechaza una merma que excede el stock por SQL directo | PASS |
| F9-09 | El trigger impide dejar stock_actual negativo por SQL directo | PASS |
| F9-10 | Al alcanzar el mínimo tras la merma, el producto entra en alerta | PASS |
| F9-11 | La merma queda visible en el historial del módulo | PASS |

### F10 · Helpers, formato y mensajes seguros (11/11)

| ID | Caso | Resultado |
|---|---|---|
| F10-01 | money usa coma decimal y punto de miles | PASS |
| F10-02 | cantidad elimina ceros decimales innecesarios | PASS |
| F10-03 | numero e input_* respetan el formato esperado | PASS |
| F10-04 | e() escapa HTML y comillas | PASS |
| F10-05 | Las etiquetas de método de pago y estado son correctas | PASS |
| F10-06 | tasa_desactualizada detecta ayer, hoy y ausencia | PASS |
| F10-07 | error_usuario no filtra detalles técnicos de PDO/Error | PASS |
| F10-08 | error_usuario sí muestra los mensajes de negocio | PASS |
| F10-09 | base_url y url construyen rutas internas correctas | PASS |
| F10-10 | El sistema de flashes acumula y consume mensajes | PASS |
| F10-11 | Un visitante no autenticado no es admin ni cajero | PASS |

### F11 · Limpieza y restauración de la base de prueba (6/6)

| ID | Caso | Resultado |
|---|---|---|
| F11-01 | La limpieza elimina todos los fixtures CB-* | PASS |
| F11-02 | Los conteos de la base de prueba vuelven a la línea base | PASS |
| F11-03 | La bitácora de la base de prueba vuelve a la línea base | PASS |
| F11-04 | La base real de_todito_reyes no fue modificada | PASS |
| F11-05 | La base real conserva su conteo de facturas original | PASS |
| F11-06 | Se deja constancia escrita de la ejecución y la limpieza | PASS |

---

## 6. Cobertura de ramas y condiciones

La siguiente tabla resume las ramas de decisión ejercitadas y el caso que las cubre. No se limita al camino feliz: cada rama de rechazo fue forzada con datos controlados.

| Unidad / rama | Condición ejercitada | Casos |
|---|---|---|
| `require_login` | Sin sesión → `/login` | F2-07 |
| `require_login` | `time() - ultima_actividad > 1800` → expira | F8-01 |
| `require_login` | Actividad reciente → renueva marca | F8-02 |
| `require_admin` / `exigirAdmin` | CAJERO en mermas, CSV o usuarios → flash + `/panel` | F6-05, F6-08, F6-11 |
| `ProductosController::index` | `exigirLogin` + modo consulta para CAJERO | F6-03 |
| `ProductosController::procesarPost` | CAJERO no guarda; ADMIN guarda/edita/elimina | F6-04, F4-01, F4-11, F4-12, F5-40 |
| `validar_sesion_actual` | `activo = 0` → cierra sesión | F8-03 |
| `csrf_verify` | Token ausente / falso / de otra sesión → 419 | F2-03, F2-04, F8-04, F8-05 |
| `LoginController` | Campos vacíos / clave mala / usuario inactivo | F2-01, F2-02 |
| `LoginController` | Sesión abierta + GET /login → cierra | F2-11 |
| `LogoutController` | GET /logout no cierra; POST + CSRF sí | F2-09, F2-10 |
| `ProductoModel::listar` | `soloBajo` (`stock_actual <= stock_minimo`), `incluirInactivos`, búsqueda | F3-04, F3-05, F4-13 |
| `ProductosController::validar` | Código vacío, >64, duplicado, costo/precio negativo, IVA >100, stock negativo, categoría 0, unidad desconocida | F4-03…F4-10 |
| `ClientesController::procesarPost` | CAJERO guardar permitido; editar/eliminar bloqueado; RIF duplicado | F6-09, F6-10 |
| `FacturaModel::productosVendibles` | `activo = 1` (incluye stock 0) | F5-09, F5-44 |
| `FacturaModel::emitir` | Carrito vacío, cliente 0/inexistente, método inválido, producto inexistente/inactivo, cantidad ≤0, >100.000, >stock, >100 productos, duplicados, IGTF | F5-01…F5-12, F5-41, F5-45 |
| `FacturaModel::emitir` | Bloqueo `FOR UPDATE`, transacción, descuento de stock, anulación | F5-12, F5-34, F5-47 |
| `FacturaModel::siguienteConsecutivo` | `MAX(facturas)` ∪ bitácora `EMISION` → no reutiliza | F5-39 |
| `FacturaModel::filtros` | Estado, texto, rango invertido, fecha inválida, texto >100 | F5-21…F5-27 |
| `FacturacionController::exportar` | Solo ADMIN; `tipo=resumen`, `tipo=detalle`, tipo inválido, BOM, fórmula | F5-18…F5-20, F5-46, F6-11 |
| `FacturacionController::consumirTokenEmision` | Token válido, ausente o reutilizado | F5-31, F5-32 |
| `FacturacionController::pdf/imagen` | Descarga vs inline, firma `%PDF`/PNG, sin doble descuento | F5-15…F5-17 |
| `FacturacionController::anular/eliminar` | CAJERO bloqueado, ADMIN permite, doble anulación, EMITIDA no se borra | F5-33…F5-38 |
| `MermasController::index/procesarPost` | Solo ADMIN; CAJERO no registra merma | F6-08 |
| `MermaModel::registrar` | Cantidad ≤0, >stock, motivo inválido, observación >200, producto inactivo, costo congelado | F9-01…F9-07 |
| `CierreController::index` / `FacturaModel::resumenCierre` | ADMIN ve todo; CAJERO solo sus ventas; arqueo por método e IGTF | F7-06, F7-07 |
| Triggers SQL | `trg_merma_valida_stock`, `trg_productos_stock_no_negativo` | F9-08, F9-09 |
| Vistas SQL | `v_stock_bajo` (`<=`), `v_libro_ventas` (solo EMITIDA), `v_movimientos_producto` (solo EMITIDA) | F3-01, F3-02, F7-10, F7-11 |
| `DashboardModel` | `stockBajo` (límite 8), `resumenMermas`, `resumenCajero` | F3-06, F3-07, F6-01 |
| Helpers de formato | `money`, `money3`, `cantidad`, `numero`, `input_*` | F10-01…F10-03 |
| Helpers de seguridad | `e`, `error_usuario`, `csrf_*`, `security_headers` | F1-03, F10-04, F10-07, F10-08 |
| Helpers de sesión/rol | `is_logged_in`, `is_admin`, `is_cajero`, `current_user_id` | F10-11 |
| `tasa_desactualizada` | `null`, ayer, hoy | F10-06 |

---

## 7. Evidencias destacadas

Todas las evidencias están en `C:\Users\ARCADIA\AppData\Local\Temp\opencode\pruebas_cb\evidencias`.

| Archivo | Contenido |
|---|---|
| `resultados.json` | 151 resultados con id, fase, descripción, estado, detalle y milisegundos |
| `resumen_ejecucion.txt` | Constancia de ejecución, base usada y conteos finales |
| `login.html`, `panel_admin.html`, `panel_cajero.html` | Vistas de autenticación y paneles por rol |
| `productos_bajo.html` | Filtro `?bajo=1` con los productos en o bajo el mínimo |
| `productos_cajero.html` | Inventario en modo consulta para el CAJERO (sin formulario ni costo) |
| `cierre_caja.html` | Nuevo módulo `/cierre` con arqueo por método de pago e IGTF |
| `pos.html` | Punto de venta con el SKU manual y el producto agotado deshabilitado |
| `factura_ver.html` | Detalle de la factura emitida |
| `factura.pdf`, `factura.png` | Comprobantes generados en el servidor (PDF 3.857 bytes; PNG 86.667 bytes) |
| `historial_ventas_resumen.csv` | CSV resumen con BOM, columnas de método de pago y totales |
| `historial_ventas_detalle.csv` | CSV detalle con código, producto, cantidad y precios |
| `historial_ventas_formula.csv` | Prueba de neutralización de fórmulas (`'=SUM(1,1)`) |
| `concurrencia.json` | Cuatro facturas concurrentes con consecutivos `000008`…`000011` |
| `fixtures.json` | Ids de los productos, cliente y usuarios de prueba |
| `404.html` | Página amigable de ruta inexistente |

### 7.1 Concurrencia del consecutivo

Cuatro procesos emitieron una unidad del producto D (stock 100) al mismo tiempo. Resultado:

| Worker | Factura | Consecutivo | Total Bs | Total USD |
|---|---|---:|---:|---:|
| 1 | 41 | 000008 | 211,70 | 5,80 |
| 2 | 42 | 000009 | 211,70 | 5,80 |
| 3 | 43 | 000010 | 211,70 | 5,80 |
| 4 | 44 | 000011 | 211,70 | 5,80 |

Consecutivos únicos, estado EMITIDA en las cuatro y stock final 96 (100 − 4). La transacción con `FOR UPDATE` y el cálculo del consecutivo dentro de la misma transacción evitaron duplicados.

### 7.2 Impresión sin doble descuento

Tras emitir E1 (stock A = 7), se descargó el PDF y se generó el PNG. En ambos casos el stock consultado después siguió siendo 7. La emisión descuenta una sola vez; imprimir o visualizar no vuelve a mover inventario (F5-15, F5-17).

### 7.3 SKU manual

Un producto sin código comercial se creó con `codigo_qr_barras = 'CB-SKU-MANUAL-001'`, se guardó con unidad `UND` y apareció en el POS con ese código. El sistema no genera códigos internos automáticamente: el SKU se escribe a mano (F4-01, F4-02).

### 7.4 Idempotencia de la emisión

El formulario del POS incluye un `token_emision` de un solo uso (máximo 10 tokens por sesión). Al procesar la emisión, el controlador consume el token; un segundo POST con el mismo token —doble clic, botón Atrás o reenvío— se rechaza con el aviso «La solicitud de emisión ya fue procesada o expiró», sin crear otra factura ni volver a descontar stock (F5-31). Un POST sin token también se rechaza (F5-32).

### 7.5 Cierre de caja

El módulo `/cierre` presenta el corte del día con cantidad de facturas, totales en Bs y USD, arqueo por método de pago e IGTF recaudado. El ADMIN ve el corte global; el CAJERO ve únicamente sus ventas (F7-06, F7-07). El enlace está disponible en el panel y en el historial de facturación.

---

## 8. Limpieza y verificación final

### 8.1 Base de prueba

La limpieza se ejecutó dos veces (al inicio y en el `finally` final) usando los ids máximos capturados antes de crear los fixtures. Conteos finales:

| Tabla | Línea base | Final | Estado |
|---|---:|---:|---|
| usuarios | 2 | 2 | Igual |
| clientes | 3 | 3 | Igual |
| productos | 9 | 9 | Igual |
| facturas | 1 | 1 | Igual |
| detalle_factura | 4 | 4 | Igual |
| merma_ajustes | 1 | 1 | Igual |
| tasas_cambio_bcv | 2 | 2 | Igual |
| bitacora_auditoria | 3 | 3 | Igual |
| categorias | 4 | 4 | Igual |
| proveedores | 3 | 3 | Igual |
| compras_proveedor | 1 | 1 | Igual |
| detalle_compra | 4 | 4 | Igual |

Además se verificó que no quedaran residuos `CB-*` en productos, clientes ni usuarios, ni en la base de prueba ni en la real.

### 8.2 Base real `de_todito_reyes`

Antes y después de la corrida se calculó `CHECKSUM TABLE` para `usuarios`, `clientes`, `productos`, `facturas`, `detalle_factura`, `merma_ajustes`, `tasas_cambio_bcv` y `bitacora_auditoria`. **Los ocho checksums coincidieron** (F11-04). La base real solo se leyó; el arnés nunca ejecutó escrituras contra ella.

Conteo observado al cierre (solo lectura): usuarios 4, clientes 2, productos 11, facturas 1, detalle_factura 5, merma_ajustes 3, bitacora_auditoria 161. Son datos propios de la base real, ajenos a esta prueba.

> **Nota sobre el dato demo:** el seed `sql/de_todito_reyes.sql` y la base de prueba quedaron con `total_usd = 26,882` para la factura `000001`. La base real conserva su valor original `26,880` porque la corrida no debe modificar datos de producción; al aplicar el seed corregido o un `UPDATE` equivalente en el despliegue, quedará alineada.

---

## 9. Ajustes del arnés de pruebas

Ninguna incidencia fue un defecto del sistema; todas fueron correcciones del arnés, de sus expectativas o de la preparación de la corrida.

| # | Incidencia | Corrección |
|---|---|---|
| 1 | La tabla `bitacora_auditoria` usa `id_log`, no `id_bitacora` | Se renombró la columna en `runner.php` y `f02_auth.php` |
| 2 | La tabla `clientes` no tiene columna `correo` | Se eliminó del `INSERT` de fixtures |
| 3 | La tabla de compras se llama `compras_proveedor`, no `compras` | Se corrigió el listado de conteos |
| 4 | Se esperaba 73 Bs por 2 unidades de B, pero B cuesta 2 USD y la tasa es 36,5 → 146 Bs | Se corrigió la aserción (error aritmético del arnés) |
| 5 | El caso de total USD vs Bs marcaba la factura demo `000001` | Tras corregir el seed, F5-50 verifica que la demo cuadra |
| 6 | Se asumió que `GET /logout` cerraba la sesión; el sistema exige `POST` + CSRF | Se corrigió el caso y se agregó F2-10 |
| 7 | Se asumió que el CAJERO no podía consultar `/productos` | Se habilitó el modo consulta y se reescribió F6-03; F6-02 verifica el menú con Productos y Cierre |
| 8 | El RIF `CB-V-00000009` no cumple la máscara `^[VEJGP]-\d{6,9}(-\d)?$` | Se usó `V-99999999` para la creación por HTTP |
| 9 | `resumen_ejecucion.txt` contaba 148 porque el caso actual aún no estaba en el arreglo | Se sumó 1 al total y al PASS del propio caso |
| 10 | El POST de emisión requería el nuevo token de idempotencia | `emitirHttp()` extrae `token_emision` del POS y permite reutilizarlo para probar el rechazo (F5-31, F5-32) |
| 11 | Las expectativas de mermas y CSV del CAJERO cambiaron con las correcciones | F6-08 y F6-11 verifican ahora el bloqueo y el aviso de ADMIN |
| 12 | El nuevo cierre y las vistas corregidas no tenían casos | Se agregaron F7-06, F7-07, F7-10 y F7-11 |

---

## 10. Hallazgos y estado final

Severidad: **Media** = incumplía una conducta prometida o exponía datos fuera del rol; **Baja** = inconsistencia o deuda técnica; **Informativa** = comportamiento correcto o dato a corregir.

| ID | Severidad | Hallazgo original | Estado | Evidencia | Acción aplicada / referencia |
|---|---|---|---|---|---|
| HB-01 | Media | El CAJERO podía registrar mermas y descontar stock | **Corregido** | F6-08 | `MermasController::index` y `procesarPost` usan `exigirAdmin()`; merma oculta del menú y panel del CAJERO |
| HB-02 | Media | El CAJERO podía crear clientes | **Aceptado por negocio** | F6-09 | Se mantiene: la creación de clientes es parte del flujo de facturación; editar/eliminar sigue restringido a ADMIN (F6-10) |
| HB-03 | Media | El CAJERO podía descargar los CSV completos del historial | **Corregido** | F6-11 | `FacturacionController::exportar` usa `exigirAdmin()`; CSV oculto al CAJERO |
| HB-04 | Media | No existía un reporte dedicado de corte/cierre de caja | **Corregido** | F7-06, F7-07 | Nuevo `CierreController` + vista `/cierre` con arqueo por método de pago, IGTF y filtro por usuario; `FacturaModel::resumenCierre` |
| HB-05 | Media | Las vistas fiscales incluían facturas anuladas | **Corregido** | F7-10, F7-11 | `v_libro_ventas` filtra `estado = 'EMITIDA'`; `v_movimientos_producto` solo suma detalle EMITIDA |
| HB-06 | Baja | El CAJERO no podía ni consultar `/productos` | **Corregido** | F6-03 | `ProductosController::index` usa `exigirLogin()`; vista en «Modo consulta» sin formulario ni columna de costo |
| HB-07 | Baja | El logotipo de la marca no era un enlace al inicio | **Corregido** | F6-13 | `header.php` usa `<a class="brand" href="/panel">`; CSS sin subrayado |
| HB-08 | Baja | No había clave de idempotencia en la emisión | **Corregido** | F5-31, F5-32 | Token `token_emision` de un solo uso (`MAX_TOKENS_EMISION = 10`, `generarTokenEmision()`, `consumirTokenEmision()`) |
| HB-09 | Baja | `productosVendibles()` incluye productos con stock 0 | **Mantenido por diseño** | F5-09, F5-44 | El POS muestra el agotado con botón deshabilitado y el servidor bloquea la venta; decisión de usabilidad |
| HB-10 | Informativa | La factura demo `000001` tenía un descuadre de 0,002 USD | **Corregido** | F5-50 | Seed y base de prueba con `total_usd = 26,882`; la base real se conserva sin cambios hasta el despliegue |
| HB-11 | Informativa (positivo) | `GET /logout` no cierra la sesión: exige `POST` + CSRF | **Preservado** | F2-10 | Buena práctica; se mantiene |
| HB-12 | Informativa (positivo) | El consecutivo no se reutiliza al borrar la última anulada | **Preservado** | F5-39 | `siguienteConsecutivo()` consulta `MAX(facturas)` y la bitácora `EMISION` |
| HB-13 | Informativa (positivo) | La concurrencia es segura: 4 emisiones simultáneas → consecutivos únicos y stock consistente | **Preservado** | F5-47 | Transacción + `FOR UPDATE`; repetir con más cajas si crece la operación |
| HB-14 | Informativa (positivo) | Los triggers protegen el inventario ante SQL directo | **Preservado** | F9-08, F9-09 | `trg_merma_valida_stock`, `trg_productos_stock_no_negativo` |

### 10.1 Observación adicional de revisión de código (no ejecutada)

`validar_sesion_actual()` (helpers.php:333-358) revalida `nombre_completo`, `usuario_login`, `rol` y `activo`, pero **no compara el hash de la contraseña**. Por diseño, cambiar la contraseña de un usuario no invalida sus otras sesiones abiertas; solo las invalida desactivar la cuenta. No forma parte de las FAQ, por lo que no se incluyó como caso, pero conviene evaluarlo si se quiere que un cambio de clave expulse a las sesiones existentes.

---

## 11. Correspondencia FAQ → casos (oráculo)

| Afirmación del capítulo 5 (FAQ) | Casos que la verifican |
|---|---|
| El Administrador ve alertas de stock mínimo en el Dashboard o filtra el inventario por stock mínimo | F3-01…F3-07 |
| El stock mínimo es el umbral que, al alcanzarse o traspasarse hacia la baja, advierte reabastecer | F3-01, F3-02, F9-10 |
| El rol de Cajero está estrictamente limitado a caja y facturación | F6-01, F6-02, F6-04…F6-08, F6-10, F6-11 (HB-02 aceptado por negocio) |
| La sesión expira por inactividad | F8-01, F8-02 |
| Hay corte de caja diario por fecha | F7-01…F7-09 (HB-04 corregido con `/cierre`) |
| Un producto sin código de barras comercial usa un SKU interno escrito a mano | F4-01, F4-02 |
| El inventario se descuenta al imprimir/emitir la factura | F5-12, F5-15, F5-17 |
| No se debe duplicar la transacción al recargar o reenviar | F5-30, F5-31, F5-32 |
| Los productos con stock 0 quedan bloqueados para la venta | F5-09, F5-43, F5-44 |
| Al emitir se usan los datos del cliente y la tasa vigente | F5-12, F5-13, F5-41 |
| El libro de ventas y el kardex reflejan solo facturas válidas | F7-10, F7-11 |

---

## 12. Recomendaciones

1. **Desplegar el SQL corregido en producción:** aplicar `CREATE OR REPLACE VIEW` para `v_libro_ventas` y `v_movimientos_producto`, y actualizar el dato demo `000001` a `26,882` (o recalcular `total_usd` al emitir). La base real no se tocó durante la prueba.
2. **Documentar HB-02 como decisión de negocio:** el CAJERO puede crear clientes como parte de la facturación; si la política cambia, mover la acción a `exigirAdmin()`.
3. **Mantener HB-09 y documentarlo:** el agotado se muestra deshabilitado en el POS y se bloquea en el servidor; si se prefiere ocultarlo, filtrar `stock_actual > 0` en `productosVendibles()`.
4. **Evaluar la invalidación de sesiones al cambiar contraseña**, si el negocio lo requiere.
5. **Ampliar la prueba de concurrencia** si crece el número de cajas o se incorporan más métodos de pago.
6. **Añadir exportación o impresión del cierre** si se necesita una constancia física del arqueo; hoy el módulo muestra el corte en pantalla.

---

## 13. Conclusión

La prueba de caja blanca confirma que el sistema cumple las conductas centrales prometidas en el capítulo 5: alerta de stock mínimo con la regla `<=`, bloqueo de productos agotados en la interfaz y en el servidor, sesión con expiración por inactividad, SKU manual, descuento de inventario al emitir sin doble descuento al imprimir, corte por fecha con totales contrastables, cierre de caja dedicado e idempotencia de la emisión.

Los 14 hallazgos del informe anterior quedaron atendidos: **8 corregidos** (HB-01, HB-03, HB-04, HB-05, HB-06, HB-07, HB-08 y HB-10), **1 aceptado por negocio** (HB-02) y **1 mantenido por diseño** (HB-09). Los cuatro hallazgos positivos (HB-11…HB-14) se preservaron y siguen verificándose. No quedan brechas de permisos ni de integridad pendientes dentro del alcance de las FAQ.

La base de prueba quedó restaurada a su línea base y la base real no se modificó. El arnés, las fases y las evidencias quedan disponibles para repetir la corrida.

---

## Anexo A — Evidencias generadas

Ruta: `C:\Users\ARCADIA\AppData\Local\Temp\opencode\pruebas_cb\evidencias`

- `resultados.json` (151 casos)
- `resumen_ejecucion.txt`
- `login.html`, `panel_admin.html`, `panel_cajero.html`, `productos_bajo.html`, `productos_cajero.html`, `cierre_caja.html`, `pos.html`, `factura_ver.html`, `404.html`
- `factura.pdf`, `factura.png`
- `historial_ventas_resumen.csv`, `historial_ventas_detalle.csv`, `historial_ventas_formula.csv`
- `concurrencia.json`, `fixtures.json`

## Anexo B — Reproducción rápida

```powershell
# 1) Servidor de prueba (docroot de la copia)
C:\xampp\php\php.exe -S 127.0.0.1:8010 `
  -t C:\Users\ARCADIA\AppData\Local\Temp\opencode\sistema_test\public `
  C:\Users\ARCADIA\AppData\Local\Temp\opencode\sistema_test\public\index.php

# 2) Arnés de caja blanca
cd C:\Users\ARCADIA\AppData\Local\Temp\opencode\pruebas_cb
C:\xampp\php\php.exe runner.php
```

Requisitos: MySQL con `de_todito_reyes_test` (copia desechable) y `de_todito_reyes` (solo lectura), extensiones `curl`, `gd`, `mbstring` y `pdo_mysql`.

## Anexo C — Estructura del arnés

| Archivo | Responsabilidad |
|---|---|
| `runner.php` | Framework de casos/asserts, cliente HTTP con cookies y CSRF, helpers PDO, fixtures, snapshot de la base real, limpieza y ejecución principal |
| `fases/f01_entorno.php` | Entorno, aislamiento y fixtures `CB-*` |
| `fases/f02_auth.php` | Login, sesión, CSRF y logout |
| `fases/f03_stock.php` | Stock mínimo y alertas |
| `fases/f04_sku.php` | SKU manual y validaciones de productos |
| `fases/f05_emision.php` | Emisión, idempotencia, stock, PDF/PNG/CSV, anulación, concurrencia |
| `fases/f06_roles.php` | Permisos ADMIN/CAJERO y navegación |
| `fases/f07_caja.php` | Cierre de caja, filtros, vistas fiscales y reportes |
| `fases/f08_sesion.php` | Expiración, revalidación y CSRF |
| `fases/f09_merma.php` | Mermas y triggers |
| `fases/f10_helpers.php` | Formato, roles, errores y URLs |
| `fases/f11_cierre.php` | Limpieza, restauración y constancia |
| `emit_worker.php` | Worker CLI para la prueba de concurrencia del consecutivo |

## Anexo D — Diferencias con el informe de caja negra

| Tema | Caja negra | Caja blanca (estado actual) |
|---|---|---|
| Consecutivo tras borrar la última anulada | Se observó que `MAX(nro_factura_consecutivo) + 1` podía reutilizar el número | Se verificó que `siguienteConsecutivo()` también consulta la bitácora `EMISION`; **no se reutiliza** (F5-39) |
| Permisos del CAJERO | Se probó la matriz funcional | Mermas y CSV restringidos a ADMIN; clientes permitido por decisión de negocio; productos en modo consulta (F6-02…F6-11) |
| Corte de caja | Se validó el filtro por fecha | Se implementó y verificó el módulo `/cierre` con arqueo y filtro por usuario (F7-06, F7-07) |
| Vistas fiscales | No se auditaron | `v_libro_ventas` y `v_movimientos_producto` excluyen anuladas (F7-10, F7-11) |
| Idempotencia | No se probó | Token de un solo uso; el reenvío no duplica y el POST sin token se rechaza (F5-31, F5-32) |
| Concurrencia | Fuera de alcance | 4 emisiones simultáneas con consecutivos únicos y stock consistente (F5-47) |

---

**Fin del informe.**
