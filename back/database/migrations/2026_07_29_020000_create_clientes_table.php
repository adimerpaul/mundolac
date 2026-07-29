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
        if (! Schema::hasTable('clientes')) Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 180);
            $table->string('nit', 40)->nullable()->index();
            $table->string('telefono', 50)->nullable();
            $table->string('celular', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->string('zona', 100)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('foto')->nullable();
            $table->text('referencia')->nullable();
            $table->text('observacion')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();
        $clients = [
            ['nombre' => 'DISTRIBUIDORA SAN MIGUEL', 'nit' => '4587213018', 'celular' => '72010001', 'direccion' => 'Av. Arce 2140', 'zona' => 'Sopocachi', 'latitud' => -16.5101180, 'longitud' => -68.1266200, 'referencia' => 'Frente a la plaza', 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'TIENDA LACTEOS DEL SUR', 'nit' => '4587213026', 'celular' => '72010002', 'direccion' => 'Calle 17 N.º 420', 'zona' => 'Obrajes', 'latitud' => -16.5309300, 'longitud' => -68.1069800, 'referencia' => 'A media cuadra de la avenida', 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'MINIMARKET EL PRADO', 'nit' => '4587213034', 'telefono' => '2441003', 'celular' => '72010003', 'direccion' => 'Av. 16 de Julio 1550', 'zona' => 'Centro', 'latitud' => -16.5000600, 'longitud' => -68.1342600, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'ABARROTES DOÑA ROSA', 'nit' => '4587213042', 'celular' => '72010004', 'direccion' => 'Av. Buenos Aires 850', 'zona' => 'Cotahuma', 'latitud' => -16.5025900, 'longitud' => -68.1519000, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'SUPERMERCADO FAMILIAR', 'nit' => '4587213050', 'telefono' => '2782205', 'celular' => '72010005', 'direccion' => 'Av. Ballivián 1020', 'zona' => 'Calacoto', 'latitud' => -16.5415700, 'longitud' => -68.0869600, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'COMERCIAL VILLA FÁTIMA', 'nit' => '4587213068', 'celular' => '72010006', 'direccion' => 'Av. Las Américas 310', 'zona' => 'Villa Fátima', 'latitud' => -16.4878100, 'longitud' => -68.1169700, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'ALMACÉN LOS ANDES', 'nit' => '4587213076', 'celular' => '72010007', 'direccion' => 'Av. Perú 720', 'zona' => 'Pura Pura', 'latitud' => -16.4824100, 'longitud' => -68.1464200, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'MERCADO CENTRAL PUESTO 18', 'nit' => '4587213084', 'celular' => '72010008', 'direccion' => 'Calle Figueroa 630', 'zona' => 'Centro', 'latitud' => -16.4957000, 'longitud' => -68.1395100, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'DISTRIBUCIONES IRPAVI', 'nit' => '4587213092', 'telefono' => '2724009', 'celular' => '72010009', 'direccion' => 'Av. Rafael Pabón 400', 'zona' => 'Irpavi', 'latitud' => -16.5367400, 'longitud' => -68.0718700, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'TIENDA NUEVO AMANECER', 'nit' => '4587213105', 'celular' => '72010010', 'direccion' => 'Av. Entre Ríos 1250', 'zona' => 'El Tejar', 'latitud' => -16.4942100, 'longitud' => -68.1560900, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
        ];
        if (DB::table('clientes')->count() === 0) {
            $defaults = [
                'nit' => null, 'telefono' => null, 'celular' => null, 'email' => null,
                'direccion' => null, 'zona' => null, 'latitud' => null, 'longitud' => null,
                'foto' => null, 'referencia' => null, 'observacion' => null, 'activo' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ];
            DB::table('clientes')->insert(array_map(fn ($client) => array_merge($defaults, $client), $clients));
        }

        foreach (['Ver Clientes', 'Gestionar Clientes'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        User::where('username', 'admin')->first()?->givePermissionTo(['Ver Clientes', 'Gestionar Clientes']);
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
