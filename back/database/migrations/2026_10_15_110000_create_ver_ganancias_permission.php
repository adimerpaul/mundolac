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
            ['name' => 'Ver Ganancias', 'guard_name' => 'web'],
            ['modulo' => 'Panel', 'etiqueta' => 'Ver ganancias', 'orden' => 0]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::where('username', 'admin')->first()?->givePermissionTo('Ver Ganancias');
    }

    public function down(): void
    {
        Permission::where('name', 'Ver Ganancias')->delete();
    }
};
