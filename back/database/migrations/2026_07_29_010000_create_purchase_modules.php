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
        Schema::table('productos', fn (Blueprint $table) => $table->decimal('stock_inicial', 12, 3)->default(0)->change());
        Schema::table('venta_detalles', fn (Blueprint $table) => $table->decimal('cantidad', 12, 3)->change());

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('nit', 30)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('direccion')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('proveedor_nombre');
            $table->string('numero_factura')->nullable()->index();
            $table->string('tipo_pago', 30);
            $table->decimal('monto_efectivo', 14, 2)->default(0);
            $table->decimal('monto_qr', 14, 2)->default(0);
            $table->text('comentario')->nullable();
            $table->decimal('total', 14, 2);
            $table->string('estado', 30)->default('COMPLETADA')->index();
            $table->timestamp('fecha');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('codigo', 50);
            $table->string('nombre');
            $table->string('unidad', 20);
            $table->string('lote')->nullable()->index();
            $table->date('fecha_vencimiento')->nullable()->index();
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_unitario', 12, 4);
            $table->decimal('total', 14, 2);
            $table->timestamps();
        });

        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('compra_detalle_id')->constrained('compra_detalles')->cascadeOnDelete();
            $table->string('lote')->nullable()->index();
            $table->date('fecha_vencimiento')->nullable()->index();
            $table->decimal('cantidad_inicial', 12, 3);
            $table->decimal('cantidad_disponible', 12, 3);
            $table->timestamps();
            $table->index(['producto_id', 'fecha_vencimiento']);
        });

        Schema::create('venta_detalle_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_detalle_id')->constrained('venta_detalles')->cascadeOnDelete();
            $table->foreignId('lote_id')->constrained('lotes')->cascadeOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->timestamps();
        });

        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_empresa')->default('Mundolac');
            $table->string('nit', 50)->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono', 80)->nullable();
            $table->string('logo')->nullable();
            $table->timestamps();
        });
        DB::table('configuraciones')->insert(['nombre_empresa' => 'Mundolac', 'created_at' => now(), 'updated_at' => now()]);

        foreach (['Ver Compras', 'Crear Compras', 'Gestionar Configuración'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        User::where('username', 'admin')->first()?->givePermissionTo(['Ver Compras', 'Crear Compras', 'Gestionar Configuración']);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
        Schema::dropIfExists('venta_detalle_lotes');
        Schema::dropIfExists('lotes');
        Schema::dropIfExists('compra_detalles');
        Schema::dropIfExists('compras');
        Schema::dropIfExists('proveedores');
    }
};
