<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Middleware\AuthMiddleware;

// ============================================
// 1. CONFIGURACIÓN INICIAL
// ============================================

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Configurar CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configurar Content-Type a JSON
header('Content-Type: application/json');

// ============================================
// 2. OBTENER URI Y MÉTODO
// ============================================

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remover query string
$uri = strtok($uri, '?');

// ============================================
// 3. DEFINIR RUTAS PÚBLICAS (Sin autenticación)
// ============================================

$publicRoutes = [
    'POST /api/auth/login',
    'OPTIONS /api/auth/login'
];

// ============================================
// 4. DEFINIR RUTAS PROTEGIDAS (Con autenticación)
// ============================================

$protectedRoutes = [
    // ==========================================
    // AUTENTICACIÓN
    // ==========================================
    'GET /api/auth/me' => ['App\Controllers\AuthController', 'me'],

    // ==========================================
    // CONTADORES
    // ==========================================
    'POST /api/contadores' => ['App\Controllers\ContadorController', 'crear'],
    'GET /api/contadores' => ['App\Controllers\ContadorController', 'listar'],
    'GET /api/contadores/{id}' => ['App\Controllers\ContadorController', 'obtener'],
    'GET /api/contadores/codigo/{codigo}' => ['App\Controllers\ContadorController', 'buscarPorCodigo'],
    'GET /api/contadores/dpi/{dpi}' => ['App\Controllers\ContadorController', 'buscarPorDpi'],
    'PUT /api/contadores/{id}' => ['App\Controllers\ContadorController', 'actualizar'],
    'DELETE /api/contadores/{id}' => ['App\Controllers\ContadorController', 'eliminar'],

    // ==========================================
    // CLIENTES
    // ==========================================
    'POST /api/clientes' => ['App\Controllers\ClienteController', 'crear'],
    'GET /api/clientes' => ['App\Controllers\ClienteController', 'listar'],
    'GET /api/clientes/{id}' => ['App\Controllers\ClienteController', 'obtener'],
    'GET /api/clientes/contador/{id_contador}' => ['App\Controllers\ClienteController', 'obtenerPorContador'],
    'PUT /api/clientes/{id}' => ['App\Controllers\ClienteController', 'actualizar'],
    'DELETE /api/clientes/{id}' => ['App\Controllers\ClienteController', 'eliminar'],

    // ==========================================
    // PAGOS
    // ==========================================
    'POST /api/pagos' => ['App\Controllers\PagoController', 'registrar'],
    'GET /api/pagos' => ['App\Controllers\PagoController', 'listar'],
    'GET /api/pagos/{id}' => ['App\Controllers\PagoController', 'obtener'],
    'GET /api/pagos/recibo/{numero}' => ['App\Controllers\PagoController', 'buscarPorRecibo'],
    'GET /api/pagos/contador/{id_contador}' => ['App\Controllers\PagoController', 'historialPorContador'],
    'GET /api/pagos/tarifa/{id_contador}' => ['App\Controllers\PagoController', 'obtenerTarifa'],
    'GET /api/pagos/resumen/diario' => ['App\Controllers\PagoController', 'resumenDiario'],
    'PUT /api/pagos/{id}' => ['App\Controllers\PagoController', 'anular'],

    // ==========================================
    // MOROSIDAD
    // ==========================================
    'GET /api/morosidad/actual' => ['App\Controllers\MorosidadController', 'estadoActual'],
    'GET /api/morosidad/historial/{id_contador}' => ['App\Controllers\MorosidadController', 'historial'],
    'GET /api/morosidad/morosos' => ['App\Controllers\MorosidadController', 'morosos'],
    'GET /api/morosidad/resumen' => ['App\Controllers\MorosidadController', 'resumen'],
    'GET /api/morosidad/{mes}/{ano}' => ['App\Controllers\MorosidadController', 'obtenerPorMesAno'],
    'POST /api/morosidad/calcular' => ['App\Controllers\MorosidadController', 'calcular'],

    // ==========================================
    // TARIFAS
    // ==========================================
    'GET /api/tarifas/actual' => ['App\Controllers\TarifaController', 'actual'],
    'POST /api/tarifas' => ['App\Controllers\TarifaController', 'crear'],
    'PUT /api/tarifas/{id}' => ['App\Controllers\TarifaController', 'actualizar'],
];

// ============================================
// 5. DETERMINAR SI LA RUTA ES PÚBLICA
// ============================================

$routeKey = $method . ' ' . $uri;
$isPublic = in_array($routeKey, $publicRoutes);

// ============================================
// 6. FUNCIÓN PARA EXTRAER PARÁMETROS DE URL
// ============================================

function matchRoute($route, $uri) {
    // Extraer solo la parte de la ruta (sin el método HTTP)
    $routeParts = explode(' ', $route);
    if (count($routeParts) < 2) {
        return false;
    }
    $routePath = $routeParts[1]; // Ej: "/api/contadores" o "/api/contadores/{id}"
    
    // Dividir la ruta y la URI
    $routeSegments = explode('/', trim($routePath, '/'));
    $uriSegments = explode('/', trim($uri, '/'));
    
    // Verificar que tengan el mismo número de segmentos
    if (count($routeSegments) !== count($uriSegments)) {
        return false;
    }
    
    $params = [];
    for ($i = 0; $i < count($routeSegments); $i++) {
        // Si es un parámetro dinámico {id}, {codigo}, etc.
        if (preg_match('/^{.*}$/', $routeSegments[$i])) {
            $paramName = trim($routeSegments[$i], '{}');
            $params[$paramName] = $uriSegments[$i];
        } 
        // Si no es dinámico, debe coincidir exactamente
        elseif ($routeSegments[$i] !== $uriSegments[$i]) {
            return false;
        }
    }
    
    return $params;
}

// ============================================
// 7. EJECUTAR RUTA
// ============================================

try {
    // Verificar autenticación (si no es pública)
    if (!$isPublic) {
        $authMiddleware = new AuthMiddleware();
        $userData = $authMiddleware->validate();
        $_SERVER['USER_DATA'] = $userData;
    }

    // Buscar en rutas protegidas
    $found = false;
    foreach ($protectedRoutes as $route => $handler) {
        $params = matchRoute($route, $uri);
        if ($params !== false && $method === explode(' ', $route)[0]) {
            $found = true;
            
            [$controllerClass, $methodName] = $handler;
            
            if (!class_exists($controllerClass)) {
                throw new Exception('Controller no encontrado: ' . $controllerClass);
            }

            $controller = new $controllerClass();

            if (!method_exists($controller, $methodName)) {
                throw new Exception('Método no encontrado: ' . $methodName);
            }

            // Llamar al controlador con los parámetros
            $controller->$methodName(...array_values($params));
            break;
        }
    }

    // Si no se encontró en protegidas, buscar en públicas
    if (!$found && $isPublic) {
        if ($routeKey === 'POST /api/auth/login') {
            $controller = new App\Controllers\AuthController();
            $controller->login();
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Ruta pública no encontrada'
            ]);
        }
    } elseif (!$found && !$isPublic) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Ruta no encontrada',
            'route' => $routeKey
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor',
        'message' => $e->getMessage()
    ]);
}