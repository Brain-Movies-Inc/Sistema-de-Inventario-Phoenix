<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

/**
 * Exportación del historial de ventas a CSV.
 *
 * Devuelve las filas como generadores para que el CSV se escriba a medida que
 * llegan, sin cargar el historial completo en memoria.
 */
final class FacturaExportModel
{
    use ConsultasFactura;

    protected PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /**
     * Devuelve las facturas del resumen como un flujo de filas.
     *
     * No se usa fetchAll(): el CSV se escribe a medida que llegan las filas
     * para que el historial completo no tenga que caber en memoria.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function exportarResumen(
        string $estado = '',
        string $busqueda = '',
        ?string $desde = null,
        ?string $hasta = null
    ): iterable {
        [$where, $params] = $this->filtros($estado, $busqueda, $desde, $hasta);

        $sql = $this->selectFacturas();

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY f.id_factura ASC';

        return $this->consultaExportacion($sql, $params);
    }

    /**
     * Devuelve el detalle de líneas como un flujo de filas.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function exportarDetalle(
        string $estado = '',
        string $busqueda = '',
        ?string $desde = null,
        ?string $hasta = null
    ): iterable {
        [$where, $params] = $this->filtros($estado, $busqueda, $desde, $hasta);

        $sql = "SELECT f.nro_factura_consecutivo, f.nro_control_fiscal, f.fecha_emision,
                       f.estado, f.metodo_pago, f.tasa_bcv_aplicada,
                       cl.rif_cedula, cl.nombre_razon_social AS cliente,
                       u.usuario_login AS cajero,
                       p.codigo_qr_barras, p.nombre_producto, p.unidad_medida,
                       d.cantidad_o_peso, d.precio_unitario_usd, d.precio_unitario_ves,
                       d.alicuota_iva_item, d.monto_iva_item_ves, d.subtotal_item_ves
                FROM facturas f
                JOIN clientes cl ON cl.id_cliente = f.id_cliente
                JOIN usuarios u ON u.id_usuario = f.id_usuario
                JOIN detalle_factura d ON d.id_factura = f.id_factura
                JOIN productos p ON p.id_producto = d.id_producto";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY f.id_factura ASC, d.id_detalle_factura ASC';

        return $this->consultaExportacion($sql, $params);
    }

    /**
     * Prepara la consulta y devuelve un generador que la recorre fila a fila.
     *
     * La preparación y ejecución ocurren aquí, antes de enviar cabeceras HTTP,
     * para que un error de SQL se pueda mostrar como aviso en lugar de dejar
     * una descarga a medias. No se usa fetchAll(): el CSV se escribe a medida
     * que llegan las filas.
     *
     * @param array<string, mixed> $params
     * @return Generator<int, array<string, mixed>>
     */
    private function consultaExportacion(string $sql, array $params): Generator
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->iterarExportacion($stmt);
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function iterarExportacion(PDOStatement $stmt): Generator
    {
        while (($fila = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            yield $fila;
        }

        $stmt->closeCursor();
    }
}
