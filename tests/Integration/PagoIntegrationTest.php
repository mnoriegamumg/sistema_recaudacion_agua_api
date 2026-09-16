<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Models\Pago;
use App\Models\Contador;
use App\Config\Database;

class PagoIntegrationTest extends TestCase
{
    private int $idContadorTest = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_NAME'] = 'municipalidad_santa_barbara_test';
        $_ENV['DB_USER'] = 'root';
        $_ENV['DB_PASSWORD'] = '';

        try {
            $db = Database::getInstance()->getConnection();

            // Limpiar pagos y contadores de prueba
            $db->exec("DELETE FROM pagos WHERE numero_recibo LIKE 'TEST-%'");
            $db->exec("DELETE FROM contadores WHERE codigo_contador LIKE 'TESTPAGO%'");

            // Crear contador de prueba
            $contadorModel = new Contador();
            $contador = $contadorModel->create([
                'codigo_contador' => 'TESTPAGO01',
                'nombre_propietario' => 'Usuario Pago Test',
                'estado' => 'activo'
            ]);
            $this->idContadorTest = (int)$contador['id_contador'];

        } catch (\Exception $e) {
            $this->markTestSkipped('No se pudo conectar a la BD: ' . $e->getMessage());
        }
    }

    /**
     * Prueba de Integración 1: Registrar un pago correctamente
     */
    public function test_registrar_pago_correctamente(): void
    {
        $pagoModel = new Pago();

        $data = [
            'id_contador' => $this->idContadorTest,
            'id_usuario_sistema' => 1,
            'monto' => 125.00,
            'mes_pagado' => 1,
            'ano_pagado' => 2026,
            'periodo_inicio' => '2026-01-01',
            'periodo_fin' => '2026-01-31',
            'es_pago_anual' => 0,
            'pagado_por' => 'Usuario Pago Test',
            'numero_recibo' => 'TEST-202601-001',
            'created_by' => 1
        ];

        $result = $pagoModel->registrar($data);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(125.00, $result['data']['monto']);
    }

    /**
     * Prueba de Integración 2: Buscar pago por número de recibo
     */
    public function test_buscar_pago_por_recibo(): void
    {
        $pagoModel = new Pago();

        // Registrar pago
        $pagoModel->registrar([
            'id_contador' => $this->idContadorTest,
            'id_usuario_sistema' => 1,
            'monto' => 100.00,
            'mes_pagado' => 2,
            'ano_pagado' => 2026,
            'periodo_inicio' => '2026-02-01',
            'periodo_fin' => '2026-02-28',
            'pagado_por' => 'Test Recibo',
            'numero_recibo' => 'TEST-202602-001',
            'created_by' => 1
        ]);

        // Buscar por recibo
        $pago = $pagoModel->getByRecibo('TEST-202602-001');

        $this->assertNotNull($pago);
        $this->assertEquals(100.00, $pago['monto']);
    }

    /**
     * Prueba de Integración 3: Verificar que un mes ya pagado no se duplique
     */
    public function test_verificar_mes_ya_pagado(): void
    {
        $pagoModel = new Pago();

        // Registrar pago
        $pagoModel->registrar([
            'id_contador' => $this->idContadorTest,
            'id_usuario_sistema' => 1,
            'monto' => 125.00,
            'mes_pagado' => 3,
            'ano_pagado' => 2026,
            'periodo_inicio' => '2026-03-01',
            'periodo_fin' => '2026-03-31',
            'pagado_por' => 'Test Duplicado',
            'numero_recibo' => 'TEST-202603-001',
            'created_by' => 1
        ]);

        // Verificar que el mes ya está pagado
        $yaPagado = $pagoModel->mesYaPagado($this->idContadorTest, 3, 2026);

        $this->assertTrue($yaPagado, 'El mes 3/2026 ya debe estar pagado');

        // Verificar un mes no pagado
        $noPagado = $pagoModel->mesYaPagado($this->idContadorTest, 4, 2026);
        $this->assertFalse($noPagado, 'El mes 4/2026 no debe estar pagado');
    }

    /**
     * Prueba de Integración 4: Obtener historial de pagos por contador
     */
    public function test_obtener_historial_pagos_por_contador(): void
    {
        $pagoModel = new Pago();

        // Registrar varios pagos
        $pagoModel->registrar([
            'id_contador' => $this->idContadorTest,
            'id_usuario_sistema' => 1,
            'monto' => 125.00,
            'mes_pagado' => 5,
            'ano_pagado' => 2026,
            'periodo_inicio' => '2026-05-01',
            'periodo_fin' => '2026-05-31',
            'pagado_por' => 'Test Historial 1',
            'numero_recibo' => 'TEST-202605-001',
            'created_by' => 1
        ]);

        $pagoModel->registrar([
            'id_contador' => $this->idContadorTest,
            'id_usuario_sistema' => 1,
            'monto' => 125.00,
            'mes_pagado' => 6,
            'ano_pagado' => 2026,
            'periodo_inicio' => '2026-06-01',
            'periodo_fin' => '2026-06-30',
            'pagado_por' => 'Test Historial 2',
            'numero_recibo' => 'TEST-202606-001',
            'created_by' => 1
        ]);

        // Obtener historial
        $historial = $pagoModel->getByContador($this->idContadorTest);

        $this->assertIsArray($historial);
        $this->assertGreaterThanOrEqual(2, count($historial));
    }
}