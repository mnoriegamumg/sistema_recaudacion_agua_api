<?php
namespace App\Controllers;

use App\Models\Morosidad;
use App\Models\Contador;
use App\Services\MorosidadService;

class MorosidadController {
    private Morosidad $morosidadModel;
    private Contador $contadorModel;
    private MorosidadService $morosidadService;

    public function __construct() {
        $this->morosidadModel = new Morosidad();
        $this->contadorModel = new Contador();
        $this->morosidadService = new MorosidadService();
    }

    /**
     * GET /api/morosidad/actual
     * Estado actual de morosidad
     */
    public function estadoActual() {
        $idContador = $_GET['id_contador'] ?? null;
        
        if (!$idContador) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'id_contador es requerido'
            ]);
            return;
        }

        $contador = $this->contadorModel->findById($idContador);
        if (!$contador) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Contador no encontrado'
            ]);
            return;
        }

        $estado = $this->morosidadModel->getEstadoActual($idContador);
        
        if (!$estado) {
            // Si no hay estado calculado, calcularlo sobre la marcha
            $this->morosidadService->calcularMorosidad((int)date('m'), (int)date('Y'));
            $estado = $this->morosidadModel->getEstadoActual($idContador);
        }

        echo json_encode([
            'success' => true,
            'data' => $estado
        ]);
    }

    /**
     * GET /api/morosidad/historial/{id_contador}
     * Historial de morosidad de un contador
     */
    public function historial($idContador) {
        $contador = $this->contadorModel->findById($idContador);
        if (!$contador) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Contador no encontrado'
            ]);
            return;
        }

        $historial = $this->morosidadModel->getHistorialByContador($idContador);
        
        echo json_encode([
            'success' => true,
            'data' => $historial,
            'meta' => [
                'total' => count($historial),
                'contador' => $contador['codigo_contador']
            ]
        ]);
    }

    /**
     * GET /api/morosidad/morosos
     * Lista de morosos actuales
     */
    public function morosos() {
        $morosos = $this->morosidadModel->getMorososActuales();
        
        echo json_encode([
            'success' => true,
            'data' => $morosos,
            'meta' => [
                'total' => count($morosos),
                'mes' => date('m'),
                'ano' => date('Y')
            ]
        ]);
    }

    /**
     * GET /api/morosidad/resumen
     * Resumen general de morosidad
     */
    public function resumen() {
        $resumen = $this->morosidadModel->getResumen();
        
        echo json_encode([
            'success' => true,
            'data' => $resumen,
            'meta' => [
                'mes' => date('m'),
                'ano' => date('Y')
            ]
        ]);
    }

    /**
     * GET /api/morosidad/{mes}/{ano}
     * Obtener morosidad por mes y año específico
     */
    public function obtenerPorMesAno($mes, $ano) {
        $estado = $_GET['estado'] ?? null;
        
        $resultados = $this->morosidadModel->getByMesAno($mes, $ano, $estado);
        
        echo json_encode([
            'success' => true,
            'data' => $resultados,
            'meta' => [
                'total' => count($resultados),
                'mes' => $mes,
                'ano' => $ano
            ]
        ]);
    }

    /**
     * POST /api/morosidad/calcular
     * Calcular morosidad manualmente
     */
    public function calcular() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $mes = $data['mes'] ?? (int)date('m');
        $ano = $data['ano'] ?? (int)date('Y');
        
        // Verificar permisos (solo admin o tesorero)
        $userData = $_SERVER['USER_DATA'];
        if (!in_array($userData->rol, ['admin', 'tesorero'])) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'No tienes permisos para ejecutar esta acción'
            ]);
            return;
        }

        $resultado = $this->morosidadService->calcularMorosidad($mes, $ano);
        
        echo json_encode([
            'success' => true,
            'data' => $resultado,
            'message' => 'Cálculo de morosidad completado'
        ]);
    }
}