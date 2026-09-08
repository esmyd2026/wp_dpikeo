<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // updateOrCreate() resetaba la contraseña de estas cuentas a un
        // valor fijo y público (está en este mismo archivo) cada vez que se
        // corría el seeder -- cualquier admin que hubiera cambiado su
        // contraseña en producción la perdía en silencio con un
        // "php artisan db:seed" de rutina. Estas cuentas solo se crean si
        // todavía no existen; si ya existen, no se les toca nada.
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $adminRole = Role::where('slug', 'admin')->first();

        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Super Administrador',
                'email' => 'admin@siglotecnologico.com',
                'password' => Hash::make('password123'),
                'is_admin' => true,
                'role' => 'super_admin',
                'role_id' => $superAdminRole?->id,
            ]
        );

        User::firstOrCreate(
            ['username' => 'gosorio'],
            [
                'name' => 'Administrador',
                'email' => 'gosorio@siglotecnologico.com',
                'password' => Hash::make('go123'),
                'is_admin' => true,
                'role' => 'admin',
                'role_id' => $adminRole?->id,
            ]
        );
    }
}
