<?php
namespace App\Controllers;

use App\Models\Pago;
use App\Models\Contador;
use App\Models\Tarifa;

class PagoController {
    private Pago $pagoModel;
    private Contador $contadorModel;
    private Tarifa $tarifaModel;

    public function __construct() {
        $this->pagoModel = new Pago();
        $this->contadorModel = new Contador();
        $this->tarifaModel = new Tarifa();
    }

    /**
     * POST /api/pagos
     * Registrar nuevo pago
     */
    public function registrar() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar campos requeridos
        $required = ['id_contador', 'monto', 'mes_pagado', 'ano_pagado', 
                     'periodo_inicio', 'periodo_fin', 'pagado_por'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => "El campo '$field' es requerido"
                ]);
                return;
            }
        }

        // Validar que el contador existe
        $contador = $this->contadorModel->findById($data['id_contador']);
        if (!$contador) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Contador no encontrado'
            ]);
            return;
        }

        // Validar monto
        if ($data['monto'] <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'El monto debe ser mayor a 0'
            ]);
            return;
        }

        // Validar mes (1-12)
        if ($data['mes_pagado'] < 1 || $data['mes_pagado'] > 12) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Mes inválido. Debe ser entre 1 y 12'
            ]);
            return;
        }

        // Verificar que el mes no esté pagado
        $yaPagado = $this->pagoModel->mesYaPagado(
            $data['id_contador'], 
            $data['mes_pagado'], 
            $data['ano_pagado']
        );
        
        if ($yaPagado) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'Este mes ya ha sido pagado para este contador'
            ]);
            return;
        }

        $data['id_usuario_sistema'] = $_SERVER['USER_DATA']->sub ?? 1;
        $result = $this->pagoModel->registrar($data);
        
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al registrar el pago: ' . $result['error']
            ]);
            return;
        }

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data' => $result['data'],
            'message' => 'Pago registrado exitosamente',
            'numero_recibo' => $result['numero_recibo']
        ]);
    }

    /**
     * GET /api/pagos
     * Listar pagos
     */
    public function listar() {
        $limit = $_GET['limit'] ?? 100;
        $offset = $_GET['offset'] ?? 0;
        
        $pagos = $this->pagoModel->getAll($limit, $offset);
        
        echo json_encode([
            'success' => true,
            'data' => $pagos,
            'meta' => [
                'total' => count($pagos),
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
    }

    /**
     * GET /api/pagos/{id}
     * Obtener pago por ID
     */
    public function obtener($id) {
        $pago = $this->pagoModel->findById($id);
        
        if (!$pago) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Pago no encontrado'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $pago
        ]);
    }

    /**
     * GET /api/pagos/recibo/{numero}
     * Buscar pago por número de recibo
     */
    public function buscarPorRecibo($numero) {
        $pago = $this->pagoModel->getByRecibo($numero);
        
        if (!$pago) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Pago no encontrado con el recibo: ' . $numero
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $pago
        ]);
    }

    /**
     * GET /api/pagos/contador/{id_contador}
     * Historial de pagos de un contador
     */
    public function historialPorContador($idContador) {
        $ano = $_GET['ano'] ?? null;
        
        $contador = $this->contadorModel->findById($idContador);
        if (!$contador) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Contador no encontrado'
            ]);
            return;
        }

        $pagos = $this->pagoModel->getByContador($idContador, $ano);
        
        echo json_encode([
            'success' => true,
            'data' => $pagos,
            'meta' => [
                'total' => count($pagos),
                'contador' => $contador['codigo_contador']
            ]
        ]);
    }

    /**
     * PUT /api/pagos/{id}
     * Anular un pago
     */
    public function anular($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['motivo'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'El motivo de anulación es requerido'
            ]);
            return;
        }

        $pago = $this->pagoModel->findById($id);
        if (!$pago) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Pago no encontrado'
            ]);
            return;
        }

        if ($pago['estado_pago'] === 'anulado') {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'Este pago ya está anulado'
            ]);
            return;
        }

        $userId = $_SERVER['USER_DATA']->sub ?? 1;
        $result = $this->pagoModel->anular($id, $data['motivo'], $userId);
        
        if (!$result) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'No se pudo anular el pago'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $this->pagoModel->findById($id),
            'message' => 'Pago anulado exitosamente'
        ]);
    }

    /**
     * GET /api/pagos/resumen/diario
     * Resumen de pagos del día
     */
    public function resumenDiario() {
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        
        $resumen = $this->pagoModel->getResumenPorFecha($fecha, $fecha);
        
        echo json_encode([
            'success' => true,
            'data' => $resumen,
            'meta' => [
                'fecha' => $fecha
            ]
        ]);
    }

    /**
     * GET /api/pagos/tarifa/{id_contador}
     * Obtener tarifa sugerida para un contador
     */
    public function obtenerTarifa($idContador) {
        $contador = $this->contadorModel->findById($idContador);
        if (!$contador) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Contador no encontrado'
            ]);
            return;
        }

        // Obtener el cliente para saber la comunidad
        $clienteModel = new \App\Models\Cliente();
        $cliente = $clienteModel->findByContador($idContador);
        
        if (!$cliente) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Cliente no encontrado para este contador'
            ]);
            return;
        }

        $tarifa = $this->tarifaModel->getActual($cliente['comunidad']);
        
        if (!$tarifa) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'No hay tarifa configurada para esta comunidad'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'comunidad' => $cliente['comunidad'],
                'tarifa_mensual' => $tarifa['tarifa_mensual'],
                'tarifa_anual' => $tarifa['tarifa_anual'],
                'descuento_anual' => $tarifa['descuento_anual']
            ]
        ]);
    }
}