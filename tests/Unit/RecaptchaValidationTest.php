<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Controllers\AuthController;

class RecaptchaValidationTest extends TestCase
{
    private AuthController $authController;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authController = new AuthController();
    }

    /**
     * Prueba 1: Token nulo o vacío debe fallar
     */
    public function test_token_vacio_falla(): void
    {
        $resNull = $this->authController->validarRecaptcha(null);
        $this->assertFalse($resNull['valido']);
        $this->assertStringContainsString('requerido', $resNull['error']);

        $resVacio = $this->authController->validarRecaptcha('');
        $this->assertFalse($resVacio['valido']);
    }

    /**
     * Prueba 2: Token de longitud insuficiente (<20 caracteres) debe fallar
     */
    public function test_token_corto_falla(): void
    {
        $res = $this->authController->validarRecaptcha('abc123short');
        $this->assertFalse($res['valido']);
        $this->assertStringContainsString('demasiado corto', $res['error']);
    }

    /**
     * Prueba 3: Token con caracteres inválidos (inyecciones, espacios) debe fallar
     */
    public function test_token_con_caracteres_invalidos_falla(): void
    {
        $res = $this->authController->validarRecaptcha('token con espacios y simbolos <>');
        $this->assertFalse($res['valido']);
        $this->assertStringContainsString('caracteres inválidos', $res['error']);
    }

    /**
     * Prueba 4: Token explícitamente expirado o marcado como error debe fallar
     */
    public function test_token_marcado_como_invalido_falla(): void
    {
        $res1 = $this->authController->validarRecaptcha('test_expired_token_1234567890');
        $this->assertFalse($res1['valido']);

        $res2 = $this->authController->validarRecaptcha('test_invalid_token_1234567890');
        $this->assertFalse($res2['valido']);
    }

    /**
     * Prueba 5: Token válido bien estructurado debe pasar
     */
    public function test_token_valido_pasa(): void
    {
        $tokenValido = '03AFcWeA7XYZ_test_recaptcha_token_valid_1234567890';
        $res = $this->authController->validarRecaptcha($tokenValido);
        $this->assertTrue($res['valido']);
        $this->assertNull($res['error']);
    }
}
