<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Cliente {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Crear un nuevo cliente asociado a un contador
     */
    public function create(array $data): array {
        $stmt = $this->db->prepare("
            INSERT INTO clientes (
                id_contador, direccion, correo, telefono, 
                telefono_alternativo, referencia_direccion, comunidad,
                created_by
            ) VALUES (
                :id_contador, :direccion, :correo, :telefono,
                :telefono_alt, :referencia, :comunidad,
                :created_by
            )
        ");
        
        $stmt->execute([
            ':id_contador' => $data['id_contador'],
            ':direccion' => $data['direccion'] ?? null,
            ':correo' => $data['correo'] ?? null,
            ':telefono' => $data['telefono'] ?? null,
            ':telefono_alt' => $data['telefono_alternativo'] ?? null,
            ':referencia' => $data['referencia_direccion'] ?? null,
            ':comunidad' => $data['comunidad'],
            ':created_by' => $data['created_by'] ?? $_SERVER['USER_DATA']->sub ?? 1
        ]);

        $id = $this->db->lastInsertId();
        return $this->findById($id);
    }

    /**
     * Obtener cliente por ID
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT cl.*, 
                   c.codigo_contador, c.nombre_propietario,
                   u1.nombre_completo as creado_por,
                   u2.nombre_completo as actualizado_por
            FROM clientes cl
            JOIN contadores c ON cl.id_contador = c.id_contador
            LEFT JOIN usuarios_sistema u1 ON cl.created_by = u1.id_usuario
            LEFT JOIN usuarios_sistema u2 ON cl.updated_by = u2.id_usuario
            WHERE cl.id_cliente = :id
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtener cliente por ID de contador
     */
    public function findByContador(int $idContador): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM clientes WHERE id_contador = :id_contador
        ");
        $stmt->execute([':id_contador' => $idContador]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Listar todos los clientes
     */
    public function getAll(): array {
        $stmt = $this->db->query("
            SELECT cl.*, 
                   c.codigo_contador, c.nombre_propietario,
                   u.nombre_completo as creado_por
            FROM clientes cl
            JOIN contadores c ON cl.id_contador = c.id_contador
            LEFT JOIN usuarios_sistema u ON cl.created_by = u.id_usuario
            ORDER BY cl.id_cliente DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Listar clientes por comunidad
     */
    public function getByComunidad(string $comunidad): array {
        $stmt = $this->db->prepare("
            SELECT cl.*, c.codigo_contador, c.nombre_propietario
            FROM clientes cl
            JOIN contadores c ON cl.id_contador = c.id_contador
            WHERE cl.comunidad = :comunidad
        ");
        $stmt->execute([':comunidad' => $comunidad]);
        return $stmt->fetchAll();
    }

    /**
     * Actualizar cliente
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = [
            'direccion', 'correo', 'telefono', 
            'telefono_alternativo', 'referencia_direccion', 'comunidad'
        ];
        
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

        $sql = "UPDATE clientes SET " . implode(', ', $fields) . " WHERE id_cliente = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Eliminar cliente
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM clientes WHERE id_cliente = :id");
        return $stmt->execute([':id' => $id]);
    }
}