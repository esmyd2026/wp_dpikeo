@extends('admin.layouts.app')

@section('header', 'Detalles de Campaña')

@section('content')
<div class="row">
    <div class="col-12 col-md-8">
        <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-4">
            <div class="p-3 p-md-4 p-lg-6">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                    <h3 class="mb-0">{{ $campaign->name }}</h3>
                    <div class="d-flex flex-wrap gap-2">
                        @if($campaign->status === 'draft' || $campaign->status === 'scheduled')
                            <a href="{{ route('admin.marketing.edit', $campaign) }}" class="btn btn-outline-warning btn-sm rounded-pill px-3">
                                <i class="fas fa-edit me-1"></i> Editar
                            </a>
                        @endif
                        @if($campaign->status === 'draft' || $campaign->status === 'scheduled')
                            <form action="{{ route('admin.marketing.send', $campaign) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Estás seguro de enviar esta campaña?')">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm rounded-pill px-3">
                                    <i class="fas fa-paper-plane me-1"></i> Enviar Campaña
                                </button>
                            </form>
                        @endif
                        @if($campaign->status === 'completed')
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#rescheduleModal">
                                <i class="fas fa-calendar-alt me-1"></i> Reprogramar
                            </button>
                        @endif
                        @if($campaign->status !== 'sending')
                            <form action="{{ route('admin.marketing.destroy', $campaign) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm(@json(
                                      $campaign->status === 'completed'
                                          ? '¿Eliminar esta campaña completada? El historial de envío se perderá.'
                                          : ($campaign->status === 'scheduled'
                                              ? '¿Cancelar y eliminar esta campaña programada?'
                                              : '¿Estás seguro de eliminar esta campaña?')
                                  ))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                                    <i class="fas fa-trash me-1"></i> Eliminar
                                </button>
                            </form>
                        @endif
                        <a href="{{ route('admin.marketing.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                            <i class="fas fa-arrow-left me-1"></i> Volver
                        </a>
                    </div>
                </div>

                @if($campaign->description)
                    <p class="text-muted mb-4">{{ $campaign->description }}</p>
                @endif

                <div class="row mb-4">
                    <div class="col-12 col-md-6 mb-3 mb-md-0">
                        <strong>Tipo de Mensaje:</strong>
                        <span class="badge bg-info ms-2">
                            @if($campaign->message_type === 'text')
                                <i class="fas fa-comment"></i> Texto
                            @elseif($campaign->message_type === 'template')
                                <i class="fas fa-file-alt"></i> Plantilla
                            @elseif($campaign->message_type === 'image')
                                <i class="fas fa-image"></i> Imagen
                            @else
                                <i class="fas fa-mouse-pointer"></i> Interactivo
                            @endif
                        </span>
                    </div>
                    <div class="col-12 col-md-6">
                        <strong>Estado:</strong>
                        @if($campaign->status === 'draft')
                            <span class="badge bg-secondary ms-2">Borrador</span>
                        @elseif($campaign->status === 'scheduled')
                            <span class="badge bg-warning ms-2">Programada</span>
                        @elseif($campaign->status === 'sending')
                            <span class="badge bg-info ms-2">Enviando...</span>
                        @elseif($campaign->status === 'completed')
                            <span class="badge bg-success ms-2">Completada</span>
                        @elseif($campaign->status === 'paused')
                            <span class="badge bg-warning ms-2">Pausada</span>
                        @else
                            <span class="badge bg-danger ms-2">Cancelada</span>
                        @endif
                    </div>
                </div>

                @if($campaign->message_type === 'text')
                    <div class="mb-4">
                        <strong>Contenido del Mensaje:</strong>
                        <div class="border rounded p-3 mt-2 bg-light">
                            {{ $campaign->message_content }}
                        </div>
                    </div>
                @elseif($campaign->message_type === 'image')
                    <div class="mb-4">
                        <strong>Imagen:</strong>
                        <div class="border rounded p-3 mt-2 bg-light">
                            @if($campaign->image_url)
                                <img src="{{ $campaign->image_url }}" alt="Imagen de la campaña" class="img-fluid rounded mb-2" style="max-height: 260px;">
                            @endif
                            @if($campaign->message_content)
                                <div>{{ $campaign->message_content }}</div>
                            @else
                                <span class="text-muted small">Sin pie de foto</span>
                            @endif
                        </div>
                        @if($campaign->product)
                            <div class="mt-2">
                                <span class="badge bg-primary-subtle text-primary">
                                    <i class="fas fa-tag me-1"></i>Producto vinculado: {{ $campaign->product->name }}
                                </span>
                                <span class="badge bg-secondary-subtle text-secondary ms-1">
                                    <i class="fas fa-mouse-pointer me-1"></i>Botón: {{ $campaign->button_text }}
                                </span>
                                <div class="form-text mt-1">Al tocar el botón, el cliente entra a la ficha de este producto y de ahí a tu flujo normal de compra.</div>
                            </div>
                        @endif
                    </div>
                @elseif($campaign->message_type === 'template' && $campaign->template)
                    <div class="mb-4">
                        <strong>Plantilla:</strong>
                        <div class="border rounded p-3 mt-2 bg-light">
                            <strong>{{ $campaign->template->name }}</strong> ({{ $campaign->template->category }})
                            @if($campaign->template_variables)
                                <br><small class="text-muted">Variables: {{ json_encode($campaign->template_variables) }}</small>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="mb-4">
                    <strong>Tipo de Destinatarios:</strong>
                    <span class="ms-2">
                        @if($campaign->recipient_type === 'all')
                            Todos los contactos activos
                        @elseif($campaign->recipient_type === 'filtered')
                            Filtrados
                        @else
                            Seleccionados manualmente
                        @endif
                    </span>
                </div>

                <div class="row mb-4">
                    <div class="col-12 col-md-6 mb-3 mb-md-0">
                        <strong>Total de Destinatarios:</strong>
                        <span class="badge bg-secondary ms-2">{{ $campaign->total_recipients }}</span>
                    </div>
                    @if($campaign->scheduled_at)
                        <div class="col-12 col-md-6">
                            <strong>Programada para:</strong>
                            <span class="ms-2">{{ $campaign->scheduled_at->format('d/m/Y H:i') }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-4 mt-4 mt-md-0">
        <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-4">
            <div class="p-3 p-md-4 p-lg-6">
                <h5 class="mb-4">Estadísticas</h5>

                @if($campaign->status === 'completed' || $campaign->status === 'sending')
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Progreso de Envío</span>
                            <span>{{ $campaign->total_recipients > 0 ? round(($campaign->sent_count / $campaign->total_recipients) * 100, 1) : 0 }}%</span>
                        </div>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar" role="progressbar"
                                 style="width: {{ $campaign->total_recipients > 0 ? ($campaign->sent_count / $campaign->total_recipients * 100) : 0 }}%">
                                {{ $campaign->sent_count }}/{{ $campaign->total_recipients }}
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <strong>Enviados:</strong>
                        <span class="badge bg-success ms-2">{{ $campaign->sent_count }}</span>
                    </div>

                    <div class="mb-3">
                        <strong>Fallidos:</strong>
                        <span class="badge bg-danger ms-2">{{ $campaign->failed_count }}</span>
                    </div>

                    <div class="mb-3">
                        <strong>Entregados:</strong>
                        <span class="badge bg-info ms-2">{{ $campaign->delivered_count }}</span>
                    </div>

                    <div class="mb-3">
                        <strong>Leídos:</strong>
                        <span class="badge bg-primary ms-2">{{ $campaign->read_count }}</span>
                    </div>

                    @if($campaign->sent_count > 0)
                        <div class="mt-4 pt-3 border-top">
                            <small class="text-muted">
                                Tasa de entrega: {{ $campaign->delivery_rate }}%<br>
                                Tasa de lectura: {{ $campaign->read_rate }}%
                            </small>
                        </div>
                    @endif
                @else
                    <p class="text-muted">Las estadísticas estarán disponibles después del envío.</p>
                @endif

                @if($campaign->failed_count > 0 && $campaign->error_details)
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="text-danger mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>Errores de Envío
                        </h6>
                        <div class="accordion" id="errorAccordion">
                            @foreach($campaign->error_details as $index => $error)
                                <div class="accordion-item border-danger">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed bg-danger-subtle" type="button" data-bs-toggle="collapse" data-bs-target="#error{{ $index }}">
                                            <i class="fas fa-user-circle me-2"></i>
                                            <strong>{{ $error['contact_name'] ?? 'Contacto desconocido' }}</strong>
                                            <small class="ms-2 text-muted">({{ $error['phone_number'] ?? 'N/A' }})</small>
                                        </button>
                                    </h2>
                                    <div id="error{{ $index }}" class="accordion-collapse collapse" data-bs-parent="#errorAccordion">
                                        <div class="accordion-body bg-white">
                                            <div class="alert alert-danger mb-0">
                                                <strong>Error:</strong><br>
                                                {{ $error['error'] ?? 'Error desconocido' }}
                                                @if(isset($error['timestamp']))
                                                    <br><small class="text-muted">
                                                        <i class="far fa-clock me-1"></i>
                                                        {{ \Carbon\Carbon::parse($error['timestamp'])->format('d/m/Y H:i:s') }}
                                                    </small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="p-3 p-md-4 p-lg-6">
                <h5 class="mb-4">Información</h5>
                <div class="small">
                    @if($campaign->created_at)
                        <div class="mb-2">
                            <strong>Creada:</strong><br>
                            {{ $campaign->created_at->format('d/m/Y H:i') }}
                        </div>
                    @endif
                    @if($campaign->sent_at)
                        <div class="mb-2">
                            <strong>Enviada:</strong><br>
                            {{ $campaign->sent_at->format('d/m/Y H:i') }}
                        </div>
                    @endif
                    @if($campaign->updated_at)
                        <div>
                            <strong>Última actualización:</strong><br>
                            {{ $campaign->updated_at->format('d/m/Y H:i') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Reprogramar -->
@if($campaign->status === 'completed')
<div class="modal fade" id="rescheduleModal" tabindex="-1" aria-labelledby="rescheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="rescheduleModalLabel">
                    <i class="fas fa-calendar-alt me-2"></i>Reprogramar Campaña
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.marketing.reschedule', $campaign) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        La campaña será reprogramada y las estadísticas se resetearán.
                    </div>
                    <div class="mb-3">
                        <label for="scheduled_at" class="form-label">Nueva Fecha y Hora de Envío <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at" required>
                        <small class="form-text text-muted">La fecha debe ser futura</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-calendar-check me-1"></i> Reprogramar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

