<?php

namespace App\Services;

use App\Models\Lote;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class VentaService
{
    /**
     * Registra una venta: descuenta stock_inicial, consume lotes en FEFO y
     * desnormaliza el producto en cada detalle.
     *
     * $data: descuento?, tipo_pago, monto_efectivo?, monto_qr?, observacion?, pedido_id?,
     *        pagado? (default true; false deja fecha_pago en NULL),
     *        detalles[] => [producto_id, cantidad, precio_venta]
     */
    /**
     * Reparte el total entre efectivo y QR según el tipo de pago y valida que cuadre.
     *
     * @return array{0: float, 1: float}
     */
    public static function splitPayment(string $type, float $total, array $data): array
    {
        $cash = match ($type) {
            'EFECTIVO' => $total, 'QR' => 0.0, default => round((float) ($data['monto_efectivo'] ?? 0), 2)
        };
        $qr = match ($type) {
            'QR' => $total, 'EFECTIVO' => 0.0, default => round((float) ($data['monto_qr'] ?? 0), 2)
        };
        abort_if(abs(($cash + $qr) - $total) > 0.009, 422, 'Los montos de efectivo y QR deben sumar el total');

        return [$cash, $qr];
    }

    /**
     * Registra un movimiento de caja sin productos: INGRESO o EGRESO.
     * $data: tipo, monto, tipo_pago, monto_efectivo?, monto_qr?, observacion, pedido_id?, venta_origen_id?
     */
    public function movimiento(User $user, array $data): Venta
    {
        return DB::transaction(function () use ($user, $data) {
            $total = round((float) $data['monto'], 2);
            abort_if($total <= 0, 422, 'El monto debe ser mayor a cero');
            [$cash, $qr] = self::splitPayment($data['tipo_pago'], $total, $data);
            $movement = Venta::create([
                'tipo' => $data['tipo'],
                'user_id' => $user->id,
                'usuario_nombre' => $user->name,
                'pedido_id' => $data['pedido_id'] ?? null,
                'venta_origen_id' => $data['venta_origen_id'] ?? null,
                'subtotal' => $total,
                'descuento' => 0,
                'total' => $total,
                'tipo_pago' => $data['tipo_pago'],
                'monto_efectivo' => $cash,
                'monto_qr' => $qr,
                'estado' => 'COMPLETADA',
                'observacion' => $data['observacion'] ?? null,
                'fecha' => now(),
                'fecha_pago' => now(),
            ]);
            $prefix = $data['tipo'] === 'EGRESO' ? 'E-' : 'I-';
            $movement->update(['numero' => $prefix.str_pad((string) $movement->id, 8, '0', STR_PAD_LEFT)]);

            return $movement;
        });
    }

    /**
     * Cobra (total o parcialmente) una venta con deuda. El pago entra en la caja
     * del día en que se registra. Cuando el saldo llega a cero se fija fecha_pago.
     * $data: monto, tipo_pago, monto_efectivo?, monto_qr?, observacion?
     */
    public function cobrar(User $user, Venta $venta, array $data): Pago
    {
        return DB::transaction(function () use ($user, $venta, $data) {
            $sale = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            abort_if($sale->tipo !== 'VENTA' || $sale->estado !== 'COMPLETADA', 422, 'Solo se puede cobrar una venta COMPLETADA.');
            $saldo = $sale->saldo;
            abort_if($saldo <= 0.009, 422, 'Esta venta ya está pagada.');
            $monto = round((float) $data['monto'], 2);
            abort_if($monto <= 0, 422, 'El monto debe ser mayor a cero');
            abort_if($monto - $saldo > 0.009, 422, 'El monto supera el saldo pendiente (Bs '.number_format($saldo, 2).')');
            [$cash, $qr] = self::splitPayment($data['tipo_pago'], $monto, $data);

            $pago = Pago::create([
                'venta_id' => $sale->id, 'pedido_id' => $sale->pedido_id, 'user_id' => $user->id,
                'usuario_nombre' => $user->name, 'tipo' => 'COBRO', 'monto' => $monto, 'tipo_pago' => $data['tipo_pago'],
                'monto_efectivo' => $cash, 'monto_qr' => $qr, 'estado' => 'COMPLETADO',
                'observacion' => $data['observacion'] ?? null, 'fecha' => now(),
            ]);
            $pagado = round((float) $sale->monto_pagado + $monto, 2);
            $sale->update(['monto_pagado' => $pagado, 'fecha_pago' => $pagado + 0.009 >= (float) $sale->total ? now() : null]);

            return $pago;
        });
    }

    /** Anula un cobro: la venta vuelve a tener ese saldo pendiente. */
    public function anularCobro(Pago $pago): Pago
    {
        return DB::transaction(function () use ($pago) {
            $pago = Pago::whereKey($pago->id)->lockForUpdate()->firstOrFail();
            abort_if($pago->estado !== 'COMPLETADO', 422, 'El pago ya está anulado');
            abort_if($pago->tipo !== 'COBRO', 422, 'Un pago al contado se anula anulando la venta');
            $sale = Venta::whereKey($pago->venta_id)->lockForUpdate()->firstOrFail();
            $pago->update(['estado' => 'ANULADO']);
            $sale->update(['monto_pagado' => max(0, round((float) $sale->monto_pagado - (float) $pago->monto, 2)), 'fecha_pago' => null]);

            return $pago;
        });
    }

    public function registrar(User $user, array $data): Venta
    {
        return DB::transaction(function () use ($user, $data) {
            $items = [];
            $subtotal = 0;
            foreach ($data['detalles'] as $detail) {
                $product = Producto::lockForUpdate()->find($detail['producto_id']);
                abort_unless($product, 422, 'Uno de los productos ya no existe');
                $quantity = round((float) $detail['cantidad'], 3);
                abort_if((float) $product->stock_inicial + 0.0001 < $quantity, 422, "Stock insuficiente para {$product->nombre}");
                $salePrice = round((float) $detail['precio_venta'], 4);
                $lineSubtotal = round($salePrice * $quantity, 2);
                $subtotal += $lineSubtotal;
                // Quien ve ganancias puede fijar el costo de la línea; si no, se usa el del producto.
                $costPrice = ! empty($data['permite_costo']) && isset($detail['precio_compra']) && $detail['precio_compra'] !== ''
                    ? round((float) $detail['precio_compra'], 2)
                    : (float) $product->precio_compra;
                $items[] = [$product, $quantity, $salePrice, $lineSubtotal, $costPrice];
            }

            $discount = round((float) ($data['descuento'] ?? 0), 2);
            abort_if($discount > $subtotal, 422, 'El descuento no puede superar el subtotal');
            $total = round($subtotal - $discount, 2);
            // A crédito: no entra dinero ahora; entrará con el INGRESO del cobro.
            $paid = (bool) ($data['pagado'] ?? true);
            $paymentType = $paid ? $data['tipo_pago'] : 'CREDITO';
            [$cash, $qr] = $paid ? self::splitPayment($paymentType, $total, $data) : [0.0, 0.0];

            $sale = Venta::create([
                'tipo' => 'VENTA',
                'user_id' => $user->id,
                'pedido_id' => $data['pedido_id'] ?? null,
                'usuario_nombre' => $user->name,
                'subtotal' => $subtotal,
                'descuento' => $discount,
                'total' => $total,
                'monto_pagado' => $paid ? $total : 0,
                'tipo_pago' => $paymentType,
                'monto_efectivo' => $cash,
                'monto_qr' => $qr,
                'estado' => 'COMPLETADA',
                'observacion' => $data['observacion'] ?? null,
                'fecha' => now(),
                'fecha_pago' => $paid ? now() : null,
            ]);
            $sale->update(['numero' => 'V-'.str_pad((string) $sale->id, 8, '0', STR_PAD_LEFT)]);
            if ($paid && $total > 0) {
                Pago::create([
                    'venta_id' => $sale->id, 'pedido_id' => $sale->pedido_id, 'user_id' => $user->id,
                    'usuario_nombre' => $user->name, 'tipo' => 'CONTADO', 'monto' => $total, 'tipo_pago' => $paymentType,
                    'monto_efectivo' => $cash, 'monto_qr' => $qr, 'estado' => 'COMPLETADO', 'fecha' => now(),
                ]);
            }

            $allocated = 0;
            foreach ($items as $index => [$product, $quantity, $salePrice, $lineSubtotal, $costPrice]) {
                $lineDiscount = $index === array_key_last($items)
                    ? $discount - $allocated
                    : round($discount * ($lineSubtotal / $subtotal), 2);
                $allocated += $lineDiscount;
                $saleDetail = $sale->detalles()->create([
                    'producto_id' => $product->id,
                    'codigo' => $product->codigo,
                    'codigo_barras' => $product->codigo_barras,
                    'nombre' => $product->nombre,
                    'categoria' => $product->categoria,
                    'unidad' => $product->unidad,
                    'foto' => $product->foto,
                    'precio_compra' => $costPrice,
                    'precio_venta' => $salePrice,
                    'cantidad' => $quantity,
                    'subtotal' => $lineSubtotal,
                    'descuento' => $lineDiscount,
                    'total' => $lineSubtotal - $lineDiscount,
                ]);
                $product->decrement('stock_inicial', $quantity);
                $remaining = $quantity;
                $lots = Lote::where('producto_id', $product->id)
                    ->where('cantidad_disponible', '>', 0)
                    ->orderByRaw('fecha_vencimiento IS NULL')
                    ->orderBy('fecha_vencimiento')->orderBy('id')->lockForUpdate()->get();
                foreach ($lots as $lot) {
                    if ($remaining <= 0.0001) {
                        break;
                    }
                    $taken = min($remaining, (float) $lot->cantidad_disponible);
                    $lot->decrement('cantidad_disponible', $taken);
                    DB::table('venta_detalle_lotes')->insert([
                        'venta_detalle_id' => $saleDetail->id, 'lote_id' => $lot->id,
                        'cantidad' => $taken, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $remaining = round($remaining - $taken, 3);
                }
            }

            return $sale;
        });
    }
}
