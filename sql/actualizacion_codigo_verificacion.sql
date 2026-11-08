-- =====================================================================
-- Actualización: código aleatorio de verificación por factura
-- =====================================================================
-- El número de factura y el número de control siguen siendo consecutivos
-- (requisito fiscal). Este código es adicional: permite comprobar que un
-- comprobante fue emitido por el sistema sin romper la numeración.
--
-- Aplicar una sola vez sobre una base existente:
--   mysql -u root de_todito_reyes < sql/actualizacion_codigo_verificacion.sql
-- =====================================================================

ALTER TABLE facturas
  ADD COLUMN codigo_verificacion VARCHAR(10) NULL DEFAULT NULL
    AFTER nro_control_fiscal,
  ADD UNIQUE KEY uq_facturas_codigo (codigo_verificacion);

-- Rellena las facturas ya emitidas con un código aleatorio de 8 caracteres.
UPDATE facturas
SET codigo_verificacion = UPPER(
      SUBSTRING(
        REPLACE(
          REPLACE(
            REPLACE(MD5(CONCAT(id_factura, RAND(), NOW())), '0', '2'),
          'O', 'Q'),
        'I', 'J'),
        1, 8
      )
    )
WHERE codigo_verificacion IS NULL;

ALTER TABLE facturas
  MODIFY codigo_verificacion VARCHAR(10) NOT NULL;
