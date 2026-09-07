<?php
namespace App\Controllers;

use App\Models\Cliente;
use App\Models\Contador;

class ClienteController {
    private Cliente $clienteModel;
    private Contador $contadorModel;

    public function __construct() {
        $this->clienteModel = new Cliente();
        $this->contadorModel = new Contador();
    }

    /**
     * POST /api/clientes
     * Crear cliente asociado a un contador
     */
    public function crear() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar campos requeridos
        if (empty($data['id_contador']) || empty($data['comunidad'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'id_contador y comunidad son requeridos'
            ]);
            return;
        }

        // Verificar que el contador existe
        $contador = $this->contadorModel->findById($data['id_contador']);
        if (!$contador) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Contador no encontrado'
            ]);
            return;
        }

        // Verificar que no tenga cliente asociado
        $existente = $this->clienteModel->findByContador($data['id_contador']);
        if ($existente) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'Este contador ya tiene un cliente asociado'
            ]);
            return;
        }

        $data['created_by'] = $_SERVER['USER_DATA']->sub ?? 1;
        $result = $this->clienteModel->create($data);
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data' => $result,
            'message' => 'Cliente creado exitosamente'
        ]);
    }

    /**
     * GET /api/clientes
     * Listar todos los clientes
     */
    public function listar() {
        $clientes = $this->clienteModel->getAll();
        
        echo json_encode([
            'success' => true,
            'data' => $clientes,
            'meta' => [
                'total' => count($clientes)
            ]
        ]);
    }

    /**
     * GET /api/clientes/{id}
     * Obtener cliente por ID
     */
    public function obtener($id) {
        $cliente = $this->clienteModel->findById($id);
        
        if (!$cliente) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Cliente no encontrado'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $cliente
        ]);
    }

    /**
     * GET /api/clientes/contador/{id_contador}
     * Obtener cliente por ID de contador
     */
    public function obtenerPorContador($idContador) {
        $cliente = $this->clienteModel->findByContador($idContador);
        
        if (!$cliente) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Cliente no encontrado para este contador'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $cliente
        ]);
    }

    /**
     * PUT /api/clientes/{id}
     * Actualizar cliente
     */
    public function actualizar($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $cliente = $this->clienteModel->findById($id);
        if (!$cliente) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Cliente no encontrado'
            ]);
            return;
        }

        // Validar comunidad si se envía
        if (isset($data['comunidad'])) {
            $comunidadesPermitidas = ['cabecera_municipal', 'caserio_san_pablo', 'finca_san_francisco'];
            if (!in_array($data['comunidad'], $comunidadesPermitidas)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Comunidad inválida. Opciones: ' . implode(', ', $comunidadesPermitidas)
                ]);
                return;
            }
        }

        $data['updated_by'] = $_SERVER['USER_DATA']->sub ?? 1;
        $result = $this->clienteModel->update($id, $data);
        
        if (!$result) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'No se pudo actualizar el cliente'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $this->clienteModel->findById($id),
            'message' => 'Cliente actualizado exitosamente'
        ]);
    }

    /**
     * DELETE /api/clientes/{id}
     * Eliminar cliente
     */
    public function eliminar($id) {
        $cliente = $this->clienteModel->findById($id);
        if (!$cliente) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Cliente no encontrado'
            ]);
            return;
        }

        $result = $this->clienteModel->delete($id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Cliente eliminado exitosamente'
        ]);
    }
}