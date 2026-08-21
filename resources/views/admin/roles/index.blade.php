@extends('admin.layouts.app')

@section('header', 'Roles y permisos')

@push('styles')
<style>
    .roles-layout { display: grid; grid-template-columns: 240px 1fr; gap: 1rem; min-height: 520px; }
    @media (max-width: 992px) { .roles-layout { grid-template-columns: 1fr; } }
    .roles-list { background: #fff; border: 1px solid #e8ecf1; border-radius: 12px; overflow: hidden; }
    .roles-list-item {
        display: block; width: 100%; text-align: left; border: none; background: transparent;
        padding: .85rem 1rem; border-bottom: 1px solid #f1f3f5; color: inherit; text-decoration: none;
    }
    .roles-list-item:hover { background: #f8f9fa; color: inherit; }
    .roles-list-item.active { background: #e7f5ef; border-left: 3px solid #128c7e; }
    .roles-list-item .name { font-weight: 600; font-size: .92rem; }
    .roles-list-item .meta { font-size: .75rem; color: #6c757d; }
    .perm-module-card {
        border: 1px solid #e8ecf1; border-radius: 12px; background: #fff;
        margin-bottom: 1rem; overflow: hidden;
    }
    .perm-module-header {
        background: #f8f9fa; padding: .75rem 1rem; border-bottom: 1px solid #eef1f4;
        font-weight: 600; font-size: .92rem;
    }
    .perm-module-body { padding: 1rem; }
    .perm-check {
        display: flex; align-items: flex-start; gap: .5rem; margin-bottom: .55rem;
        font-size: .86rem;
    }
    .perm-check.menu-perm {
        padding-bottom: .65rem; margin-bottom: .75rem;
        border-bottom: 1px dashed #dee2e6; font-weight: 600;
    }
    .perm-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: .35rem .75rem;
    }
    .roles-tabs .nav-link { color: #667781; font-weight: 500; }
    .roles-tabs .nav-link.active { color: #128c7e; font-weight: 600; }
</style>
@endpush

@section('content')
<div class="content-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Roles y permisos</h1>
        <p class="text-muted small mb-0">Configure qué módulos y acciones puede realizar cada rol en la plataforma.</p>
    </div>
    @perm('users.create')
        <a href="{{ route('admin.users.create') }}" class="btn btn-success btn-sm">
            <i class="fas fa-user-plus me-1"></i> Nuevo usuario
        </a>
    @endperm
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-users-cog me-1"></i> Gestionar usuarios
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<ul class="nav nav-tabs roles-tabs mb-3" role="tablist">
    <li class="nav-item">
        <button class="nav-link {{ request('tab', 'roles') === 'roles' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-roles" type="button">
            Permisos por rol
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ request('tab') === 'users' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-users" type="button">
            Permisos por usuario
        </button>
    </li>
</ul>

<div class="tab-content">
    {{-- Tab roles --}}
    <div class="tab-pane fade {{ request('tab', 'roles') === 'roles' ? 'show active' : '' }}" id="tab-roles">
        <div class="roles-layout">
            <div class="roles-list">
                @foreach($roles as $role)
                    <a href="{{ route('admin.roles.index', ['role_id' => $role->id, 'tab' => 'roles']) }}"
                       class="roles-list-item {{ $selectedRole && $selectedRole->id === $role->id ? 'active' : '' }}">
                        <div class="name">{{ $role->name }}</div>
                        <div class="meta">{{ $role->users_count }} usuario(s)</div>
                    </a>
                @endforeach
                @perm('roles.update')
                    <div class="p-2 border-top">
                        <button class="btn btn-sm btn-outline-success w-100" data-bs-toggle="modal" data-bs-target="#newRoleModal">
                            <i class="fas fa-plus me-1"></i> Nuevo rol
                        </button>
                    </div>
                @endperm
            </div>

            <div>
                @if($selectedRole)
                    @if($selectedRole->slug === 'super_admin')
                        <div class="alert alert-info">
                            <i class="fas fa-crown me-1"></i>
                            <strong>{{ $selectedRole->name }}</strong> tiene acceso total a todos los módulos y acciones.
                        </div>
                    @else
                        @perm('roles.update')
                            <form method="POST" action="{{ route('admin.roles.permissions.update', $selectedRole) }}">
                                @csrf
                                @method('PUT')
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                    <div>
                                        <h5 class="mb-0">{{ $selectedRole->name }}</h5>
                                        <div class="text-muted small">{{ $selectedRole->description }}</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Guardar permisos
                                    </button>
                                </div>

                                @php $assigned = $selectedRole->permissions->pluck('key')->all(); @endphp

                                @foreach($modules as $module)
                                    <div class="perm-module-card">
                                        <div class="perm-module-header">
                                            <i class="fas {{ $module['icon'] }} me-2 text-success"></i>{{ $module['label'] }}
                                        </div>
                                        <div class="perm-module-body">
                                            @php
                                                $menuPerm = $module['permissions']->firstWhere('type', 'menu');
                                                $actions = $module['permissions']->where('type', '!=', 'menu');
                                            @endphp

                                            @if($menuPerm)
                                                <label class="perm-check menu-perm">
                                                    <input type="checkbox" name="permissions[]" value="{{ $menuPerm->key }}"
                                                        @checked(in_array($menuPerm->key, $assigned, true))
                                                        class="perm-menu-toggle" data-module="{{ $module['key'] }}">
                                                    <span>{{ $menuPerm->name }}</span>
                                                </label>
                                            @endif

                                            <div class="perm-grid module-actions" data-module="{{ $module['key'] }}">
                                                @foreach($actions as $perm)
                                                    <label class="perm-check">
                                                        <input type="checkbox" name="permissions[]" value="{{ $perm->key }}"
                                                            @checked(in_array($perm->key, $assigned, true))>
                                                        <span>{{ $perm->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </form>
                        @else
                            <div class="alert alert-warning">No tiene permiso para modificar roles.</div>
                        @endperm
                    @endif

                    @if(!$selectedRole->is_system)
                        @perm('roles.update')
                            <form method="POST" action="{{ route('admin.roles.destroy', $selectedRole) }}" class="mt-2"
                                  onsubmit="return confirm('¿Eliminar este rol?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar rol</button>
                            </form>
                        @endperm
                    @endif
                @else
                    <div class="alert alert-secondary">No hay roles configurados.</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Tab usuarios --}}
    <div class="tab-pane fade {{ request('tab') === 'users' ? 'show active' : '' }}" id="tab-users">
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol asignado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td><code>{{ $user->username }}</code></td>
                                <td><strong>{{ $user->name }}</strong></td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @perm('roles.update')
                                        <form method="POST" action="{{ route('admin.users.role.update', $user) }}" class="d-flex gap-2 align-items-center">
                                            @csrf
                                            @method('PUT')
                                            <select name="role_id" class="form-select form-select-sm" style="min-width:180px">
                                                @foreach($roles as $role)
                                                    @if($role->slug === 'super_admin' && !auth()->user()->isSuperAdmin())
                                                        @continue
                                                    @endif
                                                    <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Aplicar</button>
                                        </form>
                                    @else
                                        {{ $user->roleLabel() }}
                                    @endperm
                                </td>
                                <td class="text-end">
                                    @perm('users.update')
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                                    @endperm
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-muted">No hay usuarios administradores.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-muted small mt-2">
            Para gestión completa (contraseñas, activar/desactivar y métricas diarias), usa
            <a href="{{ route('admin.users.index') }}">Usuarios del panel</a>.
        </p>
    </div>
</div>

@perm('roles.update')
<div class="modal fade" id="newRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.roles.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Nuevo rol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nombre del rol</label>
                    <input type="text" name="name" class="form-control" required placeholder="Ej. Coordinador de cobranza">
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Opcional"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Crear rol</button>
            </div>
        </form>
    </div>
</div>
@endperm
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.perm-menu-toggle').forEach(function(toggle) {
        const module = toggle.dataset.module;
        const actions = document.querySelector('.module-actions[data-module="' + module + '"]');

        function syncActions() {
            if (!actions) return;
            actions.querySelectorAll('input[type=checkbox]').forEach(function(cb) {
                cb.disabled = !toggle.checked;
                if (!toggle.checked) cb.checked = false;
            });
        }

        toggle.addEventListener('change', syncActions);
        syncActions();
    });
});
</script>
@endpush
