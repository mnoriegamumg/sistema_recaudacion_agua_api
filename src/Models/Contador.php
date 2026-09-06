<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Contador {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create(array $data): array {
        $stmt = $this->db->prepare("
            INSERT INTO contadores (codigo_contador, dpi, nit, nombre_propietario, estado, created_by)
            VALUES (:codigo, :dpi, :nit, :nombre, :estado, :created_by)
        ");
        
        $stmt->execute([
            ':codigo' => $data['codigo_contador'],
            ':dpi' => $data['dpi'] ?? null,
            ':nit' => $data['nit'] ?? null,
            ':nombre' => $data['nombre_propietario'],
            ':estado' => $data['estado'] ?? 'activo',
            ':created_by' => $data['created_by'] ?? 1
        ]);

        $id = $this->db->lastInsertId();
        return $this->findById($id);
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   cl.nombre_completo as creado_por,
                   cl2.nombre_completo as actualizado_por
            FROM contadores c
            LEFT JOIN usuarios_sistema cl ON c.created_by = cl.id_usuario
            LEFT JOIN usuarios_sistema cl2 ON c.updated_by = cl2.id_usuario
            WHERE c.id_contador = :id
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findByCodigo(string $codigo): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM contadores WHERE codigo_contador = :codigo
        ");
        $stmt->execute([':codigo' => $codigo]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getAll(): array {
        $stmt = $this->db->query("
            SELECT c.*, 
                   cl.nombre_completo as cliente_nombre,
                   cl2.nombre_completo as creado_por
            FROM contadores c
            LEFT JOIN clientes cl ON c.id_contador = cl.id_contador
            LEFT JOIN usuarios_sistema cl2 ON c.created_by = cl2.id_usuario
            ORDER BY c.id_contador DESC
        ");
        return $stmt->fetchAll();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = ['codigo_contador', 'dpi', 'nit', 'nombre_propietario', 'estado'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $fields[] = "updated_by = :updated_by";
        $params[':updated_by'] = $data['updated_by'] ?? 1;

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE contadores SET " . implode(', ', $fields) . " WHERE id_contador = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM contadores WHERE id_contador = :id");
        return $stmt->execute([':id' => $id]);
    }
}