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
            ['name' => 'Ver Panel', 'guard_name' => 'web'],
            ['modulo' => 'Panel', 'etiqueta' => 'Ver', 'orden' => 0]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::where('username', 'admin')->first()?->givePermissionTo('Ver Panel');
        User::query()->get()->each(function (User $user) {
            if ($user->hasPermissionTo('Ver Ventas')) {
                $user->givePermissionTo('Ver Panel');
            }
        });
    }

    public function down(): void
    {
        Permission::where('name', 'Ver Panel')->delete();
    }
};
