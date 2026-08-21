<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration settings for the WhatsApp Business API.
    | Using the specific environment variables from the user's setup.
    |
    */

    // WhatsApp Business API version (typing indicators requieren v23.0+)
    'api_version' => env('WHATSAPP_API_VERSION', 'v23.0'),

    // Indicador "escribiendo..." en WhatsApp del cliente
    'typing_indicator_enabled' => env('WHATSAPP_TYPING_INDICATOR', true),

    // Pausa (ms) entre mostrar "escribiendo..." y enviar respuesta del bot (para que el usuario lo vea)
    'bot_reply_delay_ms' => env('WHATSAPP_BOT_REPLY_DELAY_MS', 2500),

    // Base URL for API requests
    'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com'),

    // Webhook verification token (usado por Meta al suscribir la URL del webhook).
    // El .env de este proyecto históricamente usa WEBHOOK_VERIFY_TOKEN; se acepta
    // también WHATSAPP_VERIFY_TOKEN por si algún entorno lo definió con ese nombre.
    'verify_token' => env('WEBHOOK_VERIFY_TOKEN', env('WHATSAPP_VERIFY_TOKEN')),

    // App Secret de la app de Meta (Configuración básica > Secreto de la app).
    // Se usa para validar la firma X-Hub-Signature-256 de cada webhook entrante
    // y así rechazar payloads que no vengan realmente de Meta.
    'app_secret' => env('WHATSAPP_APP_SECRET'),

    // WhatsApp Business API Token
    'token' => env('WHATSAPP_TOKEN'),

    // Business Phone Number
    'phone_number' => env('WHATSAPP_PHONE_NUMBER'),

    // Número de WhatsApp para solicitar demo (formato internacional sin +, ej. 593988492339)
    'demo_whatsapp_number' => env('DEMO_WHATSAPP_NUMBER', '593994281769'),

    // Phone Number ID (15 dígitos) para llamadas a la API
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    // Catálogo de Commerce Manager vinculado a este número. Es la vitrina
    // visual de WhatsApp; el panel local mantiene los precios y productos.
    'catalog_id' => env('WHATSAPP_CATALOG_ID'),
    'catalog_thumbnail_retailer_id' => env('WHATSAPP_CATALOG_THUMBNAIL_RETAILER_ID'),
    'catalog_enabled' => filter_var(env('WHATSAPP_CATALOG_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    // Solo se muestra como tercer acceso del menú cuando Meta confirme que
    // el catálogo nativo ya tiene productos disponibles para este número.
    'native_catalog_menu_enabled' => filter_var(env('WHATSAPP_NATIVE_CATALOG_MENU_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // Default language for templates
    'default_language' => 'es',

    // Message status update delay in seconds
    'status_update_delay' => 60,

    // Maximum retry attempts for failed messages
    'max_retry_attempts' => 3,

    // Retry delay between attempts in seconds
    'retry_delay' => 60,

    // Webhook URL for receiving updates
    'webhook_url' => env('WHATSAPP_WEBHOOK_URL'),

    // Default message template category
    'default_template_category' => 'MARKETING',

    // Message template approval settings
    'template_approval' => [
        'auto_approve' => false,
        'approval_required' => true,
    ],

    // Chatbot settings
    'chatbot' => [
        'enabled' => true,
        'default_response' => 'Lo siento, no entiendo tu mensaje. Por favor, intenta con otra palabra clave.',
        'max_conversation_duration' => 3600, // 1 hour in seconds
    ],

    // ChatGPT / OpenAI (interruptor global; requiere también activar en la config del perfil)
    'chatgpt' => [
        'enabled' => filter_var(env('CHATGPT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],

    // WhatsApp Flows: Meta cifra los intercambios con esta clave privada.
    // Se genera con: php artisan whatsapp:flow-keys
    'flows' => [
        'private_key_path' => env('WHATSAPP_FLOW_PRIVATE_KEY_PATH', 'whatsapp/flows/private.pem'),
        'public_key_path' => env('WHATSAPP_FLOW_PUBLIC_KEY_PATH', 'whatsapp/flows/public.pem'),
        'openssl_config' => env('WHATSAPP_FLOW_OPENSSL_CONFIG', dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf'),
    ],

    // Business Profile Settings (use your custom variable names)
    'business_id' => env('WHATSAPP_BUSINESS_ID'),
];
