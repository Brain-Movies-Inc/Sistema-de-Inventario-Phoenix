-- =====================================================================
--  MIGRACION F-14 / F-16 / F-17 / F-21
--  Base: de_todito_reyes (ejecutar UNA vez sobre la base existente)
-- ---------------------------------------------------------------------
--  F-14: tabla secuencias_fiscales (consecutivo sin MAX ni bitacora).
--  F-16: motivo, fecha y usuario de anulacion en facturas.
--  F-17: tabla periodos_fiscales (bloqueo de anulacion/borrado).
--  F-21: tabla pagos_factura (desglose por moneda y medio).
--
--  El script es idempotente: puede ejecutarse de nuevo sin duplicar
--  columnas, indices, llaves foraneas ni datos.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. Columnas y llave foranea de anulacion (F-16)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS migrar_facturas_anulacion;

DELIMITER $$
CREATE PROCEDURE migrar_facturas_anulacion()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'facturas'
      AND COLUMN_NAME = 'motivo_anulacion'
  ) THEN
    ALTER TABLE facturas
      ADD COLUMN motivo_anulacion VARCHAR(200) NULL
        COMMENT 'Motivo explicado por el usuario que anula' AFTER estado;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'facturas'
      AND COLUMN_NAME = 'fecha_anulacion'
  ) THEN
    ALTER TABLE facturas
      ADD COLUMN fecha_anulacion DATETIME NULL AFTER motivo_anulacion;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'facturas'
      AND COLUMN_NAME = 'id_usuario_anulacion'
  ) THEN
    ALTER TABLE facturas
      ADD COLUMN id_usuario_anulacion INT NULL
        COMMENT 'Usuario que ejecuto la anulacion' AFTER fecha_anulacion;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'facturas'
      AND INDEX_NAME = 'idx_facturas_usuario_anulacion'
  ) THEN
    ALTER TABLE facturas
      ADD KEY idx_facturas_usuario_anulacion (id_usuario_anulacion);
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'facturas'
      AND CONSTRAINT_NAME = 'fk_facturas_usuario_anulacion'
  ) THEN
    ALTER TABLE facturas
      ADD CONSTRAINT fk_facturas_usuario_anulacion
        FOREIGN KEY (id_usuario_anulacion) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE ON DELETE SET NULL;
  END IF;
END $$
DELIMITER ;

CALL migrar_facturas_anulacion();
DROP PROCEDURE migrar_facturas_anulacion;

-- ---------------------------------------------------------------------
-- 2. Tablas nuevas (F-14, F-17, F-21)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagos_factura (
  id_pago             INT           NOT NULL AUTO_INCREMENT,
  id_factura          INT           NOT NULL,
  metodo              ENUM('EFECTIVO','PAGO_MOVIL','PUNTO_VENTA','TRANSFERENCIA','ZELLE','MIXTO')
                      NOT NULL DEFAULT 'EFECTIVO',
  moneda              ENUM('VES','USD') NOT NULL DEFAULT 'VES',
  monto               DECIMAL(12,3) NOT NULL COMMENT 'Monto en la moneda indicada',
  monto_ves           DECIMAL(12,3) NOT NULL COMMENT 'Equivalente en bolivares',
  PRIMARY KEY (id_pago),
  KEY idx_pagos_factura (id_factura),
  CONSTRAINT fk_pagos_factura
    FOREIGN KEY (id_factura) REFERENCES facturas (id_factura)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT chk_pagos_monto CHECK (monto >= 0 AND monto_ves >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS periodos_fiscales (
  anio               SMALLINT      NOT NULL,
  mes                TINYINT       NOT NULL,
  cerrado            TINYINT(1)    NOT NULL DEFAULT 0,
  fecha_cierre       DATETIME      NULL,
  id_usuario_cierre  INT           NULL,
  PRIMARY KEY (anio, mes),
  KEY idx_periodos_usuario (id_usuario_cierre),
  CONSTRAINT chk_periodos_mes CHECK (mes BETWEEN 1 AND 12),
  CONSTRAINT fk_periodos_usuario
    FOREIGN KEY (id_usuario_cierre) REFERENCES usuarios (id_usuario)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS secuencias_fiscales (
  nombre          VARCHAR(40)  NOT NULL,
  ultimo_numero   INT          NOT NULL DEFAULT 0,
  actualizado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Inicializar el consecutivo con el maximo historico conocido (F-14)
--    Incluye la bitacora una sola vez para no perder numeros si alguna
--    factura fue eliminada antes de la migracion.
-- ---------------------------------------------------------------------
INSERT INTO secuencias_fiscales (nombre, ultimo_numero)
SELECT 'FACTURA', COALESCE(MAX(numero), 0)
FROM (
  SELECT CAST(nro_factura_consecutivo AS UNSIGNED) AS numero
  FROM facturas
  UNION ALL
  SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(detalle_json, '$.nro_factura')) AS UNSIGNED)
  FROM bitacora_auditoria
  WHERE modulo = 'FACTURAS' AND accion = 'EMISION'
) AS numeracion
ON DUPLICATE KEY UPDATE
  ultimo_numero = GREATEST(secuencias_fiscales.ultimo_numero, VALUES(ultimo_numero));

-- ---------------------------------------------------------------------
-- 4. Completar el desglose de pagos de facturas anteriores (F-21)
--    Cada factura historica recibe una fila con su medio y total.
--    Las facturas MIXTO antiguas quedan como un unico pago en bolivares.
-- ---------------------------------------------------------------------
INSERT INTO pagos_factura (id_factura, metodo, moneda, monto, monto_ves)
SELECT f.id_factura,
       f.metodo_pago,
       CASE WHEN f.metodo_pago = 'ZELLE' THEN 'USD' ELSE 'VES' END,
       CASE WHEN f.metodo_pago = 'ZELLE'
            THEN ROUND(f.total_ves / NULLIF(f.tasa_bcv_aplicada, 0), 3)
            ELSE f.total_ves END,
       f.total_ves
FROM facturas f
LEFT JOIN pagos_factura p ON p.id_factura = f.id_factura
WHERE p.id_pago IS NULL;

-- ---------------------------------------------------------------------
-- 5. Actualizar la auditoria de anulacion con el motivo (F-16)
-- ---------------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_facturas_au;

DELIMITER $$
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
                        'total_nuevo', NEW.total_ves,
                        'motivo_anulacion', NEW.motivo_anulacion,
                        'fecha_anulacion', NEW.fecha_anulacion,
                        'id_usuario_anulacion', NEW.id_usuario_anulacion));
  END IF;
END $$
DELIMITER ;
