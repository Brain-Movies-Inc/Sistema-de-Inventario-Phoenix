<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

final class DashboardModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    public function resumen(): array
    {
        $sql = "SELECT
            (SELECT COUNT(*) FROM categorias WHERE activo = 1) AS categorias,
            (SELECT COUNT(*) FROM productos WHERE activo = 1) AS productos,
            (SELECT COUNT(*) FROM clientes) AS clientes,
            (SELECT COUNT(*) FROM usuarios WHERE activo = 1) AS usuarios,
            (SELECT COUNT(*) FROM facturas WHERE estado = 'EMITIDA') AS facturas,
            (SELECT COUNT(*) FROM merma_ajustes) AS mermas,
            (SELECT COALESCE(SUM(costo_usd * stock_actual), 0) FROM productos WHERE activo = 1) AS valor_costo_usd,
            (SELECT COALESCE(SUM(precio_venta_usd * stock_actual), 0) FROM productos WHERE activo = 1) AS valor_venta_usd,
            (SELECT COALESCE(SUM(total_usd), 0) FROM facturas WHERE estado = 'EMITIDA') AS total_vendido_usd,
            (SELECT COALESCE(SUM(total_ves), 0) FROM facturas WHERE estado = 'EMITIDA') AS total_vendido_ves,
            (SELECT COALESCE(SUM(cantidad_baja * costo_unitario_usd), 0) FROM merma_ajustes) AS perdidas_usd";

        return $this->db->query($sql)->fetch() ?: [];
    }

    public function tasaVigente(): ?array
    {
        $fila = $this->db->query(
            "SELECT tasa_bcv_ves, fecha_vigencia, observacion
             FROM tasas_cambio_bcv
             ORDER BY fecha_vigencia DESC
             LIMIT 1"
        )->fetch();

        return $fila ?: null;
    }

    public function stockBajo(int $limite = 8): array
    {
        $limite = max(1, $limite);

        return $this->db->query(
            "SELECT *
             FROM v_stock_bajo
             ORDER BY faltante DESC, stock_actual ASC
             LIMIT {$limite}"
        )->fetchAll();
    }

    public function ultimasFacturas(int $limite = 6): array
    {
        $limite = max(1, $limite);

        return $this->db->query(
            "SELECT f.id_factura, f.nro_factura_consecutivo, f.fecha_emision, f.estado,
                    f.total_ves, f.total_usd, cl.nombre_razon_social AS cliente
             FROM facturas f
             JOIN clientes cl ON cl.id_cliente = f.id_cliente
             ORDER BY f.id_factura DESC
             LIMIT {$limite}"
        )->fetchAll();
    }

    public function topProductos(int $limite = 5): array
    {
        $limite = max(1, $limite);

        return $this->db->query(
            "SELECT p.nombre_producto, p.unidad_medida, SUM(d.cantidad_o_peso) AS total_vendido
             FROM detalle_factura d
             JOIN facturas f ON f.id_factura = d.id_factura AND f.estado = 'EMITIDA'
             JOIN productos p ON p.id_producto = d.id_producto
             GROUP BY p.id_producto, p.nombre_producto, p.unidad_medida
             ORDER BY total_vendido DESC
             LIMIT {$limite}"
        )->fetchAll();
    }
}
