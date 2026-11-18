<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

/**
 * Construcción de consultas compartidas del historial de facturas.
 *
 * Lo usan FacturaConsultaModel (listado) y FacturaExportModel (CSV) para que
 * los filtros y el SELECT no se dupliquen entre ambos.
 */
trait ConsultasFactura
{
    /**
     * Arma el WHERE compartido por el listado y las exportaciones.
     *
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    protected function filtros(
        string $estado,
        string $busqueda,
        ?string $desde,
        ?string $hasta
    ): array {
        $where  = [];
        $params = [];

        if ($estado === 'EMITIDA' || $estado === 'ANULADA') {
            $where[] = 'f.estado = :estado';
            $params[':estado'] = $estado;
        }

        if ($busqueda !== '') {
            $where[] = '(f.nro_factura_consecutivo LIKE :q1
                         OR f.nro_control_fiscal LIKE :q2
                         OR f.codigo_verificacion LIKE :q3
                         OR cl.nombre_razon_social LIKE :q4
                         OR cl.rif_cedula LIKE :q5)';
            $like = '%' . $busqueda . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
            $params[':q5'] = $like;
        }

        if ($desde !== null && $desde !== '') {
            $where[] = 'f.fecha_emision >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }

        if ($hasta !== null && $hasta !== '') {
            $where[] = 'f.fecha_emision <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        return [$where, $params];
    }

    protected function selectFacturas(): string
    {
        return "SELECT f.id_factura, f.nro_factura_consecutivo, f.nro_control_fiscal,
                       f.codigo_verificacion,
                       f.fecha_emision, f.estado, f.metodo_pago,
                       f.subtotal_exento_ves, f.base_imponible_ves,
                       f.monto_iva_ves, f.monto_igtf_ves,
                       f.total_ves, f.total_usd, f.tasa_bcv_aplicada,
                       cl.nombre_razon_social AS cliente, cl.rif_cedula,
                       u.usuario_login AS cajero,
                       (SELECT COUNT(*) FROM detalle_factura d WHERE d.id_factura = f.id_factura) AS lineas
                FROM facturas f
                JOIN clientes cl ON cl.id_cliente = f.id_cliente
                JOIN usuarios u ON u.id_usuario = f.id_usuario";
    }
}
