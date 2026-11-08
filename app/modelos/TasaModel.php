<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

/**
 * Tasas de cambio BCV (Bs por 1 USD).
 */
final class TasaModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    public function vigente(): ?array
    {
        $fila = $this->db->query(
            "SELECT t.id_tasa, t.tasa_bcv_ves, t.fecha_vigencia, t.observacion,
                    t.id_usuario, u.usuario_login, u.nombre_completo AS usuario_nombre
             FROM tasas_cambio_bcv t
             JOIN usuarios u ON u.id_usuario = t.id_usuario
             ORDER BY t.fecha_vigencia DESC
             LIMIT 1"
        )->fetch();

        return $fila ?: null;
    }

    public function listar(int $limite = 100): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.id_tasa, t.tasa_bcv_ves, t.fecha_vigencia, t.observacion,
                    t.id_usuario, u.usuario_login, u.nombre_completo AS usuario_nombre
             FROM tasas_cambio_bcv t
             JOIN usuarios u ON u.id_usuario = t.id_usuario
             ORDER BY t.fecha_vigencia DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id_tasa, tasa_bcv_ves, fecha_vigencia, observacion, id_usuario
             FROM tasas_cambio_bcv
             WHERE id_tasa = :id"
        );
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function existeFecha(string $fecha, int $exceptoId = 0): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM tasas_cambio_bcv
             WHERE fecha_vigencia = :fecha AND id_tasa <> :id"
        );
        $stmt->execute([':fecha' => $fecha, ':id' => $exceptoId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(array $datos, int $idUsuario): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO tasas_cambio_bcv
                (tasa_bcv_ves, fecha_vigencia, observacion, id_usuario)
             VALUES
                (:tasa, :fecha, :observacion, :usuario)"
        );
        $stmt->execute([
            ':tasa'        => $this->decimal($datos['tasa_bcv_ves']),
            ':fecha'       => $datos['fecha_vigencia'],
            ':observacion' => $datos['observacion'] !== '' ? $datos['observacion'] : null,
            ':usuario'     => $idUsuario,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        $stmt = $this->db->prepare(
            "UPDATE tasas_cambio_bcv
             SET tasa_bcv_ves = :tasa,
                 fecha_vigencia = :fecha,
                 observacion = :observacion
             WHERE id_tasa = :id"
        );
        $stmt->execute([
            ':tasa'        => $this->decimal($datos['tasa_bcv_ves']),
            ':fecha'       => $datos['fecha_vigencia'],
            ':observacion' => $datos['observacion'] !== '' ? $datos['observacion'] : null,
            ':id'          => $id,
        ]);
    }

    private function decimal($valor): string
    {
        return number_format((float) $valor, 4, '.', '');
    }
}
