<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#e85d04">
    <title>Pide por WhatsApp — {{ $businessName }}</title>
</head>
<body style="margin:0;background:#fff8f2;">
    @include('bulk-order.partials.form-app', [
        'mode' => 'public',
        'catalogUrl' => $catalogUrl,
        'submitUrl' => $submitUrl,
        'contactName' => $contactName,
        'existingCartItems' => $existingCartItems,
        'branches' => $branches,
        'headerTitle' => $businessName,
    ])
</body>
</html>
