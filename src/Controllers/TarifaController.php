<?php
namespace App\Controllers;

use App\Models\Tarifa;

class TarifaController {
    private Tarifa $tarifaModel;

    public function __construct() {
        $this->tarifaModel = new Tarifa();
    }

    /**
     * GET /api/tarifas/actual
     * Obtener tarifas actuales
     */
    public function actual() {
        $comunidad = $_GET['comunidad'] ?? null;
        
        if ($comunidad) {
            // Tarifa de una comunidad específica
            $tarifa = $this->tarifaModel->getActual($comunidad);
            
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
                'data' => $tarifa
            ]);
        } else {
            // Todas las tarifas activas
            $tarifas = $this->tarifaModel->getActivas();
            
            echo json_encode([
                'success' => true,
                'data' => $tarifas
            ]);
        }
    }

    /**
     * POST /api/tarifas
     * Crear nueva tarifa
     */
    public function crear() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar campos requeridos
        $required = ['comunidad', 'tarifa_mensual', 'tarifa_anual'];
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

        // Validar comunidades permitidas
        $comunidadesPermitidas = ['cabecera_municipal', 'caserio_san_pablo', 'finca_san_francisco'];
        if (!in_array($data['comunidad'], $comunidadesPermitidas)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Comunidad inválida. Opciones: ' . implode(', ', $comunidadesPermitidas)
            ]);
            return;
        }

        $data['created_by'] = $_SERVER['USER_DATA']->sub ?? 1;
        $id = $this->tarifaModel->create($data);
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data' => $this->tarifaModel->getActual($data['comunidad']),
            'message' => 'Tarifa creada exitosamente'
        ]);
    }

    /**
     * PUT /api/tarifas/{id}
     * Actualizar tarifa
     */
    public function actualizar($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $data['updated_by'] = $_SERVER['USER_DATA']->sub ?? 1;
        $result = $this->tarifaModel->update($id, $data);
        
        if (!$result) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'No se pudo actualizar la tarifa'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Tarifa actualizada exitosamente'
        ]);
    }
}