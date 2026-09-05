@extends('admin.layouts.app')

@section('header')
<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
@php
    $botPrimary = $chatbotConfig?->primary_color ?? '#005c4b';
    $botSecondary = $chatbotConfig?->secondary_color ?? '#075e54';
    $botPrimaryRgb = $chatbotConfig
        ? $chatbotConfig->primaryColorRgb()
        : \App\Models\WhatsappChatbotConfig::hexToRgb('#005c4b');
@endphp
<style>
    :root {
        --bot-primary: {{ $botPrimary }};
        --bot-secondary: {{ $botSecondary }};
        --bot-primary-rgb: {{ $botPrimaryRgb['r'] }}, {{ $botPrimaryRgb['g'] }}, {{ $botPrimaryRgb['b'] }};
    }
    * {
        box-sizing: border-box;
    }

    body {
        background: #111b21 !important;
        font-family: 'Segoe UI', 'Helvetica Neue', Helvetica, 'Lucida Grande', Arial, Ubuntu, Cantarell, 'Fira Sans', sans-serif !important;
        margin: 0;
        padding: 0;
        color: #e9edef;
    }

    /* Pantalla completa dentro del panel admin */
    body.chat-page {
        background: #0b141a !important;
        overflow: hidden;
    }

    body.chat-page .top-navbar,
    body.chat-page .mobile-menu-btn {
        display: none !important;
    }

    /* Menú admin por encima del panel de chat en móvil */
    @media (max-width: 991.98px) {
        body.chat-page .sidebar.show {
            z-index: 2100;
        }

        body.chat-page .sidebar-overlay.show {
            z-index: 2099;
        }
    }

    body.chat-page .main-wrapper {
        min-height: 100dvh;
        height: 100dvh;
        max-height: 100dvh;
        overflow: hidden;
    }

    body.chat-page .main-content {
        padding: 0 !important;
        height: 100%;
        min-height: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    /* Escritorio: sidebar admin + lista de chats + panel */
    @media (min-width: 992px) {
        body.chat-page .main-content,
        .wa-main-bg,
        .wa-card {
            height: 100%;
            min-height: 0;
        }

        .wa-card {
            flex-direction: row;
        }

        .wa-sidebar {
            position: relative;
            transform: none !important;
            min-width: 340px;
            max-width: 340px;
            width: 340px;
            flex-shrink: 0;
        }

        .wa-chat-panel {
            flex: 1;
            min-width: 0;
            min-height: 0;
            position: relative;
            inset: auto;
            width: auto;
            height: 100%;
            max-height: none;
            z-index: auto;
        }

        .wa-chat-header {
            flex-wrap: nowrap;
            padding: 10px 16px;
            gap: 12px;
        }

        .wa-chat-header-main {
            flex: 1;
            width: auto;
            padding-bottom: 0;
            gap: 12px;
        }

        .wa-chat-header-bot {
            width: auto;
            padding: 0;
            border-top: none;
            flex-shrink: 0;
        }

        .wa-bot-avatar-inline,
        .wa-bot-name-label {
            display: inline-block !important;
        }

        .wa-bot-toggle-label {
            display: none !important;
        }

        .wa-header-actions {
            margin-left: 0;
        }
    }

    body.chat-page .alert {
        position: fixed;
        top: 12px;
        right: 12px;
        z-index: 10000;
        max-width: 380px;
        margin: 0;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
    }

    .content-header {
        display: none !important;
    }

    .main-content {
        padding: 0 !important;
    }

    /* Fondo con patrón sutil oscuro */
    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' xmlns='http://www.w3.org/2000/svg'%3E%3Cdefs%3E%3Cpattern id='grid' width='100' height='100' patternUnits='userSpaceOnUse'%3E%3Cpath d='M 100 0 L 0 0 0 100' fill='none' stroke='%2322292e' stroke-width='0.5' opacity='0.3'/%3E%3C/pattern%3E%3C/defs%3E%3Crect width='100' height='100' fill='url(%23grid)'/%3E%3C/svg%3E");
        opacity: 0.4;
        z-index: 0;
        pointer-events: none;
    }

    .wa-bubble-in {
        background: #202c33;
        color: #e9edef;
        border-radius: 7.5px;
        border-top-left-radius: 0;
        box-shadow: 0 1px 0.5px rgba(11, 20, 26, 0.13);
        position: relative;
        padding: 6px 7px 8px 9px;
        max-width: 65%;
        min-width: 60px;
        word-wrap: break-word;
    }

    .wa-bubble-in::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 0;
        width: 0;
        height: 0;
        border-style: solid;
        border-width: 0 8px 8px 0;
        border-color: transparent #202c33 transparent transparent;
    }

    .wa-bubble-out {
        background: var(--bot-primary);
        color: #e9edef;
        border-radius: 7.5px;
        border-top-right-radius: 0;
        box-shadow: 0 1px 0.5px rgba(11, 20, 26, 0.13);
        position: relative;
        padding: 6px 7px 8px 9px;
        max-width: 65%;
        min-width: 60px;
        word-wrap: break-word;
        margin-left: auto;
    }

    .wa-bubble-out.wa-bubble-bot {
        background: var(--bot-primary);
    }

    .wa-bubble-out.wa-bubble-agent {
        background: var(--bot-secondary);
        box-shadow: 0 1px 0.5px rgba(11, 20, 26, 0.13), inset 0 0 0 1px rgba(255, 255, 255, 0.06);
    }

    .wa-bubble-out::after {
        content: '';
        position: absolute;
        right: -8px;
        top: 0;
        width: 0;
        height: 0;
        border-style: solid;
        border-width: 8px 0 0 8px;
        border-color: var(--bot-primary) transparent transparent transparent;
    }

    .wa-bubble-out.wa-bubble-bot::after {
        border-color: var(--bot-primary) transparent transparent transparent;
    }

    .wa-bubble-out.wa-bubble-agent::after {
        border-color: var(--bot-secondary) transparent transparent transparent;
    }

    .wa-badge {
        display: none;
    }

    .wa-bubble-content {
        word-break: break-word;
        overflow-wrap: break-word;
        white-space: pre-wrap;
        font-size: 14.2px;
        line-height: 19px;
        color: #e9edef;
        display: inline;
    }

    .wa-bubble-content-block {
        word-break: break-word;
        overflow-wrap: break-word;
        white-space: pre-wrap;
        font-size: 14.2px;
        line-height: 19px;
        color: #e9edef;
    }

    .wa-main-bg {
        flex: 1;
        min-height: 0;
        height: 100%;
        background: #0b141a;
        display: flex;
        justify-content: stretch;
        align-items: stretch;
        padding: 0;
        position: relative;
        z-index: 1;
    }

    .wa-card {
        width: 100%;
        height: 100%;
        background: #111b21;
        border-radius: 0;
        box-shadow: none;
        display: flex;
        overflow: hidden;
        border: none;
    }

    .wa-sidebar {
        background: #202c33;
        border-right: 1px solid #313d45;
        min-width: 340px;
        max-width: 340px;
        width: 340px;
        height: 100%;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        position: relative;
        z-index: 10;
        transition: transform 0.3s ease;
        flex-shrink: 0;
    }

    .wa-sidebar-search {
        padding: 8px 12px 10px;
        background: #111b21;
        border-bottom: 1px solid #313d45;
        position: relative;
    }

    .wa-sidebar-search i {
        position: absolute;
        left: 24px;
        top: 50%;
        transform: translateY(-50%);
        color: #8696a0;
        font-size: 14px;
        pointer-events: none;
    }

    .wa-sidebar-search input {
        width: 100%;
        background: #202c33;
        border: none;
        border-radius: 8px;
        padding: 9px 12px 9px 36px;
        color: #e9edef;
        font-size: 14px;
        outline: none;
    }

    .wa-sidebar-search input::placeholder {
        color: #8696a0;
    }

    .wa-sidebar-search input:focus {
        box-shadow: 0 0 0 1px #25d366;
    }

    /* Mobile sidebar overlay */
    .wa-sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        z-index: 1999;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .wa-sidebar-overlay.show {
        display: block;
        opacity: 1;
    }

    /* Mobile toggle button */
    .wa-mobile-toggle {
        background: transparent;
        border: none;
        color: #e9edef;
        font-size: 1.25rem;
        padding: 0.5rem;
        cursor: pointer;
        border-radius: 4px;
        transition: background 0.2s ease;
        min-width: 40px;
        min-height: 40px;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .wa-mobile-toggle:hover {
        background: #2a3942;
    }

    .wa-mobile-toggle i {
        font-size: 1.1rem;
    }

    .wa-sidebar-header {
        background: #202c33;
        padding: 10px 16px;
        border-bottom: 1px solid #313d45;
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 59px;
    }

    .wa-sidebar-header .wa-mobile-toggle {
        display: none;
    }

    .wa-sidebar-header-title {
        font-size: 18px;
        font-weight: 400;
        color: #e9edef;
        flex: 1;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .wa-sidebar-contacts {
        flex: 1;
        overflow-y: auto;
        background: #111b21;
    }

    .wa-sidebar-contacts::-webkit-scrollbar {
        width: 6px;
    }

    .wa-sidebar-contacts::-webkit-scrollbar-track {
        background: transparent;
    }

    .wa-sidebar-contacts::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.1);
        border-radius: 3px;
    }

    .wa-sidebar-contacts::-webkit-scrollbar-thumb:hover {
        background: rgba(255,255,255,0.2);
    }

    .wa-sidebar-contact {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        border-bottom: 1px solid #313d45;
        cursor: pointer;
        transition: background 0.15s;
        text-decoration: none;
        color: inherit;
        position: relative;
    }

    .wa-sidebar-contact:hover {
        background: #2a3942;
    }

    .wa-sidebar-contact.active {
        background: #2a3942;
    }

    .wa-sidebar-contact.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: #25d366;
    }

    .wa-sidebar-avatar {
        width: 49px;
        height: 49px;
        border-radius: 50%;
        background: #6a7175;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        color: #e9edef;
        font-size: 20px;
        flex-shrink: 0;
        position: relative;
    }

    .wa-sidebar-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }

    .wa-sidebar-contact-info {
        flex: 1;
        min-width: 0;
    }

    .wa-sidebar-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 2px;
    }

    .wa-sidebar-name {
        font-weight: 400;
        font-size: 17px;
        color: #e9edef;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        flex: 1;
        min-width: 0;
    }

    .wa-sidebar-phone {
        font-size: 13px;
        color: #667781;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .wa-sidebar-last-message {
        font-size: 14px;
        color: #8696a0;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .wa-sidebar-time {
        font-size: 12px;
        color: #667781;
        white-space: nowrap;
        flex-shrink: 0;
        line-height: 1.3;
        text-align: right;
    }

    .wa-sidebar-contact.active .wa-sidebar-time {
        color: #aebac1;
    }

    .wa-new-message-indicator {
        color: #25d366;
        font-size: 12px;
        margin-left: 4px;
        animation: pulse 2s infinite;
    }

    .wa-agent-alert-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #f15c6d;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: 6px;
        white-space: nowrap;
        animation: agentPulse 1.5s infinite;
        flex-shrink: 0;
    }

    .wa-sidebar-contact.has-agent-request {
        background: rgba(241, 92, 109, 0.12);
        border-left: 3px solid #f15c6d;
    }

    .wa-sidebar-contact.has-agent-request .wa-sidebar-name {
        color: #f15c6d;
    }

    .wa-agent-requests-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        margin-left: 8px;
        border-radius: 10px;
        background: #f15c6d;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
    }

    .wa-agent-requests-count.hidden {
        display: none;
    }

    .wa-agent-handoff-banner {
        background: linear-gradient(90deg, #f15c6d 0%, #c0392b 100%);
        color: #fff;
        padding: 10px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        font-size: 13px;
        z-index: 2;
        position: relative;
    }

    .wa-agent-handoff-banner.hidden {
        display: none;
    }

    .wa-agent-handoff-banner button {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.35);
        color: #fff;
        border-radius: 6px;
        padding: 6px 12px;
        font-size: 12px;
        cursor: pointer;
        white-space: nowrap;
    }

    .wa-agent-handoff-banner button:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    @keyframes agentPulse {
        0%, 100% {
            opacity: 1;
            transform: scale(1);
        }
        50% {
            opacity: 0.85;
            transform: scale(1.03);
        }
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }
    /* Chat Panel Styles */
    .wa-chat-panel {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #0b141a;
        position: relative;
        min-height: 0;
        overflow: hidden;
    }

    .wa-chat-panel::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' xmlns='http://www.w3.org/2000/svg'%3E%3Cdefs%3E%3Cpattern id='grid' width='100' height='100' patternUnits='userSpaceOnUse'%3E%3Cpath d='M 100 0 L 0 0 0 100' fill='none' stroke='%2322292e' stroke-width='0.5' opacity='0.3'/%3E%3C/pattern%3E%3C/defs%3E%3Crect width='100' height='100' fill='url(%23grid)'/%3E%3C/svg%3E");
        opacity: 0.4;
        pointer-events: none;
    }

    .wa-chat-header {
        background: #202c33;
        padding: 10px 16px;
        border-bottom: 1px solid #313d45;
        display: flex;
        align-items: center;
        gap: 12px;
        z-index: 1001;
        position: relative;
        min-height: 59px;
        flex-shrink: 0;
    }

    .wa-chat-header-main {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
        min-width: 0;
    }

    .wa-chat-header-bot {
        display: flex;
        align-items: center;
        flex-shrink: 0;
    }

    .wa-bot-toggle-label {
        display: none;
        font-size: 11px;
        color: #8696a0;
        white-space: nowrap;
    }

    .wa-header-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-left: auto;
    }

    .wa-header-action-btn {
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 50%;
        background: transparent;
        color: #aebac1;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s, color 0.2s;
    }

    .wa-header-action-btn:hover,
    .wa-header-action-btn.is-active {
        background: #2a3942;
        color: #25d366;
    }

    .wa-chat-header .wa-mobile-toggle {
        display: none;
        z-index: 1002;
        position: relative;
    }

    .wa-chat-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #6a7175;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        color: #e9edef;
        font-size: 18px;
        flex-shrink: 0;
        cursor: default;
        transition: opacity 0.2s ease;
    }

    @media (max-width: 991.98px) {
        .wa-chat-avatar {
            cursor: pointer;
        }

        .wa-chat-avatar:active {
            opacity: 0.7;
        }
    }

    .wa-chat-header-info {
        flex: 1;
        min-width: 0;
    }

    .wa-chat-header-name {
        font-size: 16px;
        font-weight: 400;
        color: #e9edef;
        line-height: 21px;
    }

    .wa-chat-header-status {
        font-size: 13px;
        color: #8696a0;
        line-height: 20px;
    }

    .wa-chat-bot-control {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .bot-toggle-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
    }

    .bot-toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .bot-toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #667781;
        transition: .4s;
        border-radius: 24px;
    }

    .bot-toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    .bot-toggle-switch input:checked + .bot-toggle-slider {
        background-color: var(--bot-primary);
    }

    .bot-toggle-switch input:checked + .bot-toggle-slider:before {
        transform: translateX(20px);
    }

    .bot-toggle-switch input:focus + .bot-toggle-slider {
        box-shadow: 0 0 1px var(--bot-primary);
    }

    .bot-toggle-switch:hover .bot-toggle-slider {
        opacity: 0.8;
    }

    .wa-chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        position: relative;
        z-index: 1;
        min-height: 0;
    }

    .wa-chat-messages::-webkit-scrollbar {
        width: 6px;
    }

    .wa-chat-messages::-webkit-scrollbar-track {
        background: transparent;
    }

    .wa-chat-messages::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.1);
        border-radius: 3px;
    }

    .wa-chat-messages::-webkit-scrollbar-thumb:hover {
        background: rgba(255,255,255,0.2);
    }

    .wa-message-wrapper {
        display: flex;
        margin-bottom: 8px;
        padding: 0 12px;
    }

    .wa-message-wrapper.incoming {
        justify-content: flex-start;
    }

    .wa-message-wrapper.outgoing {
        justify-content: flex-end;
    }

    .wa-msg-col {
        display: flex;
        flex-direction: column;
        max-width: min(520px, 78%);
        gap: 4px;
    }

    .wa-message-wrapper.incoming .wa-msg-col {
        align-items: flex-start;
    }

    .wa-message-wrapper.outgoing .wa-msg-col {
        align-items: flex-end;
    }

    .wa-msg-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.01em;
        padding: 3px 8px;
        border-radius: 999px;
        line-height: 1.2;
        max-width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .wa-msg-badge i {
        font-size: 10px;
        opacity: 0.9;
        flex-shrink: 0;
    }

    .wa-msg-badge-client {
        background: rgba(32, 44, 51, 0.85);
        color: #aebac1;
        border: 1px solid rgba(255, 255, 255, 0.06);
    }

    .wa-msg-badge-bot {
        background: rgba(var(--bot-primary-rgb), 0.35);
        color: #e9edef;
        border: 1px solid rgba(var(--bot-primary-rgb), 0.45);
    }

    .wa-msg-badge-agent {
        background: rgba(23, 107, 91, 0.45);
        color: #ffe082;
        border: 1px solid rgba(255, 224, 130, 0.25);
    }

    .wa-msg-col .wa-bubble-in,
    .wa-msg-col .wa-bubble-out {
        max-width: 100%;
        margin-left: 0;
    }

    .wa-message-time {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        float: right;
        margin: 4px 0 -4px 8px;
        font-size: 11px;
        color: rgba(233, 237, 239, 0.6);
        line-height: 15px;
        vertical-align: bottom;
        position: relative;
        top: 4px;
        white-space: nowrap;
        user-select: none;
    }

    .wa-message-time.incoming {
        color: rgba(233, 237, 239, 0.55);
    }

    .wa-message-time.outgoing {
        color: rgba(233, 237, 239, 0.6);
    }

    .wa-message-status {
        display: inline-flex;
        align-items: center;
        margin-left: 3px;
    }

    .wa-input-container {
        background: #202c33;
        padding: 10px 16px 14px;
        border-top: 1px solid #313d45;
        flex-shrink: 0;
        z-index: 20;
        position: relative;
    }

    .wa-chat-input-area {
        display: flex;
        align-items: flex-end;
        gap: 10px;
        position: relative;
    }

    .wa-input-attach-outside {
        width: 42px;
        height: 42px;
        border: none;
        border-radius: 50%;
        background: transparent;
        color: #8696a0;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
        transition: background 0.2s, color 0.2s;
    }

    .wa-input-attach-outside:hover {
        background: #2a3942;
        color: #e9edef;
    }

    .wa-input-wrapper {
        flex: 1;
        background: #2a3942;
        border-radius: 8px;
        display: flex;
        align-items: flex-end;
        padding: 6px 8px 6px 12px;
        min-height: 42px;
        max-height: 120px;
    }

    .wa-input-button {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #8696a0;
        transition: background 0.2s, color 0.2s;
        flex-shrink: 0;
        border: none;
        background: transparent;
    }

    .wa-input-button:hover {
        background: #313d45;
        color: #e9edef;
    }

    .wa-input-textarea {
        flex: 1;
        border: none;
        outline: none;
        padding: 6px 8px;
        font-size: 15px;
        line-height: 20px;
        color: #e9edef;
        resize: none;
        max-height: 100px;
        font-family: inherit;
        background: transparent;
        min-height: 24px;
    }

    .wa-input-textarea::placeholder {
        color: #8696a0;
    }

    .wa-send-button {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #8696a0;
        transition: background 0.2s, color 0.2s;
        flex-shrink: 0;
        border: none;
    }

    .wa-send-button:hover,
    .wa-send-button.has-text {
        color: #25d366;
    }

    .wa-send-button.has-text {
        background: #25d366;
        color: #fff;
    }

    .wa-send-button.has-text:hover {
        background: #20ba5a;
    }

    .wa-send-button:disabled {
        background: #a0a0a0;
        cursor: not-allowed;
    }

    /* Preview containers */
    .wa-preview-container {
        margin-bottom: 8px;
        padding: 0 16px;
    }

    .wa-preview-box {
        background: #2a3942;
        border-radius: 8px;
        padding: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.3);
    }

    .wa-preview-remove {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #f44336;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: white;
        flex-shrink: 0;
        transition: background 0.2s;
    }

    .wa-preview-remove:hover {
        background: #d32f2f;
    }

    .wa-recording-box {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }

    .wa-recording-dot {
        width: 12px;
        height: 12px;
        background: #f44336;
        border-radius: 50%;
        animation: pulse 1.5s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }

    /* Panel de estadísticas (oculto por defecto, se abre con botón) */
    .stats-panel {
        display: none;
        position: fixed;
        bottom: 0;
        left: var(--sidebar-width, 250px);
        right: 0;
        max-height: 80vh;
        overflow-y: auto;
        background: #111b21;
        border-top: 2px solid #25d366;
        padding: 16px;
        z-index: 2000;
        box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.45);
    }

    body.chat-page .main-wrapper.sidebar-collapsed .stats-panel {
        left: var(--sidebar-collapsed-width, 70px);
    }

    .stats-panel.is-open {
        display: block;
    }

    .stats-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px solid #313d45;
    }

    .stats-panel-header h3 {
        margin: 0;
        font-size: 1rem;
        color: #e9edef;
        font-weight: 500;
    }

    .stats-panel-close {
        background: #2a3942;
        border: none;
        color: #e9edef;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .stats-panel-close:hover {
        background: #313d45;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1em;
        margin-bottom: 1em;
    }
    .stat-card {
        background: #202c33;
        padding: 1em;
        border-radius: 0.8em;
        border: 1px solid #313d45;
    }
    .stat-title {
        font-size: 0.9em;
        color: #8696a0;
        margin-bottom: 0.5em;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .stat-info-icon {
        cursor: help;
        color: #8696a0;
        font-size: 0.85em;
        opacity: 0.7;
        transition: opacity 0.2s;
        position: relative;
    }
    .stat-info-icon:hover {
        opacity: 1;
        color: #25d366;
    }
    .stat-tooltip {
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #202c33;
        color: #e9edef;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.8em;
        white-space: nowrap;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        border: 1px solid #313d45;
        margin-bottom: 5px;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s;
        max-width: 250px;
        white-space: normal;
    }
    .stat-info-icon:hover .stat-tooltip {
        opacity: 1;
    }
    .stat-value {
        font-size: 1.8em;
        font-weight: 600;
        color: #e9edef;
    }
    .stat-change {
        font-size: 0.85em;
        margin-top: 0.3em;
    }
    .stat-change.positive {
        color: #25d366;
    }
    .stat-change.negative {
        color: #f15c6d;
    }
    .charts-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1em;
    }
    .chart-card {
        background: #202c33;
        padding: 1em;
        border-radius: 0.8em;
        border: 1px solid #313d45;
    }
    .topics-list {
        margin-top: 1em;
    }
    .topic-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5em 0;
        border-bottom: 1px solid #313d45;
    }
    .topic-name {
        font-size: 0.9em;
        color: #e9edef;
    }
    .topic-count {
        font-size: 0.85em;
        color: #e9edef;
        background: #2a3942;
        padding: 0.2em 0.6em;
        border-radius: 9999px;
    }
    /* Estilos para el selector de emojis */
    #emoji-picker {
        display: none !important;
    }
    #emoji-picker:not(.hidden) {
        display: block !important;
    }
    .emoji-btn {
        transition: background-color 0.15s ease;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        min-height: 32px;
        background: transparent;
    }
    .emoji-btn:hover {
        background-color: #313d45 !important;
    }
    .emoji-btn:active {
        transform: scale(0.95);
    }

    /* Responsive Styles — móvil / tablet */
    @media (max-width: 991.98px) {
        body.chat-page .main-wrapper {
            margin-left: 0 !important;
        }

        body.chat-page .main-content,
        .wa-main-bg,
        .wa-card {
            height: 100%;
            min-height: 0;
            max-height: 100%;
            flex: 1;
        }

        .wa-main-bg {
            display: flex;
            flex-direction: column;
        }

        .wa-card {
            flex: 1;
            min-height: 0;
            position: relative;
            display: block;
        }

        /* Lista de chats: pantalla completa deslizable */
        .wa-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            height: 100dvh;
            z-index: 2000;
            transform: translateX(-100%);
            box-shadow: 2px 0 8px rgba(0,0,0,0.3);
        }

        .wa-sidebar.show {
            transform: translateX(0);
        }

        /* Conversación: ocupa todo el viewport */
        .wa-chat-panel {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100vh;
            height: 100dvh;
            max-height: 100dvh;
            z-index: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .wa-chat-messages {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .wa-input-container {
            flex: 0 0 auto;
            flex-shrink: 0;
            padding-bottom: max(10px, env(safe-area-inset-bottom, 10px));
            background: #202c33;
        }

        .wa-agent-handoff-banner {
            flex-shrink: 0;
        }

        /* Header en 2 filas claras */
        .wa-chat-header {
            flex: 0 0 auto;
            flex-shrink: 0;
            flex-direction: column;
            align-items: stretch;
            padding: 0;
            gap: 0;
            min-height: 0;
        }

        .wa-chat-header-main {
            display: grid;
            grid-template-columns: 38px 38px 1fr 38px;
            grid-template-areas: "menu back info stats";
            align-items: center;
            width: 100%;
            gap: 4px;
            padding: 6px 8px;
        }

        #waOpenAdminMenu { grid-area: menu; }
        #waOpenSidebar { grid-area: back; }

        .wa-chat-header-main .wa-chat-avatar {
            display: none;
        }

        .wa-chat-header-info {
            grid-area: info;
            min-width: 0;
        }

        .wa-header-actions {
            grid-area: stats;
            margin-left: 0;
        }

        .wa-chat-header .wa-mobile-toggle {
            min-width: 38px;
            min-height: 38px;
            padding: 0;
            margin: 0;
        }

        .wa-chat-header-name {
            font-size: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .wa-chat-header-status {
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .wa-chat-header-bot {
            width: 100%;
            padding: 5px 10px 6px;
            border-top: 1px solid #313d45;
            justify-content: flex-start;
        }

        .wa-bot-avatar-inline,
        .wa-bot-name-label {
            display: none !important;
        }

        .wa-bot-toggle-label {
            display: inline;
            font-size: 11px;
        }

        .wa-sidebar-header .wa-mobile-toggle,
        .wa-chat-header .wa-mobile-toggle {
            display: flex !important;
        }
    }

    @media (max-width: 767.98px) {
        .wa-agent-handoff-banner {
            font-size: 12px;
            padding: 8px 10px;
            flex-wrap: wrap;
        }

        .wa-header-action-btn {
            width: 36px;
            height: 36px;
        }

        .wa-chat-bot-control {
            gap: 6px;
        }

        #bot-status-text {
            min-width: auto !important;
            font-size: 11px !important;
        }

        .bot-toggle-switch {
            width: 38px;
            height: 22px;
        }

        .bot-toggle-slider:before {
            height: 16px;
            width: 16px;
        }

        .bot-toggle-switch input:checked + .bot-toggle-slider:before {
            transform: translateX(14px);
        }

        .wa-chat-messages {
            padding: 10px 8px;
        }

        .wa-input-container {
            padding: 8px 10px;
        }

        .wa-chat-input-area {
            gap: 6px;
        }

        .wa-input-attach-outside {
            width: 38px;
            height: 38px;
        }

        .wa-input-wrapper {
            min-height: 38px;
        }

        .wa-input-textarea {
            font-size: 16px;
        }

        .wa-send-button {
            width: 42px;
            height: 42px;
        }

        .wa-bubble-in,
        .wa-bubble-out {
            max-width: 88%;
        }

        .wa-message-wrapper {
            padding: 0 4px;
        }

        .stats-panel {
            left: 0;
            max-height: 70vh;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5em;
            margin-bottom: 0.5em;
        }

        .stat-card {
            padding: 0.6em;
            font-size: 0.85em;
        }

        #typing-unavailable-hint {
            font-size: 11px !important;
            padding: 6px 8px !important;
        }
    }

    /* Refinamiento operativo: jerarquía clara para atención de clientes. */
    .wa-sidebar {
        background: #0f1a20;
        border-right-color: #26343c;
    }

    .wa-sidebar-header {
        min-height: 74px;
        padding: 12px 16px;
        background: #132129;
        border-bottom-color: #26343c;
    }

    .wa-sidebar-header-title {
        display: block;
        font-size: 17px;
        font-weight: 750;
        line-height: 1.2;
    }

    .wa-sidebar-title-row {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .wa-sidebar-subtitle {
        display: block;
        margin-top: 4px;
        color: #8fa1ab;
        font-size: 11.5px;
        font-weight: 400;
    }

    .wa-conversations-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 20px;
        padding: 0 7px;
        border-radius: 999px;
        background: rgba(var(--bot-primary-rgb), .28);
        color: #dff8ef;
        font-size: 10px;
        font-weight: 800;
    }

    .wa-sidebar-search {
        padding: 11px 12px;
        background: #0f1a20;
        border-bottom-color: #26343c;
    }

    .wa-sidebar-search input {
        min-height: 42px;
        padding-left: 39px;
        background: #1c2a32;
        border: 1px solid transparent;
        border-radius: 10px;
    }

    .wa-sidebar-search input:focus {
        border-color: rgba(37, 211, 102, .65);
        box-shadow: 0 0 0 3px rgba(37, 211, 102, .09);
    }

    .wa-sidebar-contacts {
        padding: 7px;
        background: #0f1a20;
    }

    .wa-sidebar-contact {
        min-height: 72px;
        margin-bottom: 3px;
        padding: 10px 11px;
        border: 1px solid transparent;
        border-radius: 11px;
    }

    .wa-sidebar-contact:hover { background: #18272f; }
    .wa-sidebar-contact.active {
        background: linear-gradient(90deg, rgba(var(--bot-primary-rgb), .25), #1b2b33 72%);
        border-color: rgba(37, 211, 102, .18);
    }
    .wa-sidebar-contact.active::before {
        top: 12px;
        bottom: 12px;
        left: 0;
        width: 3px;
        border-radius: 0 4px 4px 0;
    }

    .wa-sidebar-avatar {
        width: 46px;
        height: 46px;
        background: linear-gradient(145deg, #52636c, #34434b);
        border: 1px solid rgba(255,255,255,.08);
        font-size: 17px;
        font-weight: 750;
    }

    .wa-sidebar-name { font-size: 14.5px; font-weight: 700; }
    .wa-sidebar-last-message { margin-top: 5px; font-size: 12.5px; }
    .wa-sidebar-time { color: #8fa1ab; font-size: 10.5px; }

    .wa-chat-header {
        min-height: 74px;
        padding: 11px 18px;
        background: #132129;
        border-bottom-color: #26343c;
        box-shadow: 0 2px 12px rgba(0,0,0,.13);
    }

    .wa-chat-avatar {
        width: 43px;
        height: 43px;
        background: linear-gradient(145deg, #52636c, #34434b);
        border: 1px solid rgba(255,255,255,.1);
        font-weight: 750;
    }

    .wa-chat-header-name { font-size: 15.5px; font-weight: 750; }
    .wa-chat-header-status {
        display: flex;
        align-items: center;
        gap: 5px;
        margin-top: 2px;
        color: #94a5ae;
        font-size: 11.5px;
    }

    .wa-header-actions { gap: 7px; }
    .wa-header-action-btn {
        width: auto;
        height: 36px;
        padding: 0 11px;
        gap: 6px;
        border: 1px solid #31414a;
        border-radius: 9px;
        background: #192830;
        font-size: 11.5px;
        font-weight: 650;
    }
    .wa-header-action-btn:hover,
    .wa-header-action-btn.is-active { border-color: rgba(37,211,102,.35); background: #20343c; }

    .wa-chat-bot-control {
        min-height: 48px;
        padding: 7px 9px;
        gap: 9px;
        border: 1px solid #31414a;
        border-radius: 11px;
        background: #192830;
    }

    .wa-bot-control-icon {
        width: 31px;
        height: 31px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border-radius: 8px;
        background: rgba(var(--bot-primary-rgb), .28);
        color: #53df9d;
    }

    .wa-bot-control-copy { min-width: 106px; }
    .wa-bot-control-title {
        display: block;
        color: #e9edef;
        font-size: 11px;
        font-weight: 750;
        line-height: 1.15;
    }
    #bot-status-text {
        display: block;
        margin-top: 3px;
        min-width: 0 !important;
        color: #8fa1ab !important;
        font-size: 10px !important;
        line-height: 1;
    }

    .wa-chat-messages {
        padding: 24px clamp(18px, 4vw, 58px);
        gap: 6px;
    }

    .wa-empty-state {
        width: min(390px, calc(100% - 32px));
        margin: auto;
        padding: 30px 26px;
        border: 1px solid rgba(255,255,255,.07);
        border-radius: 16px;
        background: rgba(19,33,41,.82);
        box-shadow: 0 18px 45px rgba(0,0,0,.16);
        color: #e9edef;
        text-align: center;
    }
    .wa-empty-state-icon {
        width: 54px;
        height: 54px;
        margin: 0 auto 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 15px;
        background: rgba(var(--bot-primary-rgb), .3);
        color: #53df9d;
        font-size: 21px;
    }
    .wa-empty-state strong { display: block; font-size: 15px; }
    .wa-empty-state span {
        display: block;
        max-width: 295px;
        margin: 7px auto 0;
        color: #91a1aa;
        font-size: 12px;
        line-height: 1.5;
    }

    .wa-input-container {
        padding: 10px 18px 12px;
        background: #132129;
        border-top-color: #26343c;
        box-shadow: 0 -3px 14px rgba(0,0,0,.12);
    }
    .wa-input-wrapper {
        min-height: 45px;
        padding-left: 14px;
        border: 1px solid #34454f;
        border-radius: 11px;
        background: #1c2b33;
    }
    .wa-input-wrapper:focus-within {
        border-color: rgba(37,211,102,.55);
        box-shadow: 0 0 0 3px rgba(37,211,102,.07);
    }
    .wa-send-button.has-text { background: var(--bot-primary); }
    .wa-composer-hint {
        margin: 6px 52px 0;
        color: #758892;
        font-size: 10px;
        text-align: right;
    }
    #typing-unavailable-hint {
        margin: 0 0 8px !important;
        padding: 7px 10px !important;
        border: 1px solid #725b22;
        border-radius: 8px;
        background: #2b281d !important;
        color: #e8cf86 !important;
        font-size: 11px !important;
    }

    @media (min-width: 992px) {
        .wa-sidebar { min-width: 360px; max-width: 360px; width: 360px; }
    }

    @media (max-width: 991.98px) {
        .wa-chat-header-main { grid-template-columns: 38px 38px minmax(0,1fr) auto; }
        .wa-header-action-label { display: none; }
        .wa-header-action-btn { width: 36px; padding: 0; justify-content: center; border-color: transparent; }
        .wa-chat-header-bot { padding: 6px 10px 7px; }
        .wa-chat-bot-control { width: 100%; min-height: 43px; }
        .wa-bot-control-copy { flex: 1; }
    }

    @media (max-width: 767.98px) {
        .wa-sidebar-header { min-height: 66px; }
        .wa-sidebar-contact { min-height: 68px; }
        .wa-chat-messages { padding: 16px 9px; }
        .wa-empty-state { padding: 24px 18px; }
        .wa-input-container { padding: 8px 9px max(9px, env(safe-area-inset-bottom, 9px)); }
        .wa-composer-hint { display: none; }
    }

    /* Menús del compositor: compactos, sin solaparse ni generar scroll horizontal. */
    .wa-composer-popover {
        position: absolute;
        z-index: 120;
        overflow: hidden;
        border: 1px solid #34454f;
        border-radius: 14px;
        background: #17262e;
        box-shadow: 0 18px 48px rgba(0,0,0,.38);
    }
    .wa-composer-popover.hidden { display: none !important; }
    .wa-popover-header {
        min-height: 50px;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        border-bottom: 1px solid #30414a;
        background: #1b2c34;
    }
    .wa-popover-title { display: block; color: #edf3f5; font-size: 12.5px; font-weight: 750; }
    .wa-popover-subtitle { display: block; margin-top: 2px; color: #82949e; font-size: 9.5px; }
    .wa-popover-close {
        width: 30px;
        height: 30px;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: #9aabb4;
        cursor: pointer;
    }
    .wa-popover-close:hover { background: #293b44; color: #fff; }

    #emoji-picker {
        left: 64px;
        bottom: calc(100% + 10px);
        width: 360px;
        max-width: calc(100vw - 32px);
        max-height: min(350px, 52vh);
        padding: 0;
    }
    #emoji-picker:not(.hidden) { display: flex !important; flex-direction: column; }
    .wa-emoji-scroll {
        flex: 1;
        min-height: 0;
        padding: 10px;
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: thin;
        scrollbar-color: #53656f transparent;
    }
    .wa-emoji-grid {
        display: grid;
        grid-template-columns: repeat(8, minmax(0, 1fr));
        gap: 4px;
        width: 100%;
    }
    .wa-emoji-grid .emoji-btn {
        width: 100%;
        min-width: 0;
        height: 36px;
        min-height: 36px;
        padding: 0 !important;
        border: 0;
        border-radius: 8px;
        background: transparent !important;
        font-size: 20px;
        line-height: 1;
    }
    .wa-emoji-grid .emoji-btn:hover,
    .wa-emoji-grid .emoji-btn:focus-visible {
        background: #2a3d46 !important;
        outline: none;
        transform: scale(1.06);
    }

    .wa-attach-menu {
        left: 0;
        bottom: calc(100% + 12px);
        width: 285px;
        padding: 7px;
    }
    .wa-attach-heading {
        padding: 7px 8px 8px;
        color: #8497a1;
        font-size: 10px;
        font-weight: 750;
        letter-spacing: .05em;
        text-transform: uppercase;
    }
    .wa-attach-option {
        width: 100%;
        padding: 9px;
        display: flex;
        align-items: center;
        gap: 10px;
        border: 0;
        border-radius: 10px;
        background: transparent !important;
        color: #e9edef;
        cursor: pointer;
        text-align: left;
    }
    .wa-attach-option:hover,
    .wa-attach-option:focus-visible { background: #263943 !important; outline: none; }
    .wa-attach-icon {
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border-radius: 10px;
        background: rgba(var(--bot-primary-rgb), .28);
        color: #62e2a7;
        font-size: 16px;
    }
    .wa-attach-icon.document { background: #263b52; color: #8fc5ff; }
    .wa-attach-copy strong { display: block; color: #edf3f5; font-size: 12.5px; font-weight: 700; }
    .wa-attach-copy small { display: block; margin-top: 2px; color: #8497a1; font-size: 10px; }

    .wa-input-attach-outside,
    .wa-input-button {
        border-radius: 10px;
    }
    .wa-input-attach-outside.is-open,
    .wa-input-button.is-open {
        background: rgba(var(--bot-primary-rgb), .28);
        color: #64e5ac;
    }

    /* Mensajes: el color de marca identifica al remitente sin dominar la lectura. */
    .wa-chat-panel::before { opacity: .2; }
    .wa-chat-messages {
        background: linear-gradient(180deg, rgba(8,17,22,.76), rgba(11,20,26,.92));
    }
    .wa-message-wrapper {
        margin-bottom: 11px;
        padding: 0 10px;
    }
    .wa-msg-col {
        width: fit-content;
        max-width: min(650px, 74%);
        gap: 5px;
    }
    .wa-msg-badge {
        padding: 0 3px;
        gap: 4px;
        border: 0;
        border-radius: 0;
        background: transparent !important;
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: .015em;
        opacity: .82;
    }
    .wa-msg-badge i { font-size: 8.5px; }
    .wa-msg-badge-client { color: #9aabb4; }
    .wa-msg-badge-bot { color: #e7b26f; }
    .wa-msg-badge-agent { color: #65d8b3; }

    .wa-msg-col .wa-bubble-in,
    .wa-msg-col .wa-bubble-out {
        min-width: 86px;
        padding: 9px 11px 8px;
        border: 1px solid #30414a;
        border-radius: 12px;
        color: #edf3f5;
        box-shadow: 0 3px 10px rgba(0,0,0,.13);
    }
    .wa-bubble-in {
        background: #1a2931;
        border-left: 3px solid #687b85 !important;
        border-top-left-radius: 5px !important;
    }
    .wa-bubble-out.wa-bubble-bot {
        background: #2b2b27;
        border-color: #4b4234;
        border-right: 3px solid var(--bot-primary) !important;
        border-top-right-radius: 5px !important;
    }
    .wa-bubble-out.wa-bubble-agent {
        background: #17302e;
        border-color: #285049;
        border-right: 3px solid #25b98a !important;
        border-top-right-radius: 5px !important;
    }
    .wa-bubble-in::before,
    .wa-bubble-out::after { display: none; }
    .wa-bubble-content,
    .wa-bubble-content-block {
        color: #edf3f5;
        font-size: 13px;
        line-height: 1.48;
    }
    .wa-bubble-content-block {
        margin-top: 5px !important;
        color: #9aabb4 !important;
        font-size: 11px !important;
        line-height: 1.4;
    }
    .wa-message-time {
        top: 2px;
        margin: 5px 0 -2px 10px;
        color: #8b9aa2 !important;
        font-size: 9.5px;
        line-height: 13px;
    }
    .wa-message-status svg { width: 14px; }
    .wa-msg-col img {
        display: block;
        max-width: 100%;
        border-radius: 9px !important;
    }
    .wa-image-card {
        width: min(360px, 100%);
        overflow: hidden;
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 10px;
        background: #111d23;
    }
    .wa-image-preview-button {
        position: relative;
        display: block;
        width: 100%;
        padding: 0;
        overflow: hidden;
        border: 0;
        border-radius: 9px;
        background: #111d23;
        cursor: zoom-in;
    }
    .wa-image-preview-button[hidden] { display: none; }
    .wa-image-preview-button img {
        width: 100%;
        max-height: 360px;
        object-fit: cover;
        transition: transform .2s ease, filter .2s ease;
    }
    .wa-image-preview-action {
        position: absolute;
        right: 10px;
        bottom: 10px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 999px;
        background: rgba(8,17,22,.82);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        opacity: 0;
        transform: translateY(4px);
        transition: opacity .2s ease, transform .2s ease;
        backdrop-filter: blur(5px);
    }
    .wa-image-preview-button:hover img { transform: scale(1.015); filter: brightness(.82); }
    .wa-image-preview-button:hover .wa-image-preview-action,
    .wa-image-preview-button:focus-visible .wa-image-preview-action { opacity: 1; transform: translateY(0); }
    .wa-image-caption {
        padding: 9px 10px 2px;
        overflow-wrap: anywhere;
    }
    .wa-image-placeholder {
        display: none;
        min-height: 180px;
        padding: 24px 18px;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 7px;
        text-align: center;
        color: #91a2ab;
    }
    .wa-image-placeholder.is-visible { display: flex; }
    .wa-image-placeholder-icon {
        width: 46px;
        height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 3px;
        border-radius: 13px;
        background: #20323b;
        color: #8fa5af;
        font-size: 19px;
    }
    .wa-image-placeholder strong { color: #e1e9ec; font-size: 12.5px; }
    .wa-image-placeholder small { max-width: 235px; font-size: 10.5px; line-height: 1.45; }
    .wa-image-retry {
        margin-top: 5px;
        padding: 7px 11px;
        border: 1px solid #38505b;
        border-radius: 8px;
        background: #1b2c34;
        color: #dce8ec;
        font-size: 10.5px;
        font-weight: 700;
        cursor: pointer;
    }
    .wa-image-retry:hover { border-color: rgba(37,211,102,.55); color: #72e7b2; }
    .wa-image-lightbox {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 28px;
        background: rgba(3,9,12,.9);
        backdrop-filter: blur(8px);
    }
    .wa-image-lightbox.is-open { display: flex; }
    .wa-image-lightbox img {
        max-width: min(1100px, 94vw);
        max-height: 88vh;
        object-fit: contain;
        border-radius: 12px !important;
        box-shadow: 0 24px 70px rgba(0,0,0,.5);
    }
    .wa-image-lightbox-close {
        position: absolute;
        top: 18px;
        right: 20px;
        width: 42px;
        height: 42px;
        border: 1px solid rgba(255,255,255,.18);
        border-radius: 50%;
        background: rgba(21,34,41,.9);
        color: #fff;
        cursor: pointer;
    }

    @supports (background: color-mix(in srgb, #fff 50%, #000)) {
        .wa-bubble-out.wa-bubble-bot {
            background: color-mix(in srgb, var(--bot-primary) 18%, #17242b);
            border-color: color-mix(in srgb, var(--bot-primary) 34%, #30414a);
        }
    }

    @media (max-width: 767.98px) {
        #emoji-picker {
            right: 8px;
            left: 8px;
            bottom: calc(100% + 8px);
            width: auto;
            max-width: none;
            max-height: 46vh;
        }
        .wa-emoji-grid { grid-template-columns: repeat(7, minmax(0, 1fr)); }
        .wa-attach-menu { width: min(285px, calc(100vw - 18px)); }
        .wa-message-wrapper { margin-bottom: 10px; padding: 0 3px; }
        .wa-msg-col { max-width: 91%; }
        .wa-msg-col .wa-bubble-in,
        .wa-msg-col .wa-bubble-out { padding: 8px 9px 7px; }
        .wa-bubble-content,
        .wa-bubble-content-block { font-size: 12.5px; line-height: 1.46; }
    }
    </style>

<div class="wa-main-bg">
    <!-- Sidebar Overlay (Mobile) -->
    <div class="wa-sidebar-overlay" id="waSidebarOverlay"></div>

    <div class="wa-card">
        <!-- Sidebar -->
        <div class="wa-sidebar" id="waSidebar">
            <div class="wa-sidebar-header">
                <button class="wa-mobile-toggle" id="waCloseSidebar" title="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
                <div class="wa-sidebar-header-title">
                    <div class="wa-sidebar-title-row">
                        <span>Conversaciones</span>
                        <span class="wa-conversations-count">{{ $contacts->count() }}</span>
                        @php $agentRequestsCount = $contacts->filter(fn($c) => $c->needsAgent())->count(); @endphp
                        <span id="agent-requests-count" class="wa-agent-requests-count{{ $agentRequestsCount > 0 ? '' : ' hidden' }}">{{ $agentRequestsCount > 0 ? $agentRequestsCount : '' }}</span>
                    </div>
                    <span class="wa-sidebar-subtitle">Selecciona un cliente para atenderlo</span>
                </div>
            </div>
            <div class="wa-sidebar-search">
                <i class="fas fa-search"></i>
                <input type="text" id="contact-search" placeholder="Buscar cliente o número" autocomplete="off" aria-label="Buscar conversación">
            </div>
            <div class="wa-sidebar-contacts" id="wa-sidebar-contacts">
            @foreach($contacts as $c)
                <a href="javascript:void(0)" data-contact-id="{{ $c->id }}" class="wa-sidebar-contact{{ $contact->id === $c->id ? ' active' : '' }}{{ $c->needsAgent() ? ' has-agent-request' : '' }}" @if($contact->id === $c->id) aria-current="true" @endif>
                    <div class="wa-sidebar-avatar">{{ strtoupper(mb_substr($c->name ?? 'C', 0, 1)) }}</div>
                        <div class="wa-sidebar-contact-info">
                            <div class="wa-sidebar-top">
                                <div class="wa-sidebar-name">{{ $c->name ?? 'Cliente' }}</div>
                                @if($c->needsAgent())
                                    <span class="wa-agent-alert-badge" title="Solicita asesor humano">👤 Asesor</span>
                                @endif
                                @if(!empty($c->last_message_date))
                                    <div class="wa-sidebar-time" title="{{ $c->last_message_date->format('d/m/Y H:i') }}">
                                        {{ \App\Helpers\WhatsappMessageFormatter::formatSidebarDateTime($c->last_message_date) }}
                                    </div>
                                @endif
                            </div>
                            @if(!empty($c->last_message_preview))
                                <div class="wa-sidebar-last-message">
                                    @if(!empty($c->has_new_message))
                                        <span class="wa-new-message-indicator" data-contact-id="{{ $c->id }}">●</span>
                                    @endif
                                    <span>{{ $c->last_message_preview }}</span>
                                </div>
                            @else
                                <div class="wa-sidebar-phone">{{ $c->phone_number }}</div>
                            @endif
                        </div>
                </a>
            @endforeach
            </div>
        </div>
        <!-- Chat Panel -->
        <div class="wa-chat-panel">
            <!-- Header -->
            <div class="wa-chat-header">
                <div class="wa-chat-header-main">
                    <button type="button" class="wa-mobile-toggle" id="waOpenAdminMenu" title="Menú principal">
                        <i class="fas fa-th-large"></i>
                    </button>
                    <button type="button" class="wa-mobile-toggle" id="waOpenSidebar" title="Lista de chats">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div class="wa-chat-avatar" id="waChatAvatarToggle" title="Ver contactos">{{ strtoupper(mb_substr($contact->name ?? 'C', 0, 1)) }}</div>
                    <div class="wa-chat-header-info">
                        <div class="wa-chat-header-name">{{ $contact->name ?? 'Cliente' }}</div>
                        <div class="wa-chat-header-status"><i class="fab fa-whatsapp"></i><span>{{ $contact->phone_number }}</span></div>
                    </div>
                    <div class="wa-header-actions">
                        <button type="button" id="reset-conversation-btn" class="wa-header-action-btn" title="Reiniciar conversación y cancelar el pedido incompleto">
                            <i class="fas fa-rotate-left"></i><span class="wa-header-action-label">Reiniciar</span>
                        </button>
                        <button type="button" id="stats-toggle-btn" class="wa-header-action-btn" title="Ver resumen de actividad">
                            <i class="fas fa-chart-line"></i><span class="wa-header-action-label">Actividad</span>
                        </button>
                    </div>
                </div>
                <div class="wa-chat-header-bot">
                    <div class="wa-chat-bot-control">
                        <span class="wa-bot-control-icon"><i class="fas fa-robot"></i></span>
                        <span class="wa-bot-control-copy">
                            <span class="wa-bot-control-title">Atención automática</span>
                            <span id="bot-status-text">{{ ($contact->bot_enabled ?? true) ? 'Activada' : 'Pausada' }}</span>
                        </span>
                        <label class="bot-toggle-switch" title="Activar o pausar las respuestas automáticas">
                            <input type="checkbox" id="bot-enabled-toggle"
                                   {{ ($contact->bot_enabled ?? true) ? 'checked' : '' }}
                                   data-contact-id="{{ $contact->id }}" aria-label="Atención automática">
                            <span class="bot-toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            <div id="wa-agent-handoff-banner" class="wa-agent-handoff-banner{{ $contact->needsAgent() ? '' : ' hidden' }}">
                <span><i class="fas fa-headset"></i> Este cliente solicita hablar con un asesor humano.</span>
                <button type="button" id="wa-dismiss-agent-btn">Marcar atendido</button>
            </div>
            <!-- Mensajes -->
            <div class="wa-chat-messages" id="chat-messages">
                @forelse($messages as $msg)
                    @php
                        $isIncoming = $msg->sender_type === 'client';
                        $senderKind = $msg->senderKind();
                        $senderLabel = $msg->senderBadgeLabel($contact->name ?: null);
                        $bubbleClass = $isIncoming ? 'wa-bubble-in' : 'wa-bubble-out';
                        if (!$isIncoming) {
                            $bubbleClass .= $senderKind === 'agent' ? ' wa-bubble-agent' : ' wa-bubble-bot';
                        }
                        $badgeIcon = match ($senderKind) {
                            'client' => 'user',
                            'agent' => 'headset',
                            default => 'robot',
                        };
                        $content = $msg->content;
                        $metadata = $msg->metadata ?? [];
                        $displayText = \App\Helpers\WhatsappMessageFormatter::displayText($content, $msg->type, $metadata);
                        $displayDesc = \App\Helpers\WhatsappMessageFormatter::displayDescription($content, $metadata);
                        $isInteractiveReply = \App\Helpers\WhatsappMessageFormatter::isInteractiveReply($content, $msg->type, $metadata);
                    @endphp
                    <div class="wa-message-wrapper {{ $isIncoming ? 'incoming' : 'outgoing' }}" data-message-id="{{ $msg->id }}" @if($isIncoming && $msg->message_id) data-whatsapp-message-id="{{ $msg->message_id }}" @endif>
                        <div class="wa-msg-col">
                            <div class="wa-msg-badge wa-msg-badge-{{ $senderKind }}">
                                <i class="fas fa-{{ $badgeIcon }}"></i>
                                <span>{{ $senderLabel }}</span>
                            </div>
                        <div class="{{ $bubbleClass }}">
                            @if($msg->type === 'image')
                                @php
                                    $imageUrl = route('admin.message.image', $msg->id);
                                    $imageCaption = \App\Helpers\WhatsappMessageFormatter::mediaCaption($content, $metadata);
                                @endphp
                                <div class="wa-image-card" data-image-message-id="{{ $msg->id }}">
                                    <button type="button" class="wa-image-preview-button" onclick="openImageViewer(this)" aria-label="Ampliar imagen">
                                        <img src="{{ $imageUrl }}" alt="Imagen enviada por {{ $senderLabel }}" loading="lazy" onload="handleImageLoad(this)" onerror="handleImageError(this, {{ $msg->id }})">
                                        <span class="wa-image-preview-action"><i class="fas fa-expand"></i> Ver imagen</span>
                                    </button>
                                    <div class="wa-image-placeholder" role="status">
                                        <span class="wa-image-placeholder-icon"><i class="far fa-image"></i></span>
                                        <strong>No se pudo cargar la imagen</strong>
                                        <small>El archivo puede haber expirado en WhatsApp o la conexión puede estar temporalmente interrumpida.</small>
                                        <button type="button" class="wa-image-retry" onclick="retryImageMessage(this, {{ $msg->id }})"><i class="fas fa-redo-alt"></i> Reintentar</button>
                                    </div>
                                    @if($imageCaption !== '')
                                        <div class="wa-bubble-content wa-image-caption">{{ $imageCaption }}</div>
                                    @endif
                                </div>
                            @elseif($msg->type === 'document')
                                @php
                                    $metadata = $msg->metadata ?? [];
                                    $filename = $metadata['filename'] ?? 'documento';
                                    $fileSize = isset($metadata['file_size']) ? $metadata['file_size'] : '';
                                    $fileExtension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'DOC';
                                @endphp
                                <div style="background: #202c33; border-radius: 8px; padding: 12px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px;">
                                    <div style="background: #f15c6d; border-radius: 8px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <span style="color: white; font-weight: 600; font-size: 12px;">{{ $fileExtension }}</span>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 14px; font-weight: 500; color: #e9edef; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $filename }}</div>
                                        <div style="font-size: 12px; color: #8696a0;">
                                            @if($fileSize)
                                                PDF • {{ $fileSize }}
                                            @else
                                                PDF
                                            @endif
                                        </div>
                                    </div>
                                    <button style="background: transparent; border: none; cursor: pointer; padding: 8px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #8696a0; transition: background 0.2s;" onmouseover="this.style.background='#313d45'" onmouseout="this.style.background='transparent'" title="Descargar">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                </div>
                            @elseif($isInteractiveReply)
                                <span class="wa-bubble-content">{{ $displayText }}</span>
                                @if($displayDesc)
                                    <div class="wa-bubble-content-block" style="font-size: 12px; color: #8696a0; margin-top: 2px;">{{ \Illuminate\Support\Str::limit($displayDesc, 120) }}</div>
                                @endif
                            @else
                                <span class="wa-bubble-content">{!! nl2br(e($displayText ?: $content)) !!}</span>
                            @endif
                            <div class="wa-message-time {{ $isIncoming ? 'incoming' : 'outgoing' }}">
                                <span>{{ $msg->created_at->format('H:i') }}</span>
                                @if(!$isIncoming)
                                    <span class="wa-message-status">
                                        <svg width="16" height="10" viewBox="0 0 16 10">
                                            <path d="M15.854 0.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L8.5 7.293l6.646-6.647a.5.5 0 0 1 .708 0z" fill="#53bdeb"/>
                                            <path d="M10.854 0.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L3.5 7.293l6.646-6.647a.5.5 0 0 1 .708 0z" fill="#53bdeb"/>
                                        </svg>
                                    </span>
                                @endif
                            </div>
                        </div>
                        </div>
                    </div>
                @empty
                    <div class="wa-empty-state">
                        <span class="wa-empty-state-icon"><i class="far fa-comments"></i></span>
                        <strong>Aún no hay mensajes</strong>
                        <span>Cuando el cliente escriba, la conversación aparecerá aquí y podrás responderle desde este panel.</span>
                    </div>
                @endforelse
            </div>
            <!-- Formulario de envío de mensajes -->
            <div class="wa-input-container" id="wa-input-container">
                <!-- Vista previa de imagen -->
                <div id="image-preview-container" class="wa-preview-container hidden">
                    <div class="wa-preview-box">
                        <img id="image-preview" src="" alt="Vista previa" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
                        <button type="button" id="remove-image-preview" class="wa-preview-remove">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <!-- Vista previa de audio -->
                <div id="audio-preview-container" class="wa-preview-container hidden">
                    <div class="wa-preview-box">
                        <div style="flex: 1;">
                            <div style="font-size: 14px; font-weight: 500; color: #e9edef; margin-bottom: 4px;" id="audio-preview-name">Audio grabado</div>
                            <div style="font-size: 12px; color: #8696a0;" id="audio-preview-duration">00:00</div>
                        </div>
                        <audio id="audio-preview-player" controls style="flex: 1; max-width: 200px; height: 32px;"></audio>
                        <button type="button" id="remove-audio-preview" class="wa-preview-remove">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <!-- Vista previa de documento -->
                <div id="document-preview-container" class="wa-preview-container hidden">
                    <div class="wa-preview-box">
                        <div style="background: #8b5cf6; border-radius: 8px; padding: 12px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z" fill="white"/>
                            </svg>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 14px; font-weight: 500; color: #e9edef; margin-bottom: 4px;" id="document-preview-name">Documento</div>
                            <div style="font-size: 12px; color: #8696a0;" id="document-preview-size">0 KB</div>
                        </div>
                        <button type="button" id="remove-document-preview" class="wa-preview-remove">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <!-- Panel de emojis: fuera del formulario para posicionarlo sobre el compositor. -->
                <div id="emoji-picker" class="wa-composer-popover hidden" role="dialog" aria-label="Seleccionar emoji">
                    <div class="wa-popover-header">
                        <div>
                            <span class="wa-popover-title"><i class="far fa-face-smile me-1"></i> Emojis</span>
                            <span class="wa-popover-subtitle">Puedes agregar varios antes de cerrar</span>
                        </div>
                        <button type="button" id="emoji-picker-close" class="wa-popover-close" aria-label="Cerrar emojis"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="wa-emoji-scroll">
                        <div class="wa-emoji-grid">
                                <button type="button" class="emoji-btn rounded p-1" data-emoji="😀" style="transition: background 0.15s; cursor: pointer;" onmouseover="this.style.background='#313d45'" onmouseout="this.style.background='transparent'">😀</button>
                                <button type="button" class="emoji-btn rounded p-1" data-emoji="😃" style="transition: background 0.15s; cursor: pointer;" onmouseover="this.style.background='#313d45'" onmouseout="this.style.background='transparent'">😃</button>
                                <button type="button" class="emoji-btn rounded p-1" data-emoji="😄" style="transition: background 0.15s; cursor: pointer;" onmouseover="this.style.background='#313d45'" onmouseout="this.style.background='transparent'">😄</button>
                                <button type="button" class="emoji-btn rounded p-1" data-emoji="😁" style="transition: background 0.15s; cursor: pointer;" onmouseover="this.style.background='#313d45'" onmouseout="this.style.background='transparent'">😁</button>
                                <button type="button" class="emoji-btn rounded p-1" data-emoji="😅" style="transition: background 0.15s; cursor: pointer;" onmouseover="this.style.background='#313d45'" onmouseout="this.style.background='transparent'">😅</button>
                                <button type="button" class="emoji-btn rounded p-1" data-emoji="😂" style="transition: background 0.15s; cursor: pointer;" onmouseover="this.style.background='#313d45'" onmouseout="this.style.background='transparent'">😂</button>
                                <button type="button" class="emoji-btn rounded p-1" data-emoji="🤣" style="transition: background 0.15s; cursor: pointer;" onmouseover="this.style.background='#313d45'" onmouseout="this.style.background='transparent'">🤣</button>
                                <button type="button" class="emoji-btn rounded p-1" data-emoji="😊" style="transition: background 0.15s; cursor: pointer;" onmouseover="this.style.background='#313d45'" onmouseout="this.style.background='transparent'">😊</button>
                                <button type="button" class="emoji-btn rounded p-1" data-emoji="😇" style="transition: background 0.15s; cursor: pointer;" onmouseover="this.style.background='#313d45'" onmouseout="this.style.background='transparent'">😇</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🙂">🙂</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🙃">🙃</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😉">😉</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😌">😌</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😍">😍</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🥰">🥰</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😘">😘</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😗">😗</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😙">😙</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😚">😚</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😋">😋</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😛">😛</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😝">😝</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😜">😜</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤪">🤪</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤨">🤨</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🧐">🧐</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤓">🤓</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😎">😎</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤩">🤩</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🥳">🥳</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😏">😏</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😒">😒</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😞">😞</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😔">😔</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😟">😟</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😕">😕</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🙁">🙁</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😣">😣</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😖">😖</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😫">😫</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😩">😩</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🥺">🥺</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😢">😢</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😭">😭</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😤">😤</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😠">😠</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😡">😡</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤬">🤬</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤯">🤯</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😳">😳</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🥵">🥵</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🥶">🥶</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😱">😱</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😨">😨</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😰">😰</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😥">😥</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😓">😓</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤗">🤗</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤔">🤔</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤭">🤭</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤫">🤫</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤥">🤥</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😶">😶</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😐">😐</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😑">😑</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😬">😬</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🙄">🙄</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😯">😯</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😦">😦</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😧">😧</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😮">😮</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😲">😲</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🥱">🥱</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😴">😴</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤤">🤤</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😪">😪</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😵">😵</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤐">🤐</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🥴">🥴</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤢">🤢</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤮">🤮</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤧">🤧</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😷">😷</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤒">🤒</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤕">🤕</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤑">🤑</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤠">🤠</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😈">😈</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="👿">👿</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="👹">👹</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="👺">👺</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤡">🤡</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="💩">💩</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="👻">👻</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="💀">💀</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="☠️">☠️</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="👽">👽</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="👾">👾</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🤖">🤖</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🎃">🎃</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😺">😺</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😸">😸</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😹">😹</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😻">😻</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😼">😼</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😽">😽</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🙀">🙀</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😿">😿</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="😾">😾</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="👍">👍</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="👎">👎</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="❤️">❤️</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🔥">🔥</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🎉">🎉</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="✅">✅</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="❌">❌</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="👏">👏</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🙏">🙏</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="💪">💪</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🎯">🎯</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="💰">💰</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🚀">🚀</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="⭐">⭐</button>
                                <button type="button" class="emoji-btn hover:bg-gray-100 rounded p-1" data-emoji="🎁">🎁</button>
                        </div>
                    </div>
                </div>
                <form id="send-message-form">
                    @csrf
                    <input type="hidden" name="contact_id" value="{{ $contact->id }}">
                    <input type="file" id="image-input" name="image" accept="image/*" class="hidden">
                    <input type="file" id="document-input" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar" class="hidden">
                    <input type="hidden" id="current-contact-id" value="{{ $contact->id }}">
                    <input type="hidden" id="current-contact-name" value="{{ $contact->name ?: 'Cliente' }}">
                    <input type="hidden" id="last-inbound-wamid" value="{{ $lastInboundWamid ?? '' }}">
                    @if(empty($typingAvailable))
                    <p id="typing-unavailable-hint" class="text-center">
                        <i class="fas fa-circle-info me-1"></i> El indicador “escribiendo…” se habilitará cuando el cliente envíe un mensaje dentro de la ventana de 24 horas.
                    </p>
                    @endif

                    <div class="wa-chat-input-area">
                        <!-- Botón adjuntar (fuera del campo, estilo WhatsApp) -->
                        <button type="button" id="attach-button" class="wa-input-attach-outside" title="Adjuntar archivo" aria-label="Adjuntar archivo" aria-expanded="false" aria-controls="attach-menu">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z" fill="currentColor"/>
                            </svg>
                        </button>

                        <!-- Menú de adjuntar -->
                        <div id="attach-menu" class="wa-composer-popover wa-attach-menu hidden" role="menu" aria-label="Adjuntar al mensaje">
                            <div class="wa-attach-heading">Adjuntar al mensaje</div>
                            <button type="button" id="image-button" class="wa-attach-option" role="menuitem">
                                <div class="wa-attach-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" fill="white"/>
                        </svg>
                                </div>
                                <span class="wa-attach-copy"><strong>Fotos y videos</strong><small>Imágenes de hasta 5 MB</small></span>
                    </button>
                            <button type="button" id="document-button" class="wa-attach-option" role="menuitem">
                                <div class="wa-attach-icon document">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z" fill="white"/>
                        </svg>
                                </div>
                                <span class="wa-attach-copy"><strong>Documento</strong><small>PDF, Word, Excel y otros archivos</small></span>
                    </button>
                        </div>

                        <div class="wa-input-wrapper">
                            <textarea
                                id="message-input"
                                name="message"
                                rows="1"
                                placeholder="Escribe una respuesta..."
                                class="wa-input-textarea"
                                aria-label="Mensaje para el cliente"
                            ></textarea>
                            <button type="button" id="emoji-button" class="wa-input-button" title="Agregar emoji" aria-label="Agregar emoji" aria-expanded="false" aria-controls="emoji-picker">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 18c-4.418 0-8-3.582-8-8s3.582-8 8-8 8 3.582 8 8-3.582 8-8 8z" fill="currentColor"/>
                                    <path d="M8.5 10.5c-.828 0-1.5-.895-1.5-2s.672-2 1.5-2 1.5.895 1.5 2-.672 2-1.5 2zm7 0c-.828 0-1.5-.895-1.5-2s.672-2 1.5-2 1.5.895 1.5 2-.672 2-1.5 2zM12 18c2.28 0 4.22-1.66 5-4H7c.78 2.34 2.72 4 5 4z" fill="currentColor"/>
                                </svg>
                            </button>
                        </div>

                        <button type="submit" id="send-button" class="wa-send-button" title="Enviar mensaje" aria-label="Enviar mensaje">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <path d="M1.101 21.757L23.8 12.028 1.101 2.3l.011 7.912 13.623 1.816-13.623 1.817-.011 7.912z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                    <div class="wa-composer-hint"><strong>Enter</strong> envía · <strong>Shift + Enter</strong> agrega otra línea</div>
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Stats Panel (panel deslizable, oculto por defecto) -->
<div class="stats-panel" id="stats-panel">
    <div class="stats-panel-header">
        <h3><i class="fas fa-chart-line me-2"></i>Estadísticas del chat</h3>
        <button type="button" class="stats-panel-close" id="stats-panel-close" title="Cerrar">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-title">
                📊 Total Mensajes
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Total de mensajes intercambiados con este contacto desde el inicio. Incluye todos los mensajes enviados y recibidos.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-total-messages">{{ $stats['totalMessages'] ?? 0 }}</div>
            <div class="stat-change {{ ($stats['messageGrowth'] ?? 0) >= 0 ? 'positive' : 'negative' }}" id="stat-message-growth">
                {{ ($stats['messageGrowth'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($stats['messageGrowth'] ?? 0, 1) }}% vs mes anterior
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                ⚡ Última Actividad
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Tiempo transcurrido desde el último mensaje enviado o recibido en este chat. Muestra cuándo fue la última interacción.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-last-activity" style="font-size: 0.9rem;">{{ $stats['lastActivity'] ?? 'Nunca' }}</div>
            <div class="stat-change positive" id="stat-last-activity-date">{{ $stats['lastActivityDate'] ?? 'N/A' }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                ⏱️ Tiempo Promedio Respuesta
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Tiempo promedio que tarda el sistema en responder a los mensajes del cliente. Calculado desde que el cliente envía un mensaje hasta que el sistema responde.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-avg-time">{{ $stats['avgResponseTime'] ?? '0m' }}</div>
            <div class="stat-change negative">Últimos 30 días</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                💬 Enviados vs Recibidos
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Comparación entre mensajes enviados por el sistema y mensajes recibidos del cliente. El ratio indica si se envían más mensajes de los que se reciben.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-sent-received">{{ ($stats['sentMessages'] ?? 0) . ' / ' . ($stats['receivedMessages'] ?? 0) }}</div>
            <div class="stat-change positive" id="stat-ratio">Ratio: {{ $stats['sentReceivedRatio'] ?? 0 }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                📈 Tasa Respuesta Cliente
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Porcentaje de mensajes del sistema a los que el cliente responde dentro de 24 horas. Indica el nivel de engagement del cliente.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-client-response-rate">{{ $stats['clientResponseRate'] ?? '0%' }}</div>
            <div class="stat-change positive">Últimos 30 días</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                🕐 Hora Pico
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Hora del día en la que hay mayor actividad de mensajes. Útil para identificar los mejores momentos para contactar al cliente.</span>
                </span>
        </div>
            <div class="stat-value" id="stat-peak-hour">{{ $stats['peakHour'] ?? 'N/A' }}</div>
            <div class="stat-change positive" id="stat-active-day">Día: {{ $stats['mostActiveDay'] ?? 'N/A' }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                📝 Longitud Promedio
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Número promedio de caracteres en los mensajes enviados por el cliente. Ayuda a entender el estilo de comunicación del cliente.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-avg-length">{{ $stats['avgMessageLength'] ?? '0 caracteres' }}</div>
            <div class="stat-change positive">Mensajes del cliente</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                💭 Conversaciones
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Número total de sesiones de conversación. Se cuenta como nueva conversación cuando pasan más de 2 horas sin mensajes.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-conversations">{{ $stats['conversations'] ?? 0 }}</div>
            <div class="stat-change positive">Total histórico</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                ⏳ Tiempo Entre Mensajes
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Tiempo promedio que pasa el cliente entre enviar un mensaje y el siguiente. Indica la velocidad de respuesta del cliente.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-time-between">{{ $stats['avgTimeBetweenMessages'] ?? '0m' }}</div>
            <div class="stat-change positive">Promedio del cliente</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                📅 Frecuencia Diaria
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Promedio de mensajes por día activo. Calculado dividiendo el total de mensajes entre los días en los que hubo actividad.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-frequency">{{ $stats['frequencyPerDay'] ?? 0 }}</div>
            <div class="stat-change positive">Mensajes por día activo</div>
        </div>
    </div>

    <!-- Toggle para vista global vs contacto -->
    <div style="margin: 10px 15px; display: flex; align-items: center; gap: 15px; padding: 10px; background: #202c33; border-radius: 8px;">
        <span style="color: #e9edef; font-weight: 500;">Vista:</span>
        <button id="view-toggle" style="padding: 8px 16px; background: #005c4b; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.2s;" onclick="toggleView()">
            <span id="view-mode-text">Contacto Actual</span>
        </button>
        <span id="view-indicator" style="color: #8696a0; font-size: 0.9rem;">Mostrando estadísticas del contacto seleccionado</span>
    </div>

    <div class="charts-container">
        <div class="chart-card">
            <canvas id="messagesChart"></canvas>
        </div>
        <div class="chart-card">
            <canvas id="responseTimeChart"></canvas>
        </div>
        <div class="chart-card">
            <canvas id="messageTypesChart"></canvas>
        </div>
        <div class="chart-card">
            <canvas id="topicsChart"></canvas>
        </div>
    </div>

    <!-- Indicadores adicionales en tarjetas -->
    <div class="stats-grid" style="margin-top: 20px;">
        <div class="stat-card">
            <div class="stat-title">
                📤 Mensajes Enviados
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Total de mensajes enviados por el sistema al cliente en los últimos 30 días.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-sent-only">{{ $stats['sentMessages'] ?? 0 }}</div>
            <div class="stat-change positive">Últimos 30 días</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                📥 Mensajes Recibidos
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Total de mensajes recibidos del cliente en los últimos 30 días.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-received-only">{{ $stats['receivedMessages'] ?? 0 }}</div>
            <div class="stat-change positive">Últimos 30 días</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                📊 Ratio Enviado/Recibido
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Proporción entre mensajes enviados y recibidos. Un valor mayor a 1 indica que se envían más mensajes de los que se reciben.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-ratio-only">{{ $stats['sentReceivedRatio'] ?? 0 }}</div>
            <div class="stat-change {{ ($stats['sentReceivedRatio'] ?? 0) > 1 ? 'positive' : 'negative' }}">
                {{ ($stats['sentReceivedRatio'] ?? 0) > 1 ? 'Más enviados' : 'Más recibidos' }}
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                🎯 Mensajes con Botones
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Porcentaje de mensajes que incluyen botones interactivos o listas desplegables. Los mensajes interactivos mejoran la experiencia del usuario.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-buttons">{{ $stats['buttonMessagesRate'] ?? '0%' }}</div>
            <div class="stat-change positive">Últimos 30 días</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                💬 Tasa de Interacción
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Porcentaje de mensajes enviados por el sistema que generan una respuesta del cliente. Mide la efectividad de la comunicación.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-interaction">{{ $stats['interactionRate'] ?? '0%' }}</div>
            <div class="stat-change positive">Últimos 30 días</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">
                📈 Crecimiento Mensajes
                <span class="stat-info-icon" title="Información">
                    ℹ️
                    <span class="stat-tooltip">Porcentaje de crecimiento en el número de mensajes comparado con el mes anterior. Un valor positivo indica aumento en la actividad.</span>
                </span>
            </div>
            <div class="stat-value" id="stat-growth-value">{{ ($stats['messageGrowth'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($stats['messageGrowth'] ?? 0, 1) }}%</div>
            <div class="stat-change {{ ($stats['messageGrowth'] ?? 0) >= 0 ? 'positive' : 'negative' }}">vs mes anterior</div>
        </div>
    </div>

    <!-- Tipos de Mensajes Section -->
    <div class="chart-card mt-4" id="message-types-list">
        <h3 class="text-lg font-semibold mb-3" style="color: #e9edef;">Tipos de Mensajes</h3>
        <div class="topics-list" id="topics-list">
            @if(isset($stats['messageTypes']) && count($stats['messageTypes']) > 0)
                @foreach($stats['messageTypes'] as $type => $count)
            <div class="topic-item">
                    <span class="topic-name">{{ ucfirst($type ?: 'texto') }}</span>
                    <span class="topic-count">{{ $count }}</span>
            </div>
            @endforeach
            @else
                <div class="topic-item">
                    <span class="topic-name">Sin datos disponibles</span>
                    <span class="topic-count">0</span>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    // Scroll automático al último mensaje
    window.onload = function() {
        var chat = document.getElementById('chat-messages');
        if(chat) chat.scrollTop = chat.scrollHeight;
    };

    // Panel de estadísticas
    (function() {
        const statsPanel = document.getElementById('stats-panel');
        const statsToggleBtn = document.getElementById('stats-toggle-btn');
        const statsCloseBtn = document.getElementById('stats-panel-close');

        function toggleStats(open) {
            if (!statsPanel) return;
            const shouldOpen = typeof open === 'boolean' ? open : !statsPanel.classList.contains('is-open');
            statsPanel.classList.toggle('is-open', shouldOpen);
            statsToggleBtn?.classList.toggle('is-active', shouldOpen);
        }

        statsToggleBtn?.addEventListener('click', () => toggleStats());
        statsCloseBtn?.addEventListener('click', () => toggleStats(false));
    })();

    // Indicador "escribiendo..." en WhatsApp del cliente
    (function() {
        let lastTypingSentAt = 0;
        let typingRefreshTimer = null;
        const TYPING_MIN_INTERVAL = 4000;
        const TYPING_REFRESH_INTERVAL = 12000;
        let lastInboundWhatsappId = document.getElementById('last-inbound-wamid')?.value || '';

        window.updateLastInboundWhatsappId = function(wamid) {
            if (!wamid || typeof wamid !== 'string' || !wamid.startsWith('wamid.')) return;
            lastInboundWhatsappId = wamid;
            const el = document.getElementById('last-inbound-wamid');
            if (el) el.value = wamid;
            const hint = document.getElementById('typing-unavailable-hint');
            if (hint) hint.remove();
        };

        window.sendWhatsAppTypingIndicator = function() {
            const contactId = document.getElementById('current-contact-id')?.value;
            if (!contactId) return;

            if (!lastInboundWhatsappId) {
                const incoming = document.querySelector('#chat-messages .wa-message-wrapper.incoming[data-whatsapp-message-id]');
                if (incoming) {
                    updateLastInboundWhatsappId(incoming.getAttribute('data-whatsapp-message-id'));
                }
            }
            if (!lastInboundWhatsappId) return;

            const now = Date.now();
            if (now - lastTypingSentAt < TYPING_MIN_INTERVAL) return;

            lastTypingSentAt = now;
            const payload = { contact_id: parseInt(contactId, 10) };
            if (lastInboundWhatsappId) {
                payload.whatsapp_message_id = lastInboundWhatsappId;
            }
            fetch('{{ route('admin.chat.typing') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            }).catch(function() {});
        };

        window.scheduleTypingRefresh = function() {
            clearTimeout(typingRefreshTimer);
            typingRefreshTimer = setTimeout(function() {
                sendWhatsAppTypingIndicator();
                scheduleTypingRefresh();
            }, TYPING_REFRESH_INTERVAL);
        };

        window.stopTypingRefresh = function() {
            clearTimeout(typingRefreshTimer);
        };
    })();

    // Búsqueda de contactos en sidebar
    window.applyContactSearchFilter = function() {
        const searchInput = document.getElementById('contact-search');
        const contactsList = document.getElementById('wa-sidebar-contacts');
        if (!searchInput || !contactsList) return;

        const query = searchInput.value.trim().toLowerCase();
        contactsList.querySelectorAll('.wa-sidebar-contact').forEach(function(item) {
            const name = (item.querySelector('.wa-sidebar-name')?.textContent || '').toLowerCase();
            const phone = (item.querySelector('.wa-sidebar-phone')?.textContent || '').toLowerCase();
            const match = !query || name.includes(query) || phone.includes(query);
            item.style.display = match ? '' : 'none';
        });
    };

    (function() {
        const searchInput = document.getElementById('contact-search');
        if (!searchInput) return;
        searchInput.addEventListener('input', window.applyContactSearchFilter);
    })();

    // Variables globales para polling
    let pollingInterval = null;
    let lastMessageTimestamp = null;
    let lastMessageId = 0;
    let currentPollingContactId = null;

    function resolveSenderMeta(messageData, isIncoming) {
        if (isIncoming) {
            const clientName = document.getElementById('current-contact-name')?.value || 'Cliente';
            return { kind: 'client', label: clientName, icon: 'user', bubbleExtra: '' };
        }

        if (messageData.sender_kind) {
            const kind = messageData.sender_kind;
            return {
                kind,
                label: messageData.sender_label || (kind === 'agent' ? 'Asesor' : 'Bot'),
                icon: kind === 'agent' ? 'headset' : 'robot',
                bubbleExtra: kind === 'agent' ? ' wa-bubble-agent' : ' wa-bubble-bot',
            };
        }

        if (messageData.admin_sender_name || messageData.sender_type === 'humano') {
            return {
                kind: 'agent',
                label: messageData.admin_sender_name || messageData.sender_label || 'Asesor',
                icon: 'headset',
                bubbleExtra: ' wa-bubble-agent',
            };
        }

        return {
            kind: 'bot',
            label: messageData.sender_label || 'Bot',
            icon: 'robot',
            bubbleExtra: ' wa-bubble-bot',
        };
    }

    function buildMessageBadgeHtml(meta) {
        return `<div class="wa-msg-badge wa-msg-badge-${meta.kind}"><i class="fas fa-${meta.icon}"></i><span>${escapeHtmlGlobal(meta.label)}</span></div>`;
    }

    function parseStructuredMessageContent(content) {
        if (!content || typeof content !== 'string') return null;
        const trimmed = content.trim();
        if (!trimmed.startsWith('{') && !trimmed.startsWith('[')) return null;
        try {
            const parsed = JSON.parse(trimmed);
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (error) {
            return null;
        }
    }

    function getVisibleMediaCaption(messageData) {
        const metadata = messageData.metadata || {};
        if (metadata.caption) return String(metadata.caption).trim();

        const parsed = parseStructuredMessageContent(messageData.content);
        if (parsed) return parsed.caption ? String(parsed.caption).trim() : '';

        return typeof messageData.content === 'string' ? messageData.content.trim() : '';
    }

    function buildImageMessageHtml(messageData) {
        const messageId = Number.parseInt(messageData.id, 10);
        if (!Number.isFinite(messageId)) return '';

        const imageUrl = `/admin/messages/${messageId}/image`;
        const caption = getVisibleMediaCaption(messageData);
        return `<div class="wa-image-card" data-image-message-id="${messageId}">
            <button type="button" class="wa-image-preview-button" onclick="openImageViewer(this)" aria-label="Ampliar imagen">
                <img src="${imageUrl}" alt="Imagen enviada en el chat" loading="lazy" onload="handleImageLoad(this)" onerror="handleImageError(this, ${messageId})">
                <span class="wa-image-preview-action"><i class="fas fa-expand"></i> Ver imagen</span>
            </button>
            <div class="wa-image-placeholder" role="status">
                <span class="wa-image-placeholder-icon"><i class="far fa-image"></i></span>
                <strong>No se pudo cargar la imagen</strong>
                <small>El archivo puede haber expirado en WhatsApp o la conexión puede estar temporalmente interrumpida.</small>
                <button type="button" class="wa-image-retry" onclick="retryImageMessage(this, ${messageId})"><i class="fas fa-redo-alt"></i> Reintentar</button>
            </div>
            ${caption ? `<div class="wa-bubble-content wa-image-caption">${escapeHtmlGlobal(caption)}</div>` : ''}
        </div>`;
    }

    window.handleImageError = function(imgElement) {
        const card = imgElement?.closest('.wa-image-card');
        if (!card) return;
        card.querySelector('.wa-image-preview-button')?.setAttribute('hidden', 'hidden');
        card.querySelector('.wa-image-placeholder')?.classList.add('is-visible');
    };

    window.handleImageLoad = function(imgElement) {
        const card = imgElement?.closest('.wa-image-card');
        if (!card) return;
        card.querySelector('.wa-image-preview-button')?.removeAttribute('hidden');
        card.querySelector('.wa-image-placeholder')?.classList.remove('is-visible');
    };

    window.retryImageMessage = function(button, messageId) {
        const card = button?.closest('.wa-image-card');
        const image = card?.querySelector('.wa-image-preview-button img');
        if (!card || !image) return;

        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando';
        image.src = `/admin/messages/${Number.parseInt(messageId, 10)}/image?retry=${Date.now()}`;
        setTimeout(() => {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-redo-alt"></i> Reintentar';
        }, 1200);
    };

    function ensureImageLightbox() {
        let lightbox = document.getElementById('wa-image-lightbox');
        if (lightbox) return lightbox;

        lightbox = document.createElement('div');
        lightbox.id = 'wa-image-lightbox';
        lightbox.className = 'wa-image-lightbox';
        lightbox.setAttribute('role', 'dialog');
        lightbox.setAttribute('aria-modal', 'true');
        lightbox.setAttribute('aria-label', 'Vista ampliada de imagen');
        lightbox.innerHTML = '<button type="button" class="wa-image-lightbox-close" aria-label="Cerrar"><i class="fas fa-times"></i></button><img src="" alt="Imagen ampliada">';
        lightbox.addEventListener('click', (event) => {
            if (event.target === lightbox || event.target.closest('.wa-image-lightbox-close')) {
                window.closeImageViewer();
            }
        });
        document.body.appendChild(lightbox);
        return lightbox;
    }

    window.openImageViewer = function(button) {
        const source = button?.querySelector('img');
        if (!source?.src) return;
        const lightbox = ensureImageLightbox();
        lightbox.querySelector('img').src = source.src;
        lightbox.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        lightbox.querySelector('.wa-image-lightbox-close')?.focus();
    };

    window.closeImageViewer = function() {
        const lightbox = document.getElementById('wa-image-lightbox');
        if (!lightbox) return;
        lightbox.classList.remove('is-open');
        lightbox.querySelector('img').src = '';
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') window.closeImageViewer();
    });

    // Función para agregar mensajes a la vista (debe estar fuera de DOMContentLoaded para ser accesible globalmente)
    function addMessageToView(messageData, isIncoming = false) {
        const chatMessages = document.getElementById('chat-messages');
        if (!chatMessages) {
            console.error('Chat messages container not found');
            return;
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = `wa-message-wrapper ${isIncoming ? 'incoming' : 'outgoing'}`;
        messageDiv.setAttribute('data-message-id', messageData.id || Date.now());
        if (isIncoming && messageData.whatsapp_message_id) {
            messageDiv.setAttribute('data-whatsapp-message-id', messageData.whatsapp_message_id);
            if (typeof updateLastInboundWhatsappId === 'function') {
                updateLastInboundWhatsappId(messageData.whatsapp_message_id);
            }
        }

        // Usar la fecha del mensaje si está disponible, sino la fecha actual
        const messageDate = messageData.created_at ? new Date(messageData.created_at) : new Date();
        const formattedDate = messageDate.toLocaleDateString('es-ES', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        const timeOnly = messageData.created_at_formatted || messageDate.toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit'
        });

        let contentHtml = '';

        // Si tiene imagen
        if (messageData.type === 'image' && messageData.id) {
            contentHtml += buildImageMessageHtml(messageData);
        } else if (messageData.image_url || messageData.image_data) {
            const imageUrl = messageData.image_url || (messageData.image_data ? 'data:image/jpeg;base64,' + messageData.image_data : '');
            if (imageUrl) {
                contentHtml += `<img src="${imageUrl}" alt="Imagen" class="max-w-full h-auto rounded-lg mb-2" style="max-height: 300px; cursor: pointer;" onclick="window.open(this.src, '_blank')">`;
            }
        }

        // Si tiene documento
        if (messageData.type === 'document') {
            const filename = messageData.filename || messageData.content || 'documento';
            contentHtml += `<div class="bg-gray-200 rounded-lg p-4 mb-2 text-center text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="text-sm">📄 ${escapeHtmlGlobal(filename)}</span>
                </div>`;
        }

        // Si tiene mensaje de texto
        if (messageData.content && messageData.type !== 'image') {
            contentHtml += `<span class="wa-bubble-content break-all">${escapeHtmlGlobal(messageData.content)}</span>`;
        }

        const senderMeta = resolveSenderMeta(messageData, isIncoming);
        const bubbleClass = isIncoming ? 'wa-bubble-in' : ('wa-bubble-out' + senderMeta.bubbleExtra);
        const statusHtml = !isIncoming
            ? `<span class="wa-message-status"><svg width="16" height="10" viewBox="0 0 16 10"><path d="M15.854 0.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L8.5 7.293l6.646-6.647a.5.5 0 0 1 .708 0z" fill="#53bdeb"/><path d="M10.854 0.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L3.5 7.293l6.646-6.647a.5.5 0 0 1 .708 0z" fill="#53bdeb"/></svg></span>`
            : '';

        messageDiv.innerHTML = `
            <div class="wa-msg-col">
                ${buildMessageBadgeHtml(senderMeta)}
                <div class="${bubbleClass}">
                    ${contentHtml}
                    <div class="wa-message-time ${isIncoming ? 'incoming' : 'outgoing'}">
                        <span>${timeOnly}</span>
                        ${statusHtml}
                    </div>
                </div>
            </div>
        `;

        chatMessages.appendChild(messageDiv);
    }

    // Función helper para escape HTML (versión global)
    function escapeHtmlGlobal(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Sistema de polling para obtener nuevos mensajes automáticamente
    function startPolling(contactId) {
        // Detener polling anterior si existe
        stopPolling();

        currentPollingContactId = contactId;

        // Obtener el timestamp del último mensaje visible
        const chatMessages = document.getElementById('chat-messages');
        if (chatMessages) {
            const lastMessage = chatMessages.querySelector('[data-message-id]');
            if (lastMessage) {
                lastMessageId = parseInt(lastMessage.getAttribute('data-message-id')) || 0;
            }
        }

        // Iniciar polling cada 2 segundos
        pollingInterval = setInterval(() => {
            if (currentPollingContactId) {
                checkForNewMessages(currentPollingContactId);
            }
        }, 2000); // Polling cada 2 segundos

        // Hacer una verificación inmediata
        checkForNewMessages(contactId);
    }

    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
        currentPollingContactId = null;
    }

    function checkForNewMessages(contactId) {
        if (!contactId) return;

        const params = new URLSearchParams();
        if (lastMessageId > 0) {
            params.append('last_message_id', lastMessageId);
        }
        if (lastMessageTimestamp) {
            params.append('last_timestamp', lastMessageTimestamp);
        }

        fetch(`/admin/chats/${contactId}/new-messages?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Error parseando respuesta:', text);
                return { success: false };
            }
        }))
        .then(data => {
            if (data.success && data.messages && data.messages.length > 0) {
                const chatMessages = document.getElementById('chat-messages');
                const existingMessageIds = new Set();

                // Obtener IDs de mensajes existentes
                chatMessages.querySelectorAll('[data-message-id]').forEach(msg => {
                    existingMessageIds.add(parseInt(msg.getAttribute('data-message-id')));
                });

                // Agregar solo mensajes nuevos
                let hasNewMessages = false;
                data.messages.forEach(messageData => {
                    const msgId = parseInt(messageData.id);
                    if (!existingMessageIds.has(msgId)) {
                        addMessageToView(messageData, messageData.sender_type === 'client');
                        if (messageData.sender_type === 'client' && messageData.whatsapp_message_id) {
                            updateLastInboundWhatsappId(messageData.whatsapp_message_id);
                        }
                        hasNewMessages = true;

                        // Actualizar último mensaje
                        if (msgId > lastMessageId) {
                            lastMessageId = msgId;
                        }
                        if (!lastMessageTimestamp || new Date(messageData.created_at) > new Date(lastMessageTimestamp)) {
                            lastMessageTimestamp = messageData.created_at;
                        }
                    }
                });

                // Guardar el último mensaje visto cuando llegan nuevos mensajes
                if (hasNewMessages && data.messages && data.messages.length > 0) {
                    const lastClientMessage = data.messages.filter(msg => msg.sender_type === 'client').pop();
                    if (lastClientMessage) {
                        localStorage.setItem(`last_seen_${contactId}`, lastClientMessage.created_at);
                    }
                }

                // Scroll al final si hay mensajes nuevos y el usuario está cerca del final
                if (hasNewMessages) {
                    updateContactsList();

                    setTimeout(() => {
                        const chatMessages = document.getElementById('chat-messages');
                        if (chatMessages) {
                            const isNearBottom = chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 200;
                            if (isNearBottom) {
                                chatMessages.scrollTop = chatMessages.scrollHeight;
                            }
                        }
                    }, 100);
                }
            }
        })
        .catch(error => {
            console.error('Error obteniendo nuevos mensajes:', error);
        });
    }

    // Scroll infinito (cargar más mensajes al llegar arriba)
    document.addEventListener('DOMContentLoaded', function() {
        var chat = document.getElementById('chat-messages');
        if(chat) {
            chat.addEventListener('scroll', function() {
                if(chat.scrollTop === 0) {
                    // Aquí puedes hacer una petición AJAX para cargar más mensajes
                    // y agregarlos al principio del contenedor
                }
            });
        }

        // Manejar envío de mensajes
        const messageForm = document.getElementById('send-message-form');
        const messageInput = document.getElementById('message-input');
        const sendButton = document.getElementById('send-button');

        // Verificar que los elementos existan - SI NO EXISTEN, SALIR
        if (!messageForm || !messageInput || !sendButton) {
            console.error('No se encontraron los elementos del formulario de envío');
            return; // Salir si no existen los elementos críticos
        }

        // VARIABLES GLOBALES PARA EL FORMULARIO
        let selectedImageFile = null;
        let selectedDocumentFile = null;

        function updateSendButtonState() {
            const hasContent = messageInput.value.trim().length > 0 || selectedImageFile || selectedDocumentFile;
            sendButton.classList.toggle('has-text', hasContent);
        }

        // REGISTRAR EL EVENT LISTENER DEL FORMULARIO PRIMERO - CRÍTICO
        messageForm.addEventListener('submit', function(e) {
            stopTypingRefresh();
            sendWhatsAppTypingIndicator();
            console.log('=== INICIO ENVÍO MENSAJE ===');
            console.log('1. Formulario submit interceptado', e);
            e.preventDefault();
            e.stopPropagation();
            console.log('2. preventDefault() ejecutado');

            const message = messageInput.value.trim();
            const hasMessage = message.length > 0;
            const hasImage = selectedImageFile !== null;
            const hasDocument = selectedDocumentFile !== null;

            console.log('3. Validación de contenido:', {
                message: message,
                hasMessage: hasMessage,
                hasImage: hasImage,
                hasDocument: hasDocument,
                messageLength: message.length
            });

            if (!hasMessage && !hasImage && !hasDocument) {
                console.warn('4. ❌ No hay contenido para enviar - ABORTANDO');
                return false;
            }

            const contactIdInput = document.querySelector('input[name="contact_id"]');
            const csrfTokenInput = document.querySelector('input[name="_token"]');

            console.log('5. Buscando campos del formulario:', {
                contactIdInput: contactIdInput ? 'encontrado' : 'NO ENCONTRADO',
                csrfTokenInput: csrfTokenInput ? 'encontrado' : 'NO ENCONTRADO'
            });

            if (!contactIdInput || !csrfTokenInput) {
                console.error('6. ❌ No se encontraron los campos necesarios del formulario');
                alert('Error: No se pudo obtener la información del formulario');
                return false;
            }

            const contactId = contactIdInput.value;
            const csrfToken = csrfTokenInput.value;

            console.log('6. Valores obtenidos:', {
                contactId: contactId,
                csrfToken: csrfToken ? 'presente (' + csrfToken.substring(0, 10) + '...)' : 'NO ENCONTRADO'
            });

            if (!contactId) {
                console.error('6a. ❌ No se ha seleccionado un contacto');
                alert('Error: No se ha seleccionado un contacto');
                return false;
            }

            // Deshabilitar el botón mientras se envía
            console.log('7. Deshabilitando botón de envío');
            sendButton.disabled = true;
            const originalButtonContent = sendButton.innerHTML;
            sendButton.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="animate-spin"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

            // Preparar FormData para enviar archivos
            console.log('8. Preparando FormData');
            const formData = new FormData();
            formData.append('contact_id', contactId);
            formData.append('_token', csrfToken);
            if (hasMessage) {
                formData.append('message', message);
                console.log('   - Mensaje agregado:', message.substring(0, 50) + (message.length > 50 ? '...' : ''));
            }
            if (hasImage) {
                formData.append('image', selectedImageFile);
                console.log('   - Imagen agregada:', selectedImageFile.name, selectedImageFile.size, 'bytes');
            }
            if (hasDocument) {
                formData.append('document', selectedDocumentFile);
                console.log('   - Documento agregado:', selectedDocumentFile.name, selectedDocumentFile.size, 'bytes');
            }

            const url = '{{ route("admin.chat.send") }}';
            console.log('9. Preparando petición fetch:', {
                url: url,
                method: 'POST',
                contactId: contactId,
                hasMessage: hasMessage,
                hasImage: hasImage,
                hasDocument: hasDocument
            });

            console.log('10. Iniciando fetch...');
            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('11. ✅ Respuesta HTTP recibida:', {
                    status: response.status,
                    statusText: response.statusText,
                    ok: response.ok,
                    headers: Object.fromEntries(response.headers.entries())
                });

                // Intentar parsear la respuesta como JSON
                return response.text().then(text => {
                    console.log('12. Texto de respuesta recibido:', text.substring(0, 200) + (text.length > 200 ? '...' : ''));
                    try {
                        const parsed = JSON.parse(text);
                        console.log('13. ✅ JSON parseado correctamente:', parsed);
                        return parsed;
                    } catch (e) {
                        console.error('13. ❌ Error parseando JSON:', e);
                        console.error('   Texto recibido:', text);
                        throw new Error('La respuesta del servidor no es válida');
                    }
                }).then(data => {
                    if (!response.ok) {
                        console.error('14. ❌ Respuesta HTTP no OK:', {
                            status: response.status,
                            data: data
                        });
                        throw new Error(data.message || 'Error en la respuesta del servidor');
                    }
                    console.log('14. ✅ Respuesta OK, datos:', data);
                    return data;
                });
            })
            .then(data => {
                console.log('15. Procesando respuesta exitosa:', data);
                if (data.success) {
                    console.log('16. ✅ Mensaje enviado exitosamente');
                    // Limpiar el input
                    messageInput.value = '';
                    messageInput.style.height = 'auto';

                    // Limpiar archivos
                    selectedImageFile = null;
                    selectedDocumentFile = null;
                    const imageInput = document.getElementById('image-input');
                    const documentInput = document.getElementById('document-input');
                    const imagePreviewContainer = document.getElementById('image-preview-container');
                    const documentPreviewContainer = document.getElementById('document-preview-container');
                    const imagePreview = document.getElementById('image-preview');
                    if (imageInput) imageInput.value = '';
                    if (documentInput) documentInput.value = '';
                    if (imagePreviewContainer) imagePreviewContainer.classList.add('hidden');
                    if (documentPreviewContainer) documentPreviewContainer.classList.add('hidden');
                    updateSendButtonState();
                    if (imagePreview) imagePreview.src = '';

                    // Agregar el mensaje a la vista
                    if (data.message) {
                        console.log('17. Agregando mensaje a la vista:', data.message);
                        addMessageToView(data.message);

                        // Scroll al final después de agregar el mensaje
                        setTimeout(() => {
                            const chatMessages = document.getElementById('chat-messages');
                            if (chatMessages) {
                                console.log('18. Haciendo scroll al final del chat');
                                // Forzar scroll al final
                                chatMessages.scrollTop = chatMessages.scrollHeight;
                                // Asegurar que el scroll funcione
                                requestAnimationFrame(() => {
                                    chatMessages.scrollTop = chatMessages.scrollHeight;
                                });
                            }
                        }, 100);
                    } else {
                        console.warn('17. ⚠️ No hay mensaje en la respuesta para mostrar');
                    }

                    updateContactsList();
                    updateAgentHandoffBanner(false);
                } else {
                    console.error('16. ❌ Respuesta indica fallo:', data);
                    alert('Error al enviar el mensaje: ' + (data.message || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('=== ERROR EN ENVÍO ===');
                console.error('Error completo:', error);
                console.error('Stack trace:', error.stack);
                console.error('Mensaje:', error.message);
                alert('Error al enviar el mensaje: ' + (error.message || 'Por favor, intenta nuevamente.'));
            })
            .finally(() => {
                console.log('19. Restaurando botón de envío');
                sendButton.disabled = false;
                sendButton.innerHTML = originalButtonContent;
                if (messageInput) messageInput.focus();
                console.log('=== FIN ENVÍO MENSAJE ===');
            });

            return false;
        });
        console.log('✅ Event listener del formulario registrado correctamente');
        console.log('Formulario:', messageForm);
        console.log('Input mensaje:', messageInput);
        console.log('Botón enviar:', sendButton);

        const emojiButton = document.getElementById('emoji-button');
        const emojiPicker = document.getElementById('emoji-picker');
        const emojiPickerClose = document.getElementById('emoji-picker-close');
        const attachButton = document.getElementById('attach-button');
        const attachMenu = document.getElementById('attach-menu');
        const imageButton = document.getElementById('image-button');
        const imageInput = document.getElementById('image-input');
        const imagePreviewContainer = document.getElementById('image-preview-container');
        const imagePreview = document.getElementById('image-preview');
        const removeImagePreview = document.getElementById('remove-image-preview');
        const documentButton = document.getElementById('document-button');
        const documentInput = document.getElementById('document-input');
        const documentPreviewContainer = document.getElementById('document-preview-container');
        const documentPreviewName = document.getElementById('document-preview-name');
        const documentPreviewSize = document.getElementById('document-preview-size');
        const removeDocumentPreview = document.getElementById('remove-document-preview');
        const currentContactId = document.getElementById('current-contact-id');

        // Función para cerrar el selector de emojis
        function closeEmojiPicker() {
            if (emojiPicker) {
                emojiPicker.classList.add('hidden');
            }
            emojiButton?.classList.remove('is-open');
            emojiButton?.setAttribute('aria-expanded', 'false');
        }

        function closeAttachMenu() {
            attachMenu?.classList.add('hidden');
            attachButton?.classList.remove('is-open');
            attachButton?.setAttribute('aria-expanded', 'false');
        }

        // Función para abrir/cerrar el selector de emojis
        function toggleEmojiPicker() {
            if (emojiPicker && emojiPicker.classList.contains('hidden')) {
                closeAttachMenu();
                emojiPicker.classList.remove('hidden');
                emojiButton?.classList.add('is-open');
                emojiButton?.setAttribute('aria-expanded', 'true');
            } else {
                closeEmojiPicker();
            }
        }

        // Manejar selector de emojis
        if (emojiButton) {
            emojiButton.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                toggleEmojiPicker();
            });
        }
        emojiPickerClose?.addEventListener('click', function() {
            closeEmojiPicker();
            emojiButton?.focus();
        });

        // Manejar menú de adjuntar
        if (attachButton && attachMenu) {
            attachButton.addEventListener('click', function(e) {
                e.stopPropagation();
                closeEmojiPicker();
                const shouldOpen = attachMenu.classList.contains('hidden');
                attachMenu.classList.toggle('hidden', !shouldOpen);
                attachButton.classList.toggle('is-open', shouldOpen);
                attachButton.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
                if (shouldOpen) attachMenu.querySelector('[role="menuitem"]')?.focus();
            });
        }

        // Cerrar menús al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (emojiButton && emojiPicker && !emojiButton.contains(e.target) && !emojiPicker.contains(e.target)) {
                closeEmojiPicker();
            }
            if (attachButton && attachMenu && !attachButton.contains(e.target) && !attachMenu.contains(e.target)) {
                closeAttachMenu();
            }
        });

        // Cerrar cualquiera de los paneles del compositor al presionar Escape.
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            const emojiWasOpen = emojiPicker && !emojiPicker.classList.contains('hidden');
            const attachWasOpen = attachMenu && !attachMenu.classList.contains('hidden');
            closeEmojiPicker();
            closeAttachMenu();
            if (emojiWasOpen) emojiButton?.focus();
            else if (attachWasOpen) attachButton?.focus();
        });

        // Insertar emoji al hacer clic
        if (messageInput) {
            document.querySelectorAll('.emoji-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const emoji = this.getAttribute('data-emoji');
                const cursorPos = messageInput.selectionStart;
                const textBefore = messageInput.value.substring(0, cursorPos);
                const textAfter = messageInput.value.substring(cursorPos);
                messageInput.value = textBefore + emoji + textAfter;
                messageInput.selectionStart = messageInput.selectionEnd = cursorPos + emoji.length;
                messageInput.focus();

                // Trigger resize
                messageInput.dispatchEvent(new Event('input'));

                // No cerrar el selector automáticamente (como WhatsApp Web)
                // closeEmojiPicker();
            });
        });
        }

        // Manejar selección de imagen
        if (imageButton && imageInput) {
            imageButton.addEventListener('click', function() {
                closeAttachMenu();
                // Limpiar otros archivos seleccionados
                selectedDocumentFile = null;
                if (documentInput) documentInput.value = '';
                imageInput.click();
            });

            imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) { // 5MB max
                    alert('La imagen es demasiado grande. Máximo 5MB.');
                    return;
                }

                selectedImageFile = file;
                selectedDocumentFile = null;
                if (documentInput) documentInput.value = '';
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (imagePreview) imagePreview.src = e.target.result;
                    if (imagePreviewContainer) imagePreviewContainer.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
                updateSendButtonState();
            }
        });
        }

        // Remover vista previa de imagen
        if (removeImagePreview && imageInput && imagePreviewContainer && imagePreview) {
            removeImagePreview.addEventListener('click', function() {
                selectedImageFile = null;
                imageInput.value = '';
                imagePreviewContainer.classList.add('hidden');
                imagePreview.src = '';
                updateSendButtonState();
            });
        }

        // Manejar selección de documento
        if (documentButton && documentInput && imageInput && imagePreviewContainer) {
            documentButton.addEventListener('click', function() {
                closeAttachMenu();
                // Limpiar otros archivos seleccionados
                selectedImageFile = null;
                imageInput.value = '';
                imagePreviewContainer.classList.add('hidden');
                documentInput.click();
            });

            documentInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 10 * 1024 * 1024) { // 10MB max
                    alert('El documento es demasiado grande. Máximo 10MB.');
                    return;
                }
                selectedDocumentFile = file;
                selectedImageFile = null;
                imageInput.value = '';
                imagePreviewContainer.classList.add('hidden');

                // Mostrar vista previa
                if (documentPreviewName) documentPreviewName.textContent = file.name;
                const fileSize = (file.size / 1024).toFixed(2);
                if (documentPreviewSize) documentPreviewSize.textContent = fileSize > 1024 ? `${(fileSize / 1024).toFixed(2)} MB` : `${fileSize} KB`;
                if (documentPreviewContainer) documentPreviewContainer.classList.remove('hidden');
                updateSendButtonState();
            }
        });
        }

        // Remover vista previa de documento
        if (removeDocumentPreview && documentInput && documentPreviewContainer) {
            removeDocumentPreview.addEventListener('click', function() {
                selectedDocumentFile = null;
                documentInput.value = '';
                documentPreviewContainer.classList.add('hidden');
                updateSendButtonState();
            });
        }

        // Auto-resize del textarea y estado del botón enviar
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            updateSendButtonState();

            if (this.value.trim()) {
                sendWhatsAppTypingIndicator();
                scheduleTypingRefresh();
            } else {
                stopTypingRefresh();
            }
        });

        messageInput.addEventListener('focus', function() {
            if (this.value.trim()) {
                sendWhatsAppTypingIndicator();
                scheduleTypingRefresh();
            }
        });

        messageInput.addEventListener('blur', stopTypingRefresh);

        updateSendButtonState();

        // Permitir Enter para enviar y Shift+Enter para nueva línea
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                console.log('Enter presionado (sin Shift) - disparando submit');
                e.preventDefault();
                if (messageInput.value.trim() || selectedImageFile || selectedDocumentFile) {
                    console.log('Contenido válido, disparando evento submit');
                    messageForm.dispatchEvent(new Event('submit'));
                } else {
                    console.log('Sin contenido válido, no se dispara submit');
                }
            }
        });

        // addMessageToView y escapeHtmlGlobal ahora están definidas globalmente (fuera de DOMContentLoaded)
        // Esta función escapeHtml local puede usar la global si es necesario dentro de este scope
        function escapeHtml(text) {
            return escapeHtmlGlobal(text);
        }

        // Manejar toggle del bot
        const botToggle = document.getElementById('bot-enabled-toggle');
        if (botToggle) {
            botToggle.addEventListener('change', function() {
                const contactId = this.getAttribute('data-contact-id');
                const enabled = this.checked;
                const statusText = document.getElementById('bot-status-text');

                // Actualizar texto inmediatamente
                if (statusText) {
                    statusText.textContent = enabled ? 'Activada' : 'Pausada';
                }

                // Enviar petición al servidor
                fetch(`/admin/contacts/${contactId}/toggle-bot`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        enabled: enabled
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Estado del bot actualizado:', enabled ? 'Activada' : 'Pausada');
                    } else {
                        // Revertir si falla
                        this.checked = !enabled;
                        if (statusText) {
                            statusText.textContent = !enabled ? 'Activada' : 'Pausada';
                        }
                        alert('Error al actualizar el estado del bot: ' + (data.message || 'Error desconocido'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Revertir si falla
                    this.checked = !enabled;
                    if (statusText) {
                        statusText.textContent = !enabled ? 'Activada' : 'Pausada';
                    }
                    alert('Error al actualizar el estado del bot. Por favor, intenta nuevamente.');
                });
            });
        }

        // Iniciar polling cuando se carga la página
        const contactIdInput = document.querySelector('input[name="contact_id"]');
        if (contactIdInput && contactIdInput.value) {
            startPolling(parseInt(contactIdInput.value));
        }

        // Detener polling cuando el usuario sale de la página
        window.addEventListener('beforeunload', () => {
            stopPolling();
        });

        // Detener polling cuando la página pierde el foco
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopPolling();
            } else {
                const contactIdInput = document.querySelector('input[name="contact_id"]');
                if (contactIdInput && contactIdInput.value) {
                    startPolling(parseInt(contactIdInput.value));
                }
            }
        });
    });


        // Función global para cargar chat de contacto
        function loadContactChat(contactId) {
            // Actualizar URL sin recargar
            const newUrl = `/admin/chats/${contactId}`;
            window.history.pushState({contactId: contactId}, '', newUrl);

            // Mostrar indicador de carga
            const chatMessages = document.getElementById('chat-messages');
            const chatHeader = document.querySelector('.wa-chat-header');
            chatMessages.innerHTML = '<div class="wa-empty-state"><span class="wa-empty-state-icon"><i class="fas fa-spinner fa-spin"></i></span><strong>Cargando conversación</strong><span>Estamos recuperando los mensajes de este cliente.</span></div>';

            // Cargar mensajes del contacto
            fetch(`/admin/chats/${contactId}/messages`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Actualizar header
                    const headerName = chatHeader.querySelector('.wa-chat-header-name');
                    const headerStatus = chatHeader.querySelector('.wa-chat-header-status');
                    const headerAvatar = chatHeader.querySelector('.wa-chat-avatar');

                    if (headerName) headerName.textContent = data.contact.name || 'Cliente';
                    if (headerStatus) {
                        const phoneText = headerStatus.querySelector('span');
                        if (phoneText) phoneText.textContent = data.contact.phone_number || '';
                    }
                    if (headerAvatar) {
                        headerAvatar.textContent = (data.contact.name || 'C').charAt(0).toUpperCase();
                        // Asegurar que el avatar tenga el ID para el toggle
                        if (!headerAvatar.id) {
                            headerAvatar.id = 'waChatAvatarToggle';
                        }
                    }

                    // Actualizar toggle del bot
                    const botToggle = document.getElementById('bot-enabled-toggle');
                    const botStatusText = document.getElementById('bot-status-text');
                    if (botToggle && data.contact.bot_enabled !== undefined) {
                        botToggle.checked = data.contact.bot_enabled;
                        botToggle.setAttribute('data-contact-id', contactId);
                    }
                    if (botStatusText && data.contact.bot_enabled !== undefined) {
                        botStatusText.textContent = data.contact.bot_enabled ? 'Activada' : 'Pausada';
                    }

                    updateAgentHandoffBanner(!!data.contact.needs_agent);

                    // Actualizar ID del contacto en el formulario
                    const contactIdInput = document.querySelector('input[name="contact_id"]');
                    if (contactIdInput) contactIdInput.value = contactId;
                    const currentContactIdInput = document.getElementById('current-contact-id');
                    if (currentContactIdInput) currentContactIdInput.value = contactId;

                    // Reiniciar polling para el nuevo contacto
                    lastMessageId = 0;
                    lastMessageTimestamp = null;
                    startPolling(contactId);

                    // Renderizar mensajes
                    chatMessages.innerHTML = '';
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(msg => {
                            renderMessage(msg);
                        });

                        // Actualizar último mensaje para polling
                        const lastMsg = data.messages[data.messages.length - 1];
                        if (lastMsg) {
                            lastMessageId = parseInt(lastMsg.id) || 0;
                            lastMessageTimestamp = lastMsg.created_at || null;
                        }

                        // Scroll al final
                        setTimeout(() => {
                            chatMessages.scrollTop = chatMessages.scrollHeight;
                        }, 100);
                    } else {
                        chatMessages.innerHTML = '<div class="wa-empty-state"><span class="wa-empty-state-icon"><i class="far fa-comments"></i></span><strong>Aún no hay mensajes</strong><span>Cuando el cliente escriba, la conversación aparecerá aquí y podrás responderle desde este panel.</span></div>';
                        lastMessageId = 0;
                        lastMessageTimestamp = null;
                    }

                    // Actualizar el ID del contacto actual en la variable global
                    currentContactId = parseInt(contactId);

                    // Actualizar estado activo en sidebar
                    document.querySelectorAll('.wa-sidebar-contact').forEach(contact => {
                        contact.classList.remove('active');
                        contact.removeAttribute('aria-current');
                    });
                    const selectedContact = document.querySelector(`.wa-sidebar-contact[data-contact-id="${contactId}"]`);
                    selectedContact?.classList.add('active');
                    selectedContact?.setAttribute('aria-current', 'true');

                    // Guardar el último mensaje visto para este contacto
                    if (data.messages && data.messages.length > 0) {
                        const lastClientMessage = data.messages.filter(msg => msg.sender_type === 'client').pop();
                        if (lastClientMessage) {
                            // Guardar en localStorage el timestamp del último mensaje visto
                            localStorage.setItem(`last_seen_${contactId}`, lastClientMessage.created_at);
                        } else {
                            // Si no hay mensajes del cliente, guardar la fecha actual
                            localStorage.setItem(`last_seen_${contactId}`, new Date().toISOString());
                        }
                    } else {
                        // Si no hay mensajes, guardar la fecha actual
                        localStorage.setItem(`last_seen_${contactId}`, new Date().toISOString());
                    }

                    // Remover indicador de mensaje nuevo del contacto activo
                    const activeContact = document.querySelector(`.wa-sidebar-contact[data-contact-id="${contactId}"]`);
                    if (activeContact) {
                        const newMessageIndicator = activeContact.querySelector('.wa-new-message-indicator');
                        if (newMessageIndicator) {
                            newMessageIndicator.remove();
                        }
                    }

                    // Actualizar la lista de contactos para reflejar el cambio (con un pequeño delay para asegurar que el DOM se actualizó)
                    setTimeout(() => {
                        updateContactsList();
                    }, 100);

                    // Actualizar estadísticas si están disponibles
                    if (data.stats && currentViewMode === 'contact') {
                        updateStats(data.stats);
                        updateCharts(data.stats);
                        updateMessageTypesList(data.stats.messageTypes || {});
                    }

                    // Scroll al final
                    setTimeout(() => {
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }, 100);
                }
            })
            .catch(error => {
                console.error('Error cargando mensajes:', error);
                chatMessages.innerHTML = '<div class="text-center py-8" style="color: #f15c6d;">Error al cargar los mensajes</div>';
            });
        }

        // Agregar event listeners a los contactos originales
        function attachContactListeners() {
            document.querySelectorAll('.wa-sidebar-contact[data-contact-id]').forEach(link => {
                // Remover listeners anteriores si existen
                const newLink = link.cloneNode(true);
                link.parentNode.replaceChild(newLink, link);

                newLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const contactId = this.getAttribute('data-contact-id');
                    if (contactId) {
                        loadContactChat(contactId);
                    }
                });
            });
        }

        // Agregar listeners cuando el DOM esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', attachContactListeners);
        } else {
            attachContactListeners();
        }

        function formatSidebarDateTime(isoString) {
            if (!isoString) return '';
            const date = new Date(isoString);
            if (Number.isNaN(date.getTime())) return '';

            const now = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            const time = `${pad(date.getHours())}:${pad(date.getMinutes())}`;

            const isSameDay = (a, b) =>
                a.getFullYear() === b.getFullYear() &&
                a.getMonth() === b.getMonth() &&
                a.getDate() === b.getDate();

            const yesterday = new Date(now);
            yesterday.setDate(now.getDate() - 1);

            if (isSameDay(date, now)) return time;
            if (isSameDay(date, yesterday)) return `Ayer ${time}`;
            if (date.getFullYear() === now.getFullYear()) {
                return `${pad(date.getDate())}/${pad(date.getMonth() + 1)} ${time}`;
            }
            return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} ${time}`;
        }

        function parseMessageJson(content) {
            if (!content || typeof content !== 'string') return null;
            const trimmed = content.trim();
            if (!trimmed.startsWith('{') && !trimmed.startsWith('[')) return null;
            try {
                const parsed = JSON.parse(trimmed);
                return parsed && typeof parsed === 'object' ? parsed : null;
            } catch (e) {
                return null;
            }
        }

        function formatMessageDisplay(msg) {
            const content = msg.content || '';
            const metadata = msg.metadata || {};

            if (msg.display_text) {
                return {
                    text: msg.display_text,
                    description: msg.display_description || null,
                    isInteractive: !!msg.is_interactive_reply,
                };
            }

            if (metadata.interactive) {
                const interactive = metadata.interactive;
                if (interactive.button_reply?.title) {
                    return { text: interactive.button_reply.title, description: interactive.button_reply.description || null, isInteractive: true };
                }
                if (interactive.list_reply?.title) {
                    return { text: interactive.list_reply.title, description: interactive.list_reply.description || null, isInteractive: true };
                }
            }

            const parsed = parseMessageJson(content);
            if (parsed) {
                if (parsed.type === 'button_reply' && parsed.button_reply?.title) {
                    return { text: parsed.button_reply.title, description: parsed.button_reply.description || null, isInteractive: true };
                }
                if (parsed.type === 'list_reply' && parsed.list_reply?.title) {
                    return { text: parsed.list_reply.title, description: parsed.list_reply.description || null, isInteractive: true };
                }
                if (parsed.button_reply?.title) {
                    return { text: parsed.button_reply.title, description: parsed.button_reply.description || null, isInteractive: true };
                }
                if (parsed.list_reply?.title) {
                    return { text: parsed.list_reply.title, description: parsed.list_reply.description || null, isInteractive: true };
                }
                if (parsed.title) {
                    return { text: parsed.title, description: parsed.description || null, isInteractive: true };
                }
            }

            if (msg.type === 'interactive' && !trimmedStartsWithJson(content)) {
                return { text: content, description: null, isInteractive: true };
            }

            return { text: content, description: null, isInteractive: false };
        }

        function trimmedStartsWithJson(content) {
            return typeof content === 'string' && (content.trim().startsWith('{') || content.trim().startsWith('['));
        }

        function renderMessage(msg) {
            const chatMessages = document.getElementById('chat-messages');
            const isIncoming = msg.sender_type === 'client';
            const senderMeta = resolveSenderMeta(msg, isIncoming);
            const bubbleClass = isIncoming ? 'wa-bubble-in' : ('wa-bubble-out' + senderMeta.bubbleExtra);
            const wrapperClass = isIncoming ? 'incoming' : 'outgoing';

            let contentHtml = '';
            const display = formatMessageDisplay(msg);

            if (msg.type === 'image') {
                contentHtml += buildImageMessageHtml(msg);
            } else if (msg.type === 'document') {
                const metadata = msg.metadata || {};
                const filename = metadata.filename || 'documento';
                const fileSize = metadata.file_size || '';
                const fileExtension = filename.split('.').pop()?.toUpperCase() || 'DOC';
                contentHtml += `<div style="background: #202c33; border-radius: 8px; padding: 12px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px;">
                    <div style="background: #f15c6d; border-radius: 8px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <span style="color: white; font-weight: 600; font-size: 12px;">${fileExtension}</span>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 14px; font-weight: 500; color: #e9edef; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(filename)}</div>
                        <div style="font-size: 12px; color: #8696a0;">
                            ${fileSize ? `PDF • ${fileSize}` : 'PDF'}
                        </div>
                    </div>
                    <button style="background: transparent; border: none; cursor: pointer; padding: 8px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #8696a0;" title="Descargar">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>`;
            } else if (display.isInteractive) {
                contentHtml += `<span class="wa-bubble-content">${escapeHtml(display.text)}</span>`;
                if (display.description) {
                    contentHtml += `<div class="wa-bubble-content-block" style="font-size: 12px; color: #8696a0; margin-top: 2px;">${escapeHtml(display.description)}</div>`;
                }
            } else if (display.text) {
                contentHtml += `<span class="wa-bubble-content">${escapeHtml(display.text)}</span>`;
            }

            const time = new Date(msg.created_at).toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'});
            const statusIcon = !isIncoming ? '<svg width="16" height="10" viewBox="0 0 16 10"><path d="M15.854 0.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L8.5 7.293l6.646-6.647a.5.5 0 0 1 .708 0z" fill="#53bdeb"/><path d="M10.854 0.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L3.5 7.293l6.646-6.647a.5.5 0 0 1 .708 0z" fill="#53bdeb"/></svg>' : '';

            const messageDiv = document.createElement('div');
            messageDiv.className = `wa-message-wrapper ${wrapperClass}`;
            messageDiv.setAttribute('data-message-id', msg.id);
            messageDiv.innerHTML = `
                <div class="wa-msg-col">
                    ${buildMessageBadgeHtml(senderMeta)}
                    <div class="${bubbleClass}">
                        ${contentHtml}
                        <div class="wa-message-time ${isIncoming ? 'incoming' : 'outgoing'}">
                            <span>${time}</span>
                            ${statusIcon}
                        </div>
                    </div>
                </div>
            `;
            chatMessages.appendChild(messageDiv);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Variables globales para los gráficos
        let messagesChart = null;
        let responseTimeChart = null;
        let messageTypesChart = null;
        let topicsChart = null;

        // Función para inicializar/actualizar gráficos
        function updateCharts(stats) {
            const messagesByDay = stats.messagesByDay || [];
            const responseTimeByDay = stats.responseTimeByDay || [];
            const messageTypes = stats.messageTypes || {};

            // Destruir gráficos existentes si existen
            if (messagesChart) {
                try { messagesChart.destroy(); } catch(e) { console.warn('Error destruyendo messagesChart:', e); }
                messagesChart = null;
            }
            if (responseTimeChart) {
                try { responseTimeChart.destroy(); } catch(e) { console.warn('Error destruyendo responseTimeChart:', e); }
                responseTimeChart = null;
            }
            if (messageTypesChart) {
                try { messageTypesChart.destroy(); } catch(e) { console.warn('Error destruyendo messageTypesChart:', e); }
                messageTypesChart = null;
            }
            if (topicsChart) {
                try { topicsChart.destroy(); } catch(e) { console.warn('Error destruyendo topicsChart:', e); }
                topicsChart = null;
            }

            // Message activity chart
            const messagesCtx = document.getElementById('messagesChart');
            if (messagesCtx && typeof Chart !== 'undefined') {
                messagesChart = new Chart(messagesCtx.getContext('2d'), {
            type: 'line',
            data: {
                        labels: messagesByDay.map(d => d.day),
                datasets: [{
                    label: 'Mensajes Enviados',
                            data: messagesByDay.map(d => d.sent || 0),
                    borderColor: '#25d366',
                            backgroundColor: 'rgba(37, 211, 102, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Mensajes Recibidos',
                            data: messagesByDay.map(d => d.received || 0),
                    borderColor: '#128C7E',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                        maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                                text: 'Actividad de Mensajes (Últimos 7 días)'
                            },
                            legend: {
                                labels: {
                                    color: '#8696a0'
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: { color: '#8696a0' },
                                grid: { color: 'rgba(134, 150, 160, 0.1)' }
                            },
                            y: {
                                ticks: { color: '#8696a0' },
                                grid: { color: 'rgba(134, 150, 160, 0.1)' }
                    }
                }
            }
        });
            }

        // Response time chart
            const responseCtx = document.getElementById('responseTimeChart');
            if (responseCtx && typeof Chart !== 'undefined') {
                responseTimeChart = new Chart(responseCtx.getContext('2d'), {
            type: 'bar',
            data: {
                        labels: responseTimeByDay.map(d => d.day),
                datasets: [{
                    label: 'Tiempo de Respuesta (min)',
                            data: responseTimeByDay.map(d => d.time || 0),
                    backgroundColor: '#25d366'
                }]
            },
            options: {
                responsive: true,
                        maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                                text: 'Tiempo Promedio de Respuesta (Últimos 7 días)'
                            },
                            legend: {
                                labels: {
                                    color: '#8696a0'
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: { color: '#8696a0' },
                                grid: { color: 'rgba(134, 150, 160, 0.1)' }
                            },
                            y: {
                                ticks: { color: '#8696a0' },
                                grid: { color: 'rgba(134, 150, 160, 0.1)' }
                    }
                }
            }
        });
            }

        // Message Types Distribution
            const messageTypesCtx = document.getElementById('messageTypesChart');
            if (messageTypesCtx && typeof Chart !== 'undefined') {
                const typeLabels = Object.keys(messageTypes);
                const typeData = Object.values(messageTypes);
                const colors = ['#25d366', '#128C7E', '#34B7F1', '#F15C6D', '#8b5cf6', '#37a9fe'];

                messageTypesChart = new Chart(messageTypesCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                        labels: typeLabels.length > 0 ? typeLabels : ['Sin datos'],
                datasets: [{
                            data: typeData.length > 0 ? typeData : [1],
                            backgroundColor: colors.slice(0, Math.max(typeLabels.length, 1))
                }]
            },
            options: {
                responsive: true,
                        maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Distribución de Tipos de Mensajes'
                            },
                            legend: {
                                labels: {
                                    color: '#8696a0'
                                }
                    }
                }
            }
        });
            }

            // Topics Distribution (usando tipos de mensajes)
            const topicsCtx = document.getElementById('topicsChart');
            if (topicsCtx && typeof Chart !== 'undefined') {
                const topicLabels = Object.keys(messageTypes).slice(0, 5);
                const topicData = Object.values(messageTypes).slice(0, 5);

                topicsChart = new Chart(topicsCtx.getContext('2d'), {
            type: 'bar',
            data: {
                        labels: topicLabels.length > 0 ? topicLabels : ['Sin datos'],
                datasets: [{
                            label: 'Mensajes por Tipo',
                            data: topicData.length > 0 ? topicData : [0],
                    backgroundColor: '#25d366'
                }]
            },
            options: {
                responsive: true,
                        maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                                text: 'Distribución de Tipos de Mensajes'
                            },
                            legend: {
                                labels: {
                                    color: '#8696a0'
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: { color: '#8696a0' },
                                grid: { color: 'rgba(134, 150, 160, 0.1)' }
                            },
                            y: {
                                ticks: { color: '#8696a0' },
                                grid: { color: 'rgba(134, 150, 160, 0.1)' }
                    }
                }
            }
        });
            }
        }

        // Variable global para el modo de vista
        let currentViewMode = 'contact'; // 'contact' o 'global'
        let globalStats = @json($globalStats ?? []);

        // Inicializar gráficos con datos iniciales al cargar la página
        function initializeCharts() {
            // Verificar que Chart.js esté cargado
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js no está cargado, reintentando en 100ms...');
                setTimeout(initializeCharts, 100);
                return;
            }

            @if(isset($stats))
            // Inicializar gráficos con datos del contacto actual
            const initialStats = @json($stats);
            if (initialStats && Object.keys(initialStats).length > 0) {
                // Asegurar que los datos necesarios existan
                if (!initialStats.messagesByDay) initialStats.messagesByDay = [];
                if (!initialStats.responseTimeByDay) initialStats.responseTimeByDay = [];
                if (!initialStats.messageTypes) initialStats.messageTypes = {};

                // Esperar a que los canvas estén disponibles
                setTimeout(() => {
                    updateCharts(initialStats);
                }, 200);
            } else {
                // Si no hay datos, inicializar con datos vacíos para mostrar los gráficos
                updateCharts({
                    messagesByDay: [],
                    responseTimeByDay: [],
                    messageTypes: {}
                });
            }
            @else
            // Si no hay stats, inicializar con datos vacíos
            updateCharts({
                messagesByDay: [],
                responseTimeByDay: [],
                messageTypes: {}
            });
            @endif
        }

        // Inicializar cuando el DOM esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeCharts);
        } else {
            // DOM ya está listo
            initializeCharts();
        }

        // Función para cambiar entre vista de contacto y global
        function toggleView() {
            const toggleBtn = document.getElementById('view-toggle');
            const modeText = document.getElementById('view-mode-text');
            const indicator = document.getElementById('view-indicator');

            if (currentViewMode === 'contact') {
                currentViewMode = 'global';
                modeText.textContent = 'Todos los Chats';
                indicator.textContent = 'Mostrando estadísticas globales de todos los contactos';
                toggleBtn.style.background = '#37a9fe';
                updateStats(globalStats);
                updateCharts(globalStats);
                updateMessageTypesList(globalStats.messageTypes || {});
            } else {
                currentViewMode = 'contact';
                modeText.textContent = 'Contacto Actual';
                indicator.textContent = 'Mostrando estadísticas del contacto seleccionado';
                toggleBtn.style.background = '#005c4b';
                @if(isset($stats))
                updateStats(@json($stats));
                updateCharts(@json($stats));
                updateMessageTypesList(@json($stats['messageTypes'] ?? []));
                @endif
            }
        }

        // Función para actualizar las estadísticas
        function updateStats(stats) {
            const totalMessagesEl = document.getElementById('stat-total-messages');
            const messageGrowthEl = document.getElementById('stat-message-growth');
            const lastActivityEl = document.getElementById('stat-last-activity');
            const lastActivityDateEl = document.getElementById('stat-last-activity-date');
            const avgTimeEl = document.getElementById('stat-avg-time');
            const sentReceivedEl = document.getElementById('stat-sent-received');
            const ratioEl = document.getElementById('stat-ratio');
            const clientResponseRateEl = document.getElementById('stat-client-response-rate');
            const peakHourEl = document.getElementById('stat-peak-hour');
            const activeDayEl = document.getElementById('stat-active-day');
            const avgLengthEl = document.getElementById('stat-avg-length');
            const conversationsEl = document.getElementById('stat-conversations');
            const timeBetweenEl = document.getElementById('stat-time-between');
            const frequencyEl = document.getElementById('stat-frequency');

            if (totalMessagesEl) totalMessagesEl.textContent = stats.totalMessages || 0;
            if (messageGrowthEl) {
                const growth = parseFloat(stats.messageGrowth) || 0;
                messageGrowthEl.textContent = `${growth >= 0 ? '+' : ''}${growth.toFixed(1)}% vs mes anterior`;
                messageGrowthEl.className = `stat-change ${growth >= 0 ? 'positive' : 'negative'}`;
            }
            if (lastActivityEl) lastActivityEl.textContent = stats.lastActivity || 'Nunca';
            if (lastActivityDateEl) lastActivityDateEl.textContent = stats.lastActivityDate || 'N/A';
            if (avgTimeEl) avgTimeEl.textContent = stats.avgResponseTime || '0m';
            if (sentReceivedEl) sentReceivedEl.textContent = `${stats.sentMessages || 0} / ${stats.receivedMessages || 0}`;
            if (ratioEl) ratioEl.textContent = `Ratio: ${stats.sentReceivedRatio || 0}`;
            if (clientResponseRateEl) clientResponseRateEl.textContent = stats.clientResponseRate || '0%';
            if (peakHourEl) peakHourEl.textContent = stats.peakHour || 'N/A';
            if (activeDayEl) activeDayEl.textContent = `Día: ${stats.mostActiveDay || 'N/A'}`;
            if (avgLengthEl) avgLengthEl.textContent = stats.avgMessageLength || '0 caracteres';
            if (conversationsEl) conversationsEl.textContent = stats.conversations || 0;
            if (timeBetweenEl) timeBetweenEl.textContent = stats.avgTimeBetweenMessages || '0m';
            if (frequencyEl) frequencyEl.textContent = stats.frequencyPerDay || 0;

            // Actualizar indicadores adicionales
            const sentOnlyEl = document.getElementById('stat-sent-only');
            const receivedOnlyEl = document.getElementById('stat-received-only');
            const ratioOnlyEl = document.getElementById('stat-ratio-only');
            const buttonsEl = document.getElementById('stat-buttons');
            const interactionEl = document.getElementById('stat-interaction');
            const growthValueEl = document.getElementById('stat-growth-value');

            if (sentOnlyEl) sentOnlyEl.textContent = stats.sentMessages || 0;
            if (receivedOnlyEl) receivedOnlyEl.textContent = stats.receivedMessages || 0;
            if (ratioOnlyEl) {
                ratioOnlyEl.textContent = stats.sentReceivedRatio || 0;
                const ratioChange = ratioOnlyEl.parentElement.querySelector('.stat-change');
                if (ratioChange) {
                    ratioChange.textContent = (stats.sentReceivedRatio || 0) > 1 ? 'Más enviados' : 'Más recibidos';
                    ratioChange.className = `stat-change ${(stats.sentReceivedRatio || 0) > 1 ? 'positive' : 'negative'}`;
                }
            }
            if (buttonsEl) buttonsEl.textContent = stats.buttonMessagesRate || '0%';
            if (interactionEl) interactionEl.textContent = stats.interactionRate || '0%';
            if (growthValueEl) {
                const growth = parseFloat(stats.messageGrowth) || 0;
                growthValueEl.textContent = `${growth >= 0 ? '+' : ''}${growth.toFixed(1)}%`;
                const growthChange = growthValueEl.parentElement.querySelector('.stat-change');
                if (growthChange) {
                    growthChange.className = `stat-change ${growth >= 0 ? 'positive' : 'negative'}`;
                }
            }
        }

        // Función para actualizar la lista de tipos de mensajes
        function updateMessageTypesList(messageTypes) {
            const topicsList = document.getElementById('topics-list');
            if (!topicsList) return;

            topicsList.innerHTML = '';
            const types = Object.keys(messageTypes);

            if (types.length === 0) {
                topicsList.innerHTML = '<div class="topic-item"><span class="topic-name">Sin datos disponibles</span><span class="topic-count">0</span></div>';
                return;
            }

            // Ordenar por cantidad
            const sortedTypes = types.sort((a, b) => messageTypes[b] - messageTypes[a]);

            sortedTypes.forEach(type => {
                const item = document.createElement('div');
                item.className = 'topic-item';
                item.innerHTML = `
                    <span class="topic-name">${type ? type.charAt(0).toUpperCase() + type.slice(1) : 'Texto'}</span>
                    <span class="topic-count">${messageTypes[type]}</span>
                `;
                topicsList.appendChild(item);
            });
        }

        // Menú principal del admin (móvil, desde vista de chat)
        (function() {
            const adminSidebar = document.getElementById('sidebar');
            const adminOverlay = document.getElementById('sidebarOverlay');
            const openAdminBtn = document.getElementById('waOpenAdminMenu');
            const waSidebar = document.getElementById('waSidebar');
            const waOverlay = document.getElementById('waSidebarOverlay');

            function closeWaChatSidebar() {
                if (waSidebar) waSidebar.classList.remove('show');
                if (waOverlay) waOverlay.classList.remove('show');
            }

            function toggleAdminMenu() {
                if (!adminSidebar || !adminOverlay) return;
                const willOpen = !adminSidebar.classList.contains('show');
                if (willOpen) {
                    closeWaChatSidebar();
                }
                adminSidebar.classList.toggle('show');
                adminOverlay.classList.toggle('show');
                document.body.style.overflow = adminSidebar.classList.contains('show') ? 'hidden' : '';
            }

            if (openAdminBtn) {
                openAdminBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleAdminMenu();
                });
            }
        })();

        // Mobile Sidebar Toggle (lista de chats)
        (function() {
            const sidebar = document.getElementById('waSidebar');
            const overlay = document.getElementById('waSidebarOverlay');
            const openBtn = document.getElementById('waOpenSidebar');
            const closeBtn = document.getElementById('waCloseSidebar');
            const avatarToggle = document.getElementById('waChatAvatarToggle');
            const adminSidebar = document.getElementById('sidebar');
            const adminOverlay = document.getElementById('sidebarOverlay');

            function closeAdminMenu() {
                if (adminSidebar) adminSidebar.classList.remove('show');
                if (adminOverlay) adminOverlay.classList.remove('show');
            }

            function openSidebar() {
                closeAdminMenu();
                if (sidebar) sidebar.classList.add('show');
                if (overlay) overlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                if (sidebar) sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
                document.body.style.overflow = '';
            }

            // Abrir sidebar al hacer clic en el avatar (móvil)
            if (avatarToggle) {
                avatarToggle.addEventListener('click', function(e) {
                    // Solo abrir en móvil
                    if (window.innerWidth <= 991.98) {
                        e.preventDefault();
                        e.stopPropagation();
                        openSidebar();
                    }
                });
            }

            // Botón de menú hamburguesa (backup, oculto por defecto)
            if (openBtn) {
                openBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openSidebar();
                });
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
            }

            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
            }

            // Cerrar sidebar cuando se selecciona un contacto en móvil
            const sidebarContacts = document.querySelectorAll('.wa-sidebar-contact');
            sidebarContacts.forEach(contact => {
                contact.addEventListener('click', function() {
                    // Solo cerrar en móvil
                    if (window.innerWidth <= 991.98) {
                        setTimeout(closeSidebar, 300);
                    }
                });
            });

            // Cerrar sidebar al redimensionar a desktop
            window.addEventListener('resize', function() {
                if (window.innerWidth > 991.98) {
                    closeSidebar();
                }
            });
        })();

        // Actualizar lista de contactos en tiempo real
        let contactsUpdateInterval = null;
        let currentContactId = {{ $contact->id }};

        function updateAgentHandoffBanner(needsAgent) {
            const banner = document.getElementById('wa-agent-handoff-banner');
            if (!banner) return;
            banner.classList.toggle('hidden', !needsAgent);
        }

        function updateAgentRequestsCount(count) {
            const counter = document.getElementById('agent-requests-count');
            if (!counter) return;

            if (count > 0) {
                counter.textContent = count;
                counter.classList.remove('hidden');
            } else {
                counter.textContent = '';
                counter.classList.add('hidden');
            }
        }

        function dismissAgentRequest(contactId) {
            const csrfToken = document.querySelector('input[name="_token"]')?.value
                || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            return fetch(`/admin/contacts/${contactId}/dismiss-agent`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateAgentHandoffBanner(false);
                    if (window.WaAgentAlerts?.clearContact) {
                        window.WaAgentAlerts.clearContact(contactId);
                    }
                    updateContactsList();
                }
                return data;
            });
        }

        document.getElementById('wa-dismiss-agent-btn')?.addEventListener('click', function() {
            const contactIdInput = document.getElementById('current-contact-id');
            const contactId = contactIdInput ? contactIdInput.value : currentContactId;
            if (contactId) {
                dismissAgentRequest(contactId);
            }
        });

        document.getElementById('reset-conversation-btn')?.addEventListener('click', function() {
            const contactIdInput = document.getElementById('current-contact-id');
            const contactId = contactIdInput ? contactIdInput.value : currentContactId;
            if (!contactId) return;

            if (!confirm('¿Cancelar el pedido a medias de este cliente (si tiene uno) y reiniciar su conversación? Se le avisará por WhatsApp.')) {
                return;
            }

            const csrfToken = document.querySelector('input[name="_token"]')?.value
                || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            fetch(`/admin/contacts/${contactId}/reset-conversation`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.cart_closed
                        ? 'Pedido cancelado y conversación reiniciada.'
                        : 'Conversación reiniciada (no tenía un pedido a medias).');
                } else {
                    alert(data.message || 'No se pudo reiniciar la conversación.');
                }
            })
            .catch(() => alert('No se pudo reiniciar la conversación.'));
        });

        function updateContactsList() {
            // Obtener el ID del contacto actual dinámicamente
            const currentContactIdInput = document.getElementById('current-contact-id');
            if (currentContactIdInput) {
                currentContactId = parseInt(currentContactIdInput.value) || currentContactId;
            }

            fetch(`/admin/chats/list/update?current_contact_id=${currentContactId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.contacts) {
                    if (typeof data.agent_requests_count !== 'undefined') {
                        updateAgentRequestsCount(data.agent_requests_count);
                    }

                    const contactsContainer = document.querySelector('.wa-sidebar-contacts');
                    if (!contactsContainer) return;

                    // Obtener el ID del contacto actual dinámicamente (puede haber cambiado)
                    const currentContactIdInput = document.getElementById('current-contact-id');
                    const actualCurrentContactId = currentContactIdInput ? parseInt(currentContactIdInput.value) : currentContactId;

                    // Reconstruir la lista de contactos (más reciente primero)
                    contactsContainer.innerHTML = '';
                    const sortedContacts = [...data.contacts].sort((a, b) => {
                        const agentA = a.needs_agent ? 1 : 0;
                        const agentB = b.needs_agent ? 1 : 0;
                        if (agentA !== agentB) {
                            return agentB - agentA;
                        }
                        const timeA = Number(a.last_message_sort) || new Date(a.last_message_timestamp || a.last_message_date || 0).getTime();
                        const timeB = Number(b.last_message_sort) || new Date(b.last_message_timestamp || b.last_message_date || 0).getTime();
                        return timeB - timeA;
                    });

                    const conversationsCount = document.querySelector('.wa-conversations-count');
                    if (conversationsCount) conversationsCount.textContent = sortedContacts.length;

                    const activeContactData = sortedContacts.find(c => c.id == actualCurrentContactId);
                    if (activeContactData) {
                        updateAgentHandoffBanner(!!activeContactData.needs_agent);
                    }

                    sortedContacts.forEach(contact => {
                        const isActive = contact.id == actualCurrentContactId;
                        const contactElement = document.createElement('a');
                        contactElement.href = 'javascript:void(0)';
                        contactElement.setAttribute('data-contact-id', contact.id);
                        contactElement.className = `wa-sidebar-contact${isActive ? ' active' : ''}${contact.needs_agent ? ' has-agent-request' : ''}`;
                        if (isActive) contactElement.setAttribute('aria-current', 'true');

                        // Avatar
                        const avatar = document.createElement('div');
                        avatar.className = 'wa-sidebar-avatar';
                        avatar.textContent = (contact.name || 'C').charAt(0).toUpperCase();
                        contactElement.appendChild(avatar);

                        // Info
                        const info = document.createElement('div');
                        info.className = 'wa-sidebar-contact-info';

                        const top = document.createElement('div');
                        top.className = 'wa-sidebar-top';

                        const name = document.createElement('div');
                        name.className = 'wa-sidebar-name';
                        name.textContent = contact.name || 'Cliente';
                        top.appendChild(name);

                        if (contact.needs_agent) {
                            const agentBadge = document.createElement('span');
                            agentBadge.className = 'wa-agent-alert-badge';
                            agentBadge.title = 'Solicita asesor humano';
                            agentBadge.textContent = '👤 Asesor';
                            top.appendChild(agentBadge);
                        }

                        if (contact.last_message_timestamp || contact.last_message_date) {
                            const time = document.createElement('div');
                            time.className = 'wa-sidebar-time';
                            const iso = contact.last_message_timestamp || contact.last_message_date;
                            time.textContent = contact.last_message_label || formatSidebarDateTime(iso);
                            time.title = new Date(iso).toLocaleString('es-CO');
                            top.appendChild(time);
                        }

                        info.appendChild(top);

                        const previewText = contact.last_message_preview || contact.last_client_message;

                        if (previewText) {
                            const lastSeen = localStorage.getItem(`last_seen_${contact.id}`);
                            let shouldShowIndicator = false;

                            if (contact.last_message_timestamp && contact.id != actualCurrentContactId) {
                                if (!lastSeen) {
                                    const messageDate = new Date(contact.last_message_timestamp);
                                    const twentyFourHoursAgo = new Date(Date.now() - 24 * 60 * 60 * 1000);
                                    shouldShowIndicator = messageDate > twentyFourHoursAgo;
                                } else {
                                    const lastSeenDate = new Date(lastSeen);
                                    const lastMessageDate = new Date(contact.last_message_timestamp);
                                    shouldShowIndicator = lastMessageDate > lastSeenDate;
                                }
                            }

                            const lastMsg = document.createElement('div');
                            lastMsg.className = 'wa-sidebar-last-message';

                            if (shouldShowIndicator || contact.has_new_message) {
                                const indicator = document.createElement('span');
                                indicator.className = 'wa-new-message-indicator';
                                indicator.setAttribute('data-contact-id', contact.id);
                                indicator.textContent = '●';
                                lastMsg.appendChild(indicator);
                            }

                            const previewSpan = document.createElement('span');
                            previewSpan.textContent = formatMessageDisplay({
                                content: previewText,
                                type: 'interactive',
                            }).text;
                            lastMsg.appendChild(previewSpan);
                            info.appendChild(lastMsg);
                        } else {
                            const phone = document.createElement('div');
                            phone.className = 'wa-sidebar-phone';
                            phone.textContent = contact.phone_number;
                            info.appendChild(phone);
                        }

                        contactElement.appendChild(info);
                        contactsContainer.appendChild(contactElement);

                        // Agregar evento click
                        contactElement.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const contactId = this.getAttribute('data-contact-id');
                            if (contactId) {
                                loadContactChat(contactId);
                            }
                        });
                    });

                    if (typeof window.applyContactSearchFilter === 'function') {
                        window.applyContactSearchFilter();
                    }

                    // Scroll al contacto activo si existe
                    if (actualCurrentContactId) {
                        const newActiveContact = document.querySelector(`.wa-sidebar-contact[data-contact-id="${actualCurrentContactId}"]`);
                        if (newActiveContact) {
                            newActiveContact.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error actualizando lista de contactos:', error);
            });
        }

        // Iniciar actualización periódica de contactos (cada 5 segundos)
        function startContactsUpdate() {
            if (contactsUpdateInterval) {
                clearInterval(contactsUpdateInterval);
            }
            contactsUpdateInterval = setInterval(updateContactsList, 5000);
            // Primera actualización inmediata
            updateContactsList();
        }

        // Iniciar cuando el DOM esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startContactsUpdate);
        } else {
            startContactsUpdate();
        }
</script>
@endsection
