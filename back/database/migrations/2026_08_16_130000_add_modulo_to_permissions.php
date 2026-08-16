<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Orden de aparición de cada módulo en la pantalla de permisos. */
    private array $orden = [
        'Usuarios' => 1, 'Permisos' => 2, 'Productos' => 3, 'Ventas' => 4, 'Compras' => 5,
        'Clientes' => 6, 'Pedidos' => 7, 'Reportes' => 8, 'Configuración' => 9,
    ];

    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'modulo')) {
                $table->string('modulo', 60)->default('Otros')->after('guard_name');
            }
            if (! Schema::hasColumn('permissions', 'etiqueta')) {
                $table->string('etiqueta', 100)->nullable()->after('modulo');
            }
            if (! Schema::hasColumn('permissions', 'orden')) {
                $table->unsignedSmallInteger('orden')->default(99)->after('etiqueta');
            }
        });

        foreach (DB::table('permissions')->get() as $permission) {
            $words = preg_split('/\s+/', trim($permission->name));
            $modulo = count($words) > 1 ? end($words) : 'Otros';

            DB::table('permissions')->where('id', $permission->id)->update([
                'modulo' => $modulo,
                'etiqueta' => count($words) > 1 ? implode(' ', array_slice($words, 0, -1)) : $permission->name,
                'orden' => $this->orden[$modulo] ?? 99,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['modulo', 'etiqueta', 'orden']);
        });
    }
};
