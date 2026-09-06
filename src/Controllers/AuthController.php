<?php
namespace App\Controllers;

use App\Models\User;
use App\Services\JWTService;

class AuthController {
    private User $userModel;
    private JWTService $jwtService;

    public function __construct() {
        $this->userModel = new User();
        $this->jwtService = new JWTService();
    }

    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email y contraseña son requeridos']);
            return;
        }

        $user = $this->userModel->findByEmail($data['email']);

        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Credenciales inválidas']);
            return;
        }

        // Actualizar último acceso
        $this->userModel->updateLastAccess($user['id_usuario']);

        // Generar token JWT
        $token = $this->jwtService->generateToken($user);

        echo json_encode([
            'success' => true,
            'data' => [
                'token' => $token,
                'usuario' => [
                    'id' => $user['id_usuario'],
                    'nombre' => $user['nombre_completo'],
                    'email' => $user['correo'],
                    'rol' => $user['rol']
                ]
            ]
        ]);
    }

    public function me() {
        $authMiddleware = new \App\Middleware\AuthMiddleware();
        $userData = $authMiddleware->validate();

        $user = $this->userModel->findById($userData->sub);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $user
        ]);
    }
}