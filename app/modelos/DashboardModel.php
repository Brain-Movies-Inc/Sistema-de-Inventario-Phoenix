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

    /**
     * Resumen operativo en tres consultas: una para los conteos pequeños,
     * una para el inventario y otra para las ventas. Así cada tabla se
     * recorre una sola vez en lugar de repetir subconsultas escalares.
     */
    public function resumen(): array
    {
        $conteos = $this->db->query(
            "SELECT 'categorias' AS clave, COUNT(*) AS entero, 0 AS monto
             FROM categorias
             WHERE activo = 1
             UNION ALL
             SELECT 'clientes', COUNT(*), 0
             FROM clientes
             UNION ALL
             SELECT 'usuarios', COUNT(*), 0
             FROM usuarios
             WHERE activo = 1
             UNION ALL
             SELECT 'mermas', COUNT(*), COALESCE(SUM(cantidad_baja * costo_unitario_usd), 0)
             FROM merma_ajustes"
        )->fetchAll();

        $resumen = [
            'categorias'         => 0,
            'clientes'           => 0,
            'usuarios'           => 0,
            'mermas'             => 0,
            'productos'          => 0,
            'facturas'           => 0,
            'valor_costo_usd'    => 0.0,
            'valor_venta_usd'    => 0.0,
            'total_vendido_usd'  => 0.0,
            'total_vendido_ves'  => 0.0,
            'perdidas_usd'       => 0.0,
        ];

        foreach ($conteos as $fila) {
            $clave = (string) $fila['clave'];
            $resumen[$clave] = (int) $fila['entero'];

            if ($clave === 'mermas') {
                $resumen['perdidas_usd'] = (float) $fila['monto'];
            }
        }

        $inventario = $this->db->query(
            "SELECT COUNT(*) AS productos,
                    COALESCE(SUM(costo_usd * stock_actual), 0) AS valor_costo_usd,
                    COALESCE(SUM(precio_venta_usd * stock_actual), 0) AS valor_venta_usd
             FROM productos
             WHERE activo = 1"
        )->fetch() ?: [];

        $ventas = $this->db->query(
            "SELECT COUNT(*) AS facturas,
                    COALESCE(SUM(total_usd), 0) AS total_vendido_usd,
                    COALESCE(SUM(total_ves), 0) AS total_vendido_ves
             FROM facturas
             WHERE estado = 'EMITIDA'"
        )->fetch() ?: [];

        $resumen['productos']         = (int) ($inventario['productos'] ?? 0);
        $resumen['valor_costo_usd']   = (float) ($inventario['valor_costo_usd'] ?? 0);
        $resumen['valor_venta_usd']   = (float) ($inventario['valor_venta_usd'] ?? 0);
        $resumen['facturas']          = (int) ($ventas['facturas'] ?? 0);
        $resumen['total_vendido_usd'] = (float) ($ventas['total_vendido_usd'] ?? 0);
        $resumen['total_vendido_ves'] = (float) ($ventas['total_vendido_ves'] ?? 0);

        return $resumen;
    }

    public function tasaVigente(): ?array
    {
        // Una sola implementación de la consulta de la tasa vigente.
        return (new TasaModel())->vigente();
    }

    /**
     * Resumen de mermas para el panel de inicio.
     *
     * Devuelve los totales acumulados, lo del mes en curso y los últimos
     * movimientos. El costo solo se muestra al ADMIN; el CAJERO recibe los
     * mismos datos pero su vista no los imprime.
     *
     * @return array{cantidad:int, perdida_usd:float, cantidad_mes:int,
     *               perdida_mes_usd:float, ultimas:array<int, array<string, mixed>>}
     */
    public function resumenMermas(int $limite = 5): array
    {
        $totales = $this->db->query(
            "SELECT COUNT(*) AS cantidad,
                    COALESCE(SUM(cantidad_baja * costo_unitario_usd), 0) AS perdida_usd,
                    COUNT(CASE WHEN YEAR(fecha_registro) = YEAR(CURDATE())
                                AND MONTH(fecha_registro) = MONTH(CURDATE())
                               THEN 1 END) AS cantidad_mes,
                    COALESCE(SUM(CASE WHEN YEAR(fecha_registro) = YEAR(CURDATE())
                                       AND MONTH(fecha_registro) = MONTH(CURDATE())
                                      THEN cantidad_baja * costo_unitario_usd END), 0) AS perdida_mes_usd
             FROM merma_ajustes"
        )->fetch() ?: [];

        $stmt = $this->db->prepare(
            "SELECT m.id_merma, m.fecha_registro, m.cantidad_baja, m.costo_unitario_usd,
                    m.tipo_motivo, p.nombre_producto, p.unidad_medida,
                    u.nombre_completo AS usuario
             FROM merma_ajustes m
             JOIN productos p ON p.id_producto = m.id_producto
             JOIN usuarios u ON u.id_usuario = m.id_usuario
             ORDER BY m.id_merma DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        return [
            'cantidad'        => (int) ($totales['cantidad'] ?? 0),
            'perdida_usd'     => (float) ($totales['perdida_usd'] ?? 0),
            'cantidad_mes'    => (int) ($totales['cantidad_mes'] ?? 0),
            'perdida_mes_usd' => (float) ($totales['perdida_mes_usd'] ?? 0),
            'ultimas'         => $stmt->fetchAll(),
        ];
    }

    public function stockBajo(int $limite = 8): array
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM v_stock_bajo
             ORDER BY faltante DESC, stock_actual ASC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function ultimasFacturas(int $limite = 6): array
    {
        $stmt = $this->db->prepare(
            "SELECT f.id_factura, f.nro_factura_consecutivo, f.fecha_emision, f.estado,
                    f.total_ves, f.total_usd, cl.nombre_razon_social AS cliente
             FROM facturas f
             JOIN clientes cl ON cl.id_cliente = f.id_cliente
             ORDER BY f.id_factura DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function topProductos(int $limite = 5): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.nombre_producto, p.unidad_medida, SUM(d.cantidad_o_peso) AS total_vendido
             FROM detalle_factura d
             JOIN facturas f ON f.id_factura = d.id_factura AND f.estado = 'EMITIDA'
             JOIN productos p ON p.id_producto = d.id_producto
             GROUP BY p.id_producto, p.nombre_producto, p.unidad_medida
             ORDER BY total_vendido DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Resumen personal del cajero: solo sus ventas emitidas.
     *
     * No incluye inventario, costos, mermas ni datos de otros usuarios.
     */
    public function resumenCajero(int $idUsuario): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(CASE WHEN DATE(fecha_emision) = CURDATE() THEN 1 END) AS facturas_hoy,
                COALESCE(SUM(CASE WHEN DATE(fecha_emision) = CURDATE() THEN total_ves END), 0) AS vendido_hoy_ves,
                COALESCE(SUM(CASE WHEN DATE(fecha_emision) = CURDATE() THEN total_usd END), 0) AS vendido_hoy_usd,
                COUNT(CASE WHEN YEAR(fecha_emision) = YEAR(CURDATE())
                            AND MONTH(fecha_emision) = MONTH(CURDATE())
                           THEN 1 END) AS facturas_mes,
                COALESCE(SUM(CASE WHEN YEAR(fecha_emision) = YEAR(CURDATE())
                                   AND MONTH(fecha_emision) = MONTH(CURDATE())
                                  THEN total_ves END), 0) AS vendido_mes_ves,
                COUNT(*) AS facturas_total,
                COALESCE(SUM(total_ves), 0) AS vendido_total_ves,
                MAX(fecha_emision) AS ultima_venta
             FROM facturas
             WHERE estado = 'EMITIDA' AND id_usuario = :id_usuario"
        );
        $stmt->execute([':id_usuario' => $idUsuario]);

        $resumen = $stmt->fetch() ?: [];

        return [
            'facturas_hoy'      => (int) ($resumen['facturas_hoy'] ?? 0),
            'vendido_hoy_ves'   => (float) ($resumen['vendido_hoy_ves'] ?? 0),
            'vendido_hoy_usd'   => (float) ($resumen['vendido_hoy_usd'] ?? 0),
            'facturas_mes'      => (int) ($resumen['facturas_mes'] ?? 0),
            'vendido_mes_ves'   => (float) ($resumen['vendido_mes_ves'] ?? 0),
            'facturas_total'    => (int) ($resumen['facturas_total'] ?? 0),
            'vendido_total_ves' => (float) ($resumen['vendido_total_ves'] ?? 0),
            'ultima_venta'      => $resumen['ultima_venta'] ?? null,
        ];
    }

    /**
     * Últimas facturas emitidas por el cajero indicado.
     */
    public function ultimasFacturasCajero(int $idUsuario, int $limite = 8): array
    {
        $stmt = $this->db->prepare(
            "SELECT f.id_factura, f.nro_factura_consecutivo, f.fecha_emision, f.estado,
                    f.total_ves, f.total_usd, cl.nombre_razon_social AS cliente
             FROM facturas f
             JOIN clientes cl ON cl.id_cliente = f.id_cliente
             WHERE f.id_usuario = :id_usuario
             ORDER BY f.id_factura DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
