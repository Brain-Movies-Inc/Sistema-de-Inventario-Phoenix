<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

final class ClienteModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    public function listar(string $busqueda = ''): array
    {
        $sql = "SELECT c.*,
                       (SELECT COUNT(*) FROM facturas f WHERE f.id_cliente = c.id_cliente) AS facturas
                FROM clientes c";

        if ($busqueda !== '') {
            $sql .= " WHERE c.nombre_razon_social LIKE :q1 OR c.rif_cedula LIKE :q2";
        }

        $sql .= " ORDER BY c.nombre_razon_social ASC";

        $stmt = $this->db->prepare($sql);
        if ($busqueda !== '') {
            $like = '%' . $busqueda . '%';
            $stmt->execute([':q1' => $like, ':q2' => $like]);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll();
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM clientes WHERE id_cliente = :id");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function existeRif(string $rif, int $exceptoId = 0): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM clientes WHERE rif_cedula = :rif AND id_cliente <> :id"
        );
        $stmt->execute([':rif' => $rif, ':id' => $exceptoId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO clientes (rif_cedula, nombre_razon_social, direccion_fiscal, telefono)
             VALUES (:rif, :nombre, :direccion, :telefono)"
        );
        $stmt->execute($this->parametros($datos));

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        $stmt = $this->db->prepare(
            "UPDATE clientes SET
                rif_cedula          = :rif,
                nombre_razon_social = :nombre,
                direccion_fiscal    = :direccion,
                telefono            = :telefono
             WHERE id_cliente = :id"
        );
        $stmt->execute($this->parametros($datos) + [':id' => $id]);
    }

    public function tieneFacturas(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM facturas WHERE id_cliente = :id");
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function eliminar(int $id): void
    {
        if ($this->tieneFacturas($id)) {
            throw new RuntimeException(
                'No se puede eliminar el cliente porque tiene facturas asociadas. ' .
                'Puedes editarlo, pero no borrarlo para no romper el historial fiscal.'
            );
        }

        $stmt = $this->db->prepare("DELETE FROM clientes WHERE id_cliente = :id");
        $stmt->execute([':id' => $id]);
    }

    private function parametros(array $datos): array
    {
        return [
            ':rif'       => $datos['rif_cedula'],
            ':nombre'    => $datos['nombre_razon_social'],
            ':direccion' => $datos['direccion_fiscal'] !== '' ? $datos['direccion_fiscal'] : null,
            ':telefono'  => $datos['telefono'] !== '' ? $datos['telefono'] : null,
        ];
    }
}
