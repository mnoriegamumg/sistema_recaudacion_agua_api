<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Contador {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Crear un nuevo contador
     */
    public function create(array $data): array {
        $stmt = $this->db->prepare("
            INSERT INTO contadores (
                codigo_contador, dpi, nit, nombre_propietario, 
                estado, created_by
            ) VALUES (
                :codigo, :dpi, :nit, :nombre, :estado, :created_by
            )
        ");
        
        $stmt->execute([
            ':codigo' => $data['codigo_contador'],
            ':dpi' => $data['dpi'] ?? null,
            ':nit' => $data['nit'] ?? null,
            ':nombre' => $data['nombre_propietario'],
            ':estado' => $data['estado'] ?? 'activo',
            ':created_by' => $data['created_by'] ?? $_SERVER['USER_DATA']->sub ?? 1
        ]);

        $id = $this->db->lastInsertId();
        return $this->findById($id);
    }

    /**
     * Obtener contador por ID
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   u1.nombre_completo as creado_por,
                   u2.nombre_completo as actualizado_por
            FROM contadores c
            LEFT JOIN usuarios_sistema u1 ON c.created_by = u1.id_usuario
            LEFT JOIN usuarios_sistema u2 ON c.updated_by = u2.id_usuario
            WHERE c.id_contador = :id
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Buscar por código de contador
     */
    public function findByCodigo(string $codigo): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM contadores WHERE codigo_contador = :codigo
        ");
        $stmt->execute([':codigo' => $codigo]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Buscar por DPI
     */
    public function findByDpi(string $dpi): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM contadores WHERE dpi = :dpi
        ");
        $stmt->execute([':dpi' => $dpi]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Listar todos los contadores
     */
    public function getAll(): array {
        $stmt = $this->db->query("
            SELECT c.*, 
                   cl.direccion, cl.correo, cl.telefono, cl.comunidad,
                   u.nombre_completo as creado_por
            FROM contadores c
            LEFT JOIN clientes cl ON c.id_contador = cl.id_contador
            LEFT JOIN usuarios_sistema u ON c.created_by = u.id_usuario
            ORDER BY c.id_contador DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Obtener contadores por estado
     */
    public function getByEstado(string $estado): array {
        $stmt = $this->db->prepare("
            SELECT * FROM contadores WHERE estado = :estado
        ");
        $stmt->execute([':estado' => $estado]);
        return $stmt->fetchAll();
    }

    /**
     * Actualizar contador
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = ['codigo_contador', 'dpi', 'nit', 'nombre_propietario', 'estado'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $fields[] = "updated_by = :updated_by";
        $params[':updated_by'] = $data['updated_by'] ?? $_SERVER['USER_DATA']->sub ?? 1;

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE contadores SET " . implode(', ', $fields) . " WHERE id_contador = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Eliminar contador (soft delete - cambia estado)
     */
    public function delete(int $id, int $userId): bool {
        $stmt = $this->db->prepare("
            UPDATE contadores 
            SET estado = 'inactivo', 
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
            WHERE id_contador = :id
        ");
        return $stmt->execute([
            ':id' => $id,
            ':updated_by' => $userId
        ]);
    }

    /**
     * Eliminar físicamente (solo admin)
     */
    public function hardDelete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM contadores WHERE id_contador = :id");
        return $stmt->execute([':id' => $id]);
    }
}