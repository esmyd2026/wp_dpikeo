@extends('admin.layouts.app')

@section('header', 'Categorías')

@section('content')
<style>
    .categories-page {
        --wa-green: #25d366;
        --wa-dark: #128c7e;
        --wa-teal: #075e54;
    }

    .categories-hero {
        background: linear-gradient(135deg, var(--wa-dark) 0%, var(--wa-teal) 100%);
        color: #fff;
        border-radius: 14px;
        padding: 1.5rem 1.75rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 4px 14px rgba(7, 94, 84, 0.2);
    }

    .categories-hero h2 {
        font-size: 1.35rem;
        font-weight: 600;
        margin: 0 0 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .categories-hero p {
        margin: 0;
        opacity: 0.9;
        font-size: 0.9rem;
    }

    .categories-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }

    .categories-stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 1rem 1.1rem;
        border: 1px solid #e9ecef;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    }

    .categories-stat-card .label {
        font-size: 0.75rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 0.25rem;
    }

    .categories-stat-card .value {
        font-size: 1.35rem;
        font-weight: 700;
        color: #212529;
    }

    .categories-toolbar {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
    }

    .categories-toolbar .search-wrap {
        flex: 1;
        min-width: 200px;
        position: relative;
    }

    .categories-toolbar .search-wrap i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
    }

    .categories-toolbar .search-wrap input {
        padding-left: 2.25rem;
        border-radius: 8px;
    }

    .categories-toolbar select.nice-select {
        appearance: none;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        padding: .4rem 2.1rem .4rem .85rem;
        font-size: .82rem;
        font-weight: 600;
        color: #334155;
        background-color: #fff;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23128c7e' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right .75rem center;
        cursor: pointer;
        transition: border-color .15s ease, box-shadow .15s ease, transform .12s ease;
    }
    .categories-toolbar select.nice-select:hover { border-color: #94a3b8; transform: translateY(-1px); }
    .categories-toolbar select.nice-select:focus { outline: none; border-color: #128c7e; box-shadow: 0 0 0 3px rgba(18,140,126,.15); }

    .categories-bulk-bar {
        display: none;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.65rem;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 10px;
        padding: 0.65rem 0.85rem;
        margin-bottom: 0.75rem;
        font-size: 0.85rem;
        color: #166534;
    }

    .categories-bulk-bar.is-visible {
        display: flex;
    }

    .categories-bulk-bar .bulk-count {
        font-weight: 700;
        margin-right: 0.25rem;
    }

    .categories-table .col-check {
        width: 42px;
        text-align: center;
        vertical-align: middle;
    }

    .categories-table .col-check input {
        cursor: pointer;
    }

    .categories-table-wrap {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    }

    .categories-table thead th {
        background: #f8f9fa;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6c757d;
        white-space: nowrap;
    }

    .category-cell {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .category-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: #f0fdf4;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
    }

    .badge-cat {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.35em 0.65em;
        border-radius: 6px;
    }

    .badge-active { background: #dcfce7; color: #166534; }
    .badge-inactive { background: #fee2e2; color: #991b1b; }
    .badge-count { background: #e0f2fe; color: #0369a1; font-size: 0.85rem; min-width: 2rem; }
    .badge-count-zero { background: #f3f4f6; color: #6b7280; }

    .cat-product-count {
        font-size: 0.8rem;
        font-weight: 600;
        color: #0369a1;
        margin-top: 0.15rem;
    }

    .cat-product-count .active-hint {
        font-weight: 500;
        color: #6c757d;
        font-size: 0.72rem;
    }

    .btn-wa {
        background: var(--wa-dark);
        border-color: var(--wa-dark);
        color: #fff;
    }

    .btn-wa:hover, .btn-wa:focus {
        background: var(--wa-teal);
        border-color: var(--wa-teal);
        color: #fff;
    }

    .modal-category {
        --wa-dark: #128c7e;
        --wa-teal: #075e54;
    }

    .modal-category .modal-header {
        background: linear-gradient(135deg, var(--wa-dark), var(--wa-teal));
        color: #fff;
    }

    .modal-category .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }

    .modal-category .modal-content {
        max-height: calc(100vh - 2rem);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .modal-category .modal-category-form {
        display: flex;
        flex-direction: column;
        min-height: 0;
        flex: 1 1 auto;
    }

    .modal-category .modal-body {
        overflow-y: auto;
        flex: 1 1 auto;
    }

    .modal-category .modal-footer {
        flex-shrink: 0;
        background: #fff;
        border-top: 1px solid #dee2e6;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        color: #6c757d;
    }

    #categoryToast {
        position: fixed;
        top: 1rem;
        right: 1rem;
        z-index: 1090;
        min-width: 280px;
    }
</style>

<div class="categories-page">
    <div id="categoryToast" class="toast align-items-center border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="categoryToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>

    <div class="categories-hero d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2><i class="fas fa-folder-open"></i> Categorías del catálogo</h2>
            <p>Organiza los productos que muestra el bot en WhatsApp</p>
        </div>
        <button type="button" class="btn btn-light btn-sm px-3" onclick="openCreateModal()" @if($planLimits['categories_at_limit'] ?? false) disabled title="Límite de categorías alcanzado" @endif>
            <i class="fas fa-plus me-1"></i> Nueva categoría
        </button>
    </div>

    @include('admin.partials.plan-limits-widget', ['planLimits' => $planLimits])

    <div class="categories-stats">
        <div class="categories-stat-card">
            <div class="label">Cuota categorías</div>
            <div class="value" style="color:{{ ($planLimits['categories_at_limit'] ?? false) ? '#dc2626' : '#128c7e' }}">{{ $planLimits['usage']['categories'] }}/{{ $planLimits['max_categories'] }}</div>
        </div>
        <div class="categories-stat-card">
            <div class="label">Total</div>
            <div class="value">{{ $stats['total'] }}</div>
        </div>
        <div class="categories-stat-card">
            <div class="label">Activas</div>
            <div class="value" style="color:#128c7e">{{ $stats['active'] }}</div>
        </div>
        <div class="categories-stat-card">
            <div class="label">Con productos</div>
            <div class="value" style="color:#0369a1">{{ $stats['with_products'] }}</div>
        </div>
        <div class="categories-stat-card">
            <div class="label">Productos en catálogo</div>
            <div class="value">{{ $stats['products_total'] }}</div>
            <div class="text-muted" style="font-size:0.72rem;margin-top:0.2rem">{{ $stats['products_active'] }} activos</div>
            @if(($stats['products_unassigned'] ?? 0) > 0)
                <div class="text-warning" style="font-size:0.68rem;margin-top:0.15rem">{{ $stats['products_unassigned'] }} sin categoría válida</div>
            @endif
        </div>
    </div>

    <div class="categories-toolbar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="search" class="form-control form-control-sm" placeholder="Buscar categorías...">
        </div>
        <select id="products-filter" class="form-select form-select-sm nice-select" style="width:auto;min-width:160px">
            <option value="">📦 Todos los productos</option>
            <option value="with">Con productos</option>
            <option value="empty">Sin productos</option>
        </select>
        <select id="status-filter" class="form-select form-select-sm nice-select" style="width:auto;min-width:140px">
            <option value="">Todos</option>
            <option value="1">✅ Activas</option>
            <option value="0">⛔ Inactivas</option>
        </select>
        <select id="demo-filter" class="form-select form-select-sm nice-select" style="width:auto;min-width:200px">
            <option value="">🏢 Todas las franquicias</option>
            @foreach($demoClienteOptions ?? [] as $slug => $label)
                <option value="{{ $slug }}" @selected(($activeDemoCliente ?? '') === $slug)>🏢 {{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div id="bulk-bar" class="categories-bulk-bar">
        <span><span class="bulk-count" id="bulk-selected-count">0</span> seleccionada(s)</span>
        <button type="button" class="btn btn-success btn-sm" id="bulk-activate-btn">
            <i class="fas fa-check me-1"></i> Activar
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="bulk-deactivate-btn">
            <i class="fas fa-ban me-1"></i> Desactivar
        </button>
        <button type="button" class="btn btn-link btn-sm text-muted" id="bulk-clear-btn">Limpiar</button>
    </div>

    <div class="categories-table-wrap">
        @if($categories->isEmpty())
            <div class="empty-state">
                <div style="font-size:2.5rem">🗂️</div>
                <h5>Sin categorías</h5>
                <p class="mb-3">Crea categorías para agrupar productos en el catálogo del bot.</p>
                <button type="button" class="btn btn-wa btn-sm" onclick="openCreateModal()">
                    <i class="fas fa-plus me-1"></i> Crear categoría
                </button>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 categories-table">
                    <thead>
                        <tr>
                            <th class="col-check ps-3">
                                <input type="checkbox" id="select-all-categories" title="Seleccionar visibles">
                            </th>
                            <th>Categoría</th>
                            <th>Descripción</th>
                            <th>Productos</th>
                            <th>Franquicia</th>
                            <th>Orden</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $category)
                        @php
                            $totalProducts = (int) $category->prices_count;
                            $activeProducts = (int) ($category->active_prices_count ?? 0);
                            $productLabel = $totalProducts === 1 ? '1 producto' : $totalProducts . ' productos';
                        @endphp
                        <tr class="category-row"
                            data-id="{{ $category->id }}"
                            data-search="{{ strtolower($category->title . ' ' . ($category->description ?? '')) }}"
                            data-status="{{ $category->is_active ? '1' : '0' }}"
                            data-demo="{{ $category->demo_cliente ?? '' }}"
                            data-products="{{ $totalProducts > 0 ? 'with' : 'empty' }}">
                            <td class="col-check ps-3">
                                <input type="checkbox" class="category-select" value="{{ $category->id }}" aria-label="Seleccionar {{ $category->title }}">
                            </td>
                            <td>
                                <div class="category-cell">
                                    @if($category->image_url)
                                        <img src="{{ $category->image_url }}" alt="" class="category-icon" style="object-fit:cover;font-size:0">
                                    @else
                                        <div class="category-icon">{{ $category->icon ?: '📦' }}</div>
                                    @endif
                                    <div>
                                        <div class="fw-semibold">{{ $category->title }}</div>
                                        <div class="text-muted" style="font-size:0.75rem">ID: {{ $category->action_id }}</div>
                                        <div class="cat-product-count">
                                            <i class="fas fa-box-open me-1"></i>{{ $productLabel }}
                                            @if($totalProducts > 0 && $activeProducts !== $totalProducts)
                                                <span class="active-hint">· {{ $activeProducts }} activo{{ $activeProducts === 1 ? '' : 's' }} en el bot</span>
                                            @elseif($totalProducts > 0)
                                                <span class="active-hint">· visibles en el bot</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ Str::limit($category->description, 60) ?: '—' }}</td>
                            <td>
                                @if($totalProducts === 0)
                                    <span class="badge badge-cat badge-count-zero">Sin productos</span>
                                @else
                                    <span class="badge badge-cat badge-count">{{ $totalProducts }}</span>
                                    <span class="text-muted" style="font-size:0.75rem"> total</span>
                                    @if($activeProducts < $totalProducts)
                                        <div style="font-size:0.72rem;color:#166534;margin-top:0.2rem">{{ $activeProducts }} activo{{ $activeProducts === 1 ? '' : 's' }}</div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                @if($category->franchise)
                                    <span class="badge bg-light text-dark border">{{ $category->franchise->name }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $category->order }}</td>
                            <td>
                                <span class="badge badge-cat {{ $category->is_active ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $category->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm me-1" title="Editar" onclick="openEditModal({{ $category->id }})">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" title="Eliminar" onclick="deleteCategory({{ $category->id }}, {{ $totalProducts }})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="modal fade modal-category" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="categoryForm" class="modal-category-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-folder me-2"></i>Nueva categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="formErrors" class="alert alert-danger d-none"></div>
                    <div class="mb-3">
                        <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="title" name="title" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea class="form-control form-control-sm" id="description" name="description" rows="2" maxlength="1000"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="category_image" class="form-label">Foto de la categoría</label>
                        <input type="file" class="form-control form-control-sm" name="image" id="category_image" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">JPG, PNG o WebP. Máx. 5 MB.</div>
                        <div id="categoryImagePreviewWrap" class="mt-2 d-none">
                            <img src="" alt="" id="categoryImagePreview" style="width:90px;height:90px;object-fit:cover;border-radius:10px;border:1px solid #e9ecef">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_image" id="remove_category_image" value="1">
                                <label class="form-check-label small" for="remove_category_image">Quitar imagen actual</label>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="icon" class="form-label">Icono</label>
                            <input type="text" class="form-control form-control-sm" id="icon" name="icon" placeholder="📦" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label for="order" class="form-label">Orden</label>
                            <input type="number" class="form-control form-control-sm" id="order" name="order" min="0" value="0">
                        </div>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                        <label class="form-check-label" for="is_active">Categoría activa en el catálogo del bot</label>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="franchise_id" class="form-label">Franquicia</label>
                        <select id="franchise_id" name="franchise_id" class="form-select form-select-sm" required>
                            @foreach($franchises as $franchise)
                                <option value="{{ $franchise->id }}" @selected($franchise->is_default)>{{ $franchise->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-wa btn-sm px-4" id="saveCategoryBtn">
                        <i class="fas fa-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentCategoryId = null;
let categoryModal = null;
let categoryToast = null;

document.addEventListener('DOMContentLoaded', function() {
    categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
    categoryToast = new bootstrap.Toast(document.getElementById('categoryToast'), { delay: 4500 });

    document.getElementById('search')?.addEventListener('input', filterTable);
    document.getElementById('status-filter')?.addEventListener('change', filterTable);
    document.getElementById('products-filter')?.addEventListener('change', filterTable);
    document.getElementById('demo-filter')?.addEventListener('change', filterTable);
    document.getElementById('categoryForm')?.addEventListener('submit', submitCategoryForm);
    document.getElementById('select-all-categories')?.addEventListener('change', toggleSelectAllVisible);
    document.querySelectorAll('.category-select').forEach(cb => cb.addEventListener('change', updateBulkBar));
    document.getElementById('bulk-activate-btn')?.addEventListener('click', () => bulkUpdateStatus(true));
    document.getElementById('bulk-deactivate-btn')?.addEventListener('click', () => bulkUpdateStatus(false));
    document.getElementById('bulk-clear-btn')?.addEventListener('click', clearSelection);
    filterTable();
});

const categoriesAtLimit = @json($planLimits['categories_at_limit'] ?? false);

function resetCategoryImagePreview() {
    const wrap = document.getElementById('categoryImagePreviewWrap');
    const preview = document.getElementById('categoryImagePreview');
    const fileInput = document.getElementById('category_image');
    const removeCb = document.getElementById('remove_category_image');
    if (fileInput) fileInput.value = '';
    if (removeCb) removeCb.checked = false;
    if (preview) preview.src = '';
    wrap?.classList.add('d-none');
}

function setCategoryImagePreview(url) {
    const preview = document.getElementById('categoryImagePreview');
    const wrap = document.getElementById('categoryImagePreviewWrap');
    if (!preview || !wrap) return;
    if (url) {
        preview.src = url;
        wrap.classList.remove('d-none');
    } else {
        resetCategoryImagePreview();
    }
}

document.getElementById('category_image')?.addEventListener('change', function () {
    const file = this.files?.[0];
    if (!file) return;
    document.getElementById('categoryImagePreview').src = URL.createObjectURL(file);
    document.getElementById('categoryImagePreviewWrap').classList.remove('d-none');
    document.getElementById('remove_category_image').checked = false;
});

function openCreateModal() {
    if (categoriesAtLimit) {
        showToast('Has alcanzado el límite de categorías permitidas.', 'danger');
        return;
    }
    currentCategoryId = null;
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Nueva categoría';
    document.getElementById('categoryForm').reset();
    document.getElementById('is_active').checked = true;
    document.getElementById('order').value = {{ ($categories->max('order') ?? 0) + 1 }};
    resetCategoryImagePreview();
    hideFormErrors();
    categoryModal.show();
}

function openEditModal(id) {
    currentCategoryId = id;
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-pen me-2"></i>Editar categoría';
    hideFormErrors();

    fetch(`/admin/menu-items/${id}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => {
        if (!r.ok) throw new Error('No se pudo cargar la categoría');
        return r.json();
    })
    .then(data => {
        document.getElementById('title').value = data.title || '';
        document.getElementById('description').value = data.description || '';
        document.getElementById('icon').value = data.icon || '';
        document.getElementById('order').value = data.order ?? 0;
        document.getElementById('franchise_id').value = data.franchise_id || '';
        document.getElementById('is_active').checked = !!data.is_active;
        resetCategoryImagePreview();
        setCategoryImagePreview(data.image_url || '');
        categoryModal.show();
    })
    .catch(err => showToast(err.message, 'danger'));
}

function submitCategoryForm(e) {
    e.preventDefault();
    hideFormErrors();

    const btn = document.getElementById('saveCategoryBtn');
    btn.disabled = true;

    const formData = new FormData();
    formData.append('title', document.getElementById('title').value.trim());
    formData.append('description', document.getElementById('description').value);
    formData.append('icon', document.getElementById('icon').value.trim() || '📦');
    formData.append('order', document.getElementById('order').value);
    formData.append('franchise_id', document.getElementById('franchise_id').value);
    formData.append('is_active', document.getElementById('is_active').checked ? '1' : '0');

    const imageFile = document.getElementById('category_image')?.files?.[0];
    if (imageFile) formData.append('image', imageFile);
    if (document.getElementById('remove_category_image')?.checked) formData.append('remove_image', '1');

    const isEdit = currentCategoryId !== null;
    const url = isEdit ? `/admin/menu-items/${currentCategoryId}` : '/admin/menu-items';
    if (isEdit) formData.append('_method', 'PUT');

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: formData,
    })
    .then(async r => {
        const data = await r.json();
        if (!r.ok) {
            if (data.errors) showFormErrors(data.errors);
            throw new Error(data.message || 'Error al guardar');
        }
        return data;
    })
    .then(data => {
        categoryModal.hide();
        showToast(data.message || 'Guardado', 'success');
        setTimeout(() => window.location.reload(), 700);
    })
    .catch(err => {
        if (document.getElementById('formErrors').classList.contains('d-none')) {
            showToast(err.message || 'Error al guardar', 'danger');
        }
    })
    .finally(() => { btn.disabled = false; });
}

function deleteCategory(id, productsCount) {
    if (productsCount > 0) {
        showToast('No se puede eliminar: tiene ' + productsCount + ' producto(s). Desactívala o mueve los productos.', 'danger');
        return;
    }

    if (!confirm('¿Eliminar esta categoría?')) return;

    fetch(`/admin/menu-items/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(async r => {
        const data = await r.json();
        if (!r.ok) throw new Error(data.message || 'Error al eliminar');
        return data;
    })
    .then(data => {
        showToast(data.message, 'success');
        setTimeout(() => window.location.reload(), 700);
    })
    .catch(err => showToast(err.message, 'danger'));
}

function filterTable() {
    const search = (document.getElementById('search')?.value || '').toLowerCase();
    const status = document.getElementById('status-filter')?.value || '';
    const products = document.getElementById('products-filter')?.value || '';
    const demo = document.getElementById('demo-filter')?.value || '';

    document.querySelectorAll('.category-row').forEach(row => {
        const okSearch = !search || row.dataset.search.includes(search);
        const okStatus = !status || row.dataset.status === status;
        const okProducts = !products || row.dataset.products === products;
        const rowDemo = row.dataset.demo || '';
        const okDemo = !demo || (demo === '__none__' ? rowDemo === '' : rowDemo === demo);
        row.style.display = okSearch && okStatus && okProducts && okDemo ? '' : 'none';
    });

    syncSelectAllCheckbox();
    updateBulkBar();
}

function getVisibleRows() {
    return [...document.querySelectorAll('.category-row')].filter(row => row.style.display !== 'none');
}

function getSelectedIds() {
    return [...document.querySelectorAll('.category-select:checked')].map(cb => parseInt(cb.value, 10));
}

function updateBulkBar() {
    const count = getSelectedIds().length;
    const bar = document.getElementById('bulk-bar');
    const countEl = document.getElementById('bulk-selected-count');
    if (countEl) countEl.textContent = String(count);
    if (bar) bar.classList.toggle('is-visible', count > 0);
    syncSelectAllCheckbox();
}

function syncSelectAllCheckbox() {
    const master = document.getElementById('select-all-categories');
    if (!master) return;
    const visible = getVisibleRows();
    const visibleChecks = visible.map(row => row.querySelector('.category-select')).filter(Boolean);
    const checkedVisible = visibleChecks.filter(cb => cb.checked).length;
    master.indeterminate = checkedVisible > 0 && checkedVisible < visibleChecks.length;
    master.checked = visibleChecks.length > 0 && checkedVisible === visibleChecks.length;
}

function toggleSelectAllVisible(e) {
    const checked = e.target.checked;
    getVisibleRows().forEach(row => {
        const cb = row.querySelector('.category-select');
        if (cb) cb.checked = checked;
    });
    updateBulkBar();
}

function clearSelection() {
    document.querySelectorAll('.category-select').forEach(cb => { cb.checked = false; });
    const master = document.getElementById('select-all-categories');
    if (master) {
        master.checked = false;
        master.indeterminate = false;
    }
    updateBulkBar();
}

function bulkUpdateStatus(isActive) {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        showToast('Seleccione al menos una categoría.', 'danger');
        return;
    }

    const action = isActive ? 'activar' : 'desactivar';
    if (!confirm(`¿${action.charAt(0).toUpperCase() + action.slice(1)} ${ids.length} categoría(s)?`)) {
        return;
    }

    fetch('/admin/menu-items/bulk-status', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ ids, is_active: isActive }),
        credentials: 'same-origin',
    })
    .then(async r => {
        let data = {};
        try {
            data = await r.json();
        } catch (e) {
            throw new Error(r.ok ? 'Respuesta inválida del servidor' : `Error ${r.status}`);
        }
        if (!r.ok) throw new Error(data.message || `Error ${r.status}`);
        return data;
    })
    .then(data => {
        showToast(data.message, 'success');
        setTimeout(() => window.location.reload(), 700);
    })
    .catch(err => showToast(err.message || 'No se pudo conectar con el servidor', 'danger'));
}

function showFormErrors(errors) {
    const el = document.getElementById('formErrors');
    el.innerHTML = Object.values(errors).flat().map(m => `<div>${m}</div>`).join('');
    el.classList.remove('d-none');
}

function hideFormErrors() {
    const el = document.getElementById('formErrors');
    el.classList.add('d-none');
    el.innerHTML = '';
}

function showToast(message, type = 'success') {
    const toastEl = document.getElementById('categoryToast');
    document.getElementById('categoryToastBody').textContent = message;
    toastEl.className = `toast align-items-center border-0 text-white bg-${type === 'success' ? 'success' : 'danger'}`;
    categoryToast.show();
}
</script>
@endpush
