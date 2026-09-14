<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ ($storefrontSettings ?? null)?->primary_color ?? '#ff650b' }}">
    <title>Punto de venta{{ isset($headerTitle) ? " — {$headerTitle}" : '' }}</title>
    <style>
        :root{--brand:{{ ($storefrontSettings ?? null)?->primary_color ?? '#E85D04' }};--brand-dark:{{ ($storefrontSettings ?? null)?->secondary_color ?? '#7C2D12' }};--accent:{{ ($storefrontSettings ?? null)?->accent_color ?? '#FFD166' }}}
        *{box-sizing:border-box}body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#242424;background:#fff}
        .pos-gateway{min-height:100dvh;background:#fff}
        .pos-gateway.is-browsing{min-height:0}
        .pos-gateway.is-browsing>.pos-hero,.pos-gateway.is-browsing>.pos-preview{display:none}
        .pos-hero{background:#fff;color:#222;box-shadow:0 12px 26px rgba(0,0,0,.05)}
        .pos-nav{display:flex;min-height:124px;align-items:center;padding:20px 34px}
        .pos-brand{display:flex;align-items:center;gap:20px}
        .pos-logo{width:58px;height:58px;object-fit:contain}
        .pos-admin-link{display:inline-flex;min-height:44px;margin-left:auto;padding:0 18px;align-items:center;gap:9px;border:1px solid #dedede;border-radius:10px;background:#fff;color:#242424;text-decoration:none;font-size:.88rem;font-weight:800;transition:border-color .18s,background .18s,transform .18s}
        .pos-admin-link:hover{border-color:var(--accent);background:#fff9e8;transform:translateY(-1px)}
        .pos-admin-link-icon{font-size:1.05rem}
        .pos-tabs{display:flex;height:62px;padding:0 34px;align-items:flex-end;gap:44px}
        .pos-tab{position:relative;padding:0 0 17px;font-size:1rem}
        .pos-tab:after{content:'';position:absolute;left:0;right:0;bottom:0;height:4px;border-radius:4px;background:var(--accent)}
        .pos-preview{width:min(1260px,100%);margin:auto;padding:70px 40px 120px}
        .pos-category-grid{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:76px 64px}
        .pos-category{min-height:120px;border:0;background:#fff;text-align:center;font:inherit;font-size:1.3rem;cursor:pointer}
        .pos-category img,.pos-category-icon{display:flex;width:120px;height:82px;margin:0 auto 16px;align-items:center;justify-content:center;object-fit:contain;font-size:3rem}
        .pos-category:hover{transform:translateY(-3px)}
        #posApp{display:none}
        .pos-back{display:none;margin:16px 0 0 max(22px,calc((100% - 1460px)/2));border:0;background:none;font:inherit;font-size:.92rem;font-weight:800;color:#242424;cursor:pointer}
        @media(max-width:640px){
            .pos-nav{min-height:82px;padding:12px 20px}
            .pos-brand{gap:14px}
            .pos-logo{width:52px;height:52px}
            .pos-admin-link{min-width:44px;width:44px;padding:0;justify-content:center}
            .pos-admin-link-label{display:none}
            .pos-tabs{display:none}
            .pos-preview{padding:34px 24px 48px}
            .pos-category-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:48px 24px}
            .pos-category{min-height:120px;font-size:1.08rem}
            .pos-category img,.pos-category-icon{width:96px;height:76px;margin-bottom:12px}
            .pos-back{margin-left:20px}
        }
    </style>
</head>
<body>
<main class="pos-gateway" id="posGateway">
    <section class="pos-hero">
        <nav class="pos-nav">
            <div class="pos-brand">
                @if(!empty(($storefrontSettings ?? null)?->logoUrl()))
                    <img src="{{ $storefrontSettings->logoUrl() }}" class="pos-logo" alt="{{ $headerTitle }}">
                @else
                    <strong style="font-size:1.4rem">{{ $headerTitle }}</strong>
                @endif
            </div>
            <a href="{{ route('admin.dashboard') }}" class="pos-admin-link" aria-label="Ir al panel administrativo"><span class="pos-admin-link-icon" aria-hidden="true">⚙</span><span class="pos-admin-link-label">Panel administrativo</span></a>
        </nav>
        <div class="pos-tabs"><span class="pos-tab">Productos</span></div>
    </section>
    <section class="pos-preview">
        <div class="pos-category-grid" id="posCategoryPreview" aria-label="Categorías">
            <button class="pos-category" type="button" data-preview-category="">
                @if(filled($allCategory['image'] ?? null))
                    <img src="{{ $allCategory['image'] }}" alt="">
                @else
                    <span class="pos-category-icon">☰</span>
                @endif
                <span>{{ $allCategory['title'] ?? 'Todos' }}</span>
            </button>
            @forelse($categories as $category)
                <button class="pos-category" type="button" data-preview-category="{{ $category['id'] }}">
                    @if(filled($category['image'] ?? null))
                        <img src="{{ $category['image'] }}" alt="">
                    @else
                        <span class="pos-category-icon">{{ $category['icon'] ?? '🍔' }}</span>
                    @endif
                    <span>{{ $category['title'] }}</span>
                </button>
            @empty
                <p>No hay categorías disponibles.</p>
            @endforelse
        </div>
    </section>
</main>

<button type="button" class="pos-back" id="posBack">← Volver a categorías</button>

<div id="posApp">
    @include('bulk-order.partials.form-app', [
        'mode' => 'kiosk',
        'catalogUrl' => $catalogUrl,
        'submitUrl' => $submitUrl,
        'contactsCreateUrl' => $contactsCreateUrl,
        'branches' => $branches,
        'defaultBranchId' => $defaultBranchId,
        'headerTitle' => $headerTitle ?? null,
        'storefrontSettings' => $storefrontSettings ?? null,
        'adminUrl' => route('admin.dashboard'),
        'headerSubtitle' => 'Elige tus productos. Al final te damos tu número de pedido para pagar en caja.',
    ])
</div>

<script>
(function(){
    const gateway = document.getElementById('posGateway'),
        posApp = document.getElementById('posApp'),
        backBtn = document.getElementById('posBack');
    const openCategory = (categoryId, attempt = 0) => {
        const categorySelect = document.getElementById('bulkCategory');
        const option = categorySelect?.querySelector(`option[value="${categoryId}"]`);
        if (!option && attempt < 12) {
            setTimeout(() => openCategory(categoryId, attempt + 1), 80);
            return;
        }
        if (categorySelect) {
            categorySelect.value = categoryId;
            categorySelect.dispatchEvent(new Event('change'));
        }
        gateway.classList.add('is-browsing');
        posApp.style.display = 'block';
        window.scrollTo({ top: 0 });
    };
    document.querySelectorAll('[data-preview-category]').forEach(button => {
        button.addEventListener('click', () => openCategory(button.dataset.previewCategory));
    });
    const returnToCategories = () => {
        posApp.style.display = 'none';
        backBtn.style.display = 'none';
        gateway.classList.remove('is-browsing');
        window.scrollTo({ top: 0 });
    };
    backBtn.addEventListener('click', returnToCategories);
    document.getElementById('storefrontCatalogMenu')?.addEventListener('click', returnToCategories);
})();
</script>
</body>
</html>
