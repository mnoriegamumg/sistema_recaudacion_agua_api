<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Pago {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function registrar(array $data): array {
        // Generar número de recibo único
        $numeroRecibo = 'REC-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $this->db->beginTransaction();
        
        try {
            // Insertar pago
            $stmt = $this->db->prepare("
                INSERT INTO pagos (
                    id_contador, id_usuario_sistema, monto, mes_pagado, ano_pagado,
                    periodo_inicio, periodo_fin, es_pago_anual, pagado_por,
                    identificacion, observaciones, numero_recibo, created_by
                ) VALUES (
                    :id_contador, :id_usuario, :monto, :mes, :ano,
                    :periodo_inicio, :periodo_fin, :es_anual, :pagado_por,
                    :identificacion, :observaciones, :numero_recibo, :created_by
                )
            ");
            
            $stmt->execute([
                ':id_contador' => $data['id_contador'],
                ':id_usuario' => $data['id_usuario_sistema'],
                ':monto' => $data['monto'],
                ':mes' => $data['mes_pagado'],
                ':ano' => $data['ano_pagado'],
                ':periodo_inicio' => $data['periodo_inicio'],
                ':periodo_fin' => $data['periodo_fin'],
                ':es_anual' => $data['es_pago_anual'] ?? 0,
                ':pagado_por' => $data['pagado_por'],
                ':identificacion' => $data['identificacion'] ?? null,
                ':observaciones' => $data['observaciones'] ?? null,
                ':numero_recibo' => $numeroRecibo,
                ':created_by' => $data['created_by'] ?? $data['id_usuario_sistema']
            ]);

            $idPago = $this->db->lastInsertId();
            
            // Obtener el pago completo
            $pago = $this->findById($idPago);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'data' => $pago,
                'numero_recibo' => $numeroRecibo
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT p.*, 
                   c.codigo_contador, c.nombre_propietario,
                   cli.comunidad,
                   us.nombre_completo as usuario_registro
            FROM pagos p
            JOIN contadores c ON p.id_contador = c.id_contador
            LEFT JOIN clientes cli ON c.id_contador = cli.id_contador
            JOIN usuarios_sistema us ON p.id_usuario_sistema = us.id_usuario
            WHERE p.id_pago = :id
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getByContador(int $idContador, ?int $ano = null): array {
        $sql = "
            SELECT p.*, us.nombre_completo as usuario_registro
            FROM pagos p
            JOIN usuarios_sistema us ON p.id_usuario_sistema = us.id_usuario
            WHERE p.id_contador = :id_contador
        ";
        
        $params = [':id_contador' => $idContador];
        
        if ($ano) {
            $sql .= " AND p.ano_pagado = :ano";
            $params[':ano'] = $ano;
        }
        
        $sql .= " ORDER BY p.periodo_inicio DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getByRecibo(string $numeroRecibo): ?array {
        $stmt = $this->db->prepare("
            SELECT p.*, 
                   c.codigo_contador, c.nombre_propietario,
                   us.nombre_completo as usuario_registro
            FROM pagos p
            JOIN contadores c ON p.id_contador = c.id_contador
            JOIN usuarios_sistema us ON p.id_usuario_sistema = us.id_usuario
            WHERE p.numero_recibo = :recibo
        ");
        $stmt->execute([':recibo' => $numeroRecibo]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getAll(?int $limit = 100, ?int $offset = 0): array {
        $stmt = $this->db->prepare("
            SELECT p.*, 
                   c.codigo_contador, c.nombre_propietario,
                   us.nombre_completo as usuario_registro
            FROM pagos p
            JOIN contadores c ON p.id_contador = c.id_contador
            JOIN usuarios_sistema us ON p.id_usuario_sistema = us.id_usuario
            ORDER BY p.fecha_pago DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function anular(int $idPago, string $motivo, int $userId): bool {
        $stmt = $this->db->prepare("
            UPDATE pagos 
            SET estado_pago = 'anulado', 
                observaciones = CONCAT(IFNULL(observaciones, ''), ' | ANULADO: ', :motivo),
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
            WHERE id_pago = :id
        ");
        return $stmt->execute([
            ':id' => $idPago,
            ':motivo' => $motivo,
            ':updated_by' => $userId
        ]);
    }
}