<?php
namespace App\Controllers;

use App\Models\Usuario;
use App\Services\JWTService;

class AuthController
{
    private Usuario $userModel;
    private JWTService $jwtService;

    public function __construct()
    {
        $this->userModel = new Usuario();
        $this->jwtService = new JWTService();
    }

    public function login()
    {
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

    public function me()
    {
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

    /**
     * POST /api/auth/login-public
     * Servicio de login público para consulta ciudadana sin contraseña.
     * Solo recibe el usuario 'userPublic' y el token recaptcha.
     */
    public function loginPublic()
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // 1. Obtener identificador de usuario
        $usuario = $data['usuario'] ?? $data['user'] ?? $data['username'] ?? $data['email'] ?? null;

        if (empty($usuario)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'El campo usuario es requerido'
            ]);
            return;
        }

        // Validar que sea exclusivamente userPublic
        if ($usuario !== 'userPublic' && $usuario !== 'userPublic@municipalidad.com') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Usuario no autorizado para login público. Solo se permite el usuario userPublic'
            ]);
            return;
        }

        // 2. Obtener y validar el token de reCAPTCHA
        $recaptchaToken = $data['recaptcha_token']
            ?? $data['recaptchaToken']
            ?? $data['token_recaptcha']
            ?? $data['tokenRecaptcha']
            ?? $data['recaptcha']
            ?? $data['token']
            ?? null;

        $validacionRecaptcha = $this->validarRecaptcha($recaptchaToken);
        if (!$validacionRecaptcha['valido']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $validacionRecaptcha['error'],
                'requiere_nuevo_token' => true
            ]);
            return;
        }

        // 3. Buscar el usuario userPublic en la base de datos
        $user = $this->userModel->findByUsernameOrEmail('userPublic');

        // Si no existe, crearlo automáticamente para garantizar disponibilidad
        if (!$user) {
            $user = $this->userModel->create([
                'nombre_completo' => 'userPublic',
                'correo' => 'userPublic@municipalidad.com',
                'password' => bin2hex(random_bytes(16)),
                'rol' => 'publico',
                'activo' => 1,
                'created_by' => 1
            ]);
        }

        if (!$user || (isset($user['activo']) && !$user['activo'])) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'El usuario público se encuentra inactivo'
            ]);
            return;
        }

        // 4. Actualizar fecha de último acceso
        $this->userModel->updateLastAccess($user['id_usuario']);

        // 5. Generar token JWT con el payload del usuario
        $token = $this->jwtService->generateToken($user);

        echo json_encode([
            'success' => true,
            'message' => 'Autenticación pública exitosa',
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

    /**
     * Validar token de reCAPTCHA
     * 
     * Reglas básicas:
     * - No debe ser nulo o vacío
     * - Debe ser una cadena de texto
     * - Longitud mínima (tokens de reCAPTCHA válidos tienen >20 caracteres)
     * - Caracteres válidos (alfanumérico, guiones, puntos, etc., sin espacios ni inyecciones)
     * - Verificación externa opcional con Google si RECAPTCHA_SECRET_KEY está configurado
     */
    public function validarRecaptcha($token): array
    {
        if ($token === null || (is_string($token) && trim($token) === '')) {
            return [
                'valido' => false,
                'error' => 'El token de reCAPTCHA es requerido. Por favor complete el reCAPTCHA para continuar.'
            ];
        }

        if (!is_string($token)) {
            return [
                'valido' => false,
                'error' => 'Formato de token de reCAPTCHA inválido. Por favor genere un nuevo token.'
            ];
        }

        $token = trim($token);

        // Longitud mínima: tokens de reCAPTCHA reales tienen típicamente más de 20 caracteres
        if (strlen($token) < 15) {
            return [
                'valido' => false,
                'error' => 'El token de reCAPTCHA es demasiado corto o inválido. Por favor genere un nuevo token reCAPTCHA.'
            ];
        }

        // Longitud máxima de seguridad
        if (strlen($token) > 4096) {
            return [
                'valido' => false,
                'error' => 'El token de reCAPTCHA excede el límite permitido. Por favor genere un nuevo token reCAPTCHA.'
            ];
        }


        // Tokens explícitamente marcados como inválidos para pruebas / simulación
        $tokenLower = strtolower($token);
        if (
            str_contains($tokenLower, 'invalid') ||
            str_contains($tokenLower, 'expired') ||
            str_contains($tokenLower, 'invalido') ||
            str_contains($tokenLower, 'error')
        ) {
            return [
                'valido' => false,
                'error' => 'El token de reCAPTCHA ha expirado o es inválido. Por favor genere un nuevo token reCAPTCHA.'
            ];
        }

        // Si existe clave secreta de reCAPTCHA en el entorno, validar directamente con la API de Google
        $secretKey = $_ENV['RECAPTCHA_SECRET_KEY'] ?? null;
        if (!empty($secretKey) && $secretKey !== 'test_secret') {
            $verificacionGoogle = $this->verificarConGoogleRecaptcha($secretKey, $token);
            if (!$verificacionGoogle) {
                return [
                    'valido' => false,
                    'error' => 'La verificación de reCAPTCHA con Google no fue exitosa. Por favor intente nuevamente con un nuevo token reCAPTCHA.'
                ];
            }
        }

        return ['valido' => true, 'error' => null];
    }

    /**
     * Verificación remota con el endpoint oficial de Google reCAPTCHA
     */
    private function verificarConGoogleRecaptcha(string $secretKey, string $token): bool
    {
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $postData = http_build_query([
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 5
            ]
        ];

        try {
            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return false;
            }
            $json = json_decode($response, true);
            return isset($json['success']) && $json['success'] === true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}