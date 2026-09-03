@extends('admin.layouts.app')

@section('header', 'Empresas')

@section('content')
<div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="p-6">
        <div class="flex items-start justify-between mb-6 gap-4">
            <p class="text-sm text-gray-600">
                Cada empresa tiene su propio número de WhatsApp, credenciales y conversaciones. Entrá a una empresa
                para conectar o revisar su WhatsApp.
            </p>
            <button type="button" onclick="document.getElementById('nueva-empresa-form').classList.toggle('hidden'); document.getElementById('nueva-empresa-form').classList.toggle('flex')"
                class="shrink-0 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>Nueva empresa
            </button>
        </div>

        <form id="nueva-empresa-form" action="{{ route('admin.empresas.store') }}" method="POST"
            class="hidden bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6 items-end gap-3">
            @csrf
            <div class="flex-1">
                <label for="name" class="block text-sm font-medium text-gray-700">Nombre de la empresa</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                    placeholder="Ej: Zapatos XYZ"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit"
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-save mr-2"></i>Crear
            </button>
        </form>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($companies as $company)
                <a href="{{ route('admin.empresas.whatsapp', $company) }}"
                    class="block border border-gray-200 rounded-lg p-5 hover:border-blue-400 hover:shadow-sm transition">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-lg font-medium text-gray-900">{{ $company->name }}</h3>
                        <span class="text-xs px-2 py-1 rounded-full {{ $company->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $company->status === 'active' ? 'Activa' : 'Inactiva' }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500">
                        {{ $company->whatsapp_accounts_count }}
                        {{ Str::plural('número de WhatsApp', $company->whatsapp_accounts_count) }}
                    </p>
                    <span class="inline-flex items-center text-sm font-medium text-blue-600 mt-3">
                        <i class="fas fa-arrow-right mr-1"></i> Ver WhatsApp
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
