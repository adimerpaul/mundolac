<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // tipo: VENTA (con productos), INGRESO (entrada de dinero, p. ej. cobro de un
        // pedido a crédito) o EGRESO (salida de dinero). venta_origen_id enlaza el
        // INGRESO de un cobro con la venta a crédito que salda.
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('tipo', 20)->default('VENTA')->after('numero')->index();
            $table->foreignId('venta_origen_id')->nullable()->after('pedido_id')
                ->constrained('ventas')->nullOnDelete();
        });

        // Ventas que quedaron sin cobrar con el esquema anterior pasan a CREDITO
        // (sin efectivo ni QR: el dinero entrará con el INGRESO del cobro).
        DB::table('ventas')->whereNull('fecha_pago')
            ->update(['tipo_pago' => 'CREDITO', 'monto_efectivo' => 0, 'monto_qr' => 0]);

        Permission::firstOrCreate(
            ['name' => 'Registrar Ingresos Egresos', 'guard_name' => 'web'],
            ['modulo' => 'Ventas', 'etiqueta' => 'Registrar ingresos y egresos', 'orden' => 4]
        );
        User::where('username', 'admin')->first()?->givePermissionTo('Registrar Ingresos Egresos');
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venta_origen_id');
            $table->dropColumn('tipo');
        });
        Permission::where('name', 'Registrar Ingresos Egresos')->delete();
    }
};
