@extends('admin.layouts.app')

@section('header', 'Nuevo pedido')

@section('content')
<div style="max-width:760px;margin:0 auto;">
    @include('bulk-order.partials.form-app', [
        'mode' => 'agent',
        'catalogUrl' => $catalogUrl,
        'submitUrl' => $submitUrl,
        'contactsSearchUrl' => $contactsSearchUrl,
        'contactsCreateUrl' => $contactsCreateUrl,
        'ordersUrl' => $ordersUrl,
        'branches' => $branches,
        'initialContact' => $initialContact,
        'headerTitle' => 'Nuevo pedido web',
        'successWhatsappHint' => 'El pedido quedó registrado en el panel.',
    ])
</div>
@endsection
