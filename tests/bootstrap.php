<?php
/**
 * Bootstrap para las pruebas unitarias y de integración
 * Sistema de Recaudación de Agua Potable
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Cargar variables de entorno desde .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Establecer variables de entorno para testing si no están definidas
if (!isset($_ENV['JWT_SECRET'])) {
    $_ENV['JWT_SECRET'] = 'test_secret_key_for_testing';
}
if (!isset($_ENV['JWT_EXPIRATION'])) {
    $_ENV['JWT_EXPIRATION'] = 3600;
}

echo "✅ Bootstrap de pruebas cargado correctamente\n";