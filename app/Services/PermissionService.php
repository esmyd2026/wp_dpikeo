<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    public function syncDefinitions(): void
    {
        $sort = 0;

        foreach (config('permissions.modules', []) as $moduleKey => $module) {
            foreach ($module['permissions'] as $key => $meta) {
                Permission::updateOrCreate(
                    ['key' => $key],
                    [
                        'module' => $moduleKey,
                        'name' => $meta['label'],
                        'type' => $meta['type'] ?? 'action',
                        'sort_order' => $sort++,
                    ]
                );
            }
        }
    }

    public function syncDefaultRoles(): void
    {
        $allPermissionIds = Permission::pluck('id', 'key');

        foreach (config('permissions.default_roles', []) as $slug => $definition) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'is_system' => $definition['is_system'] ?? false,
                ]
            );

            $keys = $definition['permissions'] === '*'
                ? $allPermissionIds->keys()->all()
                : ($definition['permissions'] ?? []);

            $ids = collect($keys)
                ->map(fn ($key) => $allPermissionIds[$key] ?? null)
                ->filter()
                ->values()
                ->all();

            $role->permissions()->sync($ids);
        }

        User::whereNull('role_id')->each(function (User $user) {
            $slug = $user->role
                ?? ($user->username === 'admin' ? 'super_admin' : 'admin');

            $role = Role::where('slug', $slug)->first()
                ?? Role::where('slug', 'admin')->first();

            if ($role) {
                $user->update(['role_id' => $role->id, 'role' => $role->slug]);
            }
        });
    }

    public function modulesForUi(): array
    {
        $permissions = Permission::orderBy('sort_order')->get()->groupBy('module');

        $modules = [];
        foreach (config('permissions.modules', []) as $key => $module) {
            $modules[$key] = [
                'key' => $key,
                'label' => $module['label'],
                'icon' => $module['icon'] ?? 'fa-folder',
                'permissions' => ($permissions[$key] ?? collect())->values(),
            ];
        }

        return $modules;
    }

    public function userPermissionKeys(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return Permission::pluck('key')->all();
        }

        return Cache::remember(
            'user_permissions_' . $user->id,
            300,
            fn () => $user->roleModel?->permissionKeys() ?? []
        );
    }

    public function userCan(User $user, string $permission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->userPermissionKeys($user), true);
    }

    public function forgetUserCache(User $user): void
    {
        Cache::forget('user_permissions_' . $user->id);
    }
}
