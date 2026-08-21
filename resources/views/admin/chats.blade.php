@extends('admin.layouts.app')

@section('header', 'Chats de Clientes')

@section('content')
<div class="max-w-lg mx-auto bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="divide-y divide-gray-200">
        @forelse($contacts as $contact)
            <a href="{{ route('admin.chat', $contact->id) }}" class="block hover:bg-green-50 transition-colors">
                <div class="flex items-center px-4 py-3">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 rounded-full bg-green-200 flex items-center justify-center text-lg font-bold text-green-700">
                            {{ strtoupper(substr($contact->name ?? 'C', 0, 1)) }}
                        </div>
                    </div>
                    <div class="flex-1 min-w-0 ml-4">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-gray-900 text-base truncate">{{ $contact->name ?? 'Cliente' }}</span>
                            <span class="text-xs text-gray-400 ml-2 whitespace-nowrap">{{ $contact->last_message_at ? \Carbon\Carbon::parse($contact->last_message_at)->format('H:i') : '' }}</span>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <span class="text-sm text-gray-500 truncate">{{ $contact->last_message ? $contact->last_message : $contact->phone_number }}</span>
                            @if($contact->unread_count ?? false)
                                <span class="ml-2 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-500 text-white">{{ $contact->unread_count }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="px-6 py-10 text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-green-50 flex items-center justify-center mb-4">
                    <i class="fab fa-whatsapp text-2xl text-green-600"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">No hay chats todavía</h3>
                <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">
                    Cuando un cliente escriba al bot por WhatsApp, la conversación aparecerá aquí.
                </p>

                @if($botWhatsApp ?? null)
                    <div class="mt-6 p-4 rounded-xl bg-gray-50 border border-gray-100 text-left">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Enlace del bot</p>
                        <p class="text-sm font-medium text-gray-900 mb-3">{{ $botWhatsApp['label'] }}</p>
                        <a href="{{ $botWhatsApp['url'] }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center justify-center gap-2 w-full px-4 py-3 rounded-lg text-white font-semibold text-sm no-underline"
                           style="background: linear-gradient(135deg, #128c7e, #25d366);">
                            <i class="fab fa-whatsapp text-lg"></i>
                            Escribir el primer mensaje
                        </a>
                        <p class="mt-3 text-xs text-gray-500 text-center font-mono">{{ $botWhatsApp['display_number'] }}</p>
                        <button type="button" id="copy-bot-wa-link" data-url="{{ $botWhatsApp['url'] }}"
                                class="mt-2 w-full text-xs text-emerald-700 hover:text-emerald-900 border-0 bg-transparent cursor-pointer">
                            <i class="fas fa-copy me-1"></i> Copiar enlace
                        </button>
                    </div>
                    <p class="mt-4 text-xs text-gray-400">
                        Comparte este enlace con clientes o ábrelo en tu teléfono para probar el bot.
                    </p>
                @else
                    <p class="mt-4 text-sm text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                        Configura <code class="text-xs">WHATSAPP_PHONE_NUMBER</code> en el servidor para mostrar el enlace del bot.
                    </p>
                @endif
            </div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('copy-bot-wa-link')?.addEventListener('click', function () {
    const url = this.getAttribute('data-url');
    if (!url) return;
    navigator.clipboard.writeText(url).then(() => {
        const prev = this.innerHTML;
        this.innerHTML = '<i class="fas fa-check me-1"></i> Enlace copiado';
        setTimeout(() => { this.innerHTML = prev; }, 2000);
    });
});
</script>
@endpush
