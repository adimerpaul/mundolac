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
        $locations = [
            'DISTRIBUIDORA SAN MIGUEL' => ['direccion' => 'Calle Bolívar y Pagador', 'zona' => 'Centro', 'latitud' => -17.9690200, 'longitud' => -67.1120600],
            'TIENDA LACTEOS DEL SUR' => ['direccion' => 'Av. España y Circunvalación', 'zona' => 'Zona Sud', 'latitud' => -17.9896700, 'longitud' => -67.1113200],
            'MINIMARKET EL PRADO' => ['direccion' => 'Calle La Plata y Adolfo Mier', 'zona' => 'Centro', 'latitud' => -17.9708800, 'longitud' => -67.1141900],
            'ABARROTES DOÑA ROSA' => ['direccion' => 'Av. Tomás Barrón y Villarroel', 'zona' => 'Zona Norte', 'latitud' => -17.9495800, 'longitud' => -67.1088500],
            'SUPERMERCADO FAMILIAR' => ['direccion' => 'Av. 6 de Agosto y Herrera', 'zona' => 'Central', 'latitud' => -17.9651900, 'longitud' => -67.1042300],
            'COMERCIAL VILLA FÁTIMA' => ['direccion' => 'Av. Circunvalación y América', 'zona' => 'Villa Fátima', 'latitud' => -17.9574300, 'longitud' => -67.0908200],
            'ALMACÉN LOS ANDES' => ['direccion' => 'Av. Tacna y Junín', 'zona' => 'Zona Este', 'latitud' => -17.9748700, 'longitud' => -67.0959600],
            'MERCADO CENTRAL PUESTO 18' => ['direccion' => 'Mercado Fermín López, calle Soria Galvarro', 'zona' => 'Centro', 'latitud' => -17.9717500, 'longitud' => -67.1104800],
            'DISTRIBUCIONES IRPAVI' => ['nombre' => 'DISTRIBUCIONES SOCAVÓN', 'direccion' => 'Av. Cívica y Santa Bárbara', 'zona' => 'Zona Oeste', 'latitud' => -17.9755900, 'longitud' => -67.1235900],
            'TIENDA NUEVO AMANECER' => ['direccion' => 'Av. Dehene y Campo Jordán', 'zona' => 'Zona Norte', 'latitud' => -17.9515400, 'longitud' => -67.1000100],
        ];
        foreach ($locations as $currentName => $data) {
            DB::table('clientes')->where('nombre', $currentName)->update(array_merge($data, ['updated_at' => now()]));
        }

        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable()->unique();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nombre');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre');
            $table->date('fecha_entrega')->nullable()->index();
            $table->string('direccion_entrega')->nullable();
            $table->string('tipo_pago', 30)->default('EFECTIVO');
            $table->text('observacion')->nullable();
            $table->decimal('total', 14, 2);
            $table->string('estado', 30)->default('PENDIENTE')->index();
            $table->timestamp('fecha')->index();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('pedido_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('codigo', 50);
            $table->string('nombre');
            $table->string('unidad', 20);
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_unitario', 12, 4);
            $table->decimal('total', 14, 2);
            $table->timestamps();
        });
        foreach (['Ver Pedidos', 'Crear Pedidos'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        User::where('username', 'admin')->first()?->givePermissionTo(['Ver Pedidos', 'Crear Pedidos']);
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_detalles');
        Schema::dropIfExists('pedidos');
    }
};
