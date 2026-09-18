<?php
namespace App\Models;

use App\Config\Database;
use App\Utils\Helpers;
use PDO;

class Pago {
    private PDO $db;

    /** Máximo de intentos para generar un número de recibo único ante colisión */
    private const MAX_INTENTOS_RECIBO = 5;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Registrar un nuevo pago.
     *
     * El número de recibo se genera de forma secuencial por periodo (REC-YYYYMM-NNNN)
     * dentro de la transacción. Ante una colisión con la restricción UNIQUE de la
     * columna numero_recibo (por concurrencia), se reintenta con el siguiente correlativo.
     */
    public function registrar(array $data): array {
        $periodo = date('Ym');

        for ($intento = 0; $intento < self::MAX_INTENTOS_RECIBO; $intento++) {
            $this->db->beginTransaction();

            try {
                $numeroRecibo = $this->generarNumeroReciboUnico($periodo);

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
                    ':id_usuario' => $data['id_usuario_sistema'] ?? $_SERVER['USER_DATA']->sub ?? 1,
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
                    ':created_by' => $data['created_by'] ?? $_SERVER['USER_DATA']->sub ?? 1
                ]);

                $idPago = $this->db->lastInsertId();
                $pago = $this->findById($idPago);

                $this->db->commit();

                return [
                    'success' => true,
                    'data' => $pago,
                    'numero_recibo' => $numeroRecibo
                ];

            } catch (\PDOException $e) {
                $this->db->rollBack();

                // 23000 = violación de restricción de integridad (recibo duplicado).
                // Reintentar con un nuevo correlativo salvo que se agoten los intentos.
                if ($e->getCode() === '23000' && $intento < self::MAX_INTENTOS_RECIBO - 1) {
                    continue;
                }

                return [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            } catch (\Exception $e) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'No se pudo generar un número de recibo único'
        ];
    }

    /**
     * Calcular el siguiente número de recibo correlativo para un periodo (Ym).
     * Toma el mayor correlativo ya usado en el periodo y le suma uno.
     */
    private function generarNumeroReciboUnico(string $periodo): string {
        $prefijo = 'REC-' . $periodo . '-';

        $stmt = $this->db->prepare("
            SELECT MAX(CAST(SUBSTRING(numero_recibo, :largo_prefijo) AS UNSIGNED)) AS ultimo
            FROM pagos
            WHERE numero_recibo LIKE :patron
        ");
        $stmt->bindValue(':largo_prefijo', strlen($prefijo) + 1, PDO::PARAM_INT);
        $stmt->bindValue(':patron', $prefijo . '%', PDO::PARAM_STR);
        $stmt->execute();
        $resultado = $stmt->fetch();

        $siguiente = ((int)($resultado['ultimo'] ?? 0)) + 1;

        return Helpers::generarNumeroRecibo($siguiente, 4, $periodo);
    }

    /**
     * Obtener pago por ID
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT p.*, 
                   c.codigo_contador, c.nombre_propietario,
                   cl.comunidad,
                   us.nombre_completo as usuario_registro
            FROM pagos p
            JOIN contadores c ON p.id_contador = c.id_contador
            LEFT JOIN clientes cl ON c.id_contador = cl.id_contador
            JOIN usuarios_sistema us ON p.id_usuario_sistema = us.id_usuario
            WHERE p.id_pago = :id
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtener pago por número de recibo
     */
    public function getByRecibo(string $numeroRecibo): ?array {
        $stmt = $this->db->prepare("
            SELECT p.*, 
                   c.codigo_contador, c.nombre_propietario,
                   cl.comunidad,
                   us.nombre_completo as usuario_registro
            FROM pagos p
            JOIN contadores c ON p.id_contador = c.id_contador
            LEFT JOIN clientes cl ON c.id_contador = cl.id_contador
            JOIN usuarios_sistema us ON p.id_usuario_sistema = us.id_usuario
            WHERE p.numero_recibo = :recibo
        ");
        $stmt->execute([':recibo' => $numeroRecibo]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Historial de pagos de un contador
     */
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

    /**
     * Listar todos los pagos (con paginación)
     */
    public function getAll(?int $limit = 100, ?int $offset = 0): array {
        $stmt = $this->db->prepare("
            SELECT p.*, 
                   c.codigo_contador, c.nombre_propietario,
                   us.nombre_completo as usuario_registro
            FROM pagos p
            JOIN contadores c ON p.id_contador = c.id_contador
            JOIN usuarios_sistema us ON p.id_usuario_sistema = us.id_usuario
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Resumen de pagos por fecha
     */
    public function getResumenPorFecha(string $fechaInicio, string $fechaFin): array {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_pagos,
                SUM(monto) as total_recaudado,
                AVG(monto) as promedio_pago,
                COUNT(DISTINCT id_contador) as total_contribuyentes
            FROM pagos
            WHERE DATE(created_at) BETWEEN :fecha_inicio AND :fecha_fin
            AND estado_pago = 'pagado'
        ");
        $stmt->execute([
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin' => $fechaFin
        ]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Anular un pago
     */
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

    /**
     * Verificar si un mes ya fue pagado para un contador
     */
    public function mesYaPagado(int $idContador, int $mes, int $ano): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM pagos
            WHERE id_contador = :id_contador
            AND mes_pagado = :mes
            AND ano_pagado = :ano
            AND estado_pago = 'pagado'
        ");
        $stmt->execute([
            ':id_contador' => $idContador,
            ':mes' => $mes,
            ':ano' => $ano
        ]);
        $result = $stmt->fetch();
        return $result['total'] > 0;
    }
}