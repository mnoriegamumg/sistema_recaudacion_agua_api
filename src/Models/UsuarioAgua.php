<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class UsuarioAgua {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(): array {
        $stmt = $this->db->query("
            SELECT id_usuario_agua, nombre_completo, numero_medidor, 
                   direccion, telefono, comunidad, saldo_pendiente 
            FROM usuarios_agua 
            WHERE activo = 1
        ");
        return $stmt->fetchAll();
    }

    public function findByMedidor(string $medidor): ?array {
        $stmt = $this->db->prepare("
            SELECT id_usuario_agua, nombre_completo, numero_medidor, 
                   direccion, telefono, comunidad, saldo_pendiente 
            FROM usuarios_agua 
            WHERE numero_medidor = :medidor AND activo = 1
        ");
        $stmt->execute([':medidor' => $medidor]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findByComunidad(string $comunidad): array {
        $stmt = $this->db->prepare("
            SELECT id_usuario_agua, nombre_completo, numero_medidor, 
                   direccion, telefono, comunidad, saldo_pendiente 
            FROM usuarios_agua 
            WHERE comunidad = :comunidad AND activo = 1
        ");
        $stmt->execute([':comunidad' => $comunidad]);
        return $stmt->fetchAll();
    }
}