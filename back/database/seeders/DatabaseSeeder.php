<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            // Usuarios
            'Ver Usuarios', 'Crear Usuarios', 'Editar Usuarios', 'Eliminar Usuarios',
            'Gestionar Permisos',

            // Productos
            'Ver Productos', 'Crear Productos', 'Editar Productos', 'Eliminar Productos',
        ];
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // Admin
        $admin = User::firstOrCreate(['username' => 'admin'], [
            'name' => 'ADMINISTRADOR',
            'username' => 'admin',
            'email' => 'admin@mundolac.com',
            'ci' => '00000000',
            'password' => bcrypt('admin'),
        ]);
        $admin->syncPermissions(Permission::all());

        $this->call(ProductoSeeder::class);
    }
}
