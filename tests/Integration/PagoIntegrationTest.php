<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Models\Pago;
use App\Models\Contador;
use App\Config\Database;

class PagoIntegrationTest extends TestCase
{
    private int $idContadorTest = 0;
    private Contador $contadorModel;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_NAME'] = 'municipalidad_santa_barbara_test';
        $_ENV['DB_USER'] = 'root';
        $_ENV['DB_PASSWORD'] = '';

        try {
            $this->contadorModel = new Contador();
            $this->pagoModel = new Pago();
            $db = Database::getInstance()->getConnection();

            // Limpiar pagos y contadores de prueba
            $db->exec("DELETE FROM pagos WHERE numero_recibo LIKE 'TEST-%'");
            $db->exec("DELETE FROM contadores WHERE codigo_contador LIKE 'TESTPAGO%'");

            // Crear contador de prueba
            $contador = $this->contadorModel->create([
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
    public function test_buscar_pago_por_recibo(): void {
    // 1. Primero registrar un pago para tener un recibo válido
    $contador = $this->contadorModel->findByCodigo('M001');
    
    if (!$contador) {
        $this->markTestSkipped('No hay contadores disponibles');
    }

    // Usar un mes diferente para evitar duplicados
    $mes = rand(1, 12);
    $ano = 2025; // Usar un año diferente
    
    // Verificar que no esté pagado
    if ($this->pagoModel->mesYaPagado($contador['id_contador'], $mes, $ano)) {
        $this->markTestSkipped('El mes ya está pagado');
    }

    // Registrar un pago nuevo
    $data = [
        'id_contador' => $contador['id_contador'],
        'id_usuario_sistema' => 1,
        'monto' => 125.00,
        'mes_pagado' => $mes,
        'ano_pagado' => $ano,
        'periodo_inicio' => "{$ano}-{$mes}-01",
        'periodo_fin' => date('Y-m-t', strtotime("{$ano}-{$mes}-01")),
        'pagado_por' => 'Test Usuario',
        'estado_pago' => 'pagado',
        'numero_recibo' => 'TEST-' . time()
    ];

    $resultado = $this->pagoModel->registrar($data);
    $this->assertTrue($resultado['success'], 'El pago debe registrarse');

    // 2. Ahora buscar el pago por el número de recibo generado
    $numeroRecibo = $resultado['numero_recibo'];
    $pago = $this->pagoModel->getByRecibo($numeroRecibo);

    // 3. Verificar que se encontró
    $this->assertNotNull($pago, "El pago con recibo {$numeroRecibo} debe existir");
    $this->assertEquals($numeroRecibo, $pago['numero_recibo']);
    $this->assertEquals(125.00, $pago['monto']);
    $this->assertEquals($mes, $pago['mes_pagado']);
    $this->assertEquals($ano, $pago['ano_pagado']);
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