<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request, PermissionService $permissionService)
    {
        $permissionService->syncDefinitions();

        $roles = Role::withCount('users')->orderBy('name')->get();
        $modules = $permissionService->modulesForUi();
        $selectedRole = $roles->firstWhere('id', (int) $request->input('role_id'))
            ?? $roles->first();

        if ($selectedRole) {
            $selectedRole->load('permissions');
        }

        $users = User::with('roleModel')
            ->where('is_admin', true)
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', compact('roles', 'modules', 'selectedRole', 'users'));
    }

    public function updatePermissions(Request $request, Role $role, PermissionService $permissionService)
    {
        if ($role->slug === 'super_admin') {
            return back()->with('error', 'El rol Super Administrador tiene acceso total y no se puede modificar.');
        }

        $validKeys = Permission::pluck('key')->all();
        $keys = array_values(array_intersect($request->input('permissions', []), $validKeys));
        $permissionIds = Permission::whereIn('key', $keys)->pluck('id');

        $role->permissions()->sync($permissionIds);

        User::where('role_id', $role->id)->each(fn (User $user) => $permissionService->forgetUserCache($user));

        return redirect()
            ->route('admin.roles.index', ['role_id' => $role->id, 'tab' => 'roles'])
            ->with('success', "Permisos del rol «{$role->name}» actualizados correctamente.");
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        $slug = \Illuminate\Support\Str::slug($data['name']);
        $base = $slug;
        $i = 1;
        while (Role::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $role = Role::create([
            'slug' => $slug,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        return redirect()
            ->route('admin.roles.index', ['role_id' => $role->id])
            ->with('success', 'Rol creado. Configure sus permisos.');
    }

    public function destroy(Role $role, PermissionService $permissionService)
    {
        if ($role->is_system) {
            return back()->with('error', 'No se puede eliminar un rol del sistema.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Asigne otro rol a los usuarios antes de eliminar este rol.');
        }

        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Rol eliminado.');
    }
}
