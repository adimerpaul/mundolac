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
        // UPDATE ... JOIN no es portable (falla en SQLite, usado por los tests),
        // así que el relleno se hace fila por fila.
        DB::table('pedidos')
            ->join('clientes', 'clientes.id', '=', 'pedidos.cliente_id')
            ->orderBy('pedidos.id')
            ->select('pedidos.id', 'clientes.latitud', 'clientes.longitud')
            ->chunk(500, function ($pedidos) {
                foreach ($pedidos as $pedido) {
                    DB::table('pedidos')->where('id', $pedido->id)->update([
                        'latitud_entrega' => $pedido->latitud,
                        'longitud_entrega' => $pedido->longitud,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('pedidos', fn (Blueprint $table) => $table->dropColumn(['latitud_entrega', 'longitud_entrega']));
    }
};
