<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->decimal('latitud_entrega', 10, 7)->nullable()->after('direccion_entrega');
            $table->decimal('longitud_entrega', 10, 7)->nullable()->after('latitud_entrega');
        });
        DB::table('pedidos')->join('clientes', 'clientes.id', '=', 'pedidos.cliente_id')->update([
            'pedidos.latitud_entrega' => DB::raw('clientes.latitud'),
            'pedidos.longitud_entrega' => DB::raw('clientes.longitud'),
        ]);
    }

    public function down(): void
    {
        Schema::table('pedidos', fn (Blueprint $table) => $table->dropColumn(['latitud_entrega', 'longitud_entrega']));
    }
};
