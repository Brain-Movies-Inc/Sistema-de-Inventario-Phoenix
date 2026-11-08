<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

final class ProductoModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    public function categorias(): array
    {
        return $this->db->query(
            "SELECT id_categoria, nombre_categoria, activo
             FROM categorias
             ORDER BY activo DESC, nombre_categoria ASC"
        )->fetchAll();
    }

    public function listar(
        string $busqueda = '',
        int $idCategoria = 0,
        bool $soloBajo = false,
        bool $incluirInactivos = false
    ): array {
        $where  = [];
        $params = [];

        if (!$incluirInactivos) {
            $where[] = 'p.activo = 1';
        }

        if ($busqueda !== '') {
            $where[] = '(p.nombre_producto LIKE :q1 OR p.codigo_qr_barras LIKE :q2)';
            $like = '%' . $busqueda . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
        }

        if ($idCategoria > 0) {
            $where[] = 'p.id_categoria = :categoria';
            $params[':categoria'] = $idCategoria;
        }

        if ($soloBajo) {
            $where[] = 'p.stock_actual <= p.stock_minimo';
        }

        $sql = "SELECT p.*, c.nombre_categoria
                FROM productos p
                JOIN categorias c ON c.id_categoria = p.id_categoria";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.activo DESC, p.nombre_producto ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, c.nombre_categoria
             FROM productos p
             JOIN categorias c ON c.id_categoria = p.id_categoria
             WHERE p.id_producto = :id"
        );
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function existeCodigo(string $codigo, int $exceptoId = 0): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM productos
             WHERE codigo_qr_barras = :codigo AND id_producto <> :id"
        );
        $stmt->execute([':codigo' => $codigo, ':id' => $exceptoId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO productos
                (codigo_qr_barras, nombre_producto, unidad_medida, costo_usd, precio_venta_usd,
                 alicuota_iva, stock_actual, stock_minimo, stock_maximo, id_categoria, activo)
             VALUES
                (:codigo, :nombre, :unidad, :costo, :precio,
                 :iva, :stock, :minimo, :maximo, :categoria, 1)"
        );
        $stmt->execute($this->parametros($datos));

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        $stmt = $this->db->prepare(
            "UPDATE productos SET
                codigo_qr_barras = :codigo,
                nombre_producto  = :nombre,
                unidad_medida    = :unidad,
                costo_usd        = :costo,
                precio_venta_usd = :precio,
                alicuota_iva     = :iva,
                stock_actual     = :stock,
                stock_minimo     = :minimo,
                stock_maximo     = :maximo,
                id_categoria     = :categoria
             WHERE id_producto = :id"
        );
        $stmt->execute($this->parametros($datos) + [':id' => $id]);
    }

    public function cambiarActivo(int $id, int $activo): void
    {
        $stmt = $this->db->prepare(
            "UPDATE productos SET activo = :activo WHERE id_producto = :id"
        );
        $stmt->execute([':activo' => $activo ? 1 : 0, ':id' => $id]);
    }

    /**
     * Elimina un producto solo si no tiene movimientos asociados.
     *
     * Las claves foráneas de detalle_factura, detalle_compra y merma_ajustes
     * son RESTRICT: borrar un producto con historial rompería la trazabilidad.
     * En ese caso se avisa y se sugiere desactivarlo.
     */
    public function eliminar(int $id): void
    {
        $stmt = $this->db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM detalle_factura WHERE id_producto = :f)
              + (SELECT COUNT(*) FROM detalle_compra WHERE id_producto = :c)
              + (SELECT COUNT(*) FROM merma_ajustes WHERE id_producto = :m) AS total"
        );
        $stmt->execute([':f' => $id, ':c' => $id, ':m' => $id]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException(
                'Este producto tiene ventas, compras o mermas registradas y no se puede eliminar. ' .
                'Desactívalo para que deje de aparecer en el punto de venta.'
            );
        }

        $stmt = $this->db->prepare("DELETE FROM productos WHERE id_producto = :id");
        $stmt->execute([':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':codigo'    => $datos['codigo_qr_barras'],
            ':nombre'    => $datos['nombre_producto'],
            ':unidad'    => $datos['unidad_medida'],
            ':costo'     => number_format((float) $datos['costo_usd'], 3, '.', ''),
            ':precio'    => number_format((float) $datos['precio_venta_usd'], 3, '.', ''),
            ':iva'       => number_format((float) $datos['alicuota_iva'], 2, '.', ''),
            ':stock'     => number_format((float) $datos['stock_actual'], 3, '.', ''),
            ':minimo'    => number_format((float) $datos['stock_minimo'], 3, '.', ''),
            ':maximo'    => $datos['stock_maximo'] === null
                ? null
                : number_format((float) $datos['stock_maximo'], 3, '.', ''),
            ':categoria' => (int) $datos['id_categoria'],
        ];
    }
}
