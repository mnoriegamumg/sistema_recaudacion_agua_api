<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\JWTService;

class JWTServiceTest extends TestCase
{
    private JWTService $jwtService;
    private array $userData;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurar variables de entorno para pruebas
        $_ENV['JWT_SECRET'] = 'test_secret_key_for_testing_only';
        $_ENV['JWT_EXPIRATION'] = '3600';

        $this->jwtService = new JWTService();

        $this->userData = [
            'id_usuario' => 1,
            'correo' => 'admin@municipalidad.com',
            'nombre_completo' => 'Administrador',
            'rol' => 'admin'
        ];
    }

    /**
     * Prueba 1: Generar un token JWT válido
     */
    public function test_generar_token_valido(): void
    {
        $token = $this->jwtService->generateToken($this->userData);

        $this->assertNotEmpty($token);
        $this->assertIsString($token);

        // Un token JWT tiene 3 partes separadas por puntos
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'El token JWT debe tener 3 partes');
    }

    /**
     * Prueba 2: Validar un token generado correctamente
     */
    public function test_validar_token_correcto(): void
    {
        $token = $this->jwtService->generateToken($this->userData);
        $decoded = $this->jwtService->validateToken($token);

        $this->assertNotNull($decoded);
        $this->assertIsObject($decoded);
        $this->assertEquals(1, $decoded->sub);
        $this->assertEquals('admin@municipalidad.com', $decoded->email);
        $this->assertEquals('admin', $decoded->rol);
    }

    /**
     * Prueba 3: Rechazar un token inválido
     */
    public function test_rechazar_token_invalido(): void
    {
        $tokenInvalido = 'token.invalido.12345';
        $decoded = $this->jwtService->validateToken($tokenInvalido);

        $this->assertNull($decoded, 'Un token inválido debe retornar null');
    }

    /**
     * Prueba 4: Rechazar un token firmado con otra clave
     */
    public function test_rechazar_token_con_firma_incorrecta(): void
    {
        // Generar token con un servicio que usa otra clave
        $_ENV['JWT_SECRET'] = 'otra_clave_diferente';
        $otroServicio = new JWTService();
        $tokenConOtraFirma = $otroServicio->generateToken($this->userData);

        // Restaurar la clave original
        $_ENV['JWT_SECRET'] = 'test_secret_key_for_testing_only';
        $this->jwtService = new JWTService();

        $decoded = $this->jwtService->validateToken($tokenConOtraFirma);

        $this->assertNull($decoded, 'Un token firmado con otra clave debe ser rechazado');
    }
}