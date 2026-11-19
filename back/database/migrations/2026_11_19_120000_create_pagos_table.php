<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada pago recibido por una venta. tipo: CONTADO (cobrado al vender) o
        // COBRO (abono posterior de una venta con deuda, p. ej. pedido entregado).
        // La caja se calcula desde aquí, con la fecha en que realmente entró el dinero.
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas');
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre');
            $table->string('tipo', 20)->default('CONTADO')->index();
            $table->decimal('monto', 14, 2);
            $table->string('tipo_pago', 20);
            $table->decimal('monto_efectivo', 14, 2)->default(0);
            $table->decimal('monto_qr', 14, 2)->default(0);
            $table->string('estado', 20)->default('COMPLETADO')->index();
            $table->text('observacion')->nullable();
            $table->timestamp('fecha')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('monto_pagado', 14, 2)->default(0)->after('total');
        });

        $now = now();

        // Ventas antiguas: se registran como pagadas al contado en su fecha.
        DB::statement("INSERT INTO pagos (venta_id, pedido_id, user_id, usuario_nombre, tipo, monto, tipo_pago, monto_efectivo, monto_qr, estado, observacion, fecha, created_at, updated_at)
            SELECT id, pedido_id, user_id, usuario_nombre, 'CONTADO', total, tipo_pago, monto_efectivo, monto_qr,
                CASE WHEN estado = 'ANULADA' THEN 'ANULADO' ELSE 'COMPLETADO' END, NULL, COALESCE(fecha_pago, fecha), ?, ?
            FROM ventas WHERE tipo = 'VENTA' AND tipo_pago <> 'CREDITO' AND fecha_pago IS NOT NULL AND deleted_at IS NULL", [$now, $now]);

        // Cobros hechos con el esquema anterior (INGRESO enlazado a la venta) pasan a pagos.
        DB::statement("INSERT INTO pagos (venta_id, pedido_id, user_id, usuario_nombre, tipo, monto, tipo_pago, monto_efectivo, monto_qr, estado, observacion, fecha, created_at, updated_at)
            SELECT venta_origen_id, pedido_id, user_id, usuario_nombre, 'COBRO', total, tipo_pago, monto_efectivo, monto_qr,
                CASE WHEN estado = 'ANULADA' THEN 'ANULADO' ELSE 'COMPLETADO' END, observacion, fecha, ?, ?
            FROM ventas WHERE tipo = 'INGRESO' AND venta_origen_id IS NOT NULL AND deleted_at IS NULL", [$now, $now]);
        DB::table('ventas')->where('tipo', 'INGRESO')->whereNotNull('venta_origen_id')->whereNull('deleted_at')->update(['deleted_at' => $now]);

        DB::statement("UPDATE ventas SET monto_pagado = (SELECT COALESCE(SUM(p.monto), 0) FROM pagos p WHERE p.venta_id = ventas.id AND p.estado = 'COMPLETADO' AND p.deleted_at IS NULL) WHERE tipo = 'VENTA'");
        // Ventas anuladas que estaban pagadas conservan su monto como referencia.
        DB::table('ventas')->where('tipo', 'VENTA')->where('estado', 'ANULADA')->whereNotNull('fecha_pago')->update(['monto_pagado' => DB::raw('total')]);
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('monto_pagado');
        });
        Schema::dropIfExists('pagos');
    }
};
