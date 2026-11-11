-- =====================================================================
--  SISTEMA "DE TODITO REYES"
--  Modelo Entidad-Relacion Normalizado (3FN) - 12 tablas
--  Motor: MySQL 8.0+ / MariaDB 10.4+ (InnoDB, utf8mb4)
--  Bimonetarismo BCV | Soporte pesaje DECIMAL(12,3)
--  Normativa SENIAT (Prov. 0071 y Art. 104 COT)
-- ---------------------------------------------------------------------
--  Uso en phpMyAdmin:
--    1) Entrar a phpMyAdmin
--    2) Pestana "Importar" -> "Elegir archivo" -> seleccionar este .sql
--    3) Cotejo de caracteres: utf-8  ->  "Continuar"
-- ---------------------------------------------------------------------
--  ADVERTENCIA: este script BORRA las 12 tablas si ya existen.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS de_todito_reyes
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE de_todito_reyes;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS bitacora_auditoria;
DROP TABLE IF EXISTS merma_ajustes;
DROP TABLE IF EXISTS detalle_factura;
DROP TABLE IF EXISTS facturas;
DROP TABLE IF EXISTS detalle_compra;
DROP TABLE IF EXISTS compras_proveedor;
DROP TABLE IF EXISTS tasas_cambio_bcv;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS proveedores;
DROP TABLE IF EXISTS categorias;
SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================================
-- 1. CATEGORIAS
-- =====================================================================
CREATE TABLE categorias (
  id_categoria     INT           NOT NULL AUTO_INCREMENT,
  nombre_categoria VARCHAR(80)   NOT NULL,
  descripcion      VARCHAR(150)  NULL,
  activo           TINYINT(1)    NOT NULL DEFAULT 1,
  creado_en        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_categoria),
  UNIQUE KEY uq_categorias_nombre (nombre_categoria),
  CONSTRAINT chk_categorias_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Rubros / familias de productos';


