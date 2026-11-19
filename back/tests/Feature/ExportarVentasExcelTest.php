<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExportarVentasExcelTest extends TestCase
{
    use RefreshDatabase;

    private function venta(User $user, string $numero, string $fecha, array $detalles): Venta
    {
        $venta = Venta::create([
            'numero' => $numero,
            'user_id' => $user->id,
            'usuario_nombre' => $user->name,
            'subtotal' => collect($detalles)->sum('total'),
            'descuento' => 0,
            'total' => collect($detalles)->sum('total'),
            'tipo_pago' => 'EFECTIVO',
            'monto_efectivo' => collect($detalles)->sum('total'),
            'monto_qr' => 0,
            'estado' => 'COMPLETADA',
            'fecha' => $fecha,
        ]);
        foreach ($detalles as $detalle) {
            $venta->detalles()->create($detalle + ['descuento' => 0, 'subtotal' => $detalle['total']]);
        }

        return $venta;
    }

    private function detalle(string $nombre, int $cantidad, float $precio): array
    {
        return ['producto_id' => null, 'codigo' => $nombre, 'nombre' => $nombre, 'unidad' => 'UNIDAD',
            'precio_compra' => $precio / 2, 'precio_venta' => $precio, 'cantidad' => $cantidad,
            'total' => $precio * $cantidad];
    }

    public function test_el_excel_trae_hoja_de_ventas_y_de_productos_con_los_filtros_aplicados(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->venta($admin, 'V-00000001', '2026-08-22 09:30:00', [$this->detalle('LECHE', 2, 10)]);
        $this->venta($admin, 'V-00000002', '2026-08-22 10:00:00', [$this->detalle('LECHE', 3, 10), $this->detalle('YOGURT', 1, 20)]);
        // Fuera de la franja horaria: no debe aparecer en ninguna hoja.
        $this->venta($admin, 'V-00000003', '2026-08-22 22:00:00', [$this->detalle('JUGO', 5, 10)]);

        $response = $this->get('/api/ventas-exportar/excel?desde=2026-08-22&hasta=2026-08-22&hora_desde=08:00&hora_hasta=12:00');
        $response->assertOk();

        $archivo = tempnam(sys_get_temp_dir(), 'ventas').'.xlsx';
        file_put_contents($archivo, $response->streamedContent());
        $libro = IOFactory::load($archivo);

        $this->assertSame(['Ventas', 'Productos vendidos'], $libro->getSheetNames());

        // Hoja de ventas: encabezado, ventas de la franja (más reciente primero) y totales con ganancia.
        $ventas = $libro->getSheetByName('Ventas');
        $this->assertSame('Reporte de ventas', $ventas->getCell('A2')->getValue());
        $this->assertStringContainsString('22/08/2026', (string) $ventas->getCell('A3')->getValue());
        $this->assertSame('Nº venta', $ventas->getCell('A6')->getValue());
        $this->assertSame('V-00000002', $ventas->getCell('A7')->getValue());
        $this->assertSame('10:00', $ventas->getCell('C7')->getValue());
        $this->assertSame($admin->name, $ventas->getCell('D7')->getValue());
        $this->assertSame('V-00000001', $ventas->getCell('A8')->getValue());
        $this->assertStringStartsWith('TOTAL (2 ventas', (string) $ventas->getCell('A9')->getValue());
        $this->assertEquals(70, $ventas->getCell('K9')->getCalculatedValue());
        $this->assertEquals(35, $ventas->getCell('L9')->getCalculatedValue());

        // Hoja de productos: la venta de las 22:00 (JUGO) no entra.
        $productos = $libro->getSheetByName('Productos vendidos');
        $nombres = array_column($productos->toArray(null, true, false, true), 'C');
        $this->assertContains('LECHE', $nombres);
        $this->assertContains('YOGURT', $nombres);
        $this->assertNotContains('JUGO', $nombres);

        unlink($archivo);
    }
}
