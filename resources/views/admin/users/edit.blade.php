@extends('admin.layouts.app')

@section('header', 'Editar usuario')

@section('content')
<style>
    .user-form-page { max-width: 520px; margin: 0 auto; }
    .user-form-card {
        background: #fff;
        border: 1px solid #e8ecf1;
        border-radius: 16px;
        padding: 1.5rem 1.75rem;
        box-shadow: 0 4px 20px rgba(15, 23, 42, .06);
    }
    .user-form-card h2 {
        font-size: 1.2rem;
        font-weight: 700;
        margin: 0 0 1.25rem;
        color: #0f172a;
    }
    .uf-field { margin-bottom: 1rem; }
    .uf-field label {
        display: block;
        font-size: .82rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: .35rem;
    }
    .uf-field input, .uf-field select {
        width: 100%;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: .55rem .75rem;
        font-size: .9rem;
    }
    .uf-field input:focus, .uf-field select:focus {
        outline: none;
        border-color: #128c7e;
        box-shadow: 0 0 0 3px rgba(18, 140, 126, .12);
    }
    .uf-check {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .75rem;
        background: #f8fafc;
        border-radius: 10px;
        margin-bottom: 1rem;
    }
    .uf-divider {
        border: none;
        border-top: 1px solid #eef2f7;
        margin: 1.25rem 0;
    }
    .uf-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
    .uf-branches { display:grid;gap:.5rem;margin-top:.55rem; }
    .uf-branch { display:flex;align-items:center;gap:.55rem;padding:.65rem .75rem;border:1px solid #e2e8f0;border-radius:10px;background:#fff; }
    .btn-uf-save {
        background: linear-gradient(135deg, #128c7e, #075e54);
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: .55rem 1.25rem;
        font-weight: 600;
        font-size: .875rem;
    }
    .btn-uf-back {
        border: 1px solid #e2e8f0;
        background: #fff;
        border-radius: 10px;
        padding: .55rem 1rem;
        font-size: .875rem;
        color: #475569;
        text-decoration: none;
    }
</style>

<div class="user-form-page">
    <div class="user-form-card">
        <h2><i class="fas fa-user-edit me-2 text-muted"></i>{{ $user->name }}</h2>
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            <div class="uf-field">
                <label>Nombre completo</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
            </div>
            <div class="uf-field">
                <label>Usuario de acceso</label>
                <input type="text" name="username" value="{{ old('username', $user->username) }}" required autocomplete="username">
            </div>
            <div class="uf-field">
                <label>Correo electrónico</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
            </div>
            <div class="uf-field">
                <label>Rol</label>
                <select name="role_id" required>
                    @foreach($roles as $role)
                        @if($role->slug === 'super_admin' && !auth()->user()->isSuperAdmin())
                            @continue
                        @endif
                        <option value="{{ $role->id }}" @selected(old('role_id', $activeRoleId) == $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            @if($branches->isNotEmpty())
                @php($allBranchAccess = old('all_branches', $selectedBranchIds === null))
                <div class="uf-field">
                    <label>Sucursales autorizadas</label>
                    <label class="uf-check" style="margin-bottom:.55rem">
                        <input type="checkbox" id="allBranches" name="all_branches" value="1" @checked($allBranchAccess)>
                        <span>Acceso a todas las sucursales, incluidas las nuevas</span>
                    </label>
                    <div class="uf-branches" id="branchChoices">
                        @foreach($branches as $branch)
                            <label class="uf-branch"><input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, old('branch_ids', $selectedBranchIds ?? [])))> <span>{{ $branch->name }}</span></label>
                        @endforeach
                    </div>
                    @error('branch_ids')<small class="text-danger">{{ $message }}</small>@enderror
                </div>
            @else
                <input type="hidden" name="all_branches" value="1">
            @endif
            <label class="uf-check">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))>
                <span class="text-sm text-gray-700">Usuario activo — puede iniciar sesión</span>
            </label>
            <hr class="uf-divider">
            <p class="text-sm text-muted mb-3">Deja la contraseña en blanco si no deseas cambiarla.</p>
            <div class="uf-field">
                <label>Nueva contraseña</label>
                <input type="password" name="password" autocomplete="new-password">
            </div>
            <div class="uf-field">
                <label>Confirmar contraseña</label>
                <input type="password" name="password_confirmation" autocomplete="new-password">
            </div>
            <div class="uf-actions">
                <button type="submit" class="btn-uf-save">Guardar cambios</button>
                <a href="{{ route('admin.users.index') }}" class="btn-uf-back">Volver</a>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const all = document.getElementById('allBranches');
    const choices = document.querySelectorAll('#branchChoices input');
    const sync = () => choices.forEach(input => input.disabled = !!all?.checked);
    all?.addEventListener('change', sync); sync();
});
</script>
@endsection
