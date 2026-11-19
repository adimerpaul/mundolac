<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FiltroHorarioVentasTest extends TestCase
{
    use RefreshDatabase;

    private function venta(User $user, string $numero, string $fecha): Venta
    {
        return Venta::create([
            'numero' => $numero,
            'user_id' => $user->id,
            'usuario_nombre' => $user->name,
            'subtotal' => 100,
            'descuento' => 0,
            'total' => 100,
            'tipo_pago' => 'EFECTIVO',
            'monto_efectivo' => 100,
            'monto_qr' => 0,
            'estado' => 'COMPLETADA',
            'fecha' => $fecha,
        ]);
    }

    public function test_filtra_las_ventas_por_franja_horaria(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->venta($admin, 'V-00000001', '2026-08-22 00:00:30');
        $this->venta($admin, 'V-00000002', '2026-08-22 08:15:00');
        $this->venta($admin, 'V-00000003', '2026-08-22 23:59:40');

        $response = $this->getJson('/api/ventas?desde=2026-08-22&hasta=2026-08-22&hora_desde=00:01&hora_hasta=23:59')
            ->assertOk();

        // 00:01 excluye 00:00:30; 23:59 incluye todo el minuto (hasta 23:59:59).
        $numeros = collect($response->json('data'))->pluck('numero')->sort()->values()->all();
        $this->assertSame(['V-00000002', 'V-00000003'], $numeros);
    }

    public function test_la_franja_horaria_se_aplica_a_cada_dia_del_rango(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->venta($admin, 'V-00000001', '2026-08-22 09:00:00');
        $this->venta($admin, 'V-00000002', '2026-08-23 09:00:00');
        $this->venta($admin, 'V-00000003', '2026-08-23 20:00:00');

        $response = $this->getJson('/api/ventas?desde=2026-08-22&hasta=2026-08-23&hora_desde=08:00&hora_hasta=12:00')
            ->assertOk();

        $numeros = collect($response->json('data'))->pluck('numero')->sort()->values()->all();
        $this->assertSame(['V-00000001', 'V-00000002'], $numeros);
    }

    public function test_la_lista_incluye_los_productos_vendidos(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin);

        $venta = $this->venta($admin, 'V-00000001', '2026-08-22 09:00:00');
        $venta->detalles()->create([
            'producto_id' => null, 'codigo' => 'P1', 'nombre' => 'LECHE ENTERA',
            'unidad' => 'UNIDAD', 'precio_compra' => 5, 'precio_venta' => 8,
            'cantidad' => 3, 'subtotal' => 24, 'descuento' => 0, 'total' => 24,
        ]);

        $this->getJson('/api/ventas?desde=2026-08-22&hasta=2026-08-22')
            ->assertOk()
            ->assertJsonPath('data.0.detalles.0.nombre', 'LECHE ENTERA')
            ->assertJsonPath('data.0.detalles.0.cantidad', 3);
    }
}
