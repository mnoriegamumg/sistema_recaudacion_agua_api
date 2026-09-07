<?php
namespace App\Controllers;

use App\Models\Contador;

class ContadorController {
    private Contador $contadorModel;

    public function __construct() {
        $this->contadorModel = new Contador();
    }

    /**
     * POST /api/contadores
     * Crear nuevo contador
     */
    public function crear() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar campos requeridos
        if (empty($data['codigo_contador']) || empty($data['nombre_propietario'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'codigo_contador y nombre_propietario son requeridos'
            ]);
            return;
        }

        // Verificar que el código no exista
        $existente = $this->contadorModel->findByCodigo($data['codigo_contador']);
        if ($existente) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'El código de contador ya existe'
            ]);
            return;
        }

        $data['created_by'] = $_SERVER['USER_DATA']->sub ?? 1;
        $result = $this->contadorModel->create($data);
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data' => $result,
            'message' => 'Contador creado exitosamente'
        ]);
    }

    /**
     * GET /api/contadores
     * Listar todos los contadores
     */
    public function listar() {
        $contadores = $this->contadorModel->getAll();
        
        echo json_encode([
            'success' => true,
            'data' => $contadores,
            'meta' => [
                'total' => count($contadores)
            ]
        ]);
    }

    /**
     * GET /api/contadores/{id}
     * Obtener contador por ID
     */
    public function obtener($id) {
        $contador = $this->contadorModel->findById($id);
        
        if (!$contador) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Contador no encontrado'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $contador
        ]);
    }

    /**
     * GET /api/contadores/codigo/{codigo}
     * Buscar por código de contador
     */
    public function buscarPorCodigo($codigo) {
        $contador = $this->contadorModel->findByCodigo($codigo);
        
        if (!$contador) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Contador no encontrado con el código: ' . $codigo
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $contador
        ]);
    }

    /**
     * GET /api/contadores/dpi/{dpi}
     * Buscar por DPI
     */
    public function buscarPorDpi($dpi) {
        $contador = $this->contadorModel->findByDpi($dpi);
        
        if (!$contador) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Contador no encontrado con el DPI: ' . $dpi
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $contador
        ]);
    }

    /**
     * PUT /api/contadores/{id}
     * Actualizar contador
     */
    public function actualizar($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $contador = $this->contadorModel->findById($id);
        if (!$contador) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Contador no encontrado'
            ]);
            return;
        }

        // Verificar que el código no exista en otro contador
        if (isset($data['codigo_contador'])) {
            $existente = $this->contadorModel->findByCodigo($data['codigo_contador']);
            if ($existente && $existente['id_contador'] != $id) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error' => 'El código de contador ya está en uso por otro contador'
                ]);
                return;
            }
        }

        $data['updated_by'] = $_SERVER['USER_DATA']->sub ?? 1;
        $result = $this->contadorModel->update($id, $data);
        
        if (!$result) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'No se pudo actualizar el contador'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $this->contadorModel->findById($id),
            'message' => 'Contador actualizado exitosamente'
        ]);
    }

    /**
     * DELETE /api/contadores/{id}
     * Eliminar contador (soft delete)
     */
    public function eliminar($id) {
        $contador = $this->contadorModel->findById($id);
        if (!$contador) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Contador no encontrado'
            ]);
            return;
        }

        $userId = $_SERVER['USER_DATA']->sub ?? 1;
        $result = $this->contadorModel->delete($id, $userId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Contador eliminado exitosamente'
        ]);
    }
}