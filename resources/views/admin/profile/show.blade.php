@extends('admin.layouts.app')

@section('header', 'Mi Perfil')

@section('content')
<div class="row">
    <div class="col-12 col-md-4 mb-4">
        <!-- Información del Usuario -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body text-center">
                <div class="mb-3">
                    <div class="mx-auto" style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #25d366 0%, #128c7e 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; font-weight: 600; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                </div>
                <h4 class="mb-1">{{ $user->name }}</h4>
                <p class="text-muted mb-2">{{ $user->email }}</p>
                @if($user->is_admin)
                    <span class="badge bg-success rounded-pill px-3 py-1">
                        <i class="fas fa-shield-alt me-1"></i>Administrador
                    </span>
                @endif
                <div class="mt-3 pt-3 border-top">
                    <small class="text-muted">
                        <i class="far fa-calendar me-1"></i>
                        Miembro desde {{ $user->created_at->format('d/m/Y') }}
                    </small>
                </div>
            </div>
        </div>

        <!-- Estadísticas Rápidas -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Resumen</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">Último acceso</span>
                    <strong>{{ $user->updated_at->diffForHumans() }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">Email verificado</span>
                    @if($user->email_verified_at)
                        <span class="badge bg-success">
                            <i class="fas fa-check me-1"></i>Sí
                        </span>
                    @else
                        <span class="badge bg-warning">
                            <i class="fas fa-times me-1"></i>No
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-8">
        <!-- Formulario de Información Personal -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0">
                    <i class="fas fa-user-edit me-2 text-primary"></i>Información Personal
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" 
                               id="email" name="email" value="{{ old('email', $user->email) }}" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-save me-2"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cambiar Contraseña -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0">
                    <i class="fas fa-lock me-2 text-primary"></i>Cambiar Contraseña
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.profile.password.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Contraseña Actual <span class="text-danger">*</span></label>
                        <input type="password" class="form-control @error('current_password') is-invalid @enderror" 
                               id="current_password" name="current_password" required>
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" 
                               id="password" name="password" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted">Mínimo 8 caracteres</small>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirmar Nueva Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" 
                               id="password_confirmation" name="password_confirmation" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-warning rounded-pill px-4">
                            <i class="fas fa-key me-2"></i>Actualizar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Configuración de Seguridad -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0">
                    <i class="fas fa-shield-alt me-2 text-primary"></i>Seguridad
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                    <div>
                        <h6 class="mb-1">Autenticación de dos factores</h6>
                        <small class="text-muted">Agrega una capa adicional de seguridad a tu cuenta</small>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm" disabled>
                        <i class="fas fa-toggle-off me-1"></i>Desactivado
                    </button>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                    <div>
                        <h6 class="mb-1">Sesiones activas</h6>
                        <small class="text-muted">Gestiona tus sesiones de inicio</small>
                    </div>
                    <button class="btn btn-outline-info btn-sm" disabled>
                        <i class="fas fa-info-circle me-1"></i>Ver sesiones
                    </button>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">Historial de actividad</h6>
                        <small class="text-muted">Revisa el historial de tu cuenta</small>
                    </div>
                    <button class="btn btn-outline-info btn-sm" disabled>
                        <i class="fas fa-history me-1"></i>Ver historial
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