-- =====================================================================
-- 4. PROVEEDORES
-- =====================================================================
CREATE TABLE proveedores (
  id_proveedor    INT           NOT NULL AUTO_INCREMENT,
  rif_proveedor   VARCHAR(15)   NOT NULL COMMENT 'Formato J-12345678-9',
  razon_social    VARCHAR(120)  NOT NULL,
  telefono        VARCHAR(25)   NULL,
  correo          VARCHAR(100)  NULL,
  direccion_fiscal VARCHAR(200) NULL,
  activo          TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (id_proveedor),
  UNIQUE KEY uq_proveedores_rif (rif_proveedor),
  CONSTRAINT chk_proveedores_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 7. USUARIOS
-- =====================================================================
CREATE TABLE usuarios (
  id_usuario    INT           NOT NULL AUTO_INCREMENT,
  cedula        VARCHAR(15)   NOT NULL,
  nombre_completo VARCHAR(100) NOT NULL,
  usuario_login VARCHAR(40)   NOT NULL,
  password_hash VARCHAR(255)  NOT NULL COMMENT 'Generado con password_hash() de PHP (bcrypt/argon2)',
  rol           ENUM('ADMIN','CAJERO') NOT NULL DEFAULT 'CAJERO',
  activo        TINYINT(1)    NOT NULL DEFAULT 1,
  creado        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuarios_cedula (cedula),
  UNIQUE KEY uq_usuarios_login  (usuario_login),
  CONSTRAINT chk_usuarios_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 10. CLIENTES
-- =====================================================================
CREATE TABLE clientes (
  id_cliente          INT           NOT NULL AUTO_INCREMENT,
  rif_cedula          VARCHAR(15)   NOT NULL COMMENT 'V-12345678 / J-12345678-9',
  nombre_razon_social VARCHAR(120)  NOT NULL,
  direccion_fiscal    VARCHAR(180)  NULL,
  telefono            VARCHAR(25)   NULL,
  creado_en           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_cliente),
  UNIQUE KEY uq_clientes_rif (rif_cedula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 2. PRODUCTOS
-- =====================================================================
CREATE TABLE productos (
  id_producto      INT           NOT NULL AUTO_INCREMENT,
  codigo_qr_barras VARCHAR(64)   NOT NULL COMMENT 'Codigo de barra o QR interno',
  nombre_producto  VARCHAR(120)  NOT NULL,
  unidad_medida    VARCHAR(20)   NOT NULL DEFAULT 'UND' COMMENT 'UND, KG, LT, GR, PAQ',
  costo_usd        DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  precio_venta_usd DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  alicuota_iva     DECIMAL(5,2)  NOT NULL DEFAULT 16.00 COMMENT '0 = exento, 16 = general',
  stock_actual     DECIMAL(12,3) NOT NULL DEFAULT 0.000 COMMENT 'Soporta pesaje (kg)',
  stock_minimo     DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  stock_maximo     DECIMAL(12,3) NULL,
  id_categoria     INT           NOT NULL,
  activo           TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (id_producto),
  UNIQUE KEY uq_productos_codigo (codigo_qr_barras),
  KEY idx_productos_categoria (id_categoria),
  KEY idx_productos_nombre (nombre_producto),
  CONSTRAINT fk_productos_categoria
    FOREIGN KEY (id_categoria) REFERENCES categorias (id_categoria)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_productos_iva     CHECK (alicuota_iva BETWEEN 0 AND 100),
  CONSTRAINT chk_productos_stock   CHECK (stock_actual >= 0),
  CONSTRAINT chk_productos_precios CHECK (costo_usd >= 0 AND precio_venta_usd >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 5. COMPRAS PROVEEDOR
-- =====================================================================
CREATE TABLE compras_proveedor (
  id_compra               INT           NOT NULL AUTO_INCREMENT,
  nro_factura_proveedor   VARCHAR(40)   NOT NULL,
  nro_control_proveedor   VARCHAR(40)   NULL,
  fecha_emision_prov      DATE          NOT NULL,
  fecha_recepcion         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tasa_bcv_compra         DECIMAL(12,4) NOT NULL COMMENT 'Tasa BCV aplicada al momento de la compra',
  total_compra_usd        DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  total_compra_ves        DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  id_proveedor            INT           NOT NULL,
  id_usuario              INT           NOT NULL COMMENT 'Usuario que registra la compra',
  PRIMARY KEY (id_compra),
  UNIQUE KEY uq_compras_prov_factura (id_proveedor, nro_factura_proveedor),
  KEY idx_compras_proveedor (id_proveedor),
  KEY idx_compras_usuario (id_usuario),
  KEY idx_compras_fecha (fecha_recepcion),
  CONSTRAINT fk_compras_proveedor
    FOREIGN KEY (id_proveedor) REFERENCES proveedores (id_proveedor)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_compras_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_compras_totales CHECK (total_compra_usd >= 0 AND total_compra_ves >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 6. DETALLE COMPRA
-- =====================================================================
CREATE TABLE detalle_compra (
  id_detalle_compra   INT           NOT NULL AUTO_INCREMENT,
  id_compra           INT           NOT NULL,
  id_producto         INT           NOT NULL,
  cantidad_recibida   DECIMAL(12,3) NOT NULL,
  costo_unitario_usd  DECIMAL(12,3) NOT NULL,
  subtotal_usd        DECIMAL(12,3) NOT NULL,
  lote_fabricacion    VARCHAR(40)   NULL,
  fecha_vencimiento   DATE          NULL,
  PRIMARY KEY (id_detalle_compra),
  KEY idx_dcompra_compra (id_compra),
  KEY idx_dcompra_producto (id_producto),
  KEY idx_dcompra_vencimiento (fecha_vencimiento),
  CONSTRAINT fk_dcompra_compra
    FOREIGN KEY (id_compra) REFERENCES compras_proveedor (id_compra)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_dcompra_producto
    FOREIGN KEY (id_producto) REFERENCES productos (id_producto)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_dcompra_cantidad CHECK (cantidad_recibida > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 8. TASAS DE CAMBIO BCV
-- =====================================================================
CREATE TABLE tasas_cambio_bcv (
  id_tasa        INT           NOT NULL AUTO_INCREMENT,
  tasa_bcv_ves   DECIMAL(12,4) NOT NULL COMMENT 'Bs por 1 USD',
  fecha_vigencia DATETIME      NOT NULL,
  observacion    VARCHAR(100)  NULL,
  id_usuario     INT           NOT NULL,
  PRIMARY KEY (id_tasa),
  UNIQUE KEY uq_tasas_fecha (fecha_vigencia),
  KEY idx_tasas_usuario (id_usuario),
  CONSTRAINT fk_tasas_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_tasas_valor CHECK (tasa_bcv_ves > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 11. FACTURAS (SENIAT)
-- =====================================================================
CREATE TABLE facturas (
  id_factura              INT           NOT NULL AUTO_INCREMENT,
  nro_factura_consecutivo VARCHAR(20)   NOT NULL,
  nro_control_fiscal      VARCHAR(25)   NOT NULL,
  codigo_verificacion     VARCHAR(10)   NOT NULL,
  tipo_emision            ENUM('SISTEMA','CONTINGENCIA') NOT NULL DEFAULT 'SISTEMA',
  fecha_emision           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  subtotal_exento_ves     DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  base_imponible_ves      DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  monto_iva_ves           DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  monto_igtf_ves          DECIMAL(12,3) NOT NULL DEFAULT 0.000 COMMENT '3% IGTF sobre pagos en divisa',
  total_ves               DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  total_usd               DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  tasa_bcv_aplicada       DECIMAL(12,4) NOT NULL,
  metodo_pago             ENUM('EFECTIVO','PAGO_MOVIL','PUNTO_VENTA','TRANSFERENCIA','ZELLE','MIXTO')
                          NOT NULL DEFAULT 'EFECTIVO',
  estado                  ENUM('EMITIDA','ANULADA') NOT NULL DEFAULT 'EMITIDA',
  id_cliente              INT           NOT NULL,
  id_usuario              INT           NOT NULL COMMENT 'Cajero que emite',
  PRIMARY KEY (id_factura),
  UNIQUE KEY uq_facturas_consecutivo (nro_factura_consecutivo),
  UNIQUE KEY uq_facturas_control (nro_control_fiscal),
  UNIQUE KEY uq_facturas_codigo (codigo_verificacion),
  KEY idx_facturas_cliente (id_cliente),
  KEY idx_facturas_usuario (id_usuario),
  KEY idx_facturas_fecha (fecha_emision),
  KEY idx_facturas_estado (estado),
  CONSTRAINT fk_facturas_cliente
    FOREIGN KEY (id_cliente) REFERENCES clientes (id_cliente)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_facturas_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_facturas_totales CHECK (total_ves >= 0 AND total_usd >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 9. DETALLE FACTURA
-- =====================================================================
CREATE TABLE detalle_factura (
  id_detalle_factura   INT           NOT NULL AUTO_INCREMENT,
  id_factura           INT           NOT NULL,
  id_producto          INT           NOT NULL,
  cantidad_o_peso      DECIMAL(12,3) NOT NULL COMMENT 'Cantidad o peso en kg',
  precio_unitario_usd  DECIMAL(12,3) NOT NULL,
  precio_unitario_ves  DECIMAL(12,3) NOT NULL,
  alicuota_iva_item    DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  monto_iva_item_ves   DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  subtotal_item_ves    DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (id_detalle_factura),
  KEY idx_dfactura_factura (id_factura),
  KEY idx_dfactura_producto (id_producto),
  CONSTRAINT fk_dfactura_factura
    FOREIGN KEY (id_factura) REFERENCES facturas (id_factura)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_dfactura_producto
    FOREIGN KEY (id_producto) REFERENCES productos (id_producto)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_dfactura_cantidad CHECK (cantidad_o_peso > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 3. MERMA / AJUSTES
-- =====================================================================
CREATE TABLE merma_ajustes (
  id_merma            INT           NOT NULL AUTO_INCREMENT,
  id_producto         INT           NOT NULL,
  id_usuario          INT           NOT NULL,
  fecha_registro      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cantidad_baja       DECIMAL(12,3) NOT NULL,
  costo_unitario_usd  DECIMAL(12,3) NOT NULL,
  tipo_motivo         ENUM('VENCIMIENTO','DAÑO','PERDIDA','ROBO','AJUSTE_INVENTARIO','OTRO')
                      NOT NULL DEFAULT 'OTRO',
  observacion         VARCHAR(200)  NULL,
  PRIMARY KEY (id_merma),
  KEY idx_merma_producto (id_producto),
  KEY idx_merma_usuario (id_usuario),
  KEY idx_merma_fecha (fecha_registro),
  CONSTRAINT fk_merma_producto
    FOREIGN KEY (id_producto) REFERENCES productos (id_producto)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_merma_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_merma_cantidad CHECK (cantidad_baja > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 12. BITACORA / AUDITORIA
-- =====================================================================
CREATE TABLE bitacora_auditoria (
  id_log        BIGINT       NOT NULL AUTO_INCREMENT,
  id_usuario    INT          NULL COMMENT 'NULL = accion del sistema',
  modulo        VARCHAR(50)  NOT NULL,
  accion        VARCHAR(80)  NOT NULL,
  detalle_json  TEXT         NULL,
  ip_local      VARCHAR(45)  NULL COMMENT 'IPv4 o IPv6',
  fecha_evento  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_log),
  KEY idx_bitacora_usuario (id_usuario),
  KEY idx_bitacora_fecha (fecha_evento),
  KEY idx_bitacora_modulo (modulo),
  CONSTRAINT fk_bitacora_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  VISTAS DE APOYO
-- =====================================================================

-- Tasa BCV vigente (la mas reciente)
CREATE OR REPLACE VIEW v_tasa_vigente AS
SELECT id_tasa, tasa_bcv_ves, fecha_vigencia, observacion, id_usuario
FROM tasas_cambio_bcv
ORDER BY fecha_vigencia DESC
LIMIT 1;

-- Productos en o por debajo del stock minimo
CREATE OR REPLACE VIEW v_stock_bajo AS
SELECT  p.id_producto,
        p.codigo_qr_barras,
        p.nombre_producto,
        c.nombre_categoria,
        p.unidad_medida,
        p.stock_actual,
        p.stock_minimo,
        ROUND(p.stock_minimo - p.stock_actual, 3) AS faltante
FROM productos p
JOIN categorias c ON c.id_categoria = p.id_categoria
WHERE p.activo = 1
  AND p.stock_actual <= p.stock_minimo;

-- Libro de ventas (formato SENIAT). Solo facturas vigentes: las anuladas
-- no forman parte del libro fiscal y se consultan en el historial.
CREATE OR REPLACE VIEW v_libro_ventas AS
SELECT  f.id_factura,
        f.nro_factura_consecutivo,
        f.nro_control_fiscal,
        DATE(f.fecha_emision)               AS fecha,
        cl.rif_cedula                       AS rif_ci_cliente,
        cl.nombre_razon_social              AS cliente,
        u.usuario_login                     AS cajero,
        f.tipo_emision,
        f.metodo_pago,
        f.estado,
        f.subtotal_exento_ves,
        f.base_imponible_ves,
        f.monto_iva_ves,
        f.monto_igtf_ves,
        f.total_ves,
        f.total_usd,
        f.tasa_bcv_aplicada
FROM facturas f
JOIN clientes cl ON cl.id_cliente = f.id_cliente
JOIN usuarios u  ON u.id_usuario  = f.id_usuario
WHERE f.estado = 'EMITIDA';

-- Kardex resumido: entradas (compras), salidas (ventas) y mermas por producto
CREATE OR REPLACE VIEW v_movimientos_producto AS
SELECT  p.id_producto,
        p.nombre_producto,
        COALESCE(ec.total_comprado, 0) AS total_comprado,
        COALESCE(vt.total_vendido, 0)  AS total_vendido,
        COALESCE(me.total_merma, 0)    AS total_merma,
        p.stock_actual
FROM productos p
LEFT JOIN (SELECT id_producto, SUM(cantidad_recibida) AS total_comprado
             FROM detalle_compra GROUP BY id_producto) ec ON ec.id_producto = p.id_producto
LEFT JOIN (SELECT d.id_producto, SUM(d.cantidad_o_peso) AS total_vendido
             FROM detalle_factura d
             JOIN facturas f ON f.id_factura = d.id_factura AND f.estado = 'EMITIDA'
             GROUP BY d.id_producto) vt ON vt.id_producto = p.id_producto
LEFT JOIN (SELECT id_producto, SUM(cantidad_baja) AS total_merma
             FROM merma_ajustes GROUP BY id_producto) me ON me.id_producto = p.id_producto;


-- =====================================================================
--  TRIGGERS
-- =====================================================================
DELIMITER $$

-- 1) Auditoria automatica de facturas (INSERT)
DROP TRIGGER IF EXISTS trg_facturas_ai $$
CREATE TRIGGER trg_facturas_ai
AFTER INSERT ON facturas
FOR EACH ROW
BEGIN
  INSERT INTO bitacora_auditoria (id_usuario, modulo, accion, detalle_json)
  VALUES (NEW.id_usuario, 'FACTURAS', 'EMISION',
          JSON_OBJECT('id_factura', NEW.id_factura,
                      'nro_factura', NEW.nro_factura_consecutivo,
                      'nro_control', NEW.nro_control_fiscal,
                      'total_ves', NEW.total_ves,
                      'total_usd', NEW.total_usd,
                      'tasa_bcv', NEW.tasa_bcv_aplicada,
                      'estado', NEW.estado));
END $$

-- 2) Auditoria automatica de facturas (UPDATE: anulaciones, cambios de estado)
--    PHP define @auditoria_usuario antes de anular para registrar al usuario
--    que ejecuto el cambio; si no esta definida, se usa el emisor original.
DROP TRIGGER IF EXISTS trg_facturas_au $$
CREATE TRIGGER trg_facturas_au
AFTER UPDATE ON facturas
FOR EACH ROW
BEGIN
  IF OLD.estado <> NEW.estado OR OLD.total_ves <> NEW.total_ves THEN
    INSERT INTO bitacora_auditoria (id_usuario, modulo, accion, detalle_json)
    VALUES (COALESCE(NULLIF(@auditoria_usuario, 0), NEW.id_usuario), 'FACTURAS', 'ACTUALIZACION',
            JSON_OBJECT('id_factura', NEW.id_factura,
                        'estado_anterior', OLD.estado,
                        'estado_nuevo', NEW.estado,
                        'total_anterior', OLD.total_ves,
                        'total_nuevo', NEW.total_ves));
  END IF;
END $$

-- 3) Evitar stock negativo al actualizar productos
DROP TRIGGER IF EXISTS trg_productos_stock_no_negativo $$
CREATE TRIGGER trg_productos_stock_no_negativo
BEFORE UPDATE ON productos
FOR EACH ROW
BEGIN
  IF NEW.stock_actual < 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El stock_actual no puede ser negativo (revisar salida de inventario).';
  END IF;
END $$

-- 4) Validar que la merma no exceda el stock disponible
DROP TRIGGER IF EXISTS trg_merma_valida_stock $$
CREATE TRIGGER trg_merma_valida_stock
BEFORE INSERT ON merma_ajustes
FOR EACH ROW
BEGIN
  DECLARE v_stock DECIMAL(12,3);
  SELECT stock_actual INTO v_stock FROM productos WHERE id_producto = NEW.id_producto;
  IF v_stock IS NOT NULL AND NEW.cantidad_baja > v_stock THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La cantidad de merma supera el stock disponible del producto.';
  END IF;
END $$

DELIMITER ;


-- =====================================================================
--  DATOS DE EJEMPLO (opcional: borrar este bloque si no los necesitas)
-- =====================================================================

INSERT INTO categorias (nombre_categoria, descripcion) VALUES
  ('Viveres',                'Harina, arroz, aceite, azucar, cafe'),
  ('Lacteos y Charcuteria',  'Quesos, jamones, embutidos al peso'),
  ('Limpieza',               'Detergentes, desinfectantes'),
  ('Bebidas',                'Refrescos, agua, jugos');

INSERT INTO proveedores (rif_proveedor, razon_social, telefono, correo, direccion_fiscal) VALUES
  ('J-30123456-7', 'Distribuidora Polar, C.A.',   '0212-5551234', 'ventas@polar.com.ve',    'Av. Principal, Los Ruices, Caracas'),
  ('J-31234567-8', 'Alimentos Mary, C.A.',        '0241-5559876', 'pedidos@mary.com.ve',    'Zona Industrial, Valencia'),
  ('J-32345678-9', 'Inversiones El Trigal, C.A.', '0261-5554321', 'compras@trigal.com.ve',  'Calle 77, Maracaibo');

INSERT INTO usuarios (cedula, nombre_completo, usuario_login, password_hash, rol) VALUES
  ('V-11111111', 'Administrador del Sistema', 'admin',
   '$2y$10$TPWtAhwLYhb8y/2.ZG8CMOMG8vnagoZZaiCKO5Gf1Js3LQTRVl.B.', 'ADMIN'),
  ('V-22222222', 'Maria Cajera', 'mcajera',
   '$2y$10$/.kKL.XjkDEggAkjC8eNqedMoytqHpLePCMHY2SSQkLUlmeR31nmq', 'CAJERO');
-- NOTA: claves demo -> admin / admin123 y mcajera / cajera123.
--       Cámbialas desde el módulo Usuarios o generándolas con:
--       password_hash('TuClaveSegura', PASSWORD_DEFAULT)

INSERT INTO clientes (rif_cedula, nombre_razon_social, direccion_fiscal, telefono) VALUES
  ('V-12345678', 'Juan Perez',              'Sector Centro, Casa 12',      '0414-1234567'),
  ('J-40123456-1', 'Bodega La Esquina, C.A.', 'Av. Bolivar, Local 3',      '0212-8887766'),
  ('V-87654321', 'Consumidor Final',        'N/A',                         NULL);

INSERT INTO productos
  (codigo_qr_barras, nombre_producto, unidad_medida, costo_usd, precio_venta_usd,
   alicuota_iva, stock_actual, stock_minimo, stock_maximo, id_categoria) VALUES
  ('7591234567890', 'Harina P.A.N. 1 kg',        'UND', 1.100, 1.600,  0.00, 120.000,  20.000, 200.000, 1),
  ('7591234567891', 'Arroz Mary 1 kg',           'UND', 1.000, 1.450,  0.00,  80.000,  15.000, 150.000, 1),
  ('7591234567892', 'Aceite Vatel 1 L',          'UND', 2.200, 3.100, 16.00,  45.000,  12.000, 100.000, 1),
  ('7591234567893', 'Azucar Montalban 1 kg',     'UND', 0.950, 1.400,  0.00,   8.000,  15.000,  80.000, 1),
  ('7591234567894', 'Cafe Amanecer 250 g',       'UND', 1.800, 2.750, 16.00,  60.000,  10.000, 120.000, 1),
  ('7591234567895', 'Queso blanco duro',         'KG',  4.500, 6.800,  0.00,  25.500,   5.000,  50.000, 2),
  ('7591234567896', 'Jamon planchado',           'KG',  6.200, 9.500, 16.00,  12.750,   4.000,  30.000, 2),
  ('7591234567897', 'Detergente Ariel 1 kg',     'UND', 2.600, 3.900, 16.00,  30.000,   8.000,  60.000, 3),
  ('7591234567898', 'Agua Mineral 5 L',          'UND', 0.700, 1.100,  0.00,  50.000,  10.000, 100.000, 4);

INSERT INTO tasas_cambio_bcv (tasa_bcv_ves, fecha_vigencia, observacion, id_usuario) VALUES
  (36.2000, '2026-10-29 00:00:00', 'Tasa BCV 29/10/2026', 1),
  (36.5000, '2026-10-30 00:00:00', 'Tasa BCV 30/10/2026', 1);

-- Compra al proveedor 1 (total 249,20 USD)
INSERT INTO compras_proveedor
  (nro_factura_proveedor, nro_control_proveedor, fecha_emision_prov, fecha_recepcion,
   tasa_bcv_compra, total_compra_usd, total_compra_ves, id_proveedor, id_usuario) VALUES
  ('A-00123', '00-00123', '2026-10-20', '2026-10-22 10:30:00',
   36.5000, 249.200, 9095.800, 1, 1);

INSERT INTO detalle_compra
  (id_compra, id_producto, cantidad_recibida, costo_unitario_usd, subtotal_usd,
   lote_fabricacion, fecha_vencimiento) VALUES
  (1, 1, 100.000, 1.100, 110.000, 'L-2026-10', '2027-04-30'),
  (1, 2,  80.000, 1.000,  80.000, 'L-2026-11', '2027-06-30'),
  (1, 3,  20.000, 2.200,  44.000, 'L-2026-12', '2027-03-31'),
  (1, 4,  16.000, 0.950,  15.200, 'L-2026-13', '2027-02-28');

-- Factura 000001 (total 981,20 Bs / 26,882 USD) con tasa 36,5000
INSERT INTO facturas
  (nro_factura_consecutivo, nro_control_fiscal, codigo_verificacion,
   tipo_emision, fecha_emision,
   subtotal_exento_ves, base_imponible_ves, monto_iva_ves, monto_igtf_ves,
   total_ves, total_usd, tasa_bcv_aplicada, metodo_pago, estado, id_cliente, id_usuario) VALUES
  ('000001', '00-000001', 'XFHWQHAD', 'SISTEMA', '2026-10-30 09:15:00',
   602.250, 326.680, 52.270, 0.000,
   981.200, 26.882, 36.5000, 'PAGO_MOVIL', 'EMITIDA', 1, 2);

INSERT INTO detalle_factura
  (id_factura, id_producto, cantidad_o_peso, precio_unitario_usd, precio_unitario_ves,
   alicuota_iva_item, monto_iva_item_ves, subtotal_item_ves) VALUES
  (1, 1, 5.000, 1.600,  58.400,  0.00,  0.000, 292.000),
  (1, 3, 2.000, 3.100, 113.150, 16.00, 36.210, 262.510),
  (1, 5, 1.000, 2.750, 100.380, 16.00, 16.060, 116.440),
  (1, 6, 1.250, 6.800, 248.200,  0.00,  0.000, 310.250);

-- Merma por vencimiento
INSERT INTO merma_ajustes
  (id_producto, id_usuario, cantidad_baja, costo_unitario_usd, tipo_motivo, observacion) VALUES
  (2, 1, 2.000, 1.000, 'VENCIMIENTO', 'Arroz vencido en estante, retirado el 30/10/2026');

INSERT INTO bitacora_auditoria (id_usuario, modulo, accion, detalle_json, ip_local) VALUES
  (1, 'USUARIOS', 'LOGIN', '{"usuario":"admin","resultado":"OK"}', '127.0.0.1'),
  (1, 'PRODUCTOS', 'ALTA', '{"id_producto":9,"nombre":"Agua Mineral 5 L"}', '127.0.0.1');


-- =====================================================================
--  CONSULTAS DE VERIFICACION
-- =====================================================================
-- SELECT * FROM v_stock_bajo;
-- SELECT * FROM v_tasa_vigente;
-- SELECT * FROM v_libro_ventas;
-- SELECT * FROM v_movimientos_producto;
