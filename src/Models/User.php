<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class User {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

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

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT id_usuario, nombre_completo, correo, rol 
            FROM usuarios_sistema 
            WHERE id_usuario = :id AND activo = 1
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function updateLastAccess(int $id): bool {
        $stmt = $this->db->prepare("
            UPDATE usuarios_sistema 
            SET updated_at = CURRENT_TIMESTAMP 
            WHERE id_usuario = :id
        ");
        return $stmt->execute([':id' => $id]);
    }
}