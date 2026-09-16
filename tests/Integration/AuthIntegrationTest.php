<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use App\Services\JWTService;
use App\Config\Database;

class AuthIntegrationTest extends TestCase
{
    private static bool $dbConfigured = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Configurar BD de pruebas
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_NAME'] = 'municipalidad_santa_barbara_test';
        $_ENV['DB_USER'] = 'root';
        $_ENV['DB_PASSWORD'] = '';
        $_ENV['JWT_SECRET'] = 'test_secret_key_for_testing_only';
        $_ENV['JWT_EXPIRATION'] = '3600';

        self::$dbConfigured = true;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$dbConfigured) {
            $this->markTestSkipped('La base de datos no está configurada');
        }

        // Limpiar tabla de usuarios antes de cada prueba
        try {
            $db = Database::getInstance()->getConnection();
            $db->exec("DELETE FROM usuarios_sistema WHERE correo LIKE '%test%'");
        } catch (\Exception $e) {
            $this->markTestSkipped('No se pudo conectar a la BD de pruebas: ' . $e->getMessage());
        }
    }

    /**
     * Prueba de Integración 1: Login exitoso con credenciales válidas
     */
    public function test_login_exitoso_con_credenciales_validas(): void
    {
        $db = Database::getInstance()->getConnection();

        // Crear usuario de prueba
        $passwordHash = password_hash('password123', PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            INSERT INTO usuarios_sistema (nombre_completo, correo, password_hash, rol, activo)
            VALUES (:nombre, :correo, :hash, :rol, 1)
        ");
        $stmt->execute([
            ':nombre' => 'Usuario Test',
            ':correo' => 'test.login@municipalidad.com',
            ':hash' => $passwordHash,
            ':rol' => 'admin'
        ]);

        // Buscar usuario
        $userModel = new User();
        $user = $userModel->findByEmail('test.login@municipalidad.com');

        $this->assertNotNull($user);
        $this->assertEquals('Usuario Test', $user['nombre_completo']);
        $this->assertEquals('admin', $user['rol']);

        // Verificar contraseña
        $this->assertTrue(password_verify('password123', $user['password_hash']));

        // Generar token
        $jwtService = new JWTService();
        $token = $jwtService->generateToken($user);

        $this->assertNotEmpty($token);

        // Validar token
        $decoded = $jwtService->validateToken($token);
        $this->assertNotNull($decoded);
        $this->assertEquals($user['id_usuario'], $decoded->sub);
    }

    /**
     * Prueba de Integración 2: Login fallido con credenciales inválidas
     */
    public function test_login_fallido_con_credenciales_invalidas(): void
    {
        $userModel = new User();
        $user = $userModel->findByEmail('usuario.no.existe@test.com');

        $this->assertNull($user, 'Un usuario inexistente debe retornar null');
    }

    /**
     * Prueba de Integración 3: Actualizar último acceso al hacer login
     */
    public function test_actualizar_ultimo_acceso_al_login(): void
    {
        $db = Database::getInstance()->getConnection();

        // Crear usuario de prueba
        $passwordHash = password_hash('password123', PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            INSERT INTO usuarios_sistema (nombre_completo, correo, password_hash, rol, activo)
            VALUES (:nombre, :correo, :hash, :rol, 1)
        ");
        $stmt->execute([
            ':nombre' => 'Usuario Acceso',
            ':correo' => 'test.acceso@municipalidad.com',
            ':hash' => $passwordHash,
            ':rol' => 'cajero'
        ]);

        $idUsuario = $db->lastInsertId();

        // Actualizar último acceso
        $userModel = new User();
        $result = $userModel->updateLastAccess((int)$idUsuario);

        $this->assertTrue($result, 'El último acceso debe actualizarse');

        // Verificar que se actualizó
        $stmt = $db->prepare("SELECT updated_at FROM usuarios_sistema WHERE id_usuario = :id");
        $stmt->execute([':id' => $idUsuario]);
        $usuario = $stmt->fetch();

        $this->assertNotNull($usuario['updated_at']);
    }

    /**
     * Prueba de Integración 4: Flujo completo de creación de usuario y autenticación
     */
    public function test_flujo_completo_creacion_y_autenticacion(): void
    {
        $db = Database::getInstance()->getConnection();
        $userModel = new User();

        // 1. Crear usuario
        $stmt = $db->prepare("
            INSERT INTO usuarios_sistema (nombre_completo, correo, password_hash, rol, activo)
            VALUES (:nombre, :correo, :hash, :rol, 1)
        ");
        $stmt->execute([
            ':nombre' => 'Flujo Completo',
            ':correo' => 'test.flujo@municipalidad.com',
            ':hash' => password_hash('claveSegura123', PASSWORD_BCRYPT),
            ':rol' => 'tesorero'
        ]);

        $idUsuario = (int)$db->lastInsertId();

        // 2. Buscar usuario por email
        $user = $userModel->findByEmail('test.flujo@municipalidad.com');
        $this->assertNotNull($user);
        $this->assertEquals('tesorero', $user['rol']);

        // 3. Verificar contraseña
        $this->assertTrue(password_verify('claveSegura123', $user['password_hash']));

        // 4. Generar token JWT
        $jwtService = new JWTService();
        $token = $jwtService->generateToken($user);
        $this->assertNotEmpty($token);

        // 5. Validar token
        $decoded = $jwtService->validateToken($token);
        $this->assertEquals($idUsuario, $decoded->sub);
        $this->assertEquals('tesorero', $decoded->rol);

        // 6. Buscar por ID
        $userById = $userModel->findById($idUsuario);
        $this->assertEquals('Flujo Completo', $userById['nombre_completo']);
    }
}