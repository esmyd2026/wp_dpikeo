@extends('admin.layouts.app')

@section('header', 'Mensajes')

@section('content')
<div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="p-6">
        <div class="space-y-4">
            @forelse($messages as $message)
                <div class="border rounded-lg p-4 hover:bg-gray-50">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2">
                                <span class="font-medium text-gray-900">
                                    {{ $message->contact->name ?? 'Cliente' }}
                                </span>
                                <span class="text-sm text-gray-500">
                                    {{ $message->contact->phone ?? 'Sin teléfono' }}
                                </span>
                            </div>
                            <p class="mt-2 text-gray-600">
                                @php
                                    $content = $message->content;
                                    $decoded = null;
                                    try {
                                        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                                    } catch (\Throwable $e) {
                                        $decoded = null;
                                    }
                                @endphp
                                @if(is_array($decoded) && isset($decoded['title']))
                                    <span class="font-semibold">{{ $decoded['title'] }}</span>
                                    @if(isset($decoded['description']))
                                        <br><span class="text-xs">{{ \Illuminate\Support\Str::limit($decoded['description'], 80) }}</span>
                                    @endif
                                @else
                                    {{ \Illuminate\Support\Str::limit($content, 80) }}
                                @endif
                            </p>
                            <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                <span>
                                    <i class="far fa-clock mr-1"></i>
                                    {{ $message->created_at->format('d/m/Y H:i') }}
                                </span>
                                @if($message->conversation)
                                    <span>
                                        <i class="fas fa-comments mr-1"></i>
                                        Conversación #{{ $message->conversation->id }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            @if($message->type === 'incoming')
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                    Recibido
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                    Enviado
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-gray-500">
                    No hay mensajes registrados
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $messages->links() }}
        </div>
    </div>
</div>
@endsection
