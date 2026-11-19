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
            ['name' => 'Ver Deudores', 'guard_name' => 'web'],
            ['modulo' => 'Pedidos', 'etiqueta' => 'Ver deudores', 'orden' => 7]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        User::where('username', 'admin')->first()?->givePermissionTo('Ver Deudores');
    }

    public function down(): void
    {
        Permission::where('name', 'Ver Deudores')->delete();
    }
};
