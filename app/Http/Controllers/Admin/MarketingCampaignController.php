<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappContact;
use App\Models\WhatsappPrice;
use App\Models\WhatsappTemplate;
use App\Models\WhatsappBusinessProfile;
use App\Services\WhatsappService;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MarketingCampaignController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsappService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /** Ver nota equivalente en ProductController::businessProfileId(). */
    private function businessProfileId(): ?int
    {
        return CompanyContext::current()->businessProfileId();
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $campaigns = WhatsappCampaign::with('template')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('admin.marketing.index', compact('campaigns'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Buscar plantillas aprobadas o activas (funciona en producción y local)
        // Prioriza: 'approved', 'active', 'Active - Quality pendin', etc.
        $templates = WhatsappTemplate::where(function($query) {
            $query->whereRaw('LOWER(status) LIKE ?', ['approved%'])
                  ->orWhereRaw('LOWER(status) LIKE ?', ['active%'])
                  ->orWhere('status', 'APPROVED')
                  ->orWhere('status', 'approved')
                  ->orWhere('status', 'ACTIVE')
                  ->orWhere('status', 'active');
        })->get();

        // Si no hay resultados, obtener todas excepto rechazadas/pending (fallback para local)
        if ($templates->isEmpty()) {
            $templates = WhatsappTemplate::whereNotIn('status', ['rejected', 'REJECTED', 'pending', 'PENDING'])
                                         ->whereNotNull('template_id')
                                         ->get();
        }

        $businessProfileId = $this->businessProfileId();
        $contacts = WhatsappContact::where('status', 'active')->where('business_profile_id', $businessProfileId)->orderBy('name')->get();
        $recentContactIds = $this->recentContactIds($businessProfileId);
        $products = $this->campaignProducts($businessProfileId);
        $businessProfile = CompanyContext::current()->businessProfile;

        return view('admin.marketing.create', compact('templates', 'contacts', 'recentContactIds', 'products', 'businessProfile'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'message_type' => 'required|in:text,template,image,interactive',
            'message_content' => 'required_if:message_type,text|nullable|string|max:4096',
            'image' => 'required_if:message_type,image|nullable|image|max:5120',
            'product_id' => 'nullable|exists:whatsapp_prices,id',
            'button_text' => 'nullable|string|max:20|required_with:product_id',
            'template_id' => 'required_if:message_type,template|nullable|exists:whatsapp_templates,id',
            'template_variables' => 'nullable|array',
            'recipient_type' => 'required|in:all,filtered,selected',
            'recipient_filters' => 'nullable|array',
            'selected_contacts' => 'nullable|array',
            'selected_contacts.*' => 'exists:whatsapp_contacts,id',
            'manual_numbers' => 'nullable|array',
            'manual_numbers.*' => 'string',
            'scheduled_at' => 'nullable|date|after:now',
            'send_immediately' => 'nullable|boolean',
        ]);

        $businessProfile = WhatsappBusinessProfile::first();
        if (!$businessProfile) {
            return redirect()->back()->with('error', 'No se encontró un perfil de negocio configurado');
        }

        // Manejar números manuales - crear contactos temporales si es necesario
        $selectedContacts = $validated['selected_contacts'] ?? [];
        if (!empty($validated['manual_numbers'])) {
            foreach ($validated['manual_numbers'] as $phoneNumber) {
                $phoneNumber = trim($phoneNumber);
                if ($phoneNumber) {
                    // Buscar si el contacto ya existe
                    $existingContact = WhatsappContact::where('phone_number', $phoneNumber)
                        ->where('business_profile_id', $businessProfile->id)
                        ->first();

                    if ($existingContact) {
                        // Agregar a la lista si no está ya incluido
                        if (!in_array($existingContact->id, $selectedContacts)) {
                            $selectedContacts[] = $existingContact->id;
                        }
                    } else {
                        // Crear contacto temporal
                        $newContact = WhatsappContact::create([
                            'business_profile_id' => $businessProfile->id,
                            'name' => 'Contacto ' . substr($phoneNumber, -4),
                            'phone_number' => $phoneNumber,
                            'status' => 'active',
                            'metadata' => ['temporary' => true, 'created_from_campaign' => true]
                        ]);
                        $selectedContacts[] = $newContact->id;
                    }
                }
            }
        }

        // Actualizar validated con los contactos seleccionados (incluidos los nuevos)
        $validated['selected_contacts'] = array_unique($selectedContacts);

        // Calcular total de destinatarios
        $totalRecipients = $this->calculateRecipients($validated, $businessProfile->id);

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store("campaign-images/{$businessProfile->id}", 'public')
            : null;

        $isImageType = $validated['message_type'] === 'image';

        $campaign = WhatsappCampaign::create([
            'business_profile_id' => $businessProfile->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'message_type' => $validated['message_type'],
            'message_content' => $validated['message_content'] ?? null,
            'image_path' => $imagePath,
            'product_id' => $isImageType ? ($validated['product_id'] ?? null) : null,
            'button_text' => $isImageType ? ($validated['button_text'] ?? null) : null,
            'template_id' => $validated['template_id'] ?? null,
            'template_variables' => $validated['template_variables'] ?? null,
            'recipient_type' => $validated['recipient_type'],
            'recipient_filters' => $validated['recipient_filters'] ?? null,
            'selected_contacts' => $validated['selected_contacts'] ?? null,
            'status' => $validated['scheduled_at'] ? 'scheduled' : 'draft',
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'total_recipients' => $totalRecipients,
        ]);

        if ($request->boolean('send_immediately') && !$validated['scheduled_at']) {
            $result = $this->executeCampaignSend($campaign->fresh());
            $route = redirect()->route('admin.marketing.show', $campaign);

            if (!$result['ok']) {
                return $route->with('error', 'Campaña creada, pero no se pudo enviar: ' . $result['message']);
            }

            return $route->with($result['flash_type'] ?? 'success', $result['message']);
        }

        return redirect()->route('admin.marketing.index')
            ->with('success', $validated['scheduled_at']
                ? 'Campaña programada. Se enviará automáticamente si el programador (cron) está activo.'
                : 'Campaña creada. Pulsa "Enviar" en la lista para lanzarla.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $campaign = WhatsappCampaign::with('template', 'businessProfile')->findOrFail($id);
        return view('admin.marketing.show', compact('campaign'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $campaign = WhatsappCampaign::findOrFail($id);

        if ($campaign->status === 'sending' || $campaign->status === 'completed') {
            return redirect()->route('admin.marketing.show', $campaign)
                ->with('error', 'No se puede editar una campaña que ya está en proceso o completada');
        }

        // Buscar plantillas aprobadas o activas (case-insensitive)
        // Incluye: 'approved', 'active', 'Active - Quality pendin', etc.
        $templates = WhatsappTemplate::where(function($query) {
            $query->whereRaw('LOWER(status) LIKE ?', ['approved%'])
                  ->orWhereRaw('LOWER(status) LIKE ?', ['active%'])
                  ->orWhere('status', 'APPROVED')
                  ->orWhere('status', 'approved')
                  ->orWhere('status', 'ACTIVE')
                  ->orWhere('status', 'active');
        })->get();

        // Si no hay resultados, obtener todas excepto rechazadas/pending (fallback para local)
        if ($templates->isEmpty()) {
            $templates = WhatsappTemplate::whereNotIn('status', ['rejected', 'REJECTED', 'pending', 'PENDING'])
                                         ->whereNotNull('template_id')
                                         ->get();
        }

        $businessProfileId = $this->businessProfileId();
        $contacts = WhatsappContact::where('status', 'active')->where('business_profile_id', $businessProfileId)->orderBy('name')->get();
        $recentContactIds = $this->recentContactIds($businessProfileId);
        $products = $this->campaignProducts($businessProfileId);
        $businessProfile = CompanyContext::current()->businessProfile;

        return view('admin.marketing.edit', compact('campaign', 'templates', 'contacts', 'recentContactIds', 'products', 'businessProfile'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $campaign = WhatsappCampaign::findOrFail($id);

        if ($campaign->status === 'sending' || $campaign->status === 'completed') {
            return redirect()->back()->with('error', 'No se puede editar una campaña que ya está en proceso o completada');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'message_type' => 'required|in:text,template,image,interactive',
            'message_content' => 'required_if:message_type,text|nullable|string|max:4096',
            'image' => 'nullable|image|max:5120',
            'product_id' => 'nullable|exists:whatsapp_prices,id',
            'button_text' => 'nullable|string|max:20|required_with:product_id',
            'template_id' => 'required_if:message_type,template|nullable|exists:whatsapp_templates,id',
            'template_variables' => 'nullable|array',
            'recipient_type' => 'required|in:all,filtered,selected',
            'recipient_filters' => 'nullable|array',
            'selected_contacts' => 'nullable|array',
            'selected_contacts.*' => 'exists:whatsapp_contacts,id',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        if ($validated['message_type'] === 'image' && !$request->hasFile('image') && !$campaign->image_path) {
            return redirect()->back()->withErrors([
                'image' => 'Debes subir una imagen para este tipo de mensaje.',
            ])->withInput();
        }

        // Recalcular total de destinatarios
        $totalRecipients = $this->calculateRecipients($validated, $campaign->business_profile_id);

        $imagePath = $campaign->image_path;
        if ($request->hasFile('image')) {
            if ($campaign->image_path && Storage::disk('public')->exists($campaign->image_path)) {
                Storage::disk('public')->delete($campaign->image_path);
            }
            $imagePath = $request->file('image')->store("campaign-images/{$campaign->business_profile_id}", 'public');
        }

        $isImageType = $validated['message_type'] === 'image';

        $campaign->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'message_type' => $validated['message_type'],
            'message_content' => $validated['message_content'] ?? null,
            'image_path' => $imagePath,
            'product_id' => $isImageType ? ($validated['product_id'] ?? null) : null,
            'button_text' => $isImageType ? ($validated['button_text'] ?? null) : null,
            'template_id' => $validated['template_id'] ?? null,
            'template_variables' => $validated['template_variables'] ?? null,
            'recipient_type' => $validated['recipient_type'],
            'recipient_filters' => $validated['recipient_filters'] ?? null,
            'selected_contacts' => $validated['selected_contacts'] ?? null,
            'status' => $validated['scheduled_at'] ? 'scheduled' : 'draft',
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'total_recipients' => $totalRecipients,
        ]);

        return redirect()->route('admin.marketing.index')
            ->with('success', 'Campaña actualizada correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $campaign = WhatsappCampaign::findOrFail($id);

        if ($campaign->status === 'sending') {
            return redirect()->back()->with('error', 'No se puede eliminar una campaña que está en proceso de envío');
        }

        $campaign->delete();

        return redirect()->route('admin.marketing.index')
            ->with('success', 'Campaña eliminada correctamente');
    }

    /**
     * Envía la campaña (web).
     */
    public function send($id)
    {
        $campaign = WhatsappCampaign::findOrFail($id);

        if ($campaign->status === 'sending' || $campaign->status === 'completed') {
            return redirect()->back()->with('error', 'Esta campaña ya fue enviada o está en proceso');
        }

        $result = $this->executeCampaignSend($campaign);

        if (!$result['ok']) {
            return redirect()->back()->with('error', $result['message']);
        }

        $flash = $result['flash_type'] === 'warning' ? 'warning' : 'success';

        return redirect()->route('admin.marketing.show', $campaign)
            ->with($flash, $result['message']);
    }

    /**
     * Lógica de envío reutilizable (web, cron, artisan).
     */
    public function executeCampaignSend(WhatsappCampaign $campaign): array
    {
        if ($campaign->status === 'sending' || $campaign->status === 'completed') {
            return ['ok' => false, 'message' => 'Esta campaña ya fue enviada o está en proceso'];
        }

        if (empty(trim((string) $campaign->message_content)) && $campaign->message_type === 'text') {
            return ['ok' => false, 'message' => 'La campaña no tiene contenido de mensaje'];
        }

        if ($campaign->message_type === 'template' && !$campaign->template) {
            return ['ok' => false, 'message' => 'Debe seleccionar una plantilla aprobada'];
        }

        if ($campaign->message_type === 'image' && !$campaign->image_path) {
            return ['ok' => false, 'message' => 'La campaña no tiene una imagen cargada'];
        }

        if (!in_array($campaign->message_type, ['text', 'template', 'image'], true)) {
            return ['ok' => false, 'message' => 'Tipo de mensaje no soportado aún: ' . $campaign->message_type];
        }

        // Fail closed: sin un negocio de WhatsApp propio, esta campaña no se
        // envía con el de otra empresa "por defecto".
        $businessProfile = $campaign->businessProfile;
        if (!$businessProfile || !$businessProfile->access_token) {
            return ['ok' => false, 'message' => 'Esta campaña no tiene una cuenta de WhatsApp configurada. No se envía con la de otra empresa.'];
        }

        try {
            $this->whatsappService->useBusinessProfile($businessProfile);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'La cuenta de WhatsApp de esta campaña no está disponible (desconectada o inválida). No se envía con la de otra empresa: ' . $e->getMessage()];
        }

        try {
            $campaign->update(['status' => 'sending']);

            $recipients = $this->getRecipients($campaign);

            if ($recipients->isEmpty()) {
                $campaign->update(['status' => 'draft']);
                return ['ok' => false, 'message' => 'No se encontraron destinatarios para la campaña'];
            }

            $sent = 0;
            $failed = 0;
            $errorDetails = [];

            foreach ($recipients as $contact) {
                try {
                    $success = false;
                    $errorMessage = null;

                    if ($campaign->message_type === 'text') {
                        if (!$this->contactCanReceiveFreeformText($contact)) {
                            $errorMessage = 'El contacto no escribió en las últimas 24 h. Use una plantilla aprobada para campañas masivas.';
                        } else {
                            $personalizedMessage = $this->personalizeMessage(
                                (string) $campaign->message_content,
                                $contact
                            );
                            $result = $this->whatsappService->sendTextMessage(
                                $contact,
                                $personalizedMessage,
                                false
                            );
                            [$success, $errorMessage] = $this->parseSendResult($result);
                        }
                    } elseif ($campaign->message_type === 'template' && $campaign->template) {
                        $personalizedVars = $this->personalizeVariables(
                            $campaign->template_variables ?? [],
                            $contact
                        );
                        $result = $this->whatsappService->sendTemplateMessage(
                            $contact,
                            $campaign->template,
                            $personalizedVars
                        );
                        [$success, $errorMessage] = $this->parseSendResult($result);
                    } elseif ($campaign->message_type === 'image' && $campaign->product_id) {
                        // Imagen con botón: el botón usa el mismo id
                        // "producto_{id}" que ya reconoce el motor clásico
                        // del bot, así que al tocarlo el cliente entra a la
                        // ficha real del producto y de ahí al carrito, sin
                        // un flujo de compra paralelo.
                        if (!$this->contactCanReceiveFreeformText($contact)) {
                            $errorMessage = 'El contacto no escribió en las últimas 24 h. Use una plantilla aprobada para campañas masivas.';
                        } else {
                            $body = $campaign->message_content
                                ? $this->personalizeMessage((string) $campaign->message_content, $contact)
                                : ($campaign->product?->name ?? '¡No te lo pierdas!');
                            $result = $this->whatsappService->sendImageWithButtonMessage(
                                $contact,
                                $campaign->image_url,
                                $body,
                                $campaign->product_id,
                                (string) $campaign->button_text
                            );
                            [$success, $errorMessage] = $this->parseSendResult($result);
                        }
                    } elseif ($campaign->message_type === 'image') {
                        if (!$this->contactCanReceiveFreeformText($contact)) {
                            $errorMessage = 'El contacto no escribió en las últimas 24 h. Use una plantilla aprobada para campañas masivas.';
                        } else {
                            $caption = $campaign->message_content
                                ? $this->personalizeMessage((string) $campaign->message_content, $contact)
                                : null;
                            $result = $this->whatsappService->sendImageMessage(
                                $contact,
                                Storage::disk('public')->path($campaign->image_path),
                                $caption
                            );
                            [$success, $errorMessage] = $this->parseSendResult($result);
                        }
                    } else {
                        $errorMessage = 'Configuración de plantilla inválida';
                    }

                    if ($success) {
                        $sent++;
                    } else {
                        $failed++;
                        $errorDetails[] = [
                            'contact_id' => $contact->id,
                            'contact_name' => $contact->name,
                            'phone_number' => $contact->phone_number,
                            'error' => $errorMessage ?? 'Error desconocido al enviar',
                            'timestamp' => now()->toIso8601String(),
                        ];
                    }

                    usleep(500000);
                } catch (\Exception $e) {
                    Log::error('Error enviando mensaje de campaña', [
                        'campaign_id' => $campaign->id,
                        'contact_id' => $contact->id,
                        'error' => $e->getMessage(),
                    ]);
                    $failed++;
                    $errorDetails[] = [
                        'contact_id' => $contact->id,
                        'contact_name' => $contact->name ?? 'Desconocido',
                        'phone_number' => $contact->phone_number ?? 'N/A',
                        'error' => $e->getMessage(),
                        'timestamp' => now()->toIso8601String(),
                    ];
                }

                $campaign->update([
                    'sent_count' => $sent,
                    'failed_count' => $failed,
                    'error_details' => !empty($errorDetails) ? $errorDetails : null,
                ]);
            }

            $campaign->update([
                'status' => 'completed',
                'sent_at' => now(),
                'error_details' => !empty($errorDetails) ? $errorDetails : null,
            ]);

            $message = "Campaña procesada. Enviados: {$sent}, Fallidos: {$failed}";
            $flashType = $sent === 0 && $failed > 0 ? 'warning' : 'success';

            if ($sent === 0 && $failed > 0 && in_array($campaign->message_type, ['text', 'image'], true)) {
                $message .= '. Para contactos sin conversación reciente, use plantillas aprobadas por Meta.';
            }

            return ['ok' => true, 'message' => $message, 'flash_type' => $flashType, 'sent' => $sent, 'failed' => $failed];
        } catch (\Exception $e) {
            Log::error('Error enviando campaña', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            $campaign->update(['status' => 'draft']);

            return ['ok' => false, 'message' => 'Error al enviar la campaña: ' . $e->getMessage()];
        }
    }

    private function parseSendResult($result): array
    {
        if (is_array($result)) {
            return [(bool) ($result['success'] ?? false), $result['error'] ?? null];
        }

        return [(bool) $result, $result ? null : 'No se pudo enviar el mensaje'];
    }

    /**
     * Texto libre solo si el cliente escribió en las últimas 24 h (regla de Meta).
     */
    private function contactCanReceiveFreeformText(WhatsappContact $contact): bool
    {
        return $contact->last_inbound_at !== null
            && $contact->last_inbound_at->greaterThanOrEqualTo(now()->subHours(24));
    }

    /**
     * IDs de contactos que escribieron en las últimas 24 h: son los únicos
     * que realmente pueden recibir un mensaje libre (texto o imagen). Se usa
     * tanto para filtrar la lista de selección manual en el formulario como
     * para no intentar envíos que Meta va a rechazar de entrada.
     */
    private function recentContactIds(?int $businessProfileId = null): array
    {
        return WhatsappContact::where('last_inbound_at', '>=', now()->subHours(24))
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->pluck('id')
            ->all();
    }

    /**
     * Productos activos que se pueden vincular a una campaña de imagen: al
     * tocar el botón, el cliente entra a la ficha de ese producto por el
     * mismo camino que ya usa el catálogo clásico del bot.
     */
    private function campaignProducts(?int $businessProfileId = null)
    {
        return WhatsappPrice::where('is_active', true)
            ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
            ->orderByDesc('is_promo')
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'is_promo']);
    }

    /**
     * Obtiene los destinatarios según la configuración de la campaña
     */
    private function getRecipients(WhatsappCampaign $campaign)
    {
        $query = WhatsappContact::where('status', 'active');

        if ($campaign->business_profile_id) {
            $query->where('business_profile_id', $campaign->business_profile_id);
        }

        // Un mensaje libre (texto o imagen) solo le puede llegar a quien te
        // escribió en las últimas 24 h; fuera de esa ventana Meta lo rechaza.
        // Se filtra aquí para no gastar intentos (ni ensuciar el reporte de
        // fallidos) con contactos que de entrada no son elegibles.
        if (in_array($campaign->message_type, ['text', 'image'], true)) {
            $query->where('last_inbound_at', '>=', now()->subHours(24));
        }

        switch ($campaign->recipient_type) {
            case 'all':
                // Todos los contactos activos
                break;

            case 'filtered':
                // Aplicar filtros
                $filters = $campaign->recipient_filters ?? [];
                if (isset($filters['bot_enabled'])) {
                    $query->where('bot_enabled', $filters['bot_enabled']);
                }
                // Agregar más filtros según necesidad
                break;

            case 'selected':
                // Contactos seleccionados manualmente
                $selectedIds = $campaign->selected_contacts ?? [];
                if (!empty($selectedIds)) {
                    $query->whereIn('id', $selectedIds);
                } else {
                    return collect();
                }
                break;
        }

        return $query->get();
    }

    /**
     * Calcula el total de destinatarios
     */
    private function calculateRecipients(array $data, ?int $businessProfileId = null): int
    {
        $query = WhatsappContact::where('status', 'active');

        if ($businessProfileId) {
            $query->where('business_profile_id', $businessProfileId);
        }

        $isFreeform = in_array($data['message_type'] ?? null, ['text', 'image'], true);
        if ($isFreeform) {
            $query->where('last_inbound_at', '>=', now()->subHours(24));
        }

        switch ($data['recipient_type']) {
            case 'all':
                return $query->count();

            case 'filtered':
                $filters = $data['recipient_filters'] ?? [];
                if (isset($filters['bot_enabled'])) {
                    $query->where('bot_enabled', $filters['bot_enabled']);
                }
                return $query->count();

            case 'selected':
                $selectedIds = $data['selected_contacts'] ?? [];
                if ($isFreeform && !empty($selectedIds)) {
                    return $query->whereIn('id', $selectedIds)->count();
                }
                return count($selectedIds);

            default:
                return 0;
        }
    }

    /**
     * Personaliza un mensaje de texto con datos del contacto
     */
    private function personalizeMessage(string $message, WhatsappContact $contact): string
    {
        $personalized = $message;

        // Reemplazar placeholders comunes
        $personalized = str_replace('{{name}}', $contact->name ?? 'Cliente', $personalized);
        $personalized = str_replace('{{phone}}', $contact->phone_number ?? '', $personalized);
        $personalized = str_replace('{{nombre}}', $contact->name ?? 'Cliente', $personalized);
        $personalized = str_replace('{{telefono}}', $contact->phone_number ?? '', $personalized);
        $personalized = str_replace('{{nombre_contacto}}', $contact->name ?? 'Cliente', $personalized);

        return $personalized;
    }

    /**
     * Personaliza las variables de la plantilla con datos del contacto
     */
    private function personalizeVariables(array $variables, WhatsappContact $contact): array
    {
        $personalized = [];
        foreach ($variables as $var) {
            if (is_string($var)) {
                // Reemplazar placeholders comunes
                $var = str_replace('{{name}}', $contact->name ?? 'Cliente', $var);
                $var = str_replace('{{phone}}', $contact->phone_number ?? '', $var);
                $var = str_replace('{{nombre}}', $contact->name ?? 'Cliente', $var);
                $var = str_replace('{{telefono}}', $contact->phone_number ?? '', $var);
            }
            $personalized[] = $var;
        }
        return $personalized;
    }

    /**
     * Reprograma una campaña completada o fallida
     */
    public function reschedule(Request $request, $id)
    {
        $campaign = WhatsappCampaign::findOrFail($id);

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        // Resetear estadísticas y estado
        $campaign->update([
            'status' => 'scheduled',
            'scheduled_at' => $validated['scheduled_at'],
            'sent_at' => null,
            'sent_count' => 0,
            'failed_count' => 0,
            'delivered_count' => 0,
            'read_count' => 0,
            'error_details' => null
        ]);

        return redirect()->route('admin.marketing.show', $campaign)
            ->with('success', 'Campaña reprogramada correctamente');
    }

    /**
     * Obtiene contactos para AJAX (búsqueda)
     */
    public function getContacts(Request $request)
    {
        $search = $request->get('search', '');

        $contacts = WhatsappContact::where('status', 'active')
            ->where(function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            })
            ->limit(50)
            ->get(['id', 'name', 'phone_number']);

        return response()->json($contacts);
    }
}
