<?php
namespace App\Utils;

/**
 * Funciones auxiliares reutilizables en toda la aplicación
 */
class Helpers {
    
    /**
     * Generar número de recibo único
     * Ejemplo: REC-202601-0001
     */
    public static function generarNumeroRecibo(int $contador = null): string {
        $fecha = date('Ym');
        $secuencia = $contador ?? rand(1, 9999);
        return 'REC-' . $fecha . '-' . str_pad($secuencia, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validar formato de DPI guatemalteco
     * Formato: 1234567890101 (13 dígitos)
     */
    public static function validarDPI(string $dpi): bool {
        return preg_match('/^[0-9]{13}$/', $dpi) === 1;
    }

    /**
     * Validar formato de NIT guatemalteco
     * Formato: 1234567-8 o 12345678-9
     */
    public static function validarNIT(string $nit): bool {
        return preg_match('/^[0-9]{1,8}-[0-9Kk]$/', $nit) === 1;
    }

    /**
     * Validar formato de teléfono guatemalteco
     * Formatos: 1234-5678, 12345678, (123) 456-7890
     */
    public static function validarTelefono(string $telefono): bool {
        return preg_match('/^[0-9\s\-\(\)]{8,15}$/', $telefono) === 1;
    }

    /**
     * Formatear monto en Quetzales
     * Ejemplo: 125.50 -> Q125.50
     */
    public static function formatearMonto(float $monto): string {
        return 'Q' . number_format($monto, 2, '.', ',');
    }

    /**
     * Obtener nombre del mes en español
     * Ejemplo: 1 -> Enero
     */
    public static function getNombreMes(int $mes): string {
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        return $meses[$mes] ?? 'Mes inválido';
    }

    /**
     * Obtener mes y año actuales
     */
    public static function getMesAnioActual(): array {
        return [
            'mes' => (int)date('m'),
            'ano' => (int)date('Y')
        ];
    }

    /**
     * Verificar si un contador está moroso en el mes actual
     */
    public static function estaMoroso(float $saldoPendiente): bool {
        return $saldoPendiente > 0;
    }

    /**
     * Generar respuesta JSON estandarizada
     */
    public static function jsonResponse(bool $success, $data = null, string $message = '', int $statusCode = 200): void {
        http_response_code($statusCode);
        echo json_encode([
            'success' => $success,
            'data' => $data,
            'message' => $message
        ]);
    }

    /**
     * Calcular porcentaje de morosidad
     */
    public static function calcularPorcentajeMorosidad(int $totalMorosos, int $totalContadores): float {
        if ($totalContadores === 0) {
            return 0;
        }
        return round(($totalMorosos / $totalContadores) * 100, 2);
    }

    /**
     * Validar fecha (YYYY-MM-DD)
     */
    public static function validarFecha(string $fecha): bool {
        $date = \DateTime::createFromFormat('Y-m-d', $fecha);
        return $date && $date->format('Y-m-d') === $fecha;
    }

    /**
     * Obtener rango de fechas para un mes específico
     */
    public static function getRangoMes(int $mes, int $ano): array {
        $primerDia = date('Y-m-d', strtotime("$ano-$mes-01"));
        $ultimoDia = date('Y-m-t', strtotime("$ano-$mes-01"));
        return [
            'inicio' => $primerDia,
            'fin' => $ultimoDia
        ];
    }

    /**
     * Limpiar datos de entrada (XSS prevention)
     */
    public static function sanitizarInput(string $input): string {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Verificar si un string está vacío o es null
     */
    public static function isEmpty($value): bool {
        return $value === null || trim((string)$value) === '';
    }

    /**
     * Generar código de contador secuencial
     */
    public static function generarCodigoContador(int $consecutivo): string {
        return 'M-' . str_pad($consecutivo, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calcular tarifa anual con descuento
     */
    public static function calcularTarifaAnual(float $tarifaMensual, float $descuentoPorcentaje): float {
        $totalSinDescuento = $tarifaMensual * 12;
        return $totalSinDescuento - ($totalSinDescuento * ($descuentoPorcentaje / 100));
    }
}