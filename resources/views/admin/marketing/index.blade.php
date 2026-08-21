@extends('admin.layouts.app')

@section('header', 'Campañas de Marketing')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <h2 class="mb-0">Campañas de Marketing</h2>
    <a href="{{ route('admin.marketing.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> <span class="d-none d-sm-inline">Nueva Campaña</span>
    </a>
</div>

<div class="alert alert-info mb-4">
    <strong>Importante:</strong> Las campañas de <strong>texto libre</strong> e <strong>imagen con texto</strong> solo llegan si el cliente escribió en las últimas 24 h.
    Para envíos masivos o de marketing use <strong>plantillas aprobadas</strong> por Meta.
    Las campañas <strong>programadas</strong> requieren el programador de Laravel activo (<code>php artisan schedule:work</code> o cron).
</div>

<div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="p-3 p-md-4 p-lg-6">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr class="table-light">
                        <th class="fw-semibold">Nombre</th>
                        <th class="d-none d-lg-table-cell fw-semibold">Tipo</th>
                        <th class="d-none d-md-table-cell fw-semibold">Destinatarios</th>
                        <th class="fw-semibold">Estado</th>
                        <th class="d-none d-lg-table-cell fw-semibold">Progreso</th>
                        <th class="d-none d-xl-table-cell fw-semibold">Fecha</th>
                        <th class="fw-semibold text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $campaign)
                        <tr class="{{ $campaign->status === 'completed' && $campaign->failed_count > 0 ? 'table-warning' : '' }}">
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-2">
                                        @if($campaign->message_type === 'text')
                                            <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1">
                                                <i class="fas fa-comment me-1"></i>
                                            </span>
                                        @elseif($campaign->message_type === 'template')
                                            <span class="badge bg-info-subtle text-info rounded-pill px-2 py-1">
                                                <i class="fas fa-file-alt me-1"></i>
                                            </span>
                                        @elseif($campaign->message_type === 'image')
                                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1">
                                                <i class="fas fa-image me-1"></i>
                                            </span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-1">
                                                <i class="fas fa-mouse-pointer me-1"></i>
                                            </span>
                                        @endif
                                    </div>
                                    <div>
                                        <strong class="d-block">{{ $campaign->name }}</strong>
                                        @if($campaign->description)
                                            <small class="text-muted d-none d-md-inline-block">{{ \Illuminate\Support\Str::limit($campaign->description, 50) }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-lg-table-cell">
                                @if($campaign->message_type === 'template')
                                    <button class="btn btn-sm btn-outline-info rounded-pill px-3" title="Ver plantilla">
                                        <i class="fas fa-file-alt me-1"></i> Plantilla
                                    </button>
                                @else
                                    <span class="badge bg-secondary rounded-pill px-3">
                                        @if($campaign->message_type === 'text')
                                            <i class="fas fa-comment me-1"></i> Texto
                                        @elseif($campaign->message_type === 'image')
                                            <i class="fas fa-image me-1"></i> Imagen
                                        @else
                                            <i class="fas fa-mouse-pointer me-1"></i> Interactivo
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td class="d-none d-md-table-cell">
                                <span class="badge bg-secondary rounded-pill px-3">{{ $campaign->total_recipients }}</span>
                            </td>
                            <td>
                                @if($campaign->status === 'draft')
                                    <span class="badge bg-secondary rounded-pill px-3 py-1">Borrador</span>
                                @elseif($campaign->status === 'scheduled')
                                    <span class="badge bg-warning rounded-pill px-3 py-1">Programada</span>
                                @elseif($campaign->status === 'sending')
                                    <span class="badge bg-info rounded-pill px-3 py-1">
                                        <i class="fas fa-spinner fa-spin me-1"></i> Enviando...
                                    </span>
                                @elseif($campaign->status === 'completed')
                                    <span class="badge bg-success rounded-pill px-3 py-1">Completada</span>
                                @elseif($campaign->status === 'paused')
                                    <span class="badge bg-warning rounded-pill px-3 py-1">Pausada</span>
                                @else
                                    <span class="badge bg-danger rounded-pill px-3 py-1">Cancelada</span>
                                @endif
                            </td>
                            <td class="d-none d-lg-table-cell">
                                @if($campaign->status === 'completed' || $campaign->status === 'sending')
                                    <div class="progress mb-2" style="height: 24px; border-radius: 12px;">
                                        <div class="progress-bar {{ $campaign->failed_count > 0 ? 'bg-danger' : 'bg-success' }}" 
                                             role="progressbar"
                                             style="width: {{ $campaign->total_recipients > 0 ? ($campaign->sent_count / $campaign->total_recipients * 100) : 0 }}%; border-radius: 12px; font-size: 0.85rem; font-weight: 500;"
                                             aria-valuenow="{{ $campaign->sent_count }}" 
                                             aria-valuemin="0" 
                                             aria-valuemax="{{ $campaign->total_recipients }}">
                                            {{ $campaign->sent_count }}/{{ $campaign->total_recipients }}
                                        </div>
                                    </div>
                                    <div class="small text-muted">
                                        <span class="me-2"><i class="fas fa-check-circle text-success"></i> {{ $campaign->sent_count }}</span>
                                        <span><i class="fas fa-times-circle text-danger"></i> {{ $campaign->failed_count }}</span>
                                    </div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="d-none d-xl-table-cell">
                                <div class="small">
                                    <div class="text-muted">
                                        <i class="far fa-calendar me-1"></i>{{ $campaign->created_at->format('d/m/Y') }}
                                    </div>
                                    @if($campaign->sent_at)
                                        <div class="text-muted mt-1">
                                            <i class="far fa-clock me-1"></i>{{ $campaign->sent_at->format('d/m/Y') }}
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-end gap-2 flex-wrap">
                                    <a href="{{ route('admin.marketing.show', $campaign) }}"
                                       class="btn btn-sm btn-outline-primary rounded-pill px-3" title="Ver detalles">
                                        <i class="fas fa-eye me-1"></i>
                                        <span class="d-none d-lg-inline">Ver</span>
                                    </a>
                                    @if($campaign->status === 'draft' || $campaign->status === 'scheduled')
                                        <a href="{{ route('admin.marketing.edit', $campaign) }}"
                                           class="btn btn-sm btn-outline-warning rounded-pill px-3" title="Editar">
                                            <i class="fas fa-edit me-1"></i>
                                            <span class="d-none d-lg-inline">Editar</span>
                                        </a>
                                    @endif
                                    @if($campaign->status === 'draft' || $campaign->status === 'scheduled')
                                        <form action="{{ route('admin.marketing.send', $campaign) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('¿Estás seguro de enviar esta campaña?')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3" title="Enviar">
                                                <i class="fas fa-paper-plane me-1"></i>
                                                <span class="d-none d-lg-inline">Enviar</span>
                                            </button>
                                        </form>
                                    @endif
                                    @if($campaign->status !== 'sending')
                                        <form action="{{ route('admin.marketing.destroy', $campaign) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm(@json(
                                                  $campaign->status === 'completed'
                                                      ? '¿Eliminar esta campaña completada? El historial de envío se perderá.'
                                                      : ($campaign->status === 'scheduled'
                                                          ? '¿Cancelar y eliminar esta campaña programada?'
                                                          : '¿Estás seguro de eliminar esta campaña?')
                                              ))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3" title="Eliminar">
                                                <i class="fas fa-trash me-1"></i>
                                                <span class="d-none d-lg-inline">Eliminar</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 d-block opacity-50"></i>
                                <p>No hay campañas creadas.</p>
                                <a href="{{ route('admin.marketing.create') }}" class="btn btn-primary rounded-pill px-4">
                                    <i class="fas fa-plus me-2"></i> Crear primera campaña
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $campaigns->links() }}
        </div>
    </div>
</div>
@endsection

