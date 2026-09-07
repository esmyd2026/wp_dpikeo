<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessBranch;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\UserActivityService;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserAdminController extends Controller
{
    public function index(Request $request, UserActivityService $activity)
    {
        $context = CompanyContext::current();
        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : now()->startOfDay();

        $users = User::with(['roleModel', 'branches'])
            ->where('is_admin', true)
            ->whereHas('companies', fn ($q) => $q->whereKey($context->companyId()))
            ->orderBy('name')
            ->get();
        $actorBranchIds = $request->user()->accessibleBranchIds($context->businessProfileId());
        if ($actorBranchIds !== null) {
            $users = $users->filter(function (User $user) use ($actorBranchIds, $context) {
                $targetIds = $user->accessibleBranchIds($context->businessProfileId());

                return $targetIds !== null && collect($targetIds)->diff($actorBranchIds)->isEmpty();
            })->values();
        }

        $users->each(function (User $user) use ($context) {
            $user->setAttribute('active_role', $user->roleForCompany($context->company));
        });

        $stats = $activity->dailyStatsForUsers($users, $date, $context->businessProfileId())->all();

        $summary = [
            'total' => $users->count(),
            'active' => $users->filter(fn (User $u) => $u->isActive())->count(),
            'messages_today' => collect($stats)->sum('messages_sent'),
            'clients_today' => collect($stats)->sum('clients_served'),
        ];

        $activeCompany = $context->company;
        $businessProfileId = $context->businessProfileId();

        return view('admin.users.index', compact('users', 'stats', 'date', 'summary', 'activeCompany', 'businessProfileId'));
    }

    public function create()
    {
        $context = CompanyContext::current();
        $roles = $this->rolesForCompany($context->companyId());
        $branches = $this->manageableBranches(auth()->user(), $context->businessProfileId());

        return view('admin.users.create', compact('roles', 'branches'));
    }

    public function store(Request $request, PermissionService $permissionService)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:60', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users,username'],
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'nullable|boolean',
            'all_branches' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer'],
        ]);

        $role = Role::findOrFail($data['role_id']);
        $context = CompanyContext::current();
        $this->authorizeRoleForCompany($role, $context->companyId());
        if ($role->slug !== 'super_admin') {
            $this->validateBranchSelection($request, $context->businessProfileId());
        }

        if (! auth()->user()->isSuperAdmin() && $role->slug === 'super_admin') {
            abort(403);
        }

        $user = User::create([
            'name' => $data['name'],
            'username' => strtolower($data['username']),
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => true,
            'is_active' => $request->boolean('is_active', true),
            'role_id' => $role->id,
            'role' => $role->slug,
        ]);

        // Sin esto, todo usuario nuevo (no super admin) quedaba sin ninguna
        // empresa autorizada y chocaba con CompanyContext::current() al
        // iniciar sesión -- se lo asigna a la empresa que el admin que lo
        // creó tiene activa en este momento. Un super_admin no necesita fila
        // en company_user (isSuperAdmin() ya salta ese chequeo).
        if ($role->slug !== 'super_admin') {
            $activeCompany = $context->company;
            if ($activeCompany) {
                $user->companies()->syncWithoutDetaching([
                    $activeCompany->id => ['role_id' => $role->id],
                ]);
                $this->syncBranchAccess($request, $user, $context->businessProfileId());
            }
        }

        $permissionService->forgetUserCache($user);

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user)
    {
        $this->ensureManageableUser($user);
        $context = CompanyContext::current();
        $roles = $this->rolesForCompany($context->companyId());
        $branches = $this->manageableBranches(auth()->user(), $context->businessProfileId());
        $selectedBranchIds = $user->accessibleBranchIds($context->businessProfileId());
        $activeRoleId = $user->roleForCompany($context->company)?->id;

        return view('admin.users.edit', compact('user', 'roles', 'branches', 'selectedBranchIds', 'activeRoleId'));
    }

    public function update(Request $request, User $user, PermissionService $permissionService)
    {
        $this->ensureManageableUser($user);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:60', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'nullable|boolean',
            'all_branches' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer'],
        ]);

        $role = Role::findOrFail($data['role_id']);
        $context = CompanyContext::current();
        $this->authorizeRoleForCompany($role, $context->companyId());
        $this->validateBranchSelection($request, $context->businessProfileId());

        if (! auth()->user()->isSuperAdmin() && $role->slug === 'super_admin') {
            abort(403);
        }

        if ($user->id === auth()->id() && ! $request->boolean('is_active', true)) {
            return back()->withInput()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $user->name = $data['name'];
        $user->username = strtolower($data['username']);
        $user->email = $data['email'];
        $user->role_id = $role->id;
        $user->role = $role->slug;
        $user->is_active = $request->boolean('is_active', true);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->companies()->updateExistingPivot($context->companyId(), ['role_id' => $role->id]);
        $this->syncBranchAccess($request, $user, $context->businessProfileId());
        $permissionService->forgetUserCache($user);

        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado.');
    }

    public function toggleActive(User $user, PermissionService $permissionService)
    {
        $this->ensureManageableUser($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $user->is_active = ! $user->is_active;
        $user->save();
        $permissionService->forgetUserCache($user);

        $label = $user->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Usuario {$user->name} {$label} correctamente.");
    }

    public function updateRole(Request $request, User $user, PermissionService $permissionService)
    {
        $this->ensureManageableUser($user);

        $data = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($data['role_id']);
        $context = CompanyContext::current();
        $this->authorizeRoleForCompany($role, $context->companyId());

        if (! auth()->user()->isSuperAdmin() && $role->slug === 'super_admin') {
            abort(403);
        }

        $user->update([
            'role_id' => $role->id,
            'role' => $role->slug,
        ]);
        $user->companies()->updateExistingPivot($context->companyId(), ['role_id' => $role->id]);

        $permissionService->forgetUserCache($user);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Rol de {$user->name} actualizado.");
    }

    private function ensureManageableUser(User $user): void
    {
        if (! $user->is_admin) {
            abort(404);
        }

        if (! auth()->user()->isSuperAdmin() && $user->isSuperAdmin()) {
            abort(403);
        }

        if (! $user->isSuperAdmin()) {
            $companyId = CompanyContext::current()->companyId();
            abort_unless($user->companies()->whereKey($companyId)->exists(), 404);

            $profileId = CompanyContext::current()->businessProfileId();
            $actorIds = auth()->user()->accessibleBranchIds($profileId);
            $targetIds = $user->accessibleBranchIds($profileId);
            if ($actorIds !== null) {
                abort_unless($targetIds !== null && collect($targetIds)->diff($actorIds)->isEmpty(), 404);
            }
        }
    }

    private function rolesForCompany(?int $companyId)
    {
        return Role::query()
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->when(! auth()->user()->isSuperAdmin(), fn ($q) => $q->where('slug', '!=', 'super_admin'))
            ->orderBy('name')
            ->get();
    }

    private function authorizeRoleForCompany(Role $role, ?int $companyId): void
    {
        abort_unless($role->company_id === null || (int) $role->company_id === (int) $companyId, 404);
    }

    private function manageableBranches(User $actor, ?int $businessProfileId)
    {
        return BusinessBranch::query()
            ->forUserAccess($actor, $businessProfileId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function syncBranchAccess(Request $request, User $user, ?int $businessProfileId): void
    {
        $manageable = $this->manageableBranches($request->user(), $businessProfileId);
        $manageableIds = $manageable->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = collect($request->input('branch_ids', []))->map(fn ($id) => (int) $id)->unique()->values();

        abort_if($selected->diff($manageableIds)->isNotEmpty(), 403, 'Hay sucursales que no puedes asignar.');

        $currentProfileBranchIds = BusinessBranch::where('business_profile_id', $businessProfileId)->pluck('id');
        $user->branches()->detach($currentProfileBranchIds);

        if (! $request->boolean('all_branches')) {
            $user->branches()->attach($selected->all());
        }
    }

    private function validateBranchSelection(Request $request, ?int $businessProfileId): void
    {
        $manageable = $this->manageableBranches($request->user(), $businessProfileId);
        $manageableIds = $manageable->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = collect($request->input('branch_ids', []))->filter()->map(fn ($id) => (int) $id)->unique();

        abort_if($selected->diff($manageableIds)->isNotEmpty(), 403, 'Hay sucursales que no puedes asignar.');
        abort_if($request->boolean('all_branches') && ! $request->user()->hasAllBranchAccess($businessProfileId), 403, 'No puedes conceder acceso a sucursales que no administras.');

        if (! $request->boolean('all_branches') && $manageable->isNotEmpty() && $selected->isEmpty()) {
            throw ValidationException::withMessages([
                'branch_ids' => 'Selecciona al menos una sucursal o marca acceso a todas.',
            ]);
        }
    }
}
