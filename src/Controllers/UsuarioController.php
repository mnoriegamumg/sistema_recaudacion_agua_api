<?php
namespace App\Controllers;

use App\Models\UsuarioAgua;
use App\Middleware\AuthMiddleware;

class UsuarioController {
    private UsuarioAgua $usuarioAguaModel;

    public function __construct() {
        $this->usuarioAguaModel = new UsuarioAgua();
    }

    public function listar() {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->validate();

        $usuarios = $this->usuarioAguaModel->getAll();
        
        echo json_encode([
            'success' => true,
            'data' => $usuarios
        ]);
    }

    public function buscarPorMedidor() {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->validate();

        $medidor = $_GET['medidor'] ?? null;

        if (!$medidor) {
            http_response_code(400);
            echo json_encode(['error' => 'Número de medidor requerido']);
            return;
        }

        $usuario = $this->usuarioAguaModel->findByMedidor($medidor);

        if (!$usuario) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $usuario
        ]);
    }

    public function listarPorComunidad() {
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->validate();

        $comunidad = $_GET['comunidad'] ?? null;

        if (!$comunidad) {
            http_response_code(400);
            echo json_encode(['error' => 'Comunidad requerida']);
            return;
        }

        $usuarios = $this->usuarioAguaModel->findByComunidad($comunidad);
        
        echo json_encode([
            'success' => true,
            'data' => $usuarios
        ]);
    }
}