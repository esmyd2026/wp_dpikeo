@extends('admin.layouts.app')

@section('header', 'Fallos de envío')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
    <div>
        <h2 class="mb-1 fw-bold">Fallos de envío</h2>
        <p class="text-muted mb-0">Mensajes del bot que no pudieron entregarse — a quién, qué tipo y por qué, para que los revises.</p>
    </div>
    <div class="d-flex gap-2">
        <div class="text-center px-3 py-2 rounded bg-danger-subtle">
            <div class="fw-bold text-danger fs-5">{{ $stats['unresolved'] }}</div>
            <div class="small text-muted">Sin resolver</div>
        </div>
        <div class="text-center px-3 py-2 rounded bg-secondary-subtle">
            <div class="fw-bold fs-5">{{ $stats['total_24h'] }}</div>
            <div class="small text-muted">Últimas 24 h</div>
        </div>
    </div>
</div>

<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $status === 'unresolved' ? 'active' : '' }}" href="{{ route('admin.message-failures.index', ['status' => 'unresolved']) }}">Sin resolver</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $status === 'resolved' ? 'active' : '' }}" href="{{ route('admin.message-failures.index', ['status' => 'resolved']) }}">Resueltos</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $status === 'all' ? 'active' : '' }}" href="{{ route('admin.message-failures.index', ['status' => 'all']) }}">Todos</a>
    </li>
</ul>

<div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="p-3 p-md-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr class="table-light">
                        <th>Fecha</th>
                        <th>Contacto</th>
                        <th>Tipo</th>
                        <th>Origen</th>
                        <th>Error</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($failures as $failure)
                        <tr>
                            <td class="text-nowrap small text-muted">{{ $failure->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <strong class="d-block">{{ $failure->contact->name ?? 'Contacto desconocido' }}</strong>
                                <span class="small text-muted">{{ $failure->contact->phone_number ?? $failure->phone_number ?? '—' }}</span>
                            </td>
                            <td><span class="badge bg-secondary-subtle text-secondary">{{ $failure->message_type }}</span></td>
                            <td class="small text-muted">{{ $failure->source ?? '—' }}</td>
                            <td class="small" style="max-width: 380px;">{{ \Illuminate\Support\Str::limit($failure->error_message, 180) }}</td>
                            <td>
                                @if($failure->isResolved())
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="fas fa-check me-1"></i>Resuelto
                                        @if($failure->resolvedByUser)
                                            <span class="text-muted">· {{ $failure->resolvedByUser->name }}</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger"><i class="fas fa-triangle-exclamation me-1"></i>Sin resolver</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    @if($failure->contact)
                                        <a href="{{ route('admin.chat', $failure->contact_id) }}" class="btn btn-sm btn-outline-primary" title="Abrir chat">
                                            <i class="fas fa-comments"></i>
                                        </a>
                                    @endif
                                    @if(!$failure->isResolved())
                                        @perm('message_failures.manage')
                                        <form action="{{ route('admin.message-failures.resolve', $failure) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-check me-1"></i>Marcar resuelto
                                            </button>
                                        </form>
                                        @endperm
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-check-circle fa-3x mb-3 d-block opacity-50"></i>
                                @if($status === 'unresolved')
                                    No hay fallos de envío pendientes. 🎉
                                @else
                                    No hay fallos registrados.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($failures->hasPages())
            <div class="mt-3">{{ $failures->links() }}</div>
        @endif
    </div>
</div>
@endsection
