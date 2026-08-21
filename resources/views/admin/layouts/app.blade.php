<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - DPIKEOS</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('favicon.svg') }}">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Custom CSS -->
    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 72px;
            --sidebar-bg: #111b21;
            --sidebar-config-bg: rgba(0, 0, 0, 0.22);
            --sidebar-footer-bg: #0b141a;
            --sidebar-hover: rgba(255, 255, 255, 0.08);
            --sidebar-active: rgba(37, 211, 102, 0.18);
            --sidebar-active-border: #25d366;
            --wa-green: #25d366;
            --wa-teal: #128c7e;
        }

        * {
            box-sizing: border-box;
        }

        body:not(.dashboard-page) {
            background-color: #f8f9fa;
        }

        body {
            font-size: 0.9rem;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            background: linear-gradient(180deg, var(--sidebar-bg) 0%, #0b141a 100%);
            height: 100vh;
            min-height: 100vh;
            color: white;
            padding: 0;
            box-shadow: 2px 0 12px rgba(0, 0, 0, 0.15);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            width: var(--sidebar-width);
            transition: all 0.3s ease;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .sidebar-inner-scroll {
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-inner-scroll::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar-inner-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 4px;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar.collapsed .sidebar-text,
        .sidebar.collapsed .sidebar-section {
            opacity: 0;
            visibility: hidden;
            width: 0;
            overflow: hidden;
        }

        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }

        .sidebar.collapsed .sidebar-user-info {
            justify-content: center;
            padding: 0.5rem;
        }

        .sidebar.collapsed .sidebar-user-details {
            display: none;
        }

        .sidebar-header {
            padding: 1rem 1rem 0.85rem;
            margin-bottom: 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 62px;
            flex-shrink: 0;
            background: rgba(0, 0, 0, 0.15);
        }

        .sidebar-header-content {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 0;
        }

        .sidebar-header i {
            font-size: 1.5rem;
            color: #25d366;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .sidebar-header span {
            font-size: 1.1rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-toggle-btn {
            background: transparent;
            border: none;
            color: rgba(255,255,255,.8);
            font-size: 1.1rem;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .sidebar-toggle-btn:hover {
            background: var(--sidebar-hover);
            color: white;
        }

        .sidebar-header span {
            font-size: 1rem;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #e9edef;
        }

        .sidebar-header .brand-accent {
            color: var(--wa-green);
        }

        .sidebar-nav {
            padding: 0.65rem 0.5rem;
        }

        .sidebar-nav-main {
            flex: 1 1 auto;
            padding-top: 0.75rem;
        }

        .sidebar-nav-config {
            flex-shrink: 0;
            margin-top: auto;
            padding-top: 0.5rem;
            padding-bottom: 0.75rem;
            background: var(--sidebar-config-bg);
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .sidebar .nav-link {
            color: #aebac1;
            padding: 0.7rem 0.85rem;
            margin: 0.12rem 0.35rem;
            border-radius: 0.55rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            text-decoration: none;
            white-space: nowrap;
            border-left: 3px solid transparent;
        }

        .sidebar .nav-link:hover {
            color: #fff;
            background-color: var(--sidebar-hover);
        }

        .sidebar .nav-link.active {
            background-color: var(--sidebar-active);
            color: #fff;
            border-left-color: var(--sidebar-active-border);
            font-weight: 500;
        }

        .sidebar .nav-link-disabled {
            opacity: 0.55;
            cursor: not-allowed;
            pointer-events: none;
        }

        .sidebar .nav-link-sub {
            padding-left: 1.65rem;
            font-size: 0.8125rem;
        }

        .sidebar .nav-link-sub i {
            width: 20px;
            font-size: 0.875rem;
        }

        .nav-group {
            margin: 0.12rem 0.35rem;
        }

        .nav-group-row {
            display: flex;
            align-items: stretch;
        }

        .nav-group-row > .nav-link {
            flex: 1;
            min-width: 0;
            margin: 0;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .nav-group-toggle {
            flex-shrink: 0;
            width: 2rem;
            border: none;
            background: transparent;
            color: #667781;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            border-top-right-radius: 0.55rem;
            border-bottom-right-radius: 0.55rem;
            border-left: 1px solid rgba(255, 255, 255, 0.06);
            transition: color 0.2s ease, background-color 0.2s ease;
        }

        .nav-group-toggle:hover {
            color: #fff;
            background-color: var(--sidebar-hover);
        }

        .nav-group-toggle i {
            font-size: 0.7rem;
            transition: transform 0.2s ease;
            transform: rotate(-90deg);
        }

        .nav-group.is-open .nav-group-toggle i {
            transform: rotate(0deg);
        }

        .nav-group-sub {
            display: none;
        }

        .nav-group.is-open .nav-group-sub {
            display: block;
        }

        .nav-group-sub .nav-link {
            margin-top: 0.08rem;
        }

        .sidebar.collapsed .nav-group-toggle,
        .sidebar.collapsed .nav-group-sub {
            display: none;
        }

        .sidebar.collapsed .nav-group-row > .nav-link {
            border-radius: 0.55rem;
        }

        .sidebar .nav-link i {
            width: 24px;
            font-size: 1rem;
            text-align: center;
            flex-shrink: 0;
            margin-right: 0.75rem;
        }

        .sidebar-text {
            transition: opacity 0.2s ease;
            overflow: hidden;
        }

        .sidebar-section {
            font-size: 0.68rem;
            color: #667781;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 0.35rem 0 0.5rem;
            padding: 0.35rem 0.85rem 0.15rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        /* Sidebar Footer — perfil pegado al fondo */
        .sidebar-footer {
            flex-shrink: 0;
            padding: 0.85rem 0.75rem 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            background: var(--sidebar-footer-bg);
        }

        .sidebar-user-info {
            display: flex;
            align-items: center;
            padding: 0.65rem 0.7rem;
            margin-bottom: 0.5rem;
            background-color: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 0.65rem;
            color: #e9edef;
            font-size: 0.875rem;
            gap: 0.65rem;
        }

        .sidebar-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .sidebar-user-details {
            flex: 1;
            min-width: 0;
        }

        .sidebar-user-name {
            font-weight: 600;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-role {
            font-size: 0.72rem;
            color: #8696a0;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-profile-link {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.6rem 0.85rem;
            margin: 0 0.35rem 0.5rem;
            border-radius: 0.55rem;
            color: #aebac1;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .sidebar-profile-link:hover {
            color: #fff;
            background: var(--sidebar-hover);
        }

        .sidebar-profile-link i {
            width: 24px;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-profile-link.active {
            color: #fff;
            background: var(--sidebar-active);
        }

        .sidebar-logout-form {
            margin: 0;
            padding: 0;
        }

        .sidebar-logout-btn {
            width: 100%;
            color: #ff8a8a !important;
            background-color: rgba(220, 53, 69, 0.12) !important;
            border: 1px solid rgba(220, 53, 69, 0.35) !important;
            padding: 0.65rem 0.85rem;
            margin: 0;
            border-radius: 0.55rem;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .sidebar-logout-btn:hover {
            background-color: rgba(220, 53, 69, 0.22) !important;
            border-color: rgba(220, 53, 69, 0.55) !important;
            color: #ffb4b4 !important;
        }

        .sidebar-logout-btn i {
            color: inherit;
        }

        .sidebar.collapsed .sidebar-footer {
            padding: 0.5rem;
        }

        .sidebar.collapsed .sidebar-user-info span,
        .sidebar.collapsed .sidebar-logout-btn .sidebar-text,
        .sidebar.collapsed .sidebar-profile-link .sidebar-text {
            opacity: 0;
            visibility: hidden;
            width: 0;
            overflow: hidden;
        }

        .sidebar.collapsed .sidebar-profile-link {
            justify-content: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        /* Main Content */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        .main-wrapper.sidebar-collapsed {
            margin-left: var(--sidebar-collapsed-width);
        }

        .main-content {
            padding: 1rem;
        }

        .mobile-menu-btn {
            display: none;
            background: white;
            border: 1px solid #dee2e6;
            font-size: 1.25rem;
            color: #343a40;
            cursor: pointer;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }

        .mobile-menu-btn:hover {
            background: #f8f9fa;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        .alert {
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .content-header {
            background: white;
            padding: 1rem;
            margin: -1rem -1rem 1rem -1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .content-header h1 {
            font-size: 1.5rem;
            margin: 0;
            color: #343a40;
        }

        /* Top Navbar */
        .top-navbar {
            background: white;
            border-bottom: 1px solid #dee2e6;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
            margin-bottom: 1rem;
        }

        .top-navbar .navbar-brand {
            font-size: 1.1rem;
            font-weight: 600;
            color: #343a40;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .top-navbar .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .top-navbar .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #6c757d;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .top-navbar .user-info:hover {
            background-color: #f8f9fa;
        }

        .top-navbar .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .top-navbar .user-details {
            display: flex;
            flex-direction: column;
        }

        .top-navbar .user-name {
            font-weight: 600;
            color: #343a40;
            font-size: 0.95rem;
        }

        .top-navbar .user-role {
            font-size: 0.75rem;
            color: #6c757d;
        }

        .top-navbar .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .top-navbar .logout-btn:hover {
            background: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
        }

        .top-navbar .logout-btn i {
            font-size: 0.85rem;
        }

        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }

        .user-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .user-dropdown.show .user-dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #343a40;
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid #f1f3f5;
        }

        .user-dropdown-item:last-child {
            border-bottom: none;
        }

        .user-dropdown-item:hover {
            background-color: #f8f9fa;
            color: #25d366;
        }

        .user-dropdown-item i {
            width: 20px;
            text-align: center;
        }

        .user-dropdown-item.logout-item {
            color: #dc3545;
        }

        .user-dropdown-item.logout-item:hover {
            background-color: #fff5f5;
            color: #c82333;
        }

        /* Mobile Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        /* Responsive */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width) !important;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar.collapsed {
                transform: translateX(-100%);
            }

            .main-wrapper {
                margin-left: 0 !important;
            }

            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 10px;
                left: 10px;
                z-index: 1001;
            }

            .top-navbar {
                padding: 0.5rem 1rem;
                flex-wrap: wrap;
            }

            .top-navbar {
                padding: 0.5rem 1rem;
            }

            .top-navbar .user-details {
                display: none;
            }

            .top-navbar .logout-btn span {
                display: none;
            }

            .top-navbar .logout-btn {
                padding: 0.5rem;
            }

            .main-content {
                padding: 0.75rem;
            }

            .content-header {
                margin: -0.75rem -0.75rem 1rem -0.75rem;
                padding: 0.75rem;
            }

            .content-header h1 {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 767.98px) {
            .main-content {
                padding: 0.5rem;
            }

            .content-header {
                margin: -0.5rem -0.5rem 1rem -0.5rem;
                padding: 0.5rem;
            }

            .content-header h1 {
                font-size: 1.1rem;
            }
        }

        /* Table responsive */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Cards responsive */
        @media (max-width: 767.98px) {
            .card {
                margin-bottom: 1rem;
            }
        }

        /* Alertas globales de asesor */
        .wa-agent-alerts-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-right: 12px;
            position: relative;
        }

        .wa-notify-toggle {
            position: relative;
            background: #f1f3f5;
            border: none;
            color: #495057;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background .15s, color .15s;
        }

        .wa-notify-toggle:hover { background: #e9ecef; color: #212529; }
        .wa-notify-toggle.is-active { color: #128c7e; background: rgba(18, 140, 126, 0.12); }
        .wa-notify-toggle.is-blocked { color: #dc3545; }

        .global-agent-requests-count {
            position: absolute;
            top: -2px;
            right: -2px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 9px;
            background: #f15c6d;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
        }

        .global-agent-requests-count.hidden { display: none; }

        /* Variante para el timbre de pedidos nuevos (mismo estilo, otro color) */
        .wa-notify-toggle--orders.is-active { color: #fd7e14; background: rgba(253, 126, 20, 0.12); }
        .global-orders-count { background: #fd7e14; }
        .wa-order-toast-icon { background: linear-gradient(135deg, #fd7e14, #e8590c) !important; }

        /* Variante para el timbre de fallos de envío (mismo estilo, rojo de alerta) */
        .wa-notify-toggle--failures.is-active { color: #dc3545; background: rgba(220, 53, 69, 0.12); }
        .global-failures-count { background: #dc3545; }
        .wa-failure-toast-icon { background: linear-gradient(135deg, #dc3545, #a71d2a) !important; }

        .wa-agent-notifications-panel {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: min(360px, calc(100vw - 24px));
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.06);
            z-index: 10060;
            overflow: hidden;
        }

        .wa-agent-notifications-panel.hidden { display: none; }

        .wa-agent-notifications-panel .panel-header {
            padding: 12px 16px;
            font-weight: 700;
            font-size: 14px;
            color: #111b21;
            border-bottom: 1px solid #eef0f2;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .wa-agent-notifications-panel .panel-header span {
            font-size: 11px;
            font-weight: 600;
            color: #f15c6d;
            background: #fde8eb;
            padding: 2px 8px;
            border-radius: 999px;
        }

        .wa-agent-notifications-list {
            max-height: 320px;
            overflow-y: auto;
        }

        .wa-agent-notification-item {
            display: flex;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #f1f3f5;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            transition: background .12s;
        }

        .wa-agent-notification-item:hover { background: #f8f9fa; }
        .wa-agent-notification-item:last-child { border-bottom: none; }

        .wa-agent-notification-item .ni-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f15c6d, #e74c3c);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .wa-agent-notification-item .ni-title {
            font-size: 13px;
            font-weight: 700;
            color: #111b21;
            margin: 0 0 2px;
        }

        .wa-agent-notification-item .ni-text {
            font-size: 12px;
            color: #667781;
            margin: 0;
            line-height: 1.35;
        }

        .wa-agent-notification-item .ni-time {
            font-size: 11px;
            color: #8696a0;
            margin-top: 3px;
        }

        .wa-agent-notifications-empty {
            padding: 28px 16px;
            text-align: center;
            color: #8696a0;
            font-size: 13px;
        }

        .wa-agent-notifications-panel .panel-footer {
            padding: 10px 12px;
            border-top: 1px solid #eef0f2;
            background: #fafbfc;
        }

        .wa-agent-notifications-panel .panel-footer button {
            width: 100%;
            border: 1px solid #dee2e6;
            background: #fff;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            color: #495057;
            cursor: pointer;
        }

        .wa-agent-notifications-panel .panel-footer button:hover {
            background: #f1f3f5;
        }

        .sidebar .nav-link {
            position: relative;
        }

        .sidebar-nav-badge {
            margin-left: auto;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 10px;
            background: #f15c6d;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .sidebar-nav-badge.hidden { display: none; }

        .sidebar.collapsed .sidebar-nav-badge {
            position: absolute;
            top: 6px;
            right: 8px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            font-size: 9px;
        }

        .wa-agent-toast-stack {
            position: fixed;
            top: 72px;
            right: 20px;
            z-index: 10050;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
            max-width: min(380px, calc(100vw - 32px));
        }

        .wa-agent-toast {
            pointer-events: auto;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: #fff;
            border-radius: 14px;
            padding: 14px 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(241, 92, 109, 0.25);
            cursor: pointer;
            animation: waToastIn .35s cubic-bezier(.21, 1.02, .73, 1);
            border-left: 4px solid #f15c6d;
        }

        .wa-agent-toast-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f15c6d, #e74c3c);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
            animation: agentPulse 1.2s ease-in-out infinite;
        }

        .wa-agent-toast-title { font-size: 14px; font-weight: 700; color: #111b21; margin: 0 0 2px; }
        .wa-agent-toast-text { font-size: 13px; color: #667781; margin: 0; line-height: 1.35; }
        .wa-agent-toast-time { font-size: 11px; color: #8696a0; margin-top: 4px; }

        @keyframes waToastIn {
            from { opacity: 0; transform: translateX(28px) scale(.94); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }

        @keyframes waToastOut {
            to { opacity: 0; transform: translateX(28px) scale(.94); }
        }

        @keyframes agentPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.06); }
        }
    </style>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    @yield('header')
    @stack('styles')
</head>
<body class="@if(request()->routeIs('admin.chat', 'admin.chats')) chat-page @endif @if(request()->routeIs('admin.marketing-flow*')) flow-builder-page @endif">
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-header-content">
                <i class="fab fa-whatsapp"></i>
                <span class="sidebar-text"><span class="brand-accent">WhatsApp</span> Admin</span>
            </div>
            <button class="sidebar-toggle-btn d-none d-lg-block" id="sidebarToggle" title="Minimizar/Maximizar">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>

        <div class="sidebar-inner-scroll">
            <nav class="sidebar-nav sidebar-nav-main">
                <div class="sidebar-section sidebar-text">Principal</div>
                @perm('dashboard.menu')
                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-home"></i>
                    <span class="sidebar-text">Inicio</span>
                </a>
                @endperm
                @perm('orders.menu')
                @php
                    $ordersMenuOpen = request()->routeIs('admin.orders*') || request()->routeIs('admin.reports.orders') || request()->routeIs('admin.kitchen.*') || request()->routeIs('admin.delivery.*');
                @endphp
                @if($platformFeatureAccess['orders_blocked'] ?? false)
                    <div class="nav-group {{ $ordersMenuOpen ? 'is-open' : '' }}" data-nav-group="orders">
                        <div class="nav-group-row">
                            <span class="nav-link nav-link-disabled flex-grow-1" title="Módulo de pedidos suspendido">
                                <i class="fas fa-shopping-cart"></i>
                                <span class="sidebar-text">Pedidos</span>
                                <span class="sidebar-nav-badge" style="background:#fecaca;color:#991b1b;">Off</span>
                            </span>
                            <button type="button" class="nav-group-toggle" aria-label="Mostrar u ocultar submenú de pedidos" aria-expanded="{{ $ordersMenuOpen ? 'true' : 'false' }}">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        <div class="nav-group-sub">
                            <span class="nav-link nav-link-disabled nav-link-sub" title="Módulo de pedidos suspendido">
                                <i class="fas fa-chart-bar"></i>
                                <span class="sidebar-text">Reportes pedidos</span>
                            </span>
                        </div>
                    </div>
                @else
                    <div class="nav-group {{ $ordersMenuOpen ? 'is-open' : '' }}" data-nav-group="orders">
                        <div class="nav-group-row">
                            <a href="{{ route('admin.orders') }}" class="nav-link {{ request()->routeIs('admin.orders') || request()->routeIs('admin.orders.details') || request()->routeIs('admin.orders.bulk.*') ? 'active' : '' }}">
                                <i class="fas fa-shopping-cart"></i>
                                <span class="sidebar-text">Pedidos</span>
                            </a>
                            <button type="button" class="nav-group-toggle" aria-label="Mostrar u ocultar submenú de pedidos" aria-expanded="{{ $ordersMenuOpen ? 'true' : 'false' }}">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        <div class="nav-group-sub">
                            @perm('orders.view')
                            <a href="{{ route('admin.kitchen.index') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.kitchen.index') ? 'active' : '' }}">
                                <i class="fas fa-utensils"></i>
                                <span class="sidebar-text">Comandas</span>
                            </a>
                            @endperm
                            @perm('orders.view')
                            <a href="{{ route('admin.reports.orders') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.reports.orders') ? 'active' : '' }}">
                                <i class="fas fa-chart-bar"></i>
                                <span class="sidebar-text">Reportes pedidos</span>
                            </a>
                            @endperm
                            @perm('orders.view')
                            <a href="{{ route('admin.delivery.index') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.delivery.index') ? 'active' : '' }}">
                                <i class="fas fa-motorcycle"></i>
                                <span class="sidebar-text">Delivery</span>
                            </a>
                            @endperm
                        </div>
                    </div>
                @endif
                @endperm
                @perm('clients.menu')
                <a href="{{ route('admin.clients.index') }}" class="nav-link {{ request()->routeIs('admin.clients*') ? 'active' : '' }}">
                    <i class="fas fa-users"></i>
                    <span class="sidebar-text">Clientes</span>
                </a>
                @endperm

            </nav>

            <nav class="sidebar-nav sidebar-nav-main">
                <div class="sidebar-section sidebar-text">WhatsApp</div>
                @php
                    $whatsappMenuOpen = request()->routeIs('admin.chat*')
                        || request()->routeIs('admin.reports.whatsapp')
                        || request()->routeIs('admin.wallet*');
                @endphp
                <div class="nav-group {{ $whatsappMenuOpen ? 'is-open' : '' }}" data-nav-group="whatsapp">
                    <div class="nav-group-row">
                        @perm('chats.menu')
                        @if($platformFeatureAccess['chat_blocked'] ?? false)
                            <span class="nav-link nav-link-disabled flex-grow-1" title="Interfaz de chat suspendida">
                                <i class="fab fa-whatsapp"></i>
                                <span class="sidebar-text">WhatsApp</span>
                                <span class="sidebar-nav-badge" style="background:#fecaca;color:#991b1b;">Off</span>
                            </span>
                        @else
                            <a href="{{ route('admin.chats') }}" class="nav-link {{ request()->routeIs('admin.chat*') ? 'active' : '' }}">
                                <i class="fab fa-whatsapp"></i>
                                <span class="sidebar-text">WhatsApp</span>
                                <span id="sidebar-chats-agent-count" class="sidebar-nav-badge hidden"></span>
                            </a>
                        @endif
                        @else
                            <span class="nav-link nav-link-disabled flex-grow-1">
                                <i class="fab fa-whatsapp"></i>
                                <span class="sidebar-text">WhatsApp</span>
                            </span>
                        @endperm
                        <button type="button" class="nav-group-toggle" aria-label="Mostrar u ocultar submenú de WhatsApp" aria-expanded="{{ $whatsappMenuOpen ? 'true' : 'false' }}">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="nav-group-sub">
                        @perm('dashboard.menu')
                        <a href="{{ route('admin.reports.whatsapp') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.reports.whatsapp') ? 'active' : '' }}">
                            <i class="fas fa-chart-line"></i>
                            <span class="sidebar-text">Reportes</span>
                        </a>
                        @endperm
                        @perm('wallet.menu')
                        <a href="{{ route('admin.wallet.index') }}" class="nav-link nav-link-sub {{ request()->routeIs('admin.wallet*') ? 'active' : '' }}">
                            <i class="fas fa-wallet"></i>
                            <span class="sidebar-text">Billetera</span>
                        </a>
                        @endperm
                    </div>
                </div>

            </nav>

            <nav class="sidebar-nav sidebar-nav-config">
                <div class="sidebar-section sidebar-text">Bot y ventas</div>

                @perm('menus.menu')
                <a href="{{ route('admin.menus.index') }}" class="nav-link {{ request()->routeIs('admin.menus.*') ? 'active' : '' }}">
                    <i class="fas fa-folder-open"></i>
                    <span class="sidebar-text">Categorías</span>
                </a>
                @endperm
                @perm('products.menu')
                <a href="{{ route('admin.products.index') }}" class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                    <i class="fas fa-box-open"></i>
                    <span class="sidebar-text">Productos</span>
                </a>
                <a href="{{ route('admin.reports.inventory') }}" class="nav-link {{ request()->routeIs('admin.reports.inventory') ? 'active' : '' }}">
                    <i class="fas fa-warehouse"></i>
                    <span class="sidebar-text">Inventario</span>
                </a>
                @endperm
                @perm('marketing_flow.menu')
                <a href="{{ route('admin.marketing-flow.edit') }}" class="nav-link {{ request()->routeIs('admin.marketing-flow*') ? 'active' : '' }}">
                    <i class="fas fa-project-diagram"></i>
                    <span class="sidebar-text">Flujo del bot</span>
                </a>
                @endperm
                @perm('campaigns.menu')
                <a href="{{ route('admin.marketing.index') }}" class="nav-link {{ request()->routeIs('admin.marketing.*') ? 'active' : '' }}">
                    <i class="fas fa-bullhorn"></i>
                    <span class="sidebar-text">Campañas masivas</span>
                </a>
                @endperm
                @perm('message_failures.menu')
                <a href="{{ route('admin.message-failures.index') }}" class="nav-link {{ request()->routeIs('admin.message-failures.*') ? 'active' : '' }}">
                    <i class="fas fa-triangle-exclamation"></i>
                    <span class="sidebar-text">Fallos de envío</span>
                    @if(($unresolvedFailuresCount ?? 0) > 0)
                        <span class="sidebar-nav-badge">{{ $unresolvedFailuresCount }}</span>
                    @endif
                </a>
                @endperm


            </nav>

            @if($canPerm('pricing_settings.menu') || $canPerm('roles.menu') || $canPerm('users.menu'))
            <nav class="sidebar-nav sidebar-nav-config">
                <div class="sidebar-section sidebar-text">Plataforma</div>

                @perm('users.menu')
                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                    <i class="fas fa-users-cog"></i>
                    <span class="sidebar-text">Usuarios</span>
                </a>
                @endperm
                @perm('roles.menu')
                <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->routeIs('admin.roles*') ? 'active' : '' }}">
                    <i class="fas fa-key"></i>
                    <span class="sidebar-text">Roles y permisos</span>
                </a>
                @endperm
            </nav>
            @endif
        </div>

        <div class="sidebar-footer">
            @perm('pricing_settings.menu')
            <a href="{{ route('admin.franchises.index') }}" class="nav-link {{ request()->routeIs('admin.franchises.*') ? 'active' : '' }}">
                <i class="fas fa-building"></i>
                <span class="sidebar-text">Franquicias</span>
            </a>
            <a href="{{ route('admin.branches.index') }}" class="nav-link {{ request()->routeIs('admin.branches.*') ? 'active' : '' }}">
                <i class="fas fa-store"></i>
                <span class="sidebar-text">Sucursales</span>
            </a>
            <a href="{{ route('admin.pricing-settings.edit') }}" class="nav-link {{ request()->routeIs('admin.pricing-settings*') ? 'active' : '' }}">
                <i class="fas fa-sliders-h"></i>
                <span class="sidebar-text">Parámetros plataforma</span>
            </a>
            @endperm
            @perm('chatbot.menu')
            <a href="{{ route('admin.chatbot.config') }}" class="nav-link {{ request()->routeIs('admin.chatbot.config*') ? 'active' : '' }}">
                <i class="fas fa-sliders-h"></i>
                <span class="sidebar-text">Configuración del bot</span>
            </a>
            @endperm
            {{-- <a href="{{ route('admin.profile.show') }}" class="sidebar-profile-link {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}">
                <i class="fas fa-user-circle"></i>
                <span class="sidebar-text">Mi perfil</span>
            </a>
            <div class="sidebar-user-info">
                <div class="sidebar-user-avatar">
                    {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                </div>
                <div class="sidebar-user-details">
                    <span class="sidebar-text sidebar-user-name">{{ Auth::user()->name ?? 'Administrador' }}</span>
                    <span class="sidebar-text sidebar-user-role">{{ Auth::user()->roleLabel() }}</span>
                </div>
            </div> --}}
<br>
            <form action="{{ route('logout') }}" method="POST" class="sidebar-logout-form">
                @csrf
                <button type="submit" class="sidebar-logout-btn" onclick="return confirm('¿Estás seguro de que deseas cerrar sesión?');">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="sidebar-text">Cerrar sesión</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-wrapper" id="mainWrapper">
        @perm('chats.view')
        <div id="wa-agent-toast-stack" class="wa-agent-toast-stack" aria-live="assertive"></div>
        @endperm
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <div class="navbar-brand">
                <i class="fab fa-whatsapp text-success"></i>
                <span>Panel Administrativo</span>
            </div>
            <div class="user-menu">
                @perm('message_failures.view')
                <div class="wa-agent-alerts-nav wa-failure-alerts-nav">
                    <button type="button" id="wa-enable-failure-notifications-btn" class="wa-notify-toggle wa-notify-toggle--failures" title="Fallos de envío" aria-expanded="false" aria-controls="wa-failure-notifications-panel">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span id="global-failures-count" class="global-agent-requests-count global-failures-count hidden"></span>
                    </button>
                    <div id="wa-failure-notifications-panel" class="wa-agent-notifications-panel hidden" role="dialog" aria-label="Fallos de envío">
                        <div class="panel-header">
                            Fallos de envío
                            <span id="wa-failure-notifications-panel-count"></span>
                        </div>
                        <div id="wa-failure-notifications-list" class="wa-agent-notifications-list">
                            <div class="wa-agent-notifications-empty">No hay fallos de envío pendientes 🎉</div>
                        </div>
                        <div class="panel-footer">
                            <a href="{{ route('admin.message-failures.index') }}" class="btn btn-sm btn-outline-secondary d-block mb-2">Ver todos</a>
                            <button type="button" id="wa-request-failure-browser-notify">Activar alertas del navegador y sonido</button>
                        </div>
                    </div>
                </div>
                @endperm
                @perm('orders.view')
                <div class="wa-agent-alerts-nav wa-order-alerts-nav">
                    <button type="button" id="wa-enable-order-notifications-btn" class="wa-notify-toggle wa-notify-toggle--orders" title="Pedidos nuevos" aria-expanded="false" aria-controls="wa-order-notifications-panel">
                        <i class="fas fa-receipt"></i>
                        <span id="global-orders-count" class="global-agent-requests-count global-orders-count hidden"></span>
                    </button>
                    <div id="wa-order-notifications-panel" class="wa-agent-notifications-panel hidden" role="dialog" aria-label="Pedidos nuevos">
                        <div class="panel-header">
                            Pedidos nuevos
                            <span id="wa-order-notifications-panel-count"></span>
                        </div>
                        <div id="wa-order-notifications-list" class="wa-agent-notifications-list">
                            <div class="wa-agent-notifications-empty">No hay pedidos nuevos sin abrir</div>
                        </div>
                        <div class="panel-footer">
                            <button type="button" id="wa-request-order-browser-notify">Activar alertas del navegador y sonido</button>
                        </div>
                    </div>
                </div>
                @endperm
                @perm('chats.view')
                <div class="wa-agent-alerts-nav">
                    <button type="button" id="wa-enable-notifications-btn" class="wa-notify-toggle" title="Ver notificaciones de asesor" aria-expanded="false" aria-controls="wa-agent-notifications-panel">
                        <i class="far fa-bell"></i>
                        <span id="global-agent-requests-count" class="global-agent-requests-count hidden"></span>
                    </button>
                    <div id="wa-agent-notifications-panel" class="wa-agent-notifications-panel hidden" role="dialog" aria-label="Notificaciones">
                        <div class="panel-header">
                            Notificaciones
                            <span id="wa-notifications-panel-count"></span>
                        </div>
                        <div id="wa-agent-notifications-list" class="wa-agent-notifications-list">
                            <div class="wa-agent-notifications-empty">No hay solicitudes pendientes</div>
                        </div>
                        <div class="panel-footer">
                            <button type="button" id="wa-request-browser-notify">Activar alertas del navegador y sonido</button>
                        </div>
                    </div>
                </div>
                @endperm
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-info" onclick="document.getElementById('userDropdown').classList.toggle('show')">
                        <div class="user-avatar">
                            {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                        </div>
                        <div class="user-details d-none d-md-flex">
                            <span class="user-name">{{ Auth::user()->name ?? 'Administrador' }}</span>
                            <span class="user-role">Administrador</span>
                        </div>
                        <i class="fas fa-chevron-down d-none d-md-block" style="font-size: 0.75rem;"></i>
                    </div>
                    <div class="user-dropdown-menu">
                        <a href="{{ route('admin.profile.show') }}" class="user-dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>Mi Perfil</span>
                        </a>
                        <a href="{{ route('admin.chatbot.config') }}" class="user-dropdown-item">
                            <i class="fas fa-cog"></i>
                            <span>Configuración</span>
                        </a>
                        <div style="border-top: 1px solid #dee2e6; margin: 0.5rem 0;"></div>
                        <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                            @csrf
                            <button type="submit" class="user-dropdown-item logout-item" style="width: 100%; border: none; background: none; text-align: left; cursor: pointer;" onclick="return confirm('¿Estás seguro de que deseas cerrar sesión?');">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Cerrar Sesión</span>
                            </button>
                        </form>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="d-none d-md-block">
                    @csrf
                    <button type="submit" class="logout-btn" onclick="return confirm('¿Estás seguro de que deseas cerrar sesión?');">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Cerrar Sesión</span>
                    </button>
                </form>
            </div>
        </nav>

        <!-- Mobile Menu Button (Fixed) -->
        <button class="mobile-menu-btn" id="mobileMenuBtn" style="position: fixed; top: 10px; left: 10px; z-index: 1001; background: white; border: 1px solid #dee2e6; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <i class="fas fa-bars"></i>
        </button>

        <div class="main-content">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(($platformFeatureAccess['chat_blocked'] ?? false) || ($platformFeatureAccess['orders_blocked'] ?? false) || ($platformFeatureAccess['bot_blocked'] ?? false))
                <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                    <i class="fas fa-ban me-2"></i>
                    <strong>Servicio suspendido:</strong>
                    @if($platformFeatureAccess['bot_blocked'] ?? false) bot @endif
                    @if($platformFeatureAccess['chat_blocked'] ?? false) · chat @endif
                    @if($platformFeatureAccess['orders_blocked'] ?? false) · pedidos @endif
                    — desactiva las suspensiones manuales en
                    @perm('pricing_settings.view')
                        <a href="{{ route('admin.pricing-settings.edit') }}#billing" class="alert-link">Parámetros → Facturación</a>
                    @else
                        Parámetros de plataforma
                    @endperm
                    o regulariza el pago en Billetera.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Axios -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        // Configuración global de Axios para incluir el token CSRF
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Sidebar Toggle Functionality
        (function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const mainWrapper = document.getElementById('mainWrapper');

            // Check localStorage for sidebar state
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed && window.innerWidth >= 992) {
                sidebar.classList.add('collapsed');
                mainWrapper.classList.add('sidebar-collapsed');
                if (sidebarToggle) {
                    sidebarToggle.querySelector('i').classList.remove('fa-chevron-left');
                    sidebarToggle.querySelector('i').classList.add('fa-chevron-right');
                }
            }

            // Nav group toggles (Pedidos, WhatsApp, etc.)
            document.querySelectorAll('.nav-group').forEach(function(group) {
                const key = group.dataset.navGroup;
                const toggle = group.querySelector('.nav-group-toggle');
                const hasActive = !!group.querySelector('.nav-link.active');
                const saved = key ? localStorage.getItem('nav-group-' + key) : null;

                if (saved === '1') {
                    group.classList.add('is-open');
                } else if (saved === '0') {
                    group.classList.remove('is-open');
                } else if (hasActive) {
                    group.classList.add('is-open');
                }

                if (toggle) {
                    toggle.setAttribute('aria-expanded', group.classList.contains('is-open') ? 'true' : 'false');
                    toggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        group.classList.toggle('is-open');
                        const isOpen = group.classList.contains('is-open');
                        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                        if (key) {
                            localStorage.setItem('nav-group-' + key, isOpen ? '1' : '0');
                        }
                    });
                }
            });

            // Desktop toggle
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainWrapper.classList.toggle('sidebar-collapsed');

                    const isCollapsed = sidebar.classList.contains('collapsed');
                    localStorage.setItem('sidebarCollapsed', isCollapsed);

                    const icon = this.querySelector('i');
                    if (isCollapsed) {
                        icon.classList.remove('fa-chevron-left');
                        icon.classList.add('fa-chevron-right');
                    } else {
                        icon.classList.remove('fa-chevron-right');
                        icon.classList.add('fa-chevron-left');
                    }
                });
            }

            // Mobile menu toggle
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    sidebarOverlay.classList.toggle('show');
                    document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
                });
            }

            // Close sidebar when clicking overlay
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                    document.body.style.overflow = '';
                });
            }

            // Close sidebar when clicking a link on mobile
            const navLinks = sidebar.querySelectorAll('.nav-link, .sidebar-profile-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        sidebar.classList.remove('show');
                        sidebarOverlay.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                });
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });

            // Close user dropdown when clicking outside
            document.addEventListener('click', function(event) {
                const userDropdown = document.getElementById('userDropdown');
                if (userDropdown && !userDropdown.contains(event.target)) {
                    userDropdown.classList.remove('show');
                }
            });
        })();
    </script>

    @stack('scripts')

    @perm('chats.view')
    <script>
        window.WaAgentAlertsConfig = {
            pollUrl: @json(route('admin.agent-requests.poll')),
            chatUrl: @json(url('/admin/chats')),
            favicon: @json(asset('favicon.svg')),
        };
    </script>
    <script src="{{ asset('js/admin-agent-alerts.js') }}?v=3" defer></script>
    @endperm

    @perm('orders.view')
    <script>
        window.WaOrderAlertsConfig = {
            pollUrl: @json(route('admin.orders.poll')),
            ordersUrl: @json(route('admin.orders')),
            favicon: @json(asset('favicon.svg')),
        };
    </script>
    <script src="{{ asset('js/admin-order-alerts.js') }}?v=2" defer></script>
    @endperm

    @perm('message_failures.view')
    <script>
        window.WaFailureAlertsConfig = {
            pollUrl: @json(route('admin.message-failures.poll')),
            indexUrl: @json(route('admin.message-failures.index')),
            favicon: @json(asset('favicon.svg')),
        };
    </script>
    <script src="{{ asset('js/admin-failure-alerts.js') }}?v=1" defer></script>
    @endperm
</body>
</html>
