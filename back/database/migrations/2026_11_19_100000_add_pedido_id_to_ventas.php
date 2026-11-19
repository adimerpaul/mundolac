<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pedido_id: una venta puede ser directa (NULL) o venir de un pedido entregado.
        // fecha_pago: NULL mientras la venta no se cobra (p. ej. pedido entregado a crédito).
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('pedido_id')->nullable()->after('user_id')
                ->constrained('pedidos')->nullOnDelete();
            $table->timestamp('fecha_pago')->nullable()->after('fecha')->index();
        });

        // Las ventas existentes ya se cobraron en el momento de la venta.
        DB::table('ventas')->whereNull('fecha_pago')->update(['fecha_pago' => DB::raw('fecha')]);
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pedido_id');
            $table->dropColumn('fecha_pago');
        });
    }
};
