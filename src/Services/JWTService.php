<?php
namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTService {
    private string $secret;
    private int $expiration;

    public function __construct() {
        $this->secret = $_ENV['JWT_SECRET'] ?? 'default_secret_change_me';
        $this->expiration = (int)($_ENV['JWT_EXPIRATION'] ?? 3600);
    }

    public function generateToken(array $userData): string {
        $payload = [
            'sub' => $userData['id_usuario'],
            'email' => $userData['correo'],
            'rol' => $userData['rol'],
            'iat' => time(),
            'exp' => time() + $this->expiration
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function validateToken(string $token): ?object {
        try {
            return JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (\Exception $e) {
            return null;
        }
    }
}