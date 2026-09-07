<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Tarifa {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Obtener tarifa actual de una comunidad
     */
    public function getActual(string $comunidad): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM tarifas
            WHERE comunidad = :comunidad
            AND activo = 1
            AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
            ORDER BY fecha_inicio DESC
            LIMIT 1
        ");
        $stmt->execute([':comunidad' => $comunidad]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtener todas las tarifas activas
     */
    public function getActivas(): array {
        $stmt = $this->db->query("
            SELECT * FROM tarifas
            WHERE activo = 1
            AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
            ORDER BY comunidad
        ");
        return $stmt->fetchAll();
    }

    /**
     * Crear nueva tarifa (histórico)
     */
    public function create(array $data): int {
        // Desactivar tarifas anteriores de la misma comunidad
        $this->desactivarTarifasAnteriores($data['comunidad']);
        
        $stmt = $this->db->prepare("
            INSERT INTO tarifas (
                comunidad, tarifa_mensual, tarifa_anual, 
                descuento_anual, fecha_inicio, activo, created_by
            ) VALUES (
                :comunidad, :tarifa_mensual, :tarifa_anual,
                :descuento, :fecha_inicio, 1, :created_by
            )
        ");
        
        $stmt->execute([
            ':comunidad' => $data['comunidad'],
            ':tarifa_mensual' => $data['tarifa_mensual'],
            ':tarifa_anual' => $data['tarifa_anual'],
            ':descuento' => $data['descuento_anual'] ?? 0,
            ':fecha_inicio' => $data['fecha_inicio'] ?? date('Y-m-d'),
            ':created_by' => $data['created_by'] ?? $_SERVER['USER_DATA']->sub ?? 1
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Desactivar tarifas anteriores de una comunidad
     */
    private function desactivarTarifasAnteriores(string $comunidad): bool {
        $stmt = $this->db->prepare("
            UPDATE tarifas 
            SET activo = 0, 
                fecha_fin = CURDATE(),
                updated_at = CURRENT_TIMESTAMP
            WHERE comunidad = :comunidad AND activo = 1
        ");
        return $stmt->execute([':comunidad' => $comunidad]);
    }

    /**
     * Actualizar tarifa existente
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = ['tarifa_mensual', 'tarifa_anual', 'descuento_anual', 'activo'];
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

        $sql = "UPDATE tarifas SET " . implode(', ', $fields) . " WHERE id_tarifa = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}