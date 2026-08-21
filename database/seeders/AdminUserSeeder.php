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
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $adminRole = Role::where('slug', 'admin')->first();

        User::updateOrCreate(
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

        User::updateOrCreate(
            ['username' => 'gosorio'],
            [
                'name' => 'Demo Panel',
                'email' => 'gosorio@siglotecnologico.com',
                'password' => Hash::make('go123'),
                'is_admin' => true,
                'role' => 'admin',
                'role_id' => $adminRole?->id,
            ]
        );
    }
}
