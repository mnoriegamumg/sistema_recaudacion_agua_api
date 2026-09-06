<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Configurar CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configurar Content-Type a JSON
header('Content-Type: application/json');

// Obtener URI y método
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remover query string
$uri = strtok($uri, '?');

// Router simple
$routes = [
    'POST /api/auth/login' => ['App\Controllers\AuthController', 'login'],
    'GET /api/auth/me' => ['App\Controllers\AuthController', 'me'],
    'GET /api/usuarios' => ['App\Controllers\UsuarioController', 'listar'],
    'GET /api/usuarios/buscar' => ['App\Controllers\UsuarioController', 'buscarPorMedidor'],
    'GET /api/usuarios/comunidad' => ['App\Controllers\UsuarioController', 'listarPorComunidad'],
];

$routeKey = $method . ' ' . $uri;

try {
    if (isset($routes[$routeKey])) {
        [$controllerClass, $methodName] = $routes[$routeKey];
        
        if (!class_exists($controllerClass)) {
            throw new Exception('Controller no encontrado');
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $methodName)) {
            throw new Exception('Método no encontrado');
        }

        $controller->$methodName();
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Ruta no encontrada']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}