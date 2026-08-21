<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ff650b">
    <title>Punto de venta — DPIKEOS</title>
</head>
<body style="margin:0;background:#fff7ef;">
    @include('bulk-order.partials.form-app', [
        'mode' => 'kiosk',
        'catalogUrl' => $catalogUrl,
        'submitUrl' => $submitUrl,
        'contactsCreateUrl' => $contactsCreateUrl,
        'branches' => $branches,
        'defaultBranchId' => $defaultBranchId,
        'headerTitle' => 'DPIKEOS',
        'headerSubtitle' => 'Elige tus productos. Al final te damos tu número de pedido para pagar en caja.',
    ])
</body>
</html>
