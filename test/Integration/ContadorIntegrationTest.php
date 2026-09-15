<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Models\Contador;
use App\Config\Database;

class ContadorIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_NAME'] = 'municipalidad_santa_barbara_test';
        $_ENV['DB_USER'] = 'root';
        $_ENV['DB_PASSWORD'] = '';

        try {
            $db = Database::getInstance()->getConnection();
            $db->exec("DELETE FROM contadores WHERE codigo_contador LIKE 'TEST%'");
        } catch (\Exception $e) {
            $this->markTestSkipped('No se pudo conectar a la BD: ' . $e->getMessage());
        }
    }

    /**
     * Prueba de Integración 1: Crear un contador correctamente
     */
    public function test_crear_contador_correctamente(): void
    {
        $contadorModel = new Contador();

        $data = [
            'codigo_contador' => 'TEST001',
            'dpi' => '1234567890101',
            'nit' => '1234567-8',
            'nombre_propietario' => 'Juan Test Pérez',
            'estado' => 'activo',
            'created_by' => 1
        ];

        $contador = $contadorModel->create($data);

        $this->assertIsArray($contador);
        $this->assertEquals('TEST001', $contador['codigo_contador']);
        $this->assertEquals('Juan Test Pérez', $contador['nombre_propietario']);
    }

    /**
     * Prueba de Integración 2: Buscar contador por código
     */
    public function test_buscar_contador_por_codigo(): void
    {
        $contadorModel = new Contador();

        // Crear contador
        $contadorModel->create([
            'codigo_contador' => 'TEST002',
            'nombre_propietario' => 'María Test López',
            'estado' => 'activo'
        ]);

        // Buscar por código
        $encontrado = $contadorModel->findByCodigo('TEST002');

        $this->assertNotNull($encontrado);
        $this->assertEquals('María Test López', $encontrado['nombre_propietario']);
    }

    /**
     * Prueba de Integración 3: Listar todos los contadores
     */
    public function test_listar_todos_los_contadores(): void
    {
        $contadorModel = new Contador();

        // Crear varios contadores
        $contadorModel->create([
            'codigo_contador' => 'TEST003',
            'nombre_propietario' => 'Test 1',
            'estado' => 'activo'
        ]);
        $contadorModel->create([
            'codigo_contador' => 'TEST004',
            'nombre_propietario' => 'Test 2',
            'estado' => 'activo'
        ]);

        $contadores = $contadorModel->getAll();

        $this->assertIsArray($contadores);
        $this->assertGreaterThanOrEqual(2, count($contadores));
    }

    /**
     * Prueba de Integración 4: Actualizar un contador existente
     */
    public function test_actualizar_contador_existente(): void
    {
        $contadorModel = new Contador();

        // Crear contador
        $contador = $contadorModel->create([
            'codigo_contador' => 'TEST005',
            'nombre_propietario' => 'Original',
            'estado' => 'activo'
        ]);

        $id = $contador['id_contador'];

        // Actualizar
        $result = $contadorModel->update($id, [
            'nombre_propietario' => 'Actualizado',
            'updated_by' => 1
        ]);

        $this->assertTrue($result);

        // Verificar
        $actualizado = $contadorModel->findById($id);
        $this->assertEquals('Actualizado', $actualizado['nombre_propietario']);
    }
}