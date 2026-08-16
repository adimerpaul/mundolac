<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CambioTipoPagoVentaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function venta(User $user, string $tipoPago = 'EFECTIVO'): Venta
    {
        return Venta::create([
            'numero' => 'V-00000001',
            'user_id' => $user->id,
            'usuario_nombre' => $user->name,
            'subtotal' => 100,
            'descuento' => 0,
            'total' => 100,
            'tipo_pago' => $tipoPago,
            'monto_efectivo' => $tipoPago === 'EFECTIVO' ? 100 : 0,
            'monto_qr' => $tipoPago === 'QR' ? 100 : 0,
            'estado' => 'COMPLETADA',
            'fecha' => now(),
        ]);
    }

    public function test_el_tipo_de_pago_solo_se_puede_cambiar_una_vez(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $venta = $this->venta($admin);

        $this->putJson("/api/ventas/{$venta->id}/tipo-pago", ['tipo_pago' => 'QR'])->assertOk();

        $venta->refresh();
        $this->assertTrue($venta->pago_cambiado);
        $this->assertSame('QR', $venta->tipo_pago);
        $this->assertSame('EFECTIVO', $venta->tipo_pago_original);
        $this->assertEquals(0, $venta->monto_efectivo);
        $this->assertEquals(100, $venta->monto_qr);
        $this->assertSame($admin->name, $venta->pago_cambiado_por);
        $this->assertNotNull($venta->pago_cambiado_en);

        // Segundo intento: debe quedar bloqueado.
        $this->putJson("/api/ventas/{$venta->id}/tipo-pago", ['tipo_pago' => 'EFECTIVO'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'El método de pago de esta venta ya fue cambiado una vez y no se puede volver a modificar.']);

        $venta->refresh();
        $this->assertSame('QR', $venta->tipo_pago);
        $this->assertEquals(100, $venta->monto_qr);
    }

    public function test_no_se_puede_cambiar_al_mismo_tipo_de_pago(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $venta = $this->venta($admin);

        $this->putJson("/api/ventas/{$venta->id}/tipo-pago", ['tipo_pago' => 'EFECTIVO'])->assertStatus(422);
        $this->assertFalse($venta->refresh()->pago_cambiado);
    }

    public function test_el_pago_combinado_debe_sumar_el_total(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $venta = $this->venta($admin);

        $this->putJson("/api/ventas/{$venta->id}/tipo-pago", [
            'tipo_pago' => 'COMBINADO', 'monto_efectivo' => 40, 'monto_qr' => 30,
        ])->assertStatus(422);
        $this->assertFalse($venta->refresh()->pago_cambiado);

        $this->putJson("/api/ventas/{$venta->id}/tipo-pago", [
            'tipo_pago' => 'COMBINADO', 'monto_efectivo' => 40, 'monto_qr' => 60,
        ])->assertOk();

        $venta->refresh();
        $this->assertTrue($venta->pago_cambiado);
        $this->assertEquals(40, $venta->monto_efectivo);
        $this->assertEquals(60, $venta->monto_qr);
    }

    public function test_una_venta_anulada_no_permite_cambiar_el_pago(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $venta = $this->venta($admin);
        $venta->update(['estado' => 'ANULADA']);

        $this->putJson("/api/ventas/{$venta->id}/tipo-pago", ['tipo_pago' => 'QR'])->assertStatus(422);
        $this->assertFalse($venta->refresh()->pago_cambiado);
    }

    public function test_requiere_el_permiso_cambiar_pago_ventas(): void
    {
        $admin = $this->admin();
        $venta = $this->venta($admin);

        $cajero = User::create([
            'name' => 'CAJERO', 'username' => 'cajero', 'password' => bcrypt('123456'),
        ]);
        $cajero->givePermissionTo('Ver Ventas');
        Sanctum::actingAs($cajero);

        $this->putJson("/api/ventas/{$venta->id}/tipo-pago", ['tipo_pago' => 'QR'])->assertStatus(403);
        $this->assertFalse($venta->refresh()->pago_cambiado);
    }
}
