@extends('admin.layouts.app')

@section('header', 'Nuevo usuario')

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
    .uf-actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .5rem; }
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
        <h2><i class="fas fa-user-plus me-2 text-muted"></i>Crear usuario</h2>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="uf-field">
                <label>Nombre completo</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="uf-field">
                <label>Usuario de acceso</label>
                <input type="text" name="username" value="{{ old('username') }}" required autocomplete="username">
            </div>
            <div class="uf-field">
                <label>Correo electrónico</label>
                <input type="email" name="email" value="{{ old('email') }}" required>
            </div>
            <div class="uf-field">
                <label>Rol</label>
                <select name="role_id" required>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <label class="uf-check">
                <input type="checkbox" name="is_active" value="1" checked>
                <span class="text-sm text-gray-700">Usuario activo al crear</span>
            </label>
            <div class="uf-field">
                <label>Contraseña</label>
                <input type="password" name="password" required autocomplete="new-password">
            </div>
            <div class="uf-field">
                <label>Confirmar contraseña</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <div class="uf-actions">
                <button type="submit" class="btn-uf-save">Crear usuario</button>
                <a href="{{ route('admin.users.index') }}" class="btn-uf-back">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
