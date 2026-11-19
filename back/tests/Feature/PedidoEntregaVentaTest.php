<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PedidoEntregaVentaTest extends TestCase
{
    use RefreshDatabase;

    private Producto $producto;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::where('username', 'admin')->firstOrFail());
        $this->producto = Producto::firstOrFail();
        $this->producto->update(['stock_inicial' => 50]);
        $this->cliente = Cliente::firstOrFail();
    }

    private function crearPedido(float $cantidad = 2, float $precio = 10): Pedido
    {
        $id = $this->postJson('/api/pedidos', [
            'cliente_id' => $this->cliente->id, 'tipo_pago' => 'EFECTIVO',
            'detalles' => [['producto_id' => $this->producto->id, 'cantidad' => $cantidad, 'precio_unitario' => $precio]],
        ])->assertCreated()->json('id');

        return Pedido::findOrFail($id);
    }

    public function test_se_puede_editar_un_pedido_pendiente_y_no_uno_entregado(): void
    {
        $pedido = $this->crearPedido();
        $payload = [
            'cliente_id' => $this->cliente->id, 'tipo_pago' => 'QR',
            'detalles' => [['producto_id' => $this->producto->id, 'cantidad' => 5, 'precio_unitario' => 3]],
        ];

        $this->putJson("/api/pedidos/{$pedido->id}", $payload)->assertOk();
        $pedido->refresh();
        $this->assertEquals(15, $pedido->total);
        $this->assertSame('QR', $pedido->tipo_pago);
        $this->assertCount(1, $pedido->detalles);

        $this->putJson("/api/pedidos/{$pedido->id}/estado", ['estado' => 'ENTREGADO'])->assertOk();
        $this->putJson("/api/pedidos/{$pedido->id}", $payload)->assertStatus(422);
    }

    public function test_entregar_un_pedido_crea_la_venta_y_descuenta_stock(): void
    {
        $pedido = $this->crearPedido(2, 10);

        $this->putJson("/api/pedidos/{$pedido->id}/estado", ['estado' => 'ENTREGADO'])->assertOk();

        $venta = Venta::where('pedido_id', $pedido->id)->firstOrFail();
        $this->assertSame('COMPLETADA', $venta->estado);
        $this->assertEquals(20, $venta->total);
        $this->assertNull($venta->fecha_pago);
        $this->assertEquals(48, $this->producto->fresh()->stock_inicial);
        $this->assertSame('ENTREGADO', $pedido->fresh()->estado);

        // No se puede volver a cambiar el estado mientras la venta esté vigente.
        $this->putJson("/api/pedidos/{$pedido->id}/estado", ['estado' => 'CANCELADO'])->assertStatus(422);

        // Anular la venta repone stock y devuelve el pedido a PENDIENTE.
        $this->putJson("/api/ventas/{$venta->id}/anular")->assertOk();
        $this->assertEquals(50, $this->producto->fresh()->stock_inicial);
        $this->assertSame('PENDIENTE', $pedido->fresh()->estado);
    }

    public function test_entregar_un_pedido_siempre_queda_con_deuda_y_se_cobra_en_abonos(): void
    {
        $pedido = $this->crearPedido(1, 30);

        // Aunque se envíe un tipo de pago, la entrega no cobra: queda con deuda.
        $this->putJson("/api/pedidos/{$pedido->id}/estado", ['estado' => 'ENTREGADO', 'tipo_pago' => 'EFECTIVO'])->assertOk();
        $venta = Venta::where('pedido_id', $pedido->id)->where('tipo', 'VENTA')->firstOrFail();
        $this->assertNull($venta->fecha_pago);
        $this->assertSame('CREDITO', $venta->tipo_pago);
        $this->assertEquals(0, $venta->monto_pagado);
        $this->assertSame(0, Pago::where('venta_id', $venta->id)->count());

        // Aparece en deudores y no suma a la caja.
        $this->getJson('/api/pedidos-deudores')->assertOk()->assertJsonPath('total', 30)->assertJsonPath('deudas.0.id', $venta->id);
        $this->getJson('/api/ventas-resumen')->assertJsonPath('total', 0)->assertJsonPath('credito', 30);

        // Abono parcial: entra a caja y la deuda baja.
        $this->postJson("/api/ventas/{$venta->id}/pagos", ['monto' => 10, 'tipo_pago' => 'EFECTIVO'])
            ->assertCreated()->assertJsonPath('tipo', 'COBRO');
        $this->assertNull($venta->fresh()->fecha_pago);
        $this->getJson('/api/pedidos-deudores')->assertJsonPath('total', 20);
        $this->getJson('/api/ventas-resumen')->assertJsonPath('efectivo', 10)->assertJsonPath('cobros', 10)->assertJsonPath('credito', 20);

        // No se puede cobrar más que el saldo.
        $this->postJson("/api/ventas/{$venta->id}/pagos", ['monto' => 25, 'tipo_pago' => 'EFECTIVO'])->assertStatus(422);

        // Saldo restante: la venta queda pagada.
        $pagoId = $this->postJson("/api/ventas/{$venta->id}/pagos", ['monto' => 20, 'tipo_pago' => 'COMBINADO', 'monto_efectivo' => 5, 'monto_qr' => 15])
            ->assertCreated()->json('id');
        $this->assertNotNull($venta->fresh()->fecha_pago);
        $this->assertEquals(30, $venta->fresh()->monto_pagado);
        $this->getJson('/api/pedidos-deudores')->assertJsonPath('total', 0);
        $this->getJson('/api/ventas-resumen')->assertJsonPath('efectivo', 15)->assertJsonPath('qr', 15)->assertJsonPath('total', 30);
        $this->postJson("/api/ventas/{$venta->id}/pagos", ['monto' => 1, 'tipo_pago' => 'EFECTIVO'])->assertStatus(422);

        // Con cobros no se anula la venta; anular un cobro devuelve el saldo a deudores.
        $this->putJson("/api/ventas/{$venta->id}/anular")->assertStatus(422);
        $this->putJson("/api/pagos/{$pagoId}/anular")->assertOk();
        $this->assertNull($venta->fresh()->fecha_pago);
        $this->assertEquals(10, $venta->fresh()->monto_pagado);
        $this->getJson('/api/pedidos-deudores')->assertJsonPath('total', 20);
        $this->assertSame('ENTREGADO', $pedido->fresh()->estado);
    }

    public function test_ingresos_y_egresos_manuales_afectan_la_caja(): void
    {
        $this->postJson('/api/ventas-movimientos', ['tipo' => 'INGRESO', 'monto' => 50, 'tipo_pago' => 'EFECTIVO', 'observacion' => 'Aporte'])
            ->assertCreated()->assertJsonPath('tipo', 'INGRESO');
        $egreso = $this->postJson('/api/ventas-movimientos', ['tipo' => 'EGRESO', 'monto' => 20, 'tipo_pago' => 'EFECTIVO', 'observacion' => 'Pago de flete'])
            ->assertCreated()->json();
        $this->assertStringStartsWith('E-', $egreso['numero']);
        $this->postJson('/api/ventas-movimientos', ['tipo' => 'EGRESO', 'monto' => 5, 'tipo_pago' => 'EFECTIVO'])->assertStatus(422);

        $this->getJson('/api/ventas-resumen')->assertJsonPath('ingresos', 50)->assertJsonPath('egresos', 20)
            ->assertJsonPath('efectivo', 30)->assertJsonPath('ventas', 0);
        $this->getJson('/api/ventas?tipo=EGRESO')->assertJsonPath('total', 1);
    }

    public function test_sin_stock_el_pedido_no_se_entrega(): void
    {
        $pedido = $this->crearPedido(80, 1);

        $this->putJson("/api/pedidos/{$pedido->id}/estado", ['estado' => 'ENTREGADO', 'tipo_pago' => 'EFECTIVO'])->assertStatus(422);
        $this->assertSame('PENDIENTE', $pedido->fresh()->estado);
        $this->assertSame(0, Venta::where('pedido_id', $pedido->id)->count());
    }

    public function test_venta_directa_no_tiene_pedido_y_registra_su_pago_al_contado(): void
    {
        $id = $this->postJson('/api/ventas', [
            'tipo_pago' => 'EFECTIVO',
            'detalles' => [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_venta' => 5]],
        ])->assertCreated()->json('id');

        $venta = Venta::findOrFail($id);
        $this->assertNull($venta->pedido_id);
        $this->assertNotNull($venta->fecha_pago);
        $this->assertEquals(5, $venta->monto_pagado);
        $pago = Pago::where('venta_id', $venta->id)->sole();
        $this->assertSame('CONTADO', $pago->tipo);
        $this->getJson('/api/ventas-resumen')->assertJsonPath('efectivo', 5)->assertJsonPath('cobros', 0);

        // Anular la venta anula también su pago: sale de la caja.
        $this->putJson("/api/ventas/{$venta->id}/anular")->assertOk();
        $this->assertSame('ANULADO', $pago->fresh()->estado);
        $this->getJson('/api/ventas-resumen')->assertJsonPath('efectivo', 0);
    }
}
