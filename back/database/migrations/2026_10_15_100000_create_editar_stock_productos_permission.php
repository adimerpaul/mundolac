<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => 'Editar Stock Productos', 'guard_name' => 'web'],
            ['modulo' => 'Productos', 'etiqueta' => 'Editar stock', 'orden' => 3]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::where('username', 'admin')->first()?->givePermissionTo('Editar Stock Productos');
    }

    public function down(): void
    {
        Permission::where('name', 'Editar Stock Productos')->delete();
    }
};
