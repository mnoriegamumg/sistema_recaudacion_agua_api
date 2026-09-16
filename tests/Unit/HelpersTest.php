<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Utils\Helpers;

class HelpersTest extends TestCase
{
    /**
     * Prueba 1: Validar formato de DPI guatemalteco
     */
    public function test_validar_dpi_guatemalteco(): void
    {
        // DPI válido (13 dígitos)
        $this->assertTrue(Helpers::validarDPI('1234567890101'));

        // DPI inválido (menos de 13 dígitos)
        $this->assertFalse(Helpers::validarDPI('123456'));

        // DPI inválido (con letras)
        $this->assertFalse(Helpers::validarDPI('12345678901AB'));

        // DPI vacío
        $this->assertFalse(Helpers::validarDPI(''));
    }

    /**
     * Prueba 2: Validar formato de NIT guatemalteco
     */
    public function test_validar_nit_guatemalteco(): void
    {
        // NIT válido
        $this->assertTrue(Helpers::validarNIT('1234567-8'));
        $this->assertTrue(Helpers::validarNIT('12345678-9'));
        $this->assertTrue(Helpers::validarNIT('123456-7'));

        // NIT inválido
        $this->assertFalse(Helpers::validarNIT('12345678'));
        $this->assertFalse(Helpers::validarNIT('ABC-123'));
        $this->assertFalse(Helpers::validarNIT(''));
    }

    /**
     * Prueba 3: Formatear monto en Quetzales
     */
    public function test_formatear_monto_quetzales(): void
    {
        $this->assertEquals('Q125.00', Helpers::formatearMonto(125.00));
        $this->assertEquals('Q1,350.50', Helpers::formatearMonto(1350.50));
        $this->assertEquals('Q0.00', Helpers::formatearMonto(0));
        $this->assertEquals('Q1,000,000.00', Helpers::formatearMonto(1000000));
    }

    /**
     * Prueba 4: Obtener nombre del mes en español
     */
    public function test_obtener_nombre_mes(): void
    {
        $this->assertEquals('Enero', Helpers::getNombreMes(1));
        $this->assertEquals('Febrero', Helpers::getNombreMes(2));
        $this->assertEquals('Diciembre', Helpers::getNombreMes(12));
        $this->assertEquals('Mes inválido', Helpers::getNombreMes(13));
        $this->assertEquals('Mes inválido', Helpers::getNombreMes(0));
    }
}