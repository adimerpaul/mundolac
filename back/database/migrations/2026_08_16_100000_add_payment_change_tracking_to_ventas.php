<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('tipo_pago_original', 20)->nullable()->after('monto_qr');
            $table->boolean('pago_cambiado')->default(false)->after('tipo_pago_original')->index();
            $table->timestamp('pago_cambiado_en')->nullable()->after('pago_cambiado');
            $table->foreignId('pago_cambiado_user_id')->nullable()->after('pago_cambiado_en')
                ->constrained('users')->nullOnDelete();
            $table->string('pago_cambiado_por')->nullable()->after('pago_cambiado_user_id');
            $table->string('pago_cambiado_motivo', 255)->nullable()->after('pago_cambiado_por');
        });

        Permission::firstOrCreate(['name' => 'Cambiar Pago Ventas', 'guard_name' => 'web']);
        User::where('username', 'admin')->first()?->givePermissionTo('Cambiar Pago Ventas');
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pago_cambiado_user_id');
            $table->dropColumn([
                'tipo_pago_original', 'pago_cambiado', 'pago_cambiado_en',
                'pago_cambiado_por', 'pago_cambiado_motivo',
            ]);
        });

        Permission::where('name', 'Cambiar Pago Ventas')->delete();
    }
};
