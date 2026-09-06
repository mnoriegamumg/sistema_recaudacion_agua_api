<?php
namespace App\Middleware;

use App\Services\JWTService;

class AuthMiddleware {
    private JWTService $jwtService;

    public function __construct() {
        $this->jwtService = new JWTService();
    }

    public function validate(): ?object {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            http_response_code(401);
            echo json_encode(['error' => 'Token no proporcionado']);
            exit;
        }

        $token = substr($authHeader, 7);
        $decoded = $this->jwtService->validateToken($token);

        if (!$decoded) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido o expirado']);
            exit;
        }

        return $decoded;
    }
}