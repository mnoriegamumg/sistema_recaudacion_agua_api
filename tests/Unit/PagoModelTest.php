<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Utils\Helpers;

class PagoModelTest extends TestCase
{
    /**
     * Prueba 1: Validar que un monto sea positivo
     */
    public function test_monto_debe_ser_positivo(): void
    {
        $this->assertTrue(125.00 > 0);
        $this->assertFalse(-50.00 > 0);
        $this->assertFalse(0 > 0);
    }

    /**
     * Prueba 2: Validar mes correcto (1-12)
     */
    public function test_mes_debe_estar_entre_1_y_12(): void
    {
        for ($mes = 1; $mes <= 12; $mes++) {
            $this->assertTrue($mes >= 1 && $mes <= 12);
        }

        $this->assertFalse(0 >= 1);
        $this->assertFalse(13 <= 12);
    }

    /**
     * Prueba 3: Calcular tarifa anual con descuento
     */
    public function test_calcular_tarifa_anual_con_descuento(): void
    {
        $tarifaMensual = 125.00;
        $descuentoPorcentaje = 10.00;

        $tarifaAnual = Helpers::calcularTarifaAnual($tarifaMensual, $descuentoPorcentaje);

        // 125 * 12 = 1500; 1500 - 10% = 1350
        $this->assertEquals(1350.00, $tarifaAnual);
    }

    /**
     * Prueba 4: Generar número de recibo único
     */
    public function test_generar_numero_recibo_unico(): void
    {
        $recibo1 = Helpers::generarNumeroRecibo(1);
        $recibo2 = Helpers::generarNumeroRecibo(2);

        $this->assertNotEquals($recibo1, $recibo2);
        $this->assertStringStartsWith('REC-', $recibo1);
        $this->assertStringStartsWith('REC-', $recibo2);
    }
}