@php
    $showPrincipal = $canPerm('dashboard.menu') || $canPerm('clients.menu');
    $showOperations = $canPerm('orders.menu')
        || $canPerm('kitchen.menu')
        || $canPerm('delivery.menu')
        || $canPerm('orders_reports.menu');
    $showCatalog = $canPerm('products.menu')
        || $canPerm('menus.menu')
        || $canPerm('inventory.menu')
        || $canPerm('franchises.menu');
    $showWhatsapp = $canPerm('chats.menu')
        || $canPerm('marketing_flow.menu')
        || $canPerm('chatbot.menu')
        || $canPerm('companies.menu')
        || $canPerm('campaigns.menu')
        || $canPerm('whatsapp_reports.menu')
        || $canPerm('message_failures.menu');
    $showAdministration = $canPerm('branches.menu')
        || $canPerm('pricing_settings.menu')
        || $canPerm('users.menu')
        || $canPerm('roles.menu');
@endphp

@if($showPrincipal)
    <nav class="sidebar-nav sidebar-nav-main">
        <div class="sidebar-section sidebar-text">Principal</div>

        @perm('dashboard.menu')
            <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home"></i>
                <span class="sidebar-text">Inicio</span>
            </a>
        @endperm

        @perm('clients.menu')
            <a href="{{ route('admin.clients.index') }}" class="nav-link {{ request()->routeIs('admin.clients*') ? 'active' : '' }}">
                <i class="fas fa-users"></i>
                <span class="sidebar-text">Clientes</span>
            </a>
        @endperm
    </nav>
@endif

@if($showOperations)
    @php
        $ordersMenuOpen = request()->routeIs('admin.orders*')
            || request()->routeIs('admin.reports.orders')
            || request()->routeIs('admin.kitchen.*')
            || request()->routeIs('admin.delivery.*');
    @endphp
    <nav class="sidebar-nav sidebar-nav-main">
        <div class="sidebar-section sidebar-text">Ventas y operación</div>
        <div class="nav-group {{ $ordersMenuOpen ? 'is-open' : '' }}" data-nav-group="orders">
            <div class="nav-group-row">
                @perm('orders.menu')
                    <a href="{{ route('admin.orders') }}" class="nav-link {{ request()->routeIs('admin.orders*') ? 'active' : '' }}">
                        <i class="fas fa-receipt"></i>
                        <span class="sidebar-text">Pedidos</span>
                        @if(($delayedOrdersCount ?? 0) > 0)<span class="sidebar-nav-badge" title="Pedido(s) esperando confirmación de costo hace más de 15 minutos">{{ $delayedOrdersCount }}</span>@endif
                    </a>
                @else
                    <span class="nav-link nav-link-disabled flex-grow-1"><i class="fas fa-receipt"></i><span class="sidebar-text">Pedidos</span></span>
                @endperm
                <button type="button" class="nav-group-toggle" aria-label="Mostrar u ocultar opciones de pedidos" aria-expanded="{{ $ordersMenuOpen ? 'true' : 'false' }}"><i class="fas fa-chevron-down"></i></button>
            </div>
            <div class="nav-group-sub">
                @perm('kitchen.menu')
                    <a href="{{ route('admin.kitchen.index') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.kitchen.*') ? 'active' : '' }}"><i class="fas fa-utensils"></i><span class="sidebar-text">Comandas</span></a>
                @endperm
                @perm('delivery.menu')
                    <a href="{{ route('admin.delivery.index') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.delivery.*') ? 'active' : '' }}"><i class="fas fa-motorcycle"></i><span class="sidebar-text">Delivery</span></a>
                @endperm
                @perm('orders_reports.menu')
                    <a href="{{ route('admin.reports.orders') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.reports.orders') ? 'active' : '' }}"><i class="fas fa-chart-bar"></i><span class="sidebar-text">Reporte de pedidos</span></a>
                @endperm
            </div>
        </div>
    </nav>
@endif

@if($showCatalog)
    <nav class="sidebar-nav sidebar-nav-main">
        <div class="sidebar-section sidebar-text">Catálogo</div>
        @perm('products.menu')
            <a href="{{ route('admin.products.index') }}" class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}"><i class="fas fa-box-open"></i><span class="sidebar-text">Productos</span></a>
        @endperm
        @perm('menus.menu')
            <a href="{{ route('admin.menus.index') }}" class="nav-link {{ request()->routeIs('admin.menus.*') ? 'active' : '' }}"><i class="fas fa-layer-group"></i><span class="sidebar-text">Categorías</span></a>
        @endperm
        @perm('inventory.menu')
            <a href="{{ route('admin.reports.inventory') }}" class="nav-link {{ request()->routeIs('admin.reports.inventory') ? 'active' : '' }}"><i class="fas fa-warehouse"></i><span class="sidebar-text">Inventario</span></a>
        @endperm
        @perm('franchises.menu')
            <a href="{{ route('admin.franchises.index') }}" class="nav-link {{ request()->routeIs('admin.franchises.*') ? 'active' : '' }}"><i class="fas fa-tags"></i><span class="sidebar-text">Franquicias</span></a>
        @endperm
    </nav>
@endif

@if($showWhatsapp)
    @php
        $whatsappMenuOpen = request()->routeIs('admin.chat*')
            || request()->routeIs('admin.marketing-flow*')
            || request()->routeIs('admin.chatbot.config*')
            || request()->routeIs('admin.chatbot-keywords.*')
            || request()->routeIs('admin.empresas.*')
            || request()->routeIs('admin.marketing.*')
            || request()->routeIs('admin.reports.whatsapp')
            || request()->routeIs('admin.message-failures.*');
    @endphp
    <nav class="sidebar-nav sidebar-nav-main">
        <div class="sidebar-section sidebar-text">WhatsApp</div>
        <div class="nav-group {{ $whatsappMenuOpen ? 'is-open' : '' }}" data-nav-group="whatsapp">
            <div class="nav-group-row">
                @perm('chats.menu')
                    <a href="{{ route('admin.chats') }}" class="nav-link {{ request()->routeIs('admin.chat*') ? 'active' : '' }}">
                        <i class="fab fa-whatsapp"></i><span class="sidebar-text">Conversaciones</span><span id="sidebar-chats-agent-count" class="sidebar-nav-badge hidden"></span>
                    </a>
                @else
                    <span class="nav-link nav-link-disabled flex-grow-1"><i class="fab fa-whatsapp"></i><span class="sidebar-text">WhatsApp</span></span>
                @endperm
                <button type="button" class="nav-group-toggle" aria-label="Mostrar u ocultar opciones de WhatsApp" aria-expanded="{{ $whatsappMenuOpen ? 'true' : 'false' }}"><i class="fas fa-chevron-down"></i></button>
            </div>
            <div class="nav-group-sub">
                @perm('marketing_flow.menu')
                    <a href="{{ route('admin.marketing-flow.edit') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.marketing-flow*') ? 'active' : '' }}"><i class="fas fa-project-diagram"></i><span class="sidebar-text">Flujo del bot</span></a>
                @endperm
                @perm('chatbot.menu')
                    <a href="{{ route('admin.chatbot.config') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.chatbot.config*') ? 'active' : '' }}"><i class="fas fa-sliders-h"></i><span class="sidebar-text">Configuración del bot</span></a>
                    <a href="{{ route('admin.chatbot-keywords.index') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.chatbot-keywords.*') ? 'active' : '' }}"><i class="fas fa-comment-dots"></i><span class="sidebar-text">Palabras clave</span></a>
                @endperm
                @perm('companies.menu')
                    <a href="{{ route('admin.empresas.index') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.empresas.*') ? 'active' : '' }}"><i class="fas fa-building"></i><span class="sidebar-text">Empresas y Meta</span></a>
                @endperm
                @perm('campaigns.menu')
                    <a href="{{ route('admin.marketing.index') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.marketing.*') ? 'active' : '' }}"><i class="fas fa-bullhorn"></i><span class="sidebar-text">Campañas</span></a>
                @endperm
                @perm('whatsapp_reports.menu')
                    <a href="{{ route('admin.reports.whatsapp') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.reports.whatsapp') ? 'active' : '' }}"><i class="fas fa-chart-line"></i><span class="sidebar-text">Reporte de WhatsApp</span></a>
                @endperm
                @perm('message_failures.menu')
                    <a href="{{ route('admin.message-failures.index') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.message-failures.*') ? 'active' : '' }}">
                        <i class="fas fa-triangle-exclamation"></i><span class="sidebar-text">Fallos de envío</span>
                        @if(($unresolvedFailuresCount ?? 0) > 0)<span class="sidebar-nav-badge">{{ $unresolvedFailuresCount }}</span>@endif
                    </a>
                @endperm
            </div>
        </div>
    </nav>
@endif

@if($showAdministration)
    <nav class="sidebar-nav sidebar-nav-config">
        <div class="sidebar-section sidebar-text">Administración</div>
        @perm('branches.menu')
            <a href="{{ route('admin.branches.index') }}" class="nav-link {{ request()->routeIs('admin.branches.*') ? 'active' : '' }}"><i class="fas fa-store"></i><span class="sidebar-text">Sucursales</span></a>
        @endperm
        @perm('faqs.menu')
            <a href="{{ route('admin.faqs.index') }}" class="nav-link {{ request()->routeIs('admin.faqs.*') ? 'active' : '' }}"><i class="fas fa-circle-question"></i><span class="sidebar-text">Preguntas frecuentes</span></a>
        @endperm
        @perm('pricing_settings.menu')
            <a href="{{ route('admin.pricing-settings.edit') }}" class="nav-link {{ request()->routeIs('admin.pricing-settings*') ? 'active' : '' }}"><i class="fas fa-cog"></i><span class="sidebar-text">Parámetros</span></a>
        @endperm
        @perm('users.menu')
            <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}"><i class="fas fa-users-cog"></i><span class="sidebar-text">Usuarios</span></a>
        @endperm
        @perm('roles.menu')
            <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->routeIs('admin.roles*') ? 'active' : '' }}"><i class="fas fa-key"></i><span class="sidebar-text">Roles y permisos</span></a>
        @endperm
    </nav>
@endif
