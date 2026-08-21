<?php

namespace Database\Seeders;

use App\Services\PermissionService;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(PermissionService $permissionService): void
    {
        $permissionService->syncDefinitions();
        $permissionService->syncDefaultRoles();
    }
}
