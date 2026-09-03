<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta (WhatsApp Embedded Signup)
    |--------------------------------------------------------------------------
    |
    | app_id y config_id NO son secretos (viajan al frontend, los necesita el
    | SDK de Facebook para abrir el flujo). app_secret es exclusivamente de
    | backend -- se usa una sola vez para intercambiar el "code" que entrega
    | el popup de Meta por un token de negocio; nunca debe llegar al navegador
    | ni a los logs.
    |
    */
    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'embedded_signup_config_id' => env('META_EMBEDDED_SIGNUP_CONFIG_ID'),
        'graph_api_version' => env('META_GRAPH_API_VERSION', 'v26.0'),
    ],

];
