<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

final class UsuarioModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /**
     * Devuelve el usuario si el login y la clave son correctos y está activo.
     */
    public function autenticar(string $login, string $clave): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id_usuario, cedula, nombre_completo, usuario_login, password_hash, rol, activo
             FROM usuarios
             WHERE usuario_login = :login
             LIMIT 1"
        );
        $stmt->execute([':login' => $login]);
        $fila = $stmt->fetch();

        if (!$fila || (int) $fila['activo'] !== 1) {
            return null;
        }

        if (!password_verify($clave, (string) $fila['password_hash'])) {
            return null;
        }

        // Rehash si PHP cambió el algoritmo o el costo por defecto.
        if (password_needs_rehash((string) $fila['password_hash'], PASSWORD_DEFAULT)) {
            $this->cambiarPassword((int) $fila['id_usuario'], $clave);
        }

        unset($fila['password_hash']);

        return $fila;
    }

    public function listar(string $busqueda = ''): array
    {
        $sql = "SELECT id_usuario, cedula, nombre_completo, usuario_login, rol, activo, creado
                FROM usuarios";

        if ($busqueda !== '') {
            $sql .= " WHERE nombre_completo LIKE :q1
                        OR usuario_login LIKE :q2
                        OR cedula LIKE :q3";
        }

        $sql .= " ORDER BY (rol = 'ADMIN') DESC, nombre_completo ASC";

        $stmt = $this->db->prepare($sql);
        if ($busqueda !== '') {
            $like = '%' . $busqueda . '%';
            $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll();
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id_usuario, cedula, nombre_completo, usuario_login, rol, activo, creado
             FROM usuarios
             WHERE id_usuario = :id"
        );
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function existeLogin(string $login, int $exceptoId = 0): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM usuarios WHERE usuario_login = :login AND id_usuario <> :id"
        );
        $stmt->execute([':login' => $login, ':id' => $exceptoId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function existeCedula(string $cedula, int $exceptoId = 0): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM usuarios WHERE cedula = :cedula AND id_usuario <> :id"
        );
        $stmt->execute([':cedula' => $cedula, ':id' => $exceptoId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function contarAdminsActivos(int $excluirId = 0): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM usuarios
             WHERE rol = 'ADMIN' AND activo = 1 AND id_usuario <> :id"
        );
        $stmt->execute([':id' => $excluirId]);

        return (int) $stmt->fetchColumn();
    }

    public function crear(array $datos, string $clave): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO usuarios (cedula, nombre_completo, usuario_login, password_hash, rol, activo)
             VALUES (:cedula, :nombre, :login, :hash, :rol, 1)"
        );
        $stmt->execute($this->parametros($datos) + [
            ':hash' => password_hash($clave, PASSWORD_DEFAULT),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        $stmt = $this->db->prepare(
            "UPDATE usuarios SET
                cedula           = :cedula,
                nombre_completo  = :nombre,
                usuario_login    = :login,
                rol              = :rol
             WHERE id_usuario = :id"
        );
        $stmt->execute($this->parametros($datos) + [':id' => $id]);
    }

    public function cambiarPassword(int $id, string $clave): void
    {
        $stmt = $this->db->prepare(
            "UPDATE usuarios SET password_hash = :hash WHERE id_usuario = :id"
        );
        $stmt->execute([
            ':hash' => password_hash($clave, PASSWORD_DEFAULT),
            ':id'   => $id,
        ]);
    }

    public function cambiarActivo(int $id, bool $activo): void
    {
        $stmt = $this->db->prepare(
            "UPDATE usuarios SET activo = :activo WHERE id_usuario = :id"
        );
        $stmt->execute([':activo' => $activo ? 1 : 0, ':id' => $id]);
    }

    public function registrarBitacora(?int $idUsuario, string $accion, array $detalle): void
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO bitacora_auditoria (id_usuario, modulo, accion, detalle_json, ip_local)
                 VALUES (:usuario, 'USUARIOS', :accion, :detalle, :ip)"
            );
            $stmt->execute([
                ':usuario' => $idUsuario ?: null,
                ':accion'  => $accion,
                ':detalle' => json_encode($detalle, JSON_UNESCAPED_UNICODE),
                ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            // La bitácora no debe impedir el login.
        }
    }

    private function parametros(array $datos): array
    {
        return [
            ':cedula' => $datos['cedula'],
            ':nombre' => $datos['nombre_completo'],
            ':login'  => $datos['usuario_login'],
            ':rol'    => $datos['rol'],
        ];
    }
}
