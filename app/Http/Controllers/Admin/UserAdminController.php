<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\UserActivityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserAdminController extends Controller
{
    public function index(Request $request, UserActivityService $activity)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : now()->startOfDay();

        $users = User::with('roleModel')
            ->where('is_admin', true)
            ->orderBy('name')
            ->get();

        $stats = $activity->dailyStatsForUsers($users, $date)->all();

        $summary = [
            'total' => $users->count(),
            'active' => $users->filter(fn (User $u) => $u->isActive())->count(),
            'messages_today' => collect($stats)->sum('messages_sent'),
            'clients_today' => collect($stats)->sum('clients_served'),
        ];

        return view('admin.users.index', compact('users', 'stats', 'date', 'summary'));
    }

    public function create()
    {
        $roles = Role::when(!auth()->user()->isSuperAdmin(), fn ($q) => $q->where('slug', '!=', 'super_admin'))
            ->orderBy('name')
            ->get();

        return view('admin.users.create', compact('roles'));
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
        ]);

        $role = Role::findOrFail($data['role_id']);

        if (!auth()->user()->isSuperAdmin() && $role->slug === 'super_admin') {
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

        $permissionService->forgetUserCache($user);

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user)
    {
        $this->ensureManageableUser($user);

        $roles = Role::when(!auth()->user()->isSuperAdmin(), fn ($q) => $q->where('slug', '!=', 'super_admin'))
            ->orderBy('name')
            ->get();

        return view('admin.users.edit', compact('user', 'roles'));
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
        ]);

        $role = Role::findOrFail($data['role_id']);

        if (!auth()->user()->isSuperAdmin() && $role->slug === 'super_admin') {
            abort(403);
        }

        if ($user->id === auth()->id() && !$request->boolean('is_active', true)) {
            return back()->withInput()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $user->name = $data['name'];
        $user->username = strtolower($data['username']);
        $user->email = $data['email'];
        $user->role_id = $role->id;
        $user->role = $role->slug;
        $user->is_active = $request->boolean('is_active', true);

        if (!empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        $permissionService->forgetUserCache($user);

        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado.');
    }

    public function toggleActive(User $user, PermissionService $permissionService)
    {
        $this->ensureManageableUser($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $user->is_active = !$user->is_active;
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

        if (!auth()->user()->isSuperAdmin() && $role->slug === 'super_admin') {
            abort(403);
        }

        $user->update([
            'role_id' => $role->id,
            'role' => $role->slug,
        ]);

        $permissionService->forgetUserCache($user);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Rol de {$user->name} actualizado.");
    }

    private function ensureManageableUser(User $user): void
    {
        if (!$user->is_admin) {
            abort(404);
        }

        if (!auth()->user()->isSuperAdmin() && $user->isSuperAdmin()) {
            abort(403);
        }
    }
}
