<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

/**
 * Consultas de lectura de facturación: historial, comprobantes y catálogos
 * del punto de venta.
 */
final class FacturaConsultaModel
{
    use ConsultasFactura;

    protected PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    public function tasaVigente(): ?array
    {
        // Una sola implementación de la consulta de la tasa vigente.
        return (new TasaModel())->vigente();
    }

    /**
     * Clientes para el POS, con búsqueda por nombre o RIF.
     *
     * @return array<int, array<string, mixed>>
     */
    public function clientes(string $busqueda = '', int $limite = 100): array
    {
        $limite = max(1, min(200, $limite));
        $where  = '';
        $params = [];

        if ($busqueda !== '') {
            $where  = ' WHERE (nombre_razon_social LIKE :q1 OR rif_cedula LIKE :q2)';
            $like   = '%' . $busqueda . '%';
            $params = [':q1' => $like, ':q2' => $like];
        }

        $stmt = $this->db->prepare(
            "SELECT id_cliente, rif_cedula, nombre_razon_social
             FROM clientes
             {$where}
             ORDER BY nombre_razon_social ASC
             LIMIT :limite"
        );

        foreach ($params as $marcador => $valor) {
            $stmt->bindValue($marcador, $valor);
        }

        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function clientePorId(int $idCliente): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id_cliente, rif_cedula, nombre_razon_social
             FROM clientes
             WHERE id_cliente = :id"
        );
        $stmt->execute([':id' => $idCliente]);
        $cliente = $stmt->fetch();

        return $cliente ?: null;
    }

    /**
     * Productos vendibles, paginados y con búsqueda por nombre o código.
     *
     * @return array<int, array<string, mixed>>
     */
    public function productosVendibles(
        string $busqueda = '',
        int $limite = 60,
        int $offset = 0
    ): array {
        $limite = max(1, min(200, $limite));
        $offset = max(0, $offset);
        [$where, $params] = $this->filtrosProductos($busqueda);

        $stmt = $this->db->prepare(
            "SELECT id_producto, codigo_qr_barras, nombre_producto, unidad_medida,
                    precio_venta_usd, alicuota_iva, stock_actual, stock_minimo
             FROM productos
             WHERE {$where}
             ORDER BY nombre_producto ASC
             LIMIT :limite OFFSET :offset"
        );

        foreach ($params as $marcador => $valor) {
            $stmt->bindValue($marcador, $valor);
        }

        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function contarProductosVendibles(string $busqueda = ''): int
    {
        [$where, $params] = $this->filtrosProductos($busqueda);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM productos WHERE {$where}"
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Productos concretos para reconstruir un carrito tras un error de emisión.
     *
     * @param array<int, int|string> $ids
     * @return array<int, array<string, mixed>>
     */
    public function productosPorIds(array $ids): array
    {
        $limpios = [];

        foreach ($ids as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $id = (int) $id;

                if ($id > 0) {
                    $limpios[$id] = $id;
                }
            }

            if (count($limpios) >= 500) {
                break;
            }
        }

        if (!$limpios) {
            return [];
        }

        $marcadores = [];
        $params     = [];

        foreach (array_values($limpios) as $indice => $id) {
            $marcador = ':id' . $indice;
            $marcadores[] = $marcador;
            $params[$marcador] = $id;
        }

        $stmt = $this->db->prepare(
            "SELECT id_producto, codigo_qr_barras, nombre_producto, unidad_medida,
                    precio_venta_usd, alicuota_iva, stock_actual, stock_minimo
             FROM productos
             WHERE activo = 1 AND id_producto IN (" . implode(', ', $marcadores) . ")
             ORDER BY nombre_producto ASC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function filtrosProductos(string $busqueda): array
    {
        if ($busqueda === '') {
            return ['activo = 1', []];
        }

        return [
            'activo = 1 AND (nombre_producto LIKE :q1 OR codigo_qr_barras LIKE :q2)',
            [':q1' => '%' . $busqueda . '%', ':q2' => '%' . $busqueda . '%'],
        ];
    }

    public function listar(
        string $estado = '',
        string $busqueda = '',
        int $limite = 100,
        ?string $desde = null,
        ?string $hasta = null,
        ?int $idUsuario = null
    ): array {
        [$where, $params] = $this->filtros($estado, $busqueda, $desde, $hasta);

        if ($idUsuario !== null) {
            $where[] = 'f.id_usuario = :usuario_alcance';
            $params[':usuario_alcance'] = $idUsuario;
        }

        $sql = $this->selectFacturas();

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY f.id_factura DESC LIMIT :limite';

        $stmt = $this->db->prepare($sql);

        foreach ($params as $marcador => $valor) {
            $stmt->bindValue($marcador, $valor);
        }

        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT f.*,
                    cl.rif_cedula,
                    cl.nombre_razon_social AS cliente,
                    cl.direccion_fiscal,
                    cl.telefono AS cliente_telefono,
                    u.usuario_login AS cajero_login,
                    u.nombre_completo AS cajero_nombre,
                    ua.usuario_login AS anulada_por_login,
                    ua.nombre_completo AS anulada_por_nombre
             FROM facturas f
             JOIN clientes cl ON cl.id_cliente = f.id_cliente
             JOIN usuarios u ON u.id_usuario = f.id_usuario
             LEFT JOIN usuarios ua ON ua.id_usuario = f.id_usuario_anulacion
             WHERE f.id_factura = :id"
        );
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function lineas(int $idFactura): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*, p.nombre_producto, p.codigo_qr_barras, p.unidad_medida
             FROM detalle_factura d
             JOIN productos p ON p.id_producto = d.id_producto
             WHERE d.id_factura = :id
             ORDER BY d.id_detalle_factura ASC"
        );
        $stmt->execute([':id' => $idFactura]);

        return $stmt->fetchAll();
    }

    public function resumenHoy(?int $idUsuario = null): array
    {
        $where  = "estado = 'EMITIDA'
                   AND fecha_emision >= CURDATE()
                   AND fecha_emision < (CURDATE() + INTERVAL 1 DAY)";
        $params = [];

        if ($idUsuario !== null) {
            $where .= ' AND id_usuario = :usuario';
            $params[':usuario'] = $idUsuario;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cantidad,
                    COALESCE(SUM(total_ves), 0) AS total_ves,
                    COALESCE(SUM(total_usd), 0) AS total_usd
             FROM facturas
             WHERE {$where}"
        );
        $stmt->execute($params);
        $fila = $stmt->fetch();

        return $fila ?: ['cantidad' => 0, 'total_ves' => 0, 'total_usd' => 0];
    }

    /**
     * Desglose de pagos registrado para una factura (F-21).
     *
     * @return array<int, array<string, mixed>>
     */
    public function pagos(int $idFactura): array
    {
        $stmt = $this->db->prepare(
            "SELECT id_pago, metodo, moneda, monto, monto_ves
             FROM pagos_factura
             WHERE id_factura = :id
             ORDER BY id_pago ASC"
        );
        $stmt->execute([':id' => $idFactura]);

        return $stmt->fetchAll();
    }
}
