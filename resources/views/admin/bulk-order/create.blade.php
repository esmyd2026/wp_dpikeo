@extends('admin.layouts.app')

@section('header', 'Nuevo pedido')

@section('content')
<div style="width:100%;max-width:1540px;margin:0 auto;padding:0 18px 28px;">
    @include('bulk-order.partials.form-app', [
        'mode' => 'agent',
        'catalogUrl' => $catalogUrl,
        'submitUrl' => $submitUrl,
        'contactsSearchUrl' => $contactsSearchUrl,
        'contactsCreateUrl' => $contactsCreateUrl,
        'ordersUrl' => $ordersUrl,
        'branches' => $branches,
        'initialContact' => $initialContact,
        'headerTitle' => 'Nuevo pedido',
        'successWhatsappHint' => 'El pedido quedó registrado en el panel.',
    ])
</div>
@endsection
