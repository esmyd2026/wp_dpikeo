@extends('admin.layouts.app')

@section('header', 'Flujo del bot (visual)')

@section('content')
@php
    $flowPrimary = $chatbotConfig?->primary_color ?? '#128c7e';
    $flowSecondary = $chatbotConfig?->secondary_color ?? '#075e54';
@endphp
<div class="container-fluid" style="max-width:1400px">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h2 class="mb-1 fw-bold">Flujo del bot (visual) — {{ $activeCompany?->name }}</h2>
            <p class="text-muted mb-0">
                Arrastra los nodos, conéctalos desde sus botones/opciones, o agrega nodos nuevos desde el panel de la izquierda. Los cambios se guardan automáticamente.
                <a href="{{ route('admin.marketing-flow.edit') }}">Volver al editor clásico</a>
            </p>
        </div>
    </div>

    @unless($hasFlow)
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
            <i class="fas fa-triangle-exclamation"></i>
            <div>
                <strong>SIN FLUJO CONFIGURADO</strong> para {{ $activeCompany?->name }}. Esta empresa todavía no tiene su
                propio flujo de conversación — nunca hereda el de otra empresa.
                <a href="{{ route('admin.marketing-flow.edit') }}">Creá el primero desde el editor clásico</a>,
                completando tus propios textos y opciones; después vas a poder seguir editándolo acá.
            </div>
        </div>
    @endunless

    <div
        id="flow-editor-app"
        data-api-base="{{ route('admin.marketing-flow.graph.edit') }}"
        data-primary-color="{{ $flowPrimary }}"
        data-secondary-color="{{ $flowSecondary }}"
    ></div>
</div>
@endsection

@push('scripts')
@vite('resources/js/flow-editor.js')
@endpush
