#!/usr/bin/env php
<?php
/**
 * Script para ejecutar cálculo de morosidad
 * Uso: php crons/calcular_morosidad.php [mes] [año]
 * Ejemplo: php crons/calcular_morosidad.php 1 2026
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\MorosidadService;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Obtener parámetros o usar mes/año actual
$mes = $argv[1] ?? (int)date('m');
$ano = $argv[2] ?? (int)date('Y');

echo "=== CALCULANDO MOROSIDAD ===\n";
echo "Mes: $mes, Año: $ano\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $morosidadService = new MorosidadService();
    $resultado = $morosidadService->calcularMorosidad($mes, $ano);
    
    echo "✅ Cálculo completado:\n";
    echo "   - Total contadores: {$resultado['total_calculados']}\n";
    echo "   - Solventes: {$resultado['solventes']}\n";
    echo "   - Morosos: {$resultado['morosos']}\n";
    
    if (!empty($resultado['errores'])) {
        echo "\n⚠️ Errores encontrados:\n";
        foreach ($resultado['errores'] as $error) {
            echo "   - Contador {$error['contador']}: {$error['error']}\n";
        }
    }
    
    echo "\n✅ Proceso finalizado.\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}