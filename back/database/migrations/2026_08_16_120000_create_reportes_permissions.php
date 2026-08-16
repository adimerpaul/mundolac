<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'Ver Reportes', 'guard_name' => 'web']);
        // Las columnas modulo/etiqueta/orden las agrega la migración siguiente y rellena a todos los permisos.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::where('username', 'admin')->first()?->givePermissionTo('Ver Reportes');
        User::query()->get()->each(function (User $user) {
            if ($user->hasPermissionTo('Ver Ventas')) {
                $user->givePermissionTo('Ver Reportes');
            }
        });
    }

    public function down(): void
    {
        Permission::where('name', 'Ver Reportes')->delete();
    }
};
