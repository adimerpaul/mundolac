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

        $this->assertSame(['Ventas', 'Productos'], $libro->getSheetNames());

        $ventas = $libro->getSheetByName('Ventas');
        $this->assertSame('REPORTE DE VENTAS', $ventas->getCell('A1')->getValue());
        $this->assertSame($admin->name, $ventas->getCell('B3')->getValue());
        $this->assertStringContainsString('Hora desde 08:00', (string) $ventas->getCell('B5')->getValue());
        $this->assertSame('Nº Venta', $ventas->getCell('A7')->getValue());
        $this->assertSame('V-00000002', $ventas->getCell('A8')->getValue());
        $this->assertSame('10:00:00', $ventas->getCell('C8')->getValue());
        $this->assertSame($admin->name, $ventas->getCell('D8')->getValue());
        $this->assertSame('TOTALES', $ventas->getCell('A10')->getValue());
        $this->assertEquals(70, $ventas->getCell('L10')->getValue());

        $productos = $libro->getSheetByName('Productos');
        $this->assertSame('LECHE', $productos->getCell('C8')->getValue());
        $this->assertEquals(5, $productos->getCell('F8')->getValue());
        $this->assertEquals(2, $productos->getCell('G8')->getValue());
        $this->assertSame('YOGURT', $productos->getCell('C9')->getValue());
        $this->assertSame('TOTALES', $productos->getCell('A10')->getValue());
        $this->assertEquals(70, $productos->getCell('K10')->getValue());

        unlink($archivo);
    }
}
