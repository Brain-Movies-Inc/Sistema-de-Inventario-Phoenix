<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

/**
 * Mermas y ajustes de inventario.
 *
 * Registrar una merma descuenta stock y guarda el costo del momento para
 * poder valorar la pérdida sin alterar el historial si el costo cambia luego.
 */
final class MermaModel
{
    public const MOTIVOS = [
        'VENCIMIENTO'       => 'Vencimiento',
        'DAÑO'              => 'Daño',
        'PERDIDA'           => 'Pérdida',
        'ROBO'              => 'Robo',
        'AJUSTE_INVENTARIO' => 'Ajuste de inventario',
        'OTRO'              => 'Otro',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    public function productos(): array
    {
        return $this->db->query(
            "SELECT id_producto, codigo_qr_barras, nombre_producto, unidad_medida,
                    costo_usd, stock_actual
             FROM productos
             WHERE activo = 1
             ORDER BY nombre_producto ASC"
        )->fetchAll();
    }

    public function listar(int $limite = 100): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.id_merma, m.fecha_registro, m.cantidad_baja, m.costo_unitario_usd,
                    m.tipo_motivo, m.observacion,
                    p.nombre_producto, p.unidad_medida,
                    u.nombre_completo AS usuario
             FROM merma_ajustes m
             JOIN productos p ON p.id_producto = m.id_producto
             JOIN usuarios u ON u.id_usuario = m.id_usuario
             ORDER BY m.id_merma DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Registra la merma y descuenta el stock en una sola transacción.
     *
     * @return array{producto:string, cantidad:float, stock:float, perdida_usd:float}
     */
    public function registrar(
        int $idProducto,
        float $cantidad,
        string $motivo,
        string $observacion,
        int $idUsuario
    ): array {
        if ($idProducto <= 0) {
            throw new InvalidArgumentException('Selecciona un producto.');
        }

        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad de merma debe ser mayor que cero.');
        }

        if (!isset(self::MOTIVOS[$motivo])) {
            throw new InvalidArgumentException('El motivo seleccionado no es válido.');
        }

        if (mb_strlen($observacion) > 200) {
            throw new InvalidArgumentException('La observación no puede superar los 200 caracteres.');
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "SELECT nombre_producto, unidad_medida, costo_usd, stock_actual
                 FROM productos
                 WHERE id_producto = :id AND activo = 1
                 FOR UPDATE"
            );
            $stmt->execute([':id' => $idProducto]);
            $producto = $stmt->fetch();

            if (!$producto) {
                throw new RuntimeException('El producto seleccionado no existe o está inactivo.');
            }

            $stock = (float) $producto['stock_actual'];

            if ($cantidad > $stock + 0.0001) {
                throw new RuntimeException(
                    'La cantidad supera el stock disponible de "' . $producto['nombre_producto'] .
                    '". Disponible: ' . cantidad($stock) . ' ' . $producto['unidad_medida'] . '.'
                );
            }

            $costo = (float) $producto['costo_usd'];

            $stmt = $this->db->prepare(
                "INSERT INTO merma_ajustes
                    (id_producto, id_usuario, cantidad_baja, costo_unitario_usd, tipo_motivo, observacion)
                 VALUES
                    (:producto, :usuario, :cantidad, :costo, :motivo, :observacion)"
            );
            $stmt->execute([
                ':producto'    => $idProducto,
                ':usuario'     => $idUsuario,
                ':cantidad'    => number_format($cantidad, 3, '.', ''),
                ':costo'       => number_format($costo, 3, '.', ''),
                ':motivo'      => $motivo,
                ':observacion' => $observacion !== '' ? $observacion : null,
            ]);

            $this->db->prepare(
                "UPDATE productos
                 SET stock_actual = stock_actual - :cantidad
                 WHERE id_producto = :id"
            )->execute([
                ':cantidad' => number_format($cantidad, 3, '.', ''),
                ':id'       => $idProducto,
            ]);

            $this->db->commit();

            return [
                'producto'   => (string) $producto['nombre_producto'],
                'cantidad'   => $cantidad,
                'stock'      => $stock - $cantidad,
                'perdida_usd' => round($cantidad * $costo, 3),
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
}
