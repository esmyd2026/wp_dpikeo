<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index(Request $request, PermissionService $permissionService)
    {
        $permissionService->syncDefinitions();
        // currentCompany(), no current(): roles/permisos son un concepto de
        // empresa, no de un número de WhatsApp puntual -- no debe romperse
        // solo porque la empresa tenga 2+ números sin uno marcado principal.
        $company = CompanyContext::currentCompany();

        $roles = Role::query()
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company->id))
            ->when(! $request->user()->isSuperAdmin(), fn ($q) => $q->where('slug', '!=', 'super_admin'))
            ->orderBy('name')
            ->get();
        $roles->each(function (Role $role) use ($company) {
            $role->setAttribute('users_count', \DB::table('company_user')
                ->where('company_id', $company->id)
                ->where('role_id', $role->id)
                ->count());
        });
        $modules = $permissionService->modulesForUi();
        $selectedRole = $roles->firstWhere('id', (int) $request->input('role_id'))
            ?? $roles->first();

        if ($selectedRole) {
            $selectedRole->load('permissions');
        }

        $users = User::with('roleModel')
            ->where('is_admin', true)
            ->whereHas('companies', fn ($q) => $q->whereKey($company->id))
            ->orderBy('name')
            ->get();
        $users->each(fn (User $user) => $user->setAttribute('active_role_id', $user->roleForCompany($company)?->id));

        return view('admin.roles.index', compact('roles', 'modules', 'selectedRole', 'users'));
    }

    public function updatePermissions(Request $request, Role $role, PermissionService $permissionService)
    {
        $this->authorizeRole($role, true);
        if ($role->slug === 'super_admin') {
            return back()->with('error', 'El rol Super Administrador tiene acceso total y no se puede modificar.');
        }

        $validKeys = Permission::pluck('key')->all();
        $keys = array_values(array_intersect($request->input('permissions', []), $validKeys));
        $permissionIds = Permission::whereIn('key', $keys)->pluck('id');

        $role->permissions()->sync($permissionIds);

        // wherePivot() solo existe en la relación BelongsToMany en sí (ej.
        // $user->companies()->wherePivot(...)) -- dentro del closure de
        // whereHas() lo que se recibe es un Builder normal, así que
        // wherePivot() ahí no filtra nada y generaba SQL inválido
        // ("Unknown column 'pivot'"). Se filtra calificando la columna real
        // de la tabla pivote (company_user.role_id).
        User::where('role_id', $role->id)
            ->orWhereHas('companies', fn ($q) => $q->where('company_user.role_id', $role->id))
            ->each(fn (User $user) => $permissionService->forgetUserCache($user));

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

        $slug = Str::slug($data['name']);
        $base = $slug;
        $i = 1;
        while (Role::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        $role = Role::create([
            'company_id' => CompanyContext::currentCompany()->id,
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
        $this->authorizeRole($role, true);
        if ($role->is_system) {
            return back()->with('error', 'No se puede eliminar un rol del sistema.');
        }

        if ($role->users()->exists() || \DB::table('company_user')->where('role_id', $role->id)->exists()) {
            return back()->with('error', 'Asigne otro rol a los usuarios antes de eliminar este rol.');
        }

        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Rol eliminado.');
    }

    private function authorizeRole(Role $role, bool $forMutation = false): void
    {
        $user = auth()->user();
        $companyId = CompanyContext::currentCompany()->id;

        abort_unless($role->company_id === null || (int) $role->company_id === (int) $companyId, 404);

        // Los roles globales son plantillas compartidas y solo la plataforma
        // puede modificarlos. Cada empresa puede crear sus propios roles.
        if ($forMutation && $role->company_id === null && ! $user->isSuperAdmin()) {
            abort(403, 'Crea un rol propio de la empresa para personalizar permisos.');
        }
    }
}
