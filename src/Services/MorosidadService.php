<?php
namespace App\Services;

use App\Config\Database;
use PDO;

class MorosidadService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Calcula la morosidad para todos los contadores en un mes específico
     */
    public function calcularMorosidad(int $mes, int $ano): array {
        $resultados = [
            'total_calculados' => 0,
            'solventes' => 0,
            'morosos' => 0,
            'errores' => []
        ];

        // Obtener todos los contadores activos
        $contadores = $this->obtenerContadoresActivos();
        
        foreach ($contadores as $contador) {
            try {
                $estado = $this->calcularEstadoContador($contador['id_contador'], $mes, $ano);
                
                // Guardar o actualizar en tabla morosidad
                $this->guardarMorosidad(
                    $contador['id_contador'],
                    $mes,
                    $ano,
                    $estado['estado'],
                    $estado['saldo_adeudado']
                );
                
                $resultados['total_calculados']++;
                if ($estado['estado'] === 'solvente') {
                    $resultados['solventes']++;
                } else {
                    $resultados['morosos']++;
                }
                
            } catch (\Exception $e) {
                $resultados['errores'][] = [
                    'contador' => $contador['codigo_contador'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $resultados;
    }

    /**
     * Calcula el estado de un contador específico
     */
    private function calcularEstadoContador(int $idContador, int $mes, int $ano): array {
        // Obtener tarifa mensual del contador
        $tarifa = $this->obtenerTarifa($idContador);
        
        // Obtener pagos realizados para este mes/año
        $pagos = $this->obtenerPagos($idContador, $mes, $ano);
        $totalPagado = array_sum(array_column($pagos, 'monto'));
        
        $saldoAdeudado = $tarifa - $totalPagado;
        
        return [
            'estado' => $saldoAdeudado <= 0 ? 'solvente' : 'moroso',
            'saldo_adeudado' => max(0, $saldoAdeudado)
        ];
    }

    private function obtenerContadoresActivos(): array {
        $stmt = $this->db->query("
            SELECT id_contador, codigo_contador 
            FROM contadores 
            WHERE estado = 'activo'
        ");
        return $stmt->fetchAll();
    }

    private function obtenerTarifa(int $idContador): float {
        $stmt = $this->db->prepare("
            SELECT t.tarifa_mensual
            FROM contadores c
            JOIN clientes cl ON c.id_contador = cl.id_contador
            JOIN tarifas t ON cl.comunidad = t.comunidad
            WHERE c.id_contador = :id_contador
            AND t.activo = 1
            AND (t.fecha_fin IS NULL OR t.fecha_fin >= CURDATE())
            ORDER BY t.fecha_inicio DESC
            LIMIT 1
        ");
        $stmt->execute([':id_contador' => $idContador]);
        $result = $stmt->fetch();
        return $result ? (float)$result['tarifa_mensual'] : 0;
    }

    private function obtenerPagos(int $idContador, int $mes, int $ano): array {
        $stmt = $this->db->prepare("
            SELECT monto
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
        return $stmt->fetchAll();
    }

    private function guardarMorosidad(int $idContador, int $mes, int $ano, string $estado, float $saldo): void {
        $stmt = $this->db->prepare("
            INSERT INTO morosidad (id_contador, mes, ano, estado, saldo_adeudado, fecha_calculo)
            VALUES (:id, :mes, :ano, :estado, :saldo, CURDATE())
            ON DUPLICATE KEY UPDATE
            estado = :estado,
            saldo_adeudado = :saldo,
            fecha_calculo = CURDATE(),
            updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':id' => $idContador,
            ':mes' => $mes,
            ':ano' => $ano,
            ':estado' => $estado,
            ':saldo' => $saldo
        ]);
    }

    /**
     * Obtiene el estado de morosidad actual (mes actual)
     */
    public function obtenerEstadoActual(int $idContador): ?array {
        $mes = (int)date('m');
        $ano = (int)date('Y');
        
        $stmt = $this->db->prepare("
            SELECT * FROM morosidad
            WHERE id_contador = :id_contador
            AND mes = :mes
            AND ano = :ano
        ");
        $stmt->execute([
            ':id_contador' => $idContador,
            ':mes' => $mes,
            ':ano' => $ano
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene lista de morosos actuales
     */
    public function obtenerMorososActuales(): array {
        $mes = (int)date('m');
        $ano = (int)date('Y');
        
        $stmt = $this->db->prepare("
            SELECT m.*, 
                   c.codigo_contador, c.nombre_propietario,
                   cl.comunidad
            FROM morosidad m
            JOIN contadores c ON m.id_contador = c.id_contador
            LEFT JOIN clientes cl ON c.id_contador = cl.id_contador
            WHERE m.mes = :mes
            AND m.ano = :ano
            AND m.estado = 'moroso'
            ORDER BY m.saldo_adeudado DESC
        ");
        $stmt->execute([
            ':mes' => $mes,
            ':ano' => $ano
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Resumen general de morosidad
     */
    public function resumenMorosidad(): array {
        $mes = (int)date('m');
        $ano = (int)date('Y');
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_contadores,
                SUM(CASE WHEN estado = 'solvente' THEN 1 ELSE 0 END) as solventes,
                SUM(CASE WHEN estado = 'moroso' THEN 1 ELSE 0 END) as morosos,
                SUM(saldo_adeudado) as total_adeudado
            FROM morosidad
            WHERE mes = :mes
            AND ano = :ano
        ");
        $stmt->execute([
            ':mes' => $mes,
            ':ano' => $ano
        ]);
        return $stmt->fetch() ?: [];
    }
}