<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Usuario {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Listar todos los usuarios del sistema
     */
    public function getAll(): array {
        $stmt = $this->db->query("
            SELECT id_usuario, nombre_completo, correo, rol, activo, 
                   created_at, updated_at
            FROM usuarios_sistema
            ORDER BY id_usuario ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Obtener usuario por ID
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT id_usuario, nombre_completo, correo, rol, activo, 
                   created_at, updated_at
            FROM usuarios_sistema
            WHERE id_usuario = :id
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtener usuario por correo
     */
    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare("
            SELECT id_usuario, nombre_completo, correo, password_hash, rol, activo 
            FROM usuarios_sistema 
            WHERE correo = :email AND activo = 1
        ");
        $stmt->execute([':email' => $email]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Crear un nuevo usuario
     */
    public function create(array $data): array {
        $stmt = $this->db->prepare("
            INSERT INTO usuarios_sistema (
                nombre_completo, correo, password_hash, rol, activo, created_by
            ) VALUES (
                :nombre, :correo, :password_hash, :rol, :activo, :created_by
            )
        ");

        $stmt->execute([
            ':nombre' => $data['nombre_completo'],
            ':correo' => $data['correo'],
            ':password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            ':rol' => $data['rol'] ?? 'cajero',
            ':activo' => $data['activo'] ?? 1,
            ':created_by' => $data['created_by'] ?? null
        ]);

        $id = $this->db->lastInsertId();
        return $this->findById($id);
    }

    /**
     * Actualizar datos del usuario (sin contraseña)
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        $allowedFields = ['nombre_completo', 'correo', 'rol', 'activo'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $fields[] = "updated_by = :updated_by";
        $params[':updated_by'] = $data['updated_by'] ?? null;

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE usuarios_sistema SET " . implode(', ', $fields) . " WHERE id_usuario = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Actualizar contraseña del usuario
     */
    public function updatePassword(int $id, string $newPassword, int $updatedBy): bool {
        $stmt = $this->db->prepare("
            UPDATE usuarios_sistema 
            SET password_hash = :password_hash,
                updated_at = CURRENT_TIMESTAMP,
                updated_by = :updated_by
            WHERE id_usuario = :id
        ");
        return $stmt->execute([
            ':id' => $id,
            ':password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            ':updated_by' => $updatedBy
        ]);
    }

    /**
     * Verificar contraseña actual del usuario
     */
    public function verifyPassword(int $id, string $password): bool {
        $stmt = $this->db->prepare("
            SELECT password_hash FROM usuarios_sistema WHERE id_usuario = :id
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return false;
        }
        
        return password_verify($password, $result['password_hash']);
    }

    /**
     * Verificar si un correo ya existe (excepto el usuario actual)
     */
    public function emailExists(string $email, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as total FROM usuarios_sistema WHERE correo = :email";
        $params = [':email' => $email];

        if ($excludeId) {
            $sql .= " AND id_usuario != :id";
            $params[':id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'] > 0;
    }

    /**
     * Activar/Desactivar usuario (soft delete)
     */
    public function toggleActivo(int $id, int $updatedBy): bool {
        $stmt = $this->db->prepare("
            UPDATE usuarios_sistema 
            SET activo = NOT activo,
                updated_at = CURRENT_TIMESTAMP,
                updated_by = :updated_by
            WHERE id_usuario = :id
        ");
        return $stmt->execute([
            ':id' => $id,
            ':updated_by' => $updatedBy
        ]);
    }
}