<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Utils\Helpers;

class ValidacionesTest extends TestCase
{
    /**
     * Prueba 1: Validar formato de correo electrónico
     */
    public function test_validar_correo_electronico(): void
    {
        $this->assertTrue(filter_var('admin@municipalidad.com', FILTER_VALIDATE_EMAIL) !== false);
        $this->assertTrue(filter_var('usuario.test@email.gt', FILTER_VALIDATE_EMAIL) !== false);

        $this->assertFalse(filter_var('correo-invalido', FILTER_VALIDATE_EMAIL) !== false);
        $this->assertFalse(filter_var('@sin-usuario.com', FILTER_VALIDATE_EMAIL) !== false);
        $this->assertFalse(filter_var('', FILTER_VALIDATE_EMAIL) !== false);
    }

    /**
     * Prueba 2: Validar formato de teléfono guatemalteco
     */
    public function test_validar_telefono_guatemalteco(): void
    {
        // Formatos válidos
        $this->assertTrue(Helpers::validarTelefono('5551-1234'));
        $this->assertTrue(Helpers::validarTelefono('55511234'));
        $this->assertTrue(Helpers::validarTelefono('(555) 123-4567'));

        // Formatos inválidos
        $this->assertFalse(Helpers::validarTelefono('123'));
        $this->assertFalse(Helpers::validarTelefono('abcdefgh'));
        $this->assertFalse(Helpers::validarTelefono(''));
    }

    /**
     * Prueba 3: Validar formato de fecha (YYYY-MM-DD)
     */
    public function test_validar_fecha(): void
    {
        $this->assertTrue(Helpers::validarFecha('2026-01-15'));
        $this->assertTrue(Helpers::validarFecha('2026-12-31'));

        $this->assertFalse(Helpers::validarFecha('15-01-2026'));
        $this->assertFalse(Helpers::validarFecha('2026/01/15'));
        $this->assertFalse(Helpers::validarFecha('fecha-invalida'));
        $this->assertFalse(Helpers::validarFecha(''));
    }

    /**
     * Prueba 4: Validar que un string no esté vacío
     */
    public function test_validar_string_no_vacio(): void
    {
        $this->assertFalse(Helpers::isEmpty('texto'));
        $this->assertFalse(Helpers::isEmpty('   texto con espacios   '));

        $this->assertTrue(Helpers::isEmpty(''));
        $this->assertTrue(Helpers::isEmpty('   '));
        $this->assertTrue(Helpers::isEmpty(null));
    }
}