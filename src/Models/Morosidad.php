<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Morosidad {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Obtener estado de morosidad actual de un contador
     */
    public function getEstadoActual(int $idContador): ?array {
        $mes = (int)date('m');
        $ano = (int)date('Y');
        
        $stmt = $this->db->prepare("
            SELECT m.*, 
                   c.codigo_contador, c.nombre_propietario,
                   cl.comunidad
            FROM morosidad m
            JOIN contadores c ON m.id_contador = c.id_contador
            LEFT JOIN clientes cl ON c.id_contador = cl.id_contador
            WHERE m.id_contador = :id_contador
            AND m.mes = :mes
            AND m.ano = :ano
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
     * Obtener historial de morosidad de un contador
     */
    public function getHistorialByContador(int $idContador): array {
        $stmt = $this->db->prepare("
            SELECT m.*, 
                   c.codigo_contador, c.nombre_propietario
            FROM morosidad m
            JOIN contadores c ON m.id_contador = c.id_contador
            WHERE m.id_contador = :id_contador
            ORDER BY m.ano DESC, m.mes DESC
        ");
        $stmt->execute([':id_contador' => $idContador]);
        return $stmt->fetchAll();
    }

    /**
     * Obtener lista de morosos del mes actual
     */
    public function getMorososActuales(): array {
        $mes = (int)date('m');
        $ano = (int)date('Y');
        
        $stmt = $this->db->prepare("
            SELECT m.*, 
                   c.codigo_contador, c.nombre_propietario,
                   cl.direccion, cl.correo, cl.telefono, cl.comunidad
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
     * Obtener resumen de morosidad
     */
    public function getResumen(): array {
        $mes = (int)date('m');
        $ano = (int)date('Y');
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_contadores,
                SUM(CASE WHEN estado = 'solvente' THEN 1 ELSE 0 END) as solventes,
                SUM(CASE WHEN estado = 'moroso' THEN 1 ELSE 0 END) as morosos,
                SUM(CASE WHEN estado = 'suspendido' THEN 1 ELSE 0 END) as suspendidos,
                SUM(saldo_adeudado) as total_adeudado,
                AVG(saldo_adeudado) as promedio_adeudado
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

    /**
     * Obtener morosidad por mes y año específico
     */
    public function getByMesAno(int $mes, int $ano, ?string $estado = null): array {
        $sql = "
            SELECT m.*, 
                   c.codigo_contador, c.nombre_propietario,
                   cl.comunidad
            FROM morosidad m
            JOIN contadores c ON m.id_contador = c.id_contador
            LEFT JOIN clientes cl ON c.id_contador = cl.id_contador
            WHERE m.mes = :mes AND m.ano = :ano
        ";
        
        $params = [
            ':mes' => $mes,
            ':ano' => $ano
        ];
        
        if ($estado) {
            $sql .= " AND m.estado = :estado";
            $params[':estado'] = $estado;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}