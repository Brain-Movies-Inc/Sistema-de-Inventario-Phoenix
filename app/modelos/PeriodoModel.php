<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

/**
 * Períodos fiscales mensuales (F-17).
 *
 * Un período cerrado impide anular o eliminar facturas cuya fecha de emisión
 * caiga dentro de ese mes. El cierre y la reapertura quedan registrados en la
 * bitácora de auditoría con el usuario que los ejecutó.
 */
final class PeriodoModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /**
     * Devuelve los 12 meses de un año con su estado y sus totales.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listar(int $anio): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.mes,
                    COALESCE(p.cerrado, 0) AS cerrado,
                    p.fecha_cierre,
                    u.usuario_login AS cerrado_por,
                    COALESCE(f.cantidad, 0) AS cantidad,
                    COALESCE(f.anuladas, 0) AS anuladas,
                    COALESCE(f.total_ves, 0) AS total_ves,
                    COALESCE(f.total_usd, 0) AS total_usd
             FROM (
                 SELECT 1 AS mes UNION ALL SELECT 2 UNION ALL SELECT 3
                 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
                 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
                 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
             ) m
             LEFT JOIN periodos_fiscales p
                    ON p.anio = ? AND p.mes = m.mes
             LEFT JOIN usuarios u
                    ON u.id_usuario = p.id_usuario_cierre
             LEFT JOIN (
                 SELECT MONTH(fecha_emision) AS mes,
                        COUNT(*) AS cantidad,
                        SUM(CASE WHEN estado = 'ANULADA' THEN 1 ELSE 0 END) AS anuladas,
                        COALESCE(SUM(CASE WHEN estado = 'EMITIDA' THEN total_ves ELSE 0 END), 0) AS total_ves,
                        COALESCE(SUM(CASE WHEN estado = 'EMITIDA' THEN total_usd ELSE 0 END), 0) AS total_usd
                 FROM facturas
                 WHERE YEAR(fecha_emision) = ?
                 GROUP BY MONTH(fecha_emision)
             ) f ON f.mes = m.mes
             ORDER BY m.mes ASC"
        );
        $stmt->execute([$anio, $anio]);

        return array_map(static function (array $fila) use ($anio): array {
            return [
                'anio'        => $anio,
                'mes'         => (int) $fila['mes'],
                'cerrado'     => (int) $fila['cerrado'] === 1,
                'fecha_cierre' => $fila['fecha_cierre'],
                'cerrado_por' => $fila['cerrado_por'],
                'cantidad'    => (int) $fila['cantidad'],
                'anuladas'    => (int) $fila['anuladas'],
                'total_ves'   => (float) $fila['total_ves'],
                'total_usd'   => (float) $fila['total_usd'],
            ];
        }, $stmt->fetchAll());
    }

    /**
     * Años que tienen facturas, períodos registrados o el año actual.
     *
     * @return array<int, int>
     */
    public function aniosDisponibles(): array
    {
        $stmt = $this->db->query(
            "SELECT anio
             FROM (
                 SELECT DISTINCT YEAR(fecha_emision) AS anio FROM facturas
                 UNION
                 SELECT DISTINCT anio FROM periodos_fiscales
                 UNION
                 SELECT YEAR(CURDATE()) AS anio
             ) AS anios
             WHERE anio IS NOT NULL
             ORDER BY anio DESC"
        );

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function estaCerrado(int $anio, int $mes): bool
    {
        $stmt = $this->db->prepare(
            "SELECT cerrado
             FROM periodos_fiscales
             WHERE anio = :anio AND mes = :mes
             LIMIT 1"
        );
        $stmt->execute([':anio' => $anio, ':mes' => $mes]);
        $valor = $stmt->fetchColumn();

        return $valor !== false && (int) $valor === 1;
    }

    public function cerrar(int $anio, int $mes, int $idUsuario): void
    {
        $this->validarPeriodo($anio, $mes);

        $inicio = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));

        if ($inicio > new DateTimeImmutable(date('Y-m-01'))) {
            throw new InvalidArgumentException('No se puede cerrar un período fiscal futuro.');
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "SELECT cerrado
                 FROM periodos_fiscales
                 WHERE anio = :anio AND mes = :mes
                 FOR UPDATE"
            );
            $stmt->execute([':anio' => $anio, ':mes' => $mes]);
            $fila = $stmt->fetch();

            if ($fila && (int) $fila['cerrado'] === 1) {
                throw new RuntimeException('El período indicado ya está cerrado.');
            }

            if ($fila) {
                $stmt = $this->db->prepare(
                    "UPDATE periodos_fiscales
                     SET cerrado = 1,
                         fecha_cierre = NOW(),
                         id_usuario_cierre = :usuario
                     WHERE anio = :anio AND mes = :mes"
                );
            } else {
                $stmt = $this->db->prepare(
                    "INSERT INTO periodos_fiscales
                        (anio, mes, cerrado, fecha_cierre, id_usuario_cierre)
                     VALUES
                        (:anio, :mes, 1, NOW(), :usuario)"
                );
            }

            $stmt->execute([
                ':anio'    => $anio,
                ':mes'     => $mes,
                ':usuario' => $idUsuario,
            ]);

            $this->auditar('CIERRE', $anio, $mes, $idUsuario);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function reabrir(int $anio, int $mes, int $idUsuario): void
    {
        $this->validarPeriodo($anio, $mes);
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "SELECT cerrado
                 FROM periodos_fiscales
                 WHERE anio = :anio AND mes = :mes
                 FOR UPDATE"
            );
            $stmt->execute([':anio' => $anio, ':mes' => $mes]);
            $fila = $stmt->fetch();

            if (!$fila || (int) $fila['cerrado'] !== 1) {
                throw new RuntimeException('El período indicado no está cerrado.');
            }

            $stmt = $this->db->prepare(
                "UPDATE periodos_fiscales
                 SET cerrado = 0,
                     fecha_cierre = NULL,
                     id_usuario_cierre = NULL
                 WHERE anio = :anio AND mes = :mes"
            );
            $stmt->execute([':anio' => $anio, ':mes' => $mes]);

            $this->auditar('REAPERTURA', $anio, $mes, $idUsuario);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function validarPeriodo(int $anio, int $mes): void
    {
        if ($anio < 2000 || $anio > 2100) {
            throw new InvalidArgumentException('El año del período fiscal no es válido.');
        }

        if ($mes < 1 || $mes > 12) {
            throw new InvalidArgumentException('El mes del período fiscal no es válido.');
        }
    }

    private function auditar(string $accion, int $anio, int $mes, int $idUsuario): void
    {
        $detalle = json_encode(
            ['anio' => $anio, 'mes' => $mes],
            JSON_UNESCAPED_UNICODE
        );

        $stmt = $this->db->prepare(
            "INSERT INTO bitacora_auditoria
                (id_usuario, modulo, accion, detalle_json, ip_local)
             VALUES
                (:usuario, 'PERIODOS', :accion, :detalle, :ip)"
        );
        $stmt->execute([
            ':usuario' => $idUsuario,
            ':accion'  => $accion,
            ':detalle' => $detalle,
            ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
