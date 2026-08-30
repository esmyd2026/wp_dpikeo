@extends('admin.layouts.app')

@section('header', 'Productos')

@section('content')
<style>
    .products-page {
        --wa-green: #25d366;
        --wa-dark: #128c7e;
        --wa-teal: #075e54;
    }

    .products-hero {
        background: linear-gradient(135deg, var(--wa-dark) 0%, var(--wa-teal) 100%);
        color: #fff;
        border-radius: 14px;
        padding: 1.5rem 1.75rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 4px 14px rgba(7, 94, 84, 0.2);
    }

    .products-hero h2 {
        font-size: 1.35rem;
        font-weight: 600;
        margin: 0 0 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .products-hero p {
        margin: 0;
        opacity: 0.9;
        font-size: 0.9rem;
    }

    .products-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }

    .products-stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 1rem 1.1rem;
        border: 1px solid #e9ecef;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    }

    .products-stat-card .label {
        font-size: 0.75rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 0.25rem;
    }

    .products-stat-card .value {
        font-size: 1.35rem;
        font-weight: 700;
        color: #212529;
    }

    .products-toolbar {
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

    .products-toolbar .search-wrap {
        flex: 1;
        min-width: 200px;
        position: relative;
    }

    .products-toolbar .search-wrap i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
    }

    .products-toolbar .search-wrap input {
        padding-left: 2.25rem;
        border-radius: 8px;
    }

    .products-toolbar select.nice-select {
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
    .products-toolbar select.nice-select:hover { border-color: #94a3b8; transform: translateY(-1px); }
    .products-toolbar select.nice-select:focus { outline: none; border-color: #128c7e; box-shadow: 0 0 0 3px rgba(18,140,126,.15); }

    .products-bulk-bar {
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

    .products-bulk-bar.is-visible {
        display: flex;
    }

    .products-bulk-bar .bulk-count {
        font-weight: 700;
        margin-right: 0.25rem;
    }

    .products-table .col-check {
        width: 42px;
    }

    .products-table-wrap {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    }

    .products-table {
        margin: 0;
    }

    .products-table thead th {
        background: #f8f9fa;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6c757d;
        border-bottom: 1px solid #e9ecef;
        white-space: nowrap;
    }

    .products-table tbody td {
        vertical-align: middle;
        font-size: 0.875rem;
    }

    .product-cell {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .product-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: #f0fdf4;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
        overflow: hidden;
    }

    .product-icon img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .product-image-preview {
        max-height: 120px;
        max-width: 100%;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid #dee2e6;
    }

    .product-name {
        font-weight: 600;
        color: #212529;
        line-height: 1.2;
    }

    .product-desc {
        color: #6c757d;
        font-size: 0.78rem;
        margin-top: 0.15rem;
    }

    .price-regular {
        font-weight: 600;
        color: #212529;
    }

    .price-promo {
        color: #dc3545;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .badge-product {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.35em 0.65em;
        border-radius: 6px;
    }

    .badge-active { background: #dcfce7; color: #166534; }
    .badge-inactive { background: #fee2e2; color: #991b1b; }
    .badge-promo { background: #fef3c7; color: #92400e; }
    .badge-stock-ok { background: #e0f2fe; color: #0369a1; }
    .badge-stock-low { background: #ffedd5; color: #c2410c; }
    .badge-stock-out { background: #f3f4f6; color: #6b7280; }

    .btn-wa {
        background: var(--wa-dark);
        border-color: var(--wa-dark);
        color: #fff;
    }

    .btn-wa:hover {
        background: var(--wa-teal);
        border-color: var(--wa-teal);
        color: #fff;
    }

    .btn-wa:focus,
    .btn-wa:active {
        background: var(--wa-teal) !important;
        border-color: var(--wa-teal) !important;
        color: #fff !important;
        box-shadow: 0 0 0 0.2rem rgba(18, 140, 126, 0.35);
    }

    .btn-action {
        width: 34px;
        height: 34px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
    }

    .modal-product {
        --wa-green: #25d366;
        --wa-dark: #128c7e;
        --wa-teal: #075e54;
    }

    .modal-product .modal-content {
        max-height: calc(100vh - 2rem);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .modal-product .modal-product-form {
        display: flex;
        flex-direction: column;
        min-height: 0;
        flex: 1 1 auto;
    }

    .modal-product .modal-body {
        overflow-y: auto;
        flex: 1 1 auto;
    }

    .modal-product .modal-footer {
        flex-shrink: 0;
        background: #fff;
        border-top: 1px solid #dee2e6;
        position: sticky;
        bottom: 0;
        z-index: 5;
        gap: 0.5rem;
        justify-content: flex-end;
        padding: 0.85rem 1rem;
    }

    .modal-product .modal-header {
        background: linear-gradient(135deg, var(--wa-dark), var(--wa-teal));
        color: #fff;
    }

    .modal-product .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }

    .form-hint {
        font-size: 0.78rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 2.5rem;
        color: #dee2e6;
        margin-bottom: 0.75rem;
    }

    #productToast {
        position: fixed;
        top: 1rem;
        right: 1rem;
        z-index: 1090;
        min-width: 280px;
    }

    #importProductsModal .modal-content {
        max-height: calc(100vh - 2rem);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    #importProductsModal #importProductsForm {
        display: flex;
        flex-direction: column;
        min-height: 0;
        flex: 1 1 auto;
    }

    #importProductsModal .modal-body {
        overflow-y: auto;
        flex: 1 1 auto;
    }

    #importProductsModal .import-modal-footer {
        flex-shrink: 0;
        background: #fff;
        border-top: 1px solid #dee2e6;
        padding: 0.85rem 1rem;
    }

    #importProductsModal .import-modal-footer-inner {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        width: 100%;
    }

    #importProductsModal .import-modal-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        margin-left: auto;
    }
</style>

<div class="products-page">
    <div id="productToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="productToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>

    <div class="products-hero d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2><i class="fas fa-box-open"></i> Catálogo de productos</h2>
            <p>Gestiona precios, stock y categorías del chatbot de WhatsApp</p>
            @if(!empty($activeDemoCliente))
                <p class="mt-2 mb-0 small opacity-90">
                    <i class="fas fa-filter me-1"></i>
                    Franquicia visible en bot: <strong>{{ $demoClienteOptions[$activeDemoCliente] ?? $activeDemoCliente }}</strong>.
                    <a href="{{ route('admin.franchises.index') }}" class="text-white text-decoration-underline ms-1">Administrar franquicias</a>
                </p>
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-light btn-sm px-3" data-bs-toggle="modal" data-bs-target="#importProductsModal">
                <i class="fas fa-file-excel me-1"></i> Importar Excel
            </button>
            <a href="{{ route('admin.products.export') }}" class="btn btn-outline-light btn-sm px-3">
                <i class="fas fa-download me-1"></i> Exportar catálogo
            </a>
            <button type="button" class="btn btn-light btn-sm px-3" onclick="openCreateModal()">
                <i class="fas fa-plus me-1"></i> Nuevo producto
            </button>
        </div>
    </div>

    <div class="products-stats">
        <div class="products-stat-card">
            <div class="label">Total</div>
            <div class="value">{{ $stats['total'] }}</div>
        </div>
        <div class="products-stat-card">
            <div class="label">Activos</div>
            <div class="value" style="color:#128c7e">{{ $stats['active'] }}</div>
        </div>
        <div class="products-stat-card">
            <div class="label">En promo</div>
            <div class="value" style="color:#d97706">{{ $stats['promo'] }}</div>
        </div>
        <div class="products-stat-card">
            <div class="label">Sin stock</div>
            <div class="value" style="color:#6c757d">{{ $stats['no_stock'] }}</div>
        </div>
    </div>

    <div class="products-toolbar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="search" class="form-control form-control-sm" placeholder="Buscar por nombre, SKU o categoría...">
        </div>
        <select id="category-filter" class="form-select form-select-sm nice-select" style="width:auto;min-width:170px">
            <option value="">📂 Todas las categorías</option>
            @foreach($categories as $category)
                <option value="{{ $category->title }}">{{ $category->icon }} {{ $category->title }}</option>
            @endforeach
        </select>
        <select id="demo-filter" class="form-select form-select-sm nice-select" style="width:auto;min-width:200px">
            <option value="">🏢 Todas las franquicias</option>
            @foreach($demoClienteOptions ?? [] as $slug => $label)
                <option value="{{ $slug }}" @selected(($activeDemoCliente ?? '') === $slug)>🏢 {{ $label }}</option>
            @endforeach
        </select>
        <select id="status-filter" class="form-select form-select-sm nice-select" style="width:auto;min-width:130px">
            <option value="">Todos</option>
            <option value="1">✅ Activos</option>
            <option value="0">⛔ Inactivos</option>
        </select>
    </div>

    <div id="bulk-bar" class="products-bulk-bar">
        <span><span class="bulk-count" id="bulk-selected-count">0</span> seleccionado(s)</span>
        <button type="button" class="btn btn-success btn-sm" id="bulk-activate-btn">
            <i class="fas fa-check me-1"></i> Activar
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="bulk-deactivate-btn">
            <i class="fas fa-ban me-1"></i> Desactivar
        </button>
        <button type="button" class="btn btn-link btn-sm text-muted" id="bulk-clear-btn">Limpiar</button>
    </div>

    <div class="products-table-wrap">
        @if($products->isEmpty())
            <div class="empty-state">
                <div><i class="fas fa-box-open"></i></div>
                <h5>No hay productos</h5>
                <p class="mb-3">Crea el primer producto para el catálogo del bot.</p>
                <button type="button" class="btn btn-wa btn-sm" onclick="openCreateModal()">
                    <i class="fas fa-plus me-1"></i> Crear producto
                </button>
            </div>
        @else
            <div class="table-responsive">
                <table class="table products-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="col-check ps-3">
                                <input type="checkbox" id="select-all-products" title="Seleccionar visibles">
                            </th>
                            <th>Producto</th>
                            <th>SKU</th>
                            <th>Categoría</th>
                            <th>Franquicia</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Modificado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody">
                        @foreach($products as $product)
                        <tr class="product-row"
                            data-id="{{ $product->id }}"
                            data-search="{{ strtolower($product->sku . ' ' . $product->name . ' ' . ($product->menuCategory?->title ?? $product->category)) }}"
                            data-category="{{ $product->menuCategory?->title ?? $product->category }}"
                            data-demo="{{ $product->demo_cliente ?? '' }}"
                            data-status="{{ $product->is_active ? '1' : '0' }}">
                            <td class="col-check ps-3">
                                <input type="checkbox" class="product-select" value="{{ $product->id }}" aria-label="Seleccionar {{ $product->name }}">
                            </td>
                            <td class="ps-0">
                                <div class="product-cell">
                                    <div class="product-icon">
                                        @if($product->image_url)
                                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                                        @else
                                            {{ $product->icon }}
                                        @endif
                                    </div>
                                    <div>
                                        <div class="product-name">{{ $product->name }}</div>
                                        @if($product->description)
                                            <div class="product-desc">{{ Str::limit($product->description, 55) }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td><code>{{ $product->sku }}</code></td>
                            <td>{{ $product->menuCategory?->title ?? $product->category ?? '—' }}</td>
                            <td>
                                @if($product->franchise)
                                    <span class="badge bg-light text-dark border">{{ $product->franchise->name }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($product->is_promo && $product->promo_price)
                                    <div class="price-promo">${{ number_format($product->promo_price, 2) }}</div>
                                    <div class="text-muted text-decoration-line-through" style="font-size:0.78rem">${{ number_format($product->price, 2) }}</div>
                                @else
                                    <div class="price-regular">${{ number_format($product->price, 2) }}</div>
                                @endif
                            </td>
                            <td>
                                @if($product->stock <= 0)
                                    <span class="badge badge-product badge-stock-out">Agotado</span>
                                @elseif($product->stock <= 5)
                                    <span class="badge badge-product badge-stock-low">{{ $product->stock }} uds.</span>
                                @else
                                    <span class="badge badge-product badge-stock-ok">{{ $product->stock }} uds.</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-product {{ $product->is_active ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                                @if($product->is_promo)
                                    <span class="badge badge-product badge-promo ms-1">Promo</span>
                                @endif
                            </td>
                            <td class="text-muted" style="font-size:0.78rem;white-space:nowrap;">
                                {{ $product->updated_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="text-end pe-3">
                                <button type="button" class="btn btn-outline-secondary btn-action me-1" title="Editar" onclick="openEditModal({{ $product->id }})">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-action me-1" title="Duplicar a otra franquicia" onclick="openDuplicateModal({{ $product->id }}, {{ Illuminate\Support\Js::from($product->name) }}, {{ $product->franchise_id ?? 'null' }})">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-action" title="Eliminar" onclick="deleteProduct({{ $product->id }})">
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

<div class="modal fade modal-product" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="productForm" class="modal-product-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-box me-2"></i>Nuevo producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="formErrors" class="alert alert-danger d-none"></div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="sku" id="sku" required maxlength="20" pattern="[A-Za-z0-9\-]{1,20}" title="Hasta 20 caracteres alfanuméricos">
                            <div class="form-hint">Ej: CQ001, SW-001</div>
                        </div>
                        <div class="col-md-8">
                            <label for="menu_item_id" class="form-label">Categoría <span class="text-danger">*</span></label>
                            <select name="menu_item_id" id="menu_item_id" class="form-select form-select-sm" required>
                                <option value="">Seleccione categoría</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->icon }} {{ $category->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="name" id="name" required maxlength="255">
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea id="description" name="description" rows="2" class="form-control form-control-sm"></textarea>
                        </div>
                        <div class="col-12">
                            <label for="product_image" class="form-label">Imagen del producto</label>
                            <input type="file" class="form-control form-control-sm" name="image" id="product_image" accept="image/jpeg,image/png,image/webp">
                            <div class="form-hint">JPG, PNG o WebP. Máx. 5 MB. Se muestra en el detalle del producto en WhatsApp.</div>
                            <div id="productImagePreviewWrap" class="mt-2 d-none">
                                <img src="" alt="" id="productImagePreview" class="product-image-preview">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_image" id="remove_image" value="1">
                                    <label class="form-check-label small" for="remove_image">Quitar imagen actual</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="price" class="form-label">Precio <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" name="price" id="price" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="promo_price" class="form-label">Precio promocional</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" name="promo_price" id="promo_price" class="form-control">
                            </div>
                            <div class="form-hint">Debe ser menor al precio regular</div>
                        </div>
                        <div class="col-md-4">
                            <label for="stock" class="form-label">Stock</label>
                            <input type="number" min="0" name="stock" id="stock" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="min_quantity" class="form-label">Cant. mínima</label>
                            <input type="number" min="1" name="min_quantity" id="min_quantity" class="form-control form-control-sm" value="1">
                        </div>
                        <div class="col-md-4">
                            <label for="max_quantity" class="form-label">Cant. máxima</label>
                            <input type="number" min="1" name="max_quantity" id="max_quantity" class="form-control form-control-sm" value="999">
                        </div>
                        <div class="col-md-6">
                            <label for="benefits" class="form-label">Beneficios</label>
                            <textarea id="benefits" name="benefits" rows="3" class="form-control form-control-sm"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="characteristics" class="form-label">Características</label>
                            <textarea id="characteristics" name="characteristics" rows="3" class="form-control form-control-sm" placeholder="Una característica por línea"></textarea>
                            <div class="form-hint">Se muestran en el detalle del producto en WhatsApp</div>
                        </div>
                        <div class="col-md-6">
                            <label for="variations" class="form-label">Variaciones</label>
                            <textarea id="variations" name="variations" rows="3" class="form-control form-control-sm" placeholder="Con gaseosa | 5.99&#10;Sin gaseosa | 5.40"></textarea>
                            <div class="form-hint">Una por línea. Formato: nombre | precio.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="extras" class="form-label">Extras</label>
                            <textarea id="extras" name="extras" rows="3" class="form-control form-control-sm" placeholder="Salsa Spice Chicken | 0.50&#10;Papas extra | 1.00"></textarea>
                            <div class="form-hint">Una por línea. El precio puede quedar vacío si se cotiza.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="franchise_id" class="form-label">Franquicia</label>
                            <select class="form-select form-select-sm" name="franchise_id" id="franchise_id" required>
                                @foreach($franchises as $franchise)
                                    <option value="{{ $franchise->id }}" @selected($franchise->is_default)>{{ $franchise->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                <label class="form-check-label" for="is_active">Producto activo en el catálogo</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="allow_quantity_selection" name="allow_quantity_selection" checked>
                                <label class="form-check-label" for="allow_quantity_selection">Permitir elegir cantidad</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-wa btn-sm px-4" id="saveProductBtn">
                        <i class="fas fa-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="duplicateProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#128c7e,#075e54);color:#fff">
                <h5 class="modal-title"><i class="fas fa-copy me-2"></i>Duplicar producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Duplicar <strong id="duplicateProductName"></strong> hacia:</p>
                <div id="duplicateFranchiseList" class="d-grid gap-2"></div>
                <div id="duplicateResults" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-wa btn-sm px-4" id="confirmDuplicateBtn" onclick="submitDuplicate()">
                    <i class="fas fa-copy me-1"></i> Duplicar
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="importProductsModal" tabindex="-1" aria-labelledby="importProductsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="importProductsForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="importProductsModalLabel">
                        <i class="fas fa-file-excel me-2 text-success"></i>Carga masiva de productos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small mb-3">
                        <strong>Formato Excel (.xlsx)</strong>
                        <ul class="mb-0 mt-2 ps-3">
                            <li>Descargue la <a href="{{ route('admin.products.import.template') }}">plantilla con instrucciones</a> o exporte el catálogo actual como base.</li>
                            <li>Hoja <strong>Productos</strong>: columnas <code>sku</code>, <code>nombre</code>, <code>categoria</code>, <code>precio</code> (obligatorias).</li>
                            <li>Opcionales: <code>precio_promo</code>, <code>descripcion</code>, <code>beneficios</code>, <code>caracteristicas</code> (separar con <code>|</code>), <code>stock</code>, <code>cant_min</code>, <code>cant_max</code>, <code>activo</code> y <code>permitir_cantidad</code>.</li>
                            <li>La hoja <strong>Categorias</strong> lista los nombres válidos para la columna <code>categoria</code>.</li>
                            <li>Si el SKU ya existe, el producto se actualiza (modo por defecto).</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <label for="import_mode" class="form-label">Modo de importación</label>
                        <select name="mode" id="import_mode" class="form-select form-select-sm">
                            <option value="upsert" selected>Crear y actualizar (por SKU)</option>
                            <option value="create">Solo crear productos nuevos</option>
                            <option value="update">Solo actualizar productos existentes</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="import_file" class="form-label">Archivo Excel <span class="text-danger">*</span></label>
                        <input type="file" class="form-control form-control-sm" name="file" id="import_file" accept=".xlsx,.xls,.csv" required>
                        <div class="form-text">Máximo 10 MB. Formatos: .xlsx, .xls, .csv</div>
                    </div>

                    <div id="importResult" class="d-none">
                        <div id="importResultSummary" class="alert mb-2"></div>
                        <div id="importResultErrors" class="small border rounded p-2 bg-light" style="max-height:220px;overflow-y:auto"></div>
                    </div>
                </div>
                <div class="modal-footer import-modal-footer">
                    <div class="import-modal-footer-inner">
                        <a href="{{ route('admin.products.import.template') }}" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-file-download me-1"></i> Descargar plantilla
                        </a>
                        <div class="import-modal-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success btn-sm px-4" id="importProductsBtn">
                                <i class="fas fa-upload me-1"></i> Importar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentProductId = null;
let productModal = null;
let productToast = null;
let duplicateProductModal = null;
let duplicateProductId = null;

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}
const ALL_FRANCHISES = @json($franchises->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->values());
const DUPLICATE_URL_TEMPLATE = @json(url('/admin/products/__ID__/duplicate'));

document.addEventListener('DOMContentLoaded', function() {
    productModal = new bootstrap.Modal(document.getElementById('productModal'));
    productToast = new bootstrap.Toast(document.getElementById('productToast'), { delay: 4500 });
    duplicateProductModal = new bootstrap.Modal(document.getElementById('duplicateProductModal'));

    document.getElementById('search')?.addEventListener('input', filterTable);
    document.getElementById('category-filter')?.addEventListener('change', filterTable);
    document.getElementById('demo-filter')?.addEventListener('change', filterTable);
    document.getElementById('status-filter')?.addEventListener('change', filterTable);
    document.getElementById('productForm')?.addEventListener('submit', submitProductForm);
    document.getElementById('importProductsForm')?.addEventListener('submit', submitImportForm);
    document.getElementById('importProductsModal')?.addEventListener('hidden.bs.modal', resetImportModal);
    document.getElementById('product_image')?.addEventListener('change', previewProductImage);
    document.getElementById('select-all-products')?.addEventListener('change', toggleSelectAllVisible);
    document.querySelectorAll('.product-select').forEach(cb => cb.addEventListener('change', updateBulkBar));
    document.getElementById('bulk-activate-btn')?.addEventListener('click', () => bulkUpdateStatus(true));
    document.getElementById('bulk-deactivate-btn')?.addEventListener('click', () => bulkUpdateStatus(false));
    document.getElementById('bulk-clear-btn')?.addEventListener('click', clearSelection);
    filterTable();
});

function openCreateModal() {
    currentProductId = null;
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Nuevo producto';
    document.getElementById('productForm').reset();
    document.getElementById('is_active').checked = true;
    document.getElementById('allow_quantity_selection').checked = true;
    document.getElementById('min_quantity').value = 1;
    document.getElementById('max_quantity').value = 999;
    document.getElementById('stock').value = 0;
    resetProductImagePreview();
    hideFormErrors();
    productModal.show();
}

function resetProductImagePreview() {
    const wrap = document.getElementById('productImagePreviewWrap');
    const preview = document.getElementById('productImagePreview');
    const fileInput = document.getElementById('product_image');
    const removeCb = document.getElementById('remove_image');
    if (fileInput) fileInput.value = '';
    if (removeCb) removeCb.checked = false;
    if (preview) preview.src = '';
    wrap?.classList.add('d-none');
}

function previewProductImage() {
    const fileInput = document.getElementById('product_image');
    const preview = document.getElementById('productImagePreview');
    const wrap = document.getElementById('productImagePreviewWrap');
    const removeCb = document.getElementById('remove_image');
    if (!fileInput?.files?.[0] || !preview || !wrap) return;
    preview.src = URL.createObjectURL(fileInput.files[0]);
    wrap.classList.remove('d-none');
    if (removeCb) removeCb.checked = false;
}

function setProductImagePreview(url) {
    const preview = document.getElementById('productImagePreview');
    const wrap = document.getElementById('productImagePreviewWrap');
    if (!preview || !wrap) return;
    if (url) {
        preview.src = url;
        preview.dataset.currentSrc = url;
        wrap.classList.remove('d-none');
    } else {
        resetProductImagePreview();
    }
}

function openDuplicateModal(id, name, currentFranchiseId) {
    duplicateProductId = id;
    document.getElementById('duplicateProductName').textContent = name;
    document.getElementById('duplicateResults').innerHTML = '';

    const targets = ALL_FRANCHISES.filter(f => f.id !== currentFranchiseId);
    const listEl = document.getElementById('duplicateFranchiseList');
    if (targets.length === 0) {
        listEl.innerHTML = '<p class="text-muted small mb-0">No hay otra franquicia activa disponible.</p>';
        document.getElementById('confirmDuplicateBtn').disabled = true;
    } else {
        listEl.innerHTML = targets.map(f => `
            <label class="d-flex align-items-center gap-2 border rounded p-2" style="cursor:pointer">
                <input type="checkbox" class="form-check-input m-0 duplicate-franchise-check" value="${f.id}">
                <span>${esc(f.name)}</span>
            </label>
        `).join('');
        document.getElementById('confirmDuplicateBtn').disabled = false;
    }

    duplicateProductModal.show();
}

function submitDuplicate() {
    const franchiseIds = Array.from(document.querySelectorAll('.duplicate-franchise-check:checked')).map(cb => parseInt(cb.value, 10));
    if (franchiseIds.length === 0) {
        showToast('Selecciona al menos una franquicia', 'danger');
        return;
    }

    const btn = document.getElementById('confirmDuplicateBtn');
    btn.disabled = true;

    fetch(DUPLICATE_URL_TEMPLATE.replace('__ID__', duplicateProductId), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ franchise_ids: franchiseIds }),
    })
    .then(async r => {
        const data = await r.json();
        if (!r.ok) throw new Error(data.message || 'Error al duplicar');
        return data;
    })
    .then(data => {
        const icons = { created: '✅', skipped: '⚠️', error: '❌' };
        document.getElementById('duplicateResults').innerHTML = (data.results || []).map(r =>
            `<div class="small mb-1">${icons[r.status] || ''} <strong>${esc(r.franchise || '?')}</strong>: ${esc(r.message)}</div>`
        ).join('');
        if ((data.results || []).some(r => r.status === 'created')) {
            showToast('Producto duplicado. Actualizando lista…', 'success');
            setTimeout(() => window.location.reload(), 1400);
        }
    })
    .catch(err => showToast(err.message || 'Error al duplicar', 'danger'))
    .finally(() => { btn.disabled = false; });
}

function openEditModal(id) {
    currentProductId = id;
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-pen me-2"></i>Editar producto';
    hideFormErrors();

    fetch(`/admin/products/${id}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => {
        if (!r.ok) throw new Error('No se pudo cargar el producto');
        return r.json();
    })
    .then(data => {
        document.getElementById('sku').value = data.sku || '';
        document.getElementById('name').value = data.name || '';
        document.getElementById('description').value = data.description || '';
        document.getElementById('menu_item_id').value = data.menu_item_id || '';
        document.getElementById('price').value = data.price ?? '';
        document.getElementById('promo_price').value = data.promo_price ?? '';
        document.getElementById('benefits').value = data.benefits || '';
        document.getElementById('characteristics').value = data.characteristics || '';
        document.getElementById('stock').value = data.stock ?? 0;
        document.getElementById('min_quantity').value = data.min_quantity ?? 1;
        document.getElementById('max_quantity').value = data.max_quantity ?? 999;
        document.getElementById('franchise_id').value = data.franchise_id || '';
        document.getElementById('variations').value = data.variations || '';
        document.getElementById('extras').value = data.extras || '';
        document.getElementById('is_active').checked = !!data.is_active;
        document.getElementById('allow_quantity_selection').checked = data.allow_quantity_selection !== false;
        setProductImagePreview(data.image_url || '');
        productModal.show();
    })
    .catch(err => {
        showToast(err.message || 'Error al cargar el producto', 'danger');
    });
}

function submitProductForm(e) {
    e.preventDefault();
    hideFormErrors();

    const btn = document.getElementById('saveProductBtn');
    btn.disabled = true;

    const formData = new FormData();
    formData.append('sku', document.getElementById('sku').value.trim());
    formData.append('name', document.getElementById('name').value.trim());
    formData.append('menu_item_id', document.getElementById('menu_item_id').value);
    formData.append('price', document.getElementById('price').value);
    formData.append('promo_price', document.getElementById('promo_price').value);
    formData.append('description', document.getElementById('description').value);
    formData.append('benefits', document.getElementById('benefits').value);
    formData.append('characteristics', document.getElementById('characteristics').value);
    formData.append('variations', document.getElementById('variations').value);
    formData.append('extras', document.getElementById('extras').value);
    formData.append('stock', document.getElementById('stock').value);
    formData.append('min_quantity', document.getElementById('min_quantity').value);
    formData.append('max_quantity', document.getElementById('max_quantity').value);
    formData.append('franchise_id', document.getElementById('franchise_id').value);
    formData.append('is_active', document.getElementById('is_active').checked ? '1' : '0');
    formData.append('allow_quantity_selection', document.getElementById('allow_quantity_selection').checked ? '1' : '0');

    const imageFile = document.getElementById('product_image')?.files?.[0];
    if (imageFile) formData.append('image', imageFile);
    if (document.getElementById('remove_image')?.checked) formData.append('remove_image', '1');

    const isEdit = currentProductId !== null;
    const url = isEdit ? `/admin/products/${currentProductId}` : '{{ route("admin.products.store") }}';
    const method = isEdit ? 'POST' : 'POST';
    if (isEdit) formData.append('_method', 'PUT');

    fetch(url, {
        method,
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
    })
    .then(async r => {
        const data = await r.json();
        if (!r.ok) {
            if (data.errors) {
                showFormErrors(data.errors);
            }
            throw new Error(data.message || 'Error al guardar');
        }
        return data;
    })
    .then(data => {
        productModal.hide();
        showToast(data.message || 'Guardado correctamente', 'success');
        setTimeout(() => window.location.reload(), 800);
    })
    .catch(err => {
        if (!document.getElementById('formErrors').classList.contains('d-none')) return;
        showToast(err.message || 'Error al guardar el producto', 'danger');
    })
    .finally(() => {
        btn.disabled = false;
    });
}

function deleteProduct(id) {
    if (!confirm('¿Eliminar este producto? Esta acción no se puede deshacer.')) return;

    fetch(`/admin/products/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(r => r.json().then(data => ({ ok: r.ok, data })))
    .then(({ ok, data }) => {
        if (!ok) throw new Error(data.message || 'Error al eliminar');
        showToast(data.message || 'Producto eliminado', 'success');
        setTimeout(() => window.location.reload(), 800);
    })
    .catch(err => showToast(err.message || 'Error al eliminar', 'danger'));
}

function filterTable() {
    const search = (document.getElementById('search')?.value || '').toLowerCase();
    const category = document.getElementById('category-filter')?.value || '';
    const demo = document.getElementById('demo-filter')?.value || '';
    const status = document.getElementById('status-filter')?.value || '';

    document.querySelectorAll('.product-row').forEach(row => {
        const matchesSearch = !search || row.dataset.search.includes(search);
        const matchesCategory = !category || row.dataset.category === category;
        const rowDemo = row.dataset.demo || '';
        const matchesDemo = !demo || (demo === '__none__' ? rowDemo === '' : rowDemo === demo);
        const matchesStatus = !status || row.dataset.status === status;
        row.style.display = matchesSearch && matchesCategory && matchesDemo && matchesStatus ? '' : 'none';
    });

    syncSelectAllCheckbox();
    updateBulkBar();
}

function getVisibleRows() {
    return [...document.querySelectorAll('.product-row')].filter(row => row.style.display !== 'none');
}

function getSelectedIds() {
    return [...document.querySelectorAll('.product-select:checked')].map(cb => parseInt(cb.value, 10));
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
    const master = document.getElementById('select-all-products');
    if (!master) return;
    const visibleChecks = getVisibleRows()
        .map(row => row.querySelector('.product-select'))
        .filter(Boolean);
    const checkedVisible = visibleChecks.filter(cb => cb.checked).length;
    master.indeterminate = checkedVisible > 0 && checkedVisible < visibleChecks.length;
    master.checked = visibleChecks.length > 0 && checkedVisible === visibleChecks.length;
}

function toggleSelectAllVisible(e) {
    const checked = e.target.checked;
    getVisibleRows().forEach(row => {
        const cb = row.querySelector('.product-select');
        if (cb) cb.checked = checked;
    });
    updateBulkBar();
}

function clearSelection() {
    document.querySelectorAll('.product-select').forEach(cb => { cb.checked = false; });
    const master = document.getElementById('select-all-products');
    if (master) {
        master.checked = false;
        master.indeterminate = false;
    }
    updateBulkBar();
}

function bulkUpdateStatus(isActive) {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        showToast('Seleccione al menos un producto.', 'danger');
        return;
    }

    const action = isActive ? 'activar' : 'desactivar';
    if (!confirm(`¿${action.charAt(0).toUpperCase() + action.slice(1)} ${ids.length} producto(s)?`)) {
        return;
    }

    fetch('/admin/products/bulk-status', {
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
    const messages = Object.values(errors).flat();
    el.innerHTML = messages.map(m => `<div>${m}</div>`).join('');
    el.classList.remove('d-none');
}

function hideFormErrors() {
    const el = document.getElementById('formErrors');
    el.classList.add('d-none');
    el.innerHTML = '';
}

function showToast(message, type = 'success') {
    const toastEl = document.getElementById('productToast');
    const body = document.getElementById('productToastBody');
    toastEl.className = `toast align-items-center border-0 text-white bg-${type === 'success' ? 'success' : 'danger'}`;
    body.textContent = message;
    productToast.show();
}

function resetImportModal() {
    document.getElementById('importProductsForm')?.reset();
    document.getElementById('importResult')?.classList.add('d-none');
    document.getElementById('importResultSummary').textContent = '';
    document.getElementById('importResultErrors').innerHTML = '';
}

function submitImportForm(e) {
    e.preventDefault();

    const fileInput = document.getElementById('import_file');
    if (!fileInput?.files?.length) {
        showToast('Seleccione un archivo Excel.', 'danger');
        return;
    }

    const btn = document.getElementById('importProductsBtn');
    btn.disabled = true;

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('mode', document.getElementById('import_mode').value);

    fetch('{{ route("admin.products.import") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
    })
    .then(async r => {
        const data = await r.json();
        return { ok: r.ok, data };
    })
    .then(({ ok, data }) => {
        const result = data.result || {};
        const summaryEl = document.getElementById('importResultSummary');
        const errorsEl = document.getElementById('importResultErrors');
        document.getElementById('importResult').classList.remove('d-none');

        summaryEl.className = `alert mb-2 alert-${ok ? 'success' : 'warning'}`;
        summaryEl.textContent = data.message || 'Importación finalizada.';

        if (result.errors?.length) {
            errorsEl.innerHTML = '<strong>Detalle por fila:</strong><ul class="mb-0 mt-1 ps-3">' +
                result.errors.map(err => `<li>Fila ${err.row}${err.sku ? ' (' + err.sku + ')' : ''}: ${err.message}</li>`).join('') +
                '</ul>';
        } else {
            errorsEl.innerHTML = '';
        }

        if (ok && (result.created || result.updated)) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 1500);
        }
    })
    .catch(() => showToast('Error al procesar el archivo.', 'danger'))
    .finally(() => {
        btn.disabled = false;
    });
}
</script>
@endpush
