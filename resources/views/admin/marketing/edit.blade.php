@extends('admin.layouts.app')

@section('header', 'Editar Campaña de Marketing')

@section('content')
<div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="p-3 p-md-4 p-lg-6">
        <form action="{{ route('admin.marketing.update', $campaign) }}" method="POST" id="campaignForm" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <!-- Información Básica -->
            <div class="mb-4">
                <h4 class="mb-3">Información Básica</h4>

                <div class="mb-3">
                    <label for="name" class="form-label">Nombre de la Campaña <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $campaign->name) }}" required>
                    @error('name')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Descripción</label>
                    <textarea class="form-control" id="description" name="description" rows="2">{{ old('description', $campaign->description) }}</textarea>
                    @error('description')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Tipo de Mensaje -->
            <div class="mb-4">
                <h4 class="mb-3">Tipo de Mensaje</h4>

                <div class="mb-3">
                    <label for="message_type" class="form-label">Tipo <span class="text-danger">*</span></label>
                    <select class="form-control" id="message_type" name="message_type" required onchange="toggleMessageFields()">
                        <option value="text" {{ old('message_type', $campaign->message_type) === 'text' ? 'selected' : '' }}>Mensaje de Texto</option>
                        <option value="image" {{ old('message_type', $campaign->message_type) === 'image' ? 'selected' : '' }}>Imagen con Texto</option>
                        <option value="template" {{ old('message_type', $campaign->message_type) === 'template' ? 'selected' : '' }}>Plantilla Aprobada</option>
                    </select>
                    @error('message_type')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Campo para la imagen (solo tipo "Imagen con Texto") -->
                <div class="mb-3" id="image_upload_field" style="display: none;">
                    <label for="image" class="form-label">Imagen @if(!$campaign->image_path)<span class="text-danger">*</span>@endif</label>
                    @if($campaign->image_path)
                        <div class="mb-2">
                            <img src="{{ $campaign->image_url }}" alt="Imagen actual" class="rounded border" style="max-height: 140px;">
                            <div class="form-text">Imagen actual. Sube otra solo si quieres reemplazarla.</div>
                        </div>
                    @endif
                    <input type="file" class="form-control" id="image" name="image" accept="image/png,image/jpeg,image/webp">
                    <small class="form-text text-muted">JPG, PNG o WEBP. Máximo 5 MB.</small>
                    @error('image')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Campo para mensaje de texto (también usado como pie de foto en tipo "Imagen") -->
                <div class="mb-3" id="text_message_field">
                    <label for="message_content" class="form-label" id="message_content_label">Contenido del Mensaje <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="message_content" name="message_content" rows="5"
                              placeholder="Escribe el mensaje que se enviará a los destinatarios...">{{ old('message_content', $campaign->message_content) }}</textarea>
                    <small class="form-text text-muted">Puedes usar @{{name}} para personalizar con el nombre del contacto</small>
                    @error('message_content')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Vincular a un producto: agrega un botón que lleva a la ficha real del producto -->
                <div class="mb-3" id="image_product_field" style="display: none;">
                    <div class="card border-primary">
                        <div class="card-header bg-primary-subtle">
                            <i class="fas fa-tag me-2"></i>Vincular a un producto (opcional)
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="product_id" class="form-label">Producto</label>
                                <select class="form-control" id="product_id" name="product_id" onchange="toggleProductButtonField()">
                                    <option value="">Sin producto vinculado (solo imagen)</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" {{ old('product_id', $campaign->product_id) == $product->id ? 'selected' : '' }}>
                                            {{ $product->is_promo ? '🔥 ' : '' }}{{ $product->name }} (${{ number_format((float) $product->price, 2) }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    Si eliges un producto, la imagen se envía con un botón. Al tocarlo, el cliente entra directo a la ficha de ese producto y de ahí a tu flujo normal de compra (agregar al carrito, variaciones, etc.) — no es un paso aparte.
                                </small>
                            </div>
                            <div id="product_button_text_field" style="display: none;">
                                <label for="button_text" class="form-label">Texto del botón <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="button_text" name="button_text"
                                       value="{{ old('button_text', $campaign->button_text) }}" maxlength="20" placeholder="🔥 Aprovechar">
                                <small class="form-text text-muted">
                                    Máximo 20 caracteres (límite de WhatsApp). Ideas: "🔥 Aprovechar", "🛒 Comprar ya", "👀 Ver oferta".
                                    <span id="button_text_count">0</span>/20
                                </small>
                                @error('button_text')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Campo para plantilla -->
                <div class="mb-3" id="template_field" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center">
                        <label for="template_id" class="form-label mb-0">Plantilla <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="sync_templates_btn" onclick="syncTemplatesFromMeta()">
                            <i class="fas fa-sync-alt me-1"></i>Sincronizar desde Meta
                        </button>
                    </div>
                    <select class="form-control mt-2" id="template_id" name="template_id" onchange="renderTemplateVariables()">
                        <option value="">Selecciona una plantilla</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}"
                                    data-slots="{{ json_encode($template->variableSlots()) }}"
                                    data-body="{{ $template->bodyText() }}"
                                    {{ old('template_id', $campaign->template_id) == $template->id ? 'selected' : '' }}>
                                {{ $template->name }} ({{ $template->category }})
                            </option>
                        @endforeach
                    </select>
                    @error('template_id')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror

                    <div id="template_body_preview" class="alert alert-secondary mt-3 small" style="display: none;"></div>

                    <div id="template_variables_field" class="mt-3" style="display: none;">
                        <label class="form-label">Variables de la Plantilla</label>
                        <div id="variables_container"></div>
                    </div>
                </div>
            </div>

            <!-- Destinatarios -->
            <div class="mb-4">
                <h4 class="mb-3">Destinatarios</h4>

                <div class="mb-3">
                    <label for="recipient_type" class="form-label">Tipo de Destinatarios <span class="text-danger">*</span></label>
                    <select class="form-control" id="recipient_type" name="recipient_type" required onchange="toggleRecipientFields()">
                        <option value="all" {{ old('recipient_type', $campaign->recipient_type) === 'all' ? 'selected' : '' }}>Todos los contactos activos</option>
                        <option value="filtered" {{ old('recipient_type', $campaign->recipient_type) === 'filtered' ? 'selected' : '' }}>Filtrados</option>
                        <option value="selected" {{ old('recipient_type', $campaign->recipient_type) === 'selected' ? 'selected' : '' }}>Seleccionar manualmente</option>
                    </select>
                    @error('recipient_type')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Filtros -->
                <div class="mb-3" id="filters_field" style="display: none;">
                    <label class="form-label">Filtros</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="filter_bot_enabled" name="recipient_filters[bot_enabled]" value="1"
                               {{ old('recipient_filters.bot_enabled', $campaign->recipient_filters['bot_enabled'] ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="filter_bot_enabled">
                            Solo contactos con bot habilitado
                        </label>
                    </div>
                </div>

                <!-- Selección manual -->
                <div class="mb-3" id="selected_contacts_field" style="display: none;">
                    <label class="form-label">Seleccionar Contactos</label>
                    <div class="alert alert-warning py-2 small mb-3" id="recent_only_notice" style="display: none;">
                        <i class="fas fa-clock me-1"></i>
                        Mensaje libre: solo se muestran contactos que te escribieron en las últimas 24 h (los demás no pueden recibirlo).
                    </div>
                    <div class="mb-2">
                        <input type="text" class="form-control" id="contact_search" placeholder="Buscar contacto..."
                               onkeyup="searchContacts(this.value)">
                    </div>
                    <div id="contacts_list" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                        @foreach($contacts as $contact)
                            <div class="form-check" data-recent="{{ in_array($contact->id, $recentContactIds ?? []) ? '1' : '0' }}">
                                <input class="form-check-input" type="checkbox" name="selected_contacts[]"
                                       value="{{ $contact->id }}" id="contact_{{ $contact->id }}"
                                       {{ in_array($contact->id, old('selected_contacts', $campaign->selected_contacts ?? [])) ? 'checked' : '' }}>
                                <label class="form-check-label" for="contact_{{ $contact->id }}">
                                    {{ $contact->name }} ({{ $contact->phone_number }})
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <small class="form-text text-muted">Total seleccionados: <span id="selected_count">0</span></small>
                </div>
            </div>

            <!-- Programación -->
            <div class="mb-4">
                <h4 class="mb-3">Programación</h4>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="schedule_campaign"
                               {{ $campaign->scheduled_at ? 'checked' : '' }} onchange="toggleSchedule()">
                        <label class="form-check-label" for="schedule_campaign">
                            Programar envío
                        </label>
                    </div>
                </div>

                <div class="mb-3" id="schedule_field" style="display: {{ $campaign->scheduled_at ? 'block' : 'none' }};">
                    <label for="scheduled_at" class="form-label">Fecha y Hora de Envío</label>
                    <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at"
                           value="{{ old('scheduled_at', $campaign->scheduled_at ? $campaign->scheduled_at->format('Y-m-d\TH:i') : '') }}">
                    @error('scheduled_at')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Botones -->
            <div class="d-flex justify-content-between">
                <a href="{{ route('admin.marketing.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Actualizar Campaña
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const existingTemplateVariables = @json(old('template_variables', $campaign->template_variables ?? []));

function renderTemplateVariables() {
    const select = document.getElementById('template_id');
    const option = select.options[select.selectedIndex];
    const variablesField = document.getElementById('template_variables_field');
    const container = document.getElementById('variables_container');
    const preview = document.getElementById('template_body_preview');
    container.innerHTML = '';

    if (!option || !option.value) {
        variablesField.style.display = 'none';
        preview.style.display = 'none';
        return;
    }

    const body = option.dataset.body || '';
    if (body) {
        preview.textContent = 'Vista previa: ' + body;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }

    let slots = [];
    try {
        slots = JSON.parse(option.dataset.slots || '[]');
    } catch (e) {
        slots = [];
    }

    if (slots.length === 0) {
        variablesField.style.display = 'none';
        return;
    }

    slots.forEach((slot, index) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'mb-2';
        wrapper.innerHTML = `
            <label class="form-label small mb-1">${slot.label}</label>
            <input type="text" class="form-control form-control-sm" name="template_variables[]" required
                   placeholder="Valor para ${slot.label} (puedes usar @{{nombre}})">
        `;
        wrapper.querySelector('input').value = existingTemplateVariables[index] ?? '';
        container.appendChild(wrapper);
    });

    variablesField.style.display = 'block';
}

function syncTemplatesFromMeta() {
    const btn = document.getElementById('sync_templates_btn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sincronizando...';

    fetch('{{ route('admin.marketing.templates.sync') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]').value,
            'Accept': 'application/json',
        },
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Plantillas sincronizadas: ' + (data.templates?.length ?? 0) + ' aprobadas encontradas en Meta.');
                window.location.reload();
            } else {
                alert('No se pudo sincronizar: ' + (data.message || 'Error desconocido'));
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        })
        .catch(() => {
            alert('No se pudo conectar con el servidor para sincronizar las plantillas.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
}

function toggleMessageFields() {
    const messageType = document.getElementById('message_type').value;
    const textField = document.getElementById('text_message_field');
    const templateField = document.getElementById('template_field');
    const imageField = document.getElementById('image_upload_field');
    const productField = document.getElementById('image_product_field');
    const messageContentLabel = document.getElementById('message_content_label');
    const imageInput = document.getElementById('image');
    const hasExistingImage = @json((bool) $campaign->image_path);

    imageField.style.display = 'none';
    imageInput.required = false;
    productField.style.display = 'none';

    if (messageType === 'text' || messageType === 'image') {
        textField.style.display = 'block';
        templateField.style.display = 'none';
        templateField.querySelector('#template_id').required = false;

        if (messageType === 'image') {
            imageField.style.display = 'block';
            imageInput.required = !hasExistingImage;
            productField.style.display = 'block';
            textField.querySelector('#message_content').required = false;
            messageContentLabel.textContent = 'Pie de foto / cuerpo del mensaje (opcional)';
            toggleProductButtonField();
        } else {
            textField.querySelector('#message_content').required = true;
            messageContentLabel.innerHTML = 'Contenido del Mensaje <span class="text-danger">*</span>';
        }
    } else if (messageType === 'template') {
        textField.style.display = 'none';
        textField.querySelector('#message_content').required = false;
        templateField.style.display = 'block';
        templateField.querySelector('#template_id').required = true;
    }

    filterContactsByRecency(messageType === 'text' || messageType === 'image');
}

function toggleProductButtonField() {
    const productId = document.getElementById('product_id').value;
    const buttonField = document.getElementById('product_button_text_field');
    const buttonInput = document.getElementById('button_text');

    const hasProduct = !!productId;
    buttonField.style.display = hasProduct ? 'block' : 'none';
    buttonInput.required = hasProduct;
}

function filterContactsByRecency(isFreeform) {
    const notice = document.getElementById('recent_only_notice');
    if (notice) notice.style.display = isFreeform ? 'block' : 'none';

    document.querySelectorAll('#contacts_list .form-check').forEach(row => {
        const eligible = !isFreeform || row.dataset.recent === '1';
        row.style.display = eligible ? 'block' : 'none';
        if (!eligible) {
            row.querySelector('input[type="checkbox"]').checked = false;
        }
    });

    if (typeof updateSelectedCount === 'function') {
        updateSelectedCount();
    }
}

function toggleRecipientFields() {
    const recipientType = document.getElementById('recipient_type').value;
    const filtersField = document.getElementById('filters_field');
    const selectedField = document.getElementById('selected_contacts_field');

    if (recipientType === 'filtered') {
        filtersField.style.display = 'block';
        selectedField.style.display = 'none';
    } else if (recipientType === 'selected') {
        filtersField.style.display = 'none';
        selectedField.style.display = 'block';
        updateSelectedCount();
    } else {
        filtersField.style.display = 'none';
        selectedField.style.display = 'none';
    }
}

function toggleSchedule() {
    const scheduleCheckbox = document.getElementById('schedule_campaign');
    const scheduleField = document.getElementById('schedule_field');

    if (scheduleCheckbox.checked) {
        scheduleField.style.display = 'block';
        scheduleField.querySelector('#scheduled_at').required = true;
    } else {
        scheduleField.style.display = 'none';
        scheduleField.querySelector('#scheduled_at').required = false;
    }
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('#selected_contacts_field input[type="checkbox"]:checked');
    document.getElementById('selected_count').textContent = checkboxes.length;
}

function searchContacts(query) {
    const messageType = document.getElementById('message_type').value;
    const isFreeform = messageType === 'text' || messageType === 'image';
    const contacts = document.querySelectorAll('#contacts_list .form-check');
    contacts.forEach(contact => {
        const text = contact.textContent.toLowerCase();
        const matchesSearch = text.includes(query.toLowerCase());
        const eligible = !isFreeform || contact.dataset.recent === '1';
        contact.style.display = (matchesSearch && eligible) ? 'block' : 'none';
    });
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    toggleMessageFields();
    toggleRecipientFields();
    renderTemplateVariables();

    // Actualizar contador al cambiar checkboxes
    const checkboxes = document.querySelectorAll('#selected_contacts_field input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    updateSelectedCount();

    // Contador de caracteres del texto del botón del producto
    const buttonTextInput = document.getElementById('button_text');
    const buttonTextCount = document.getElementById('button_text_count');
    if (buttonTextInput && buttonTextCount) {
        const updateButtonTextCount = () => { buttonTextCount.textContent = buttonTextInput.value.length; };
        buttonTextInput.addEventListener('input', updateButtonTextCount);
        updateButtonTextCount();
    }
});
</script>
@endsection

