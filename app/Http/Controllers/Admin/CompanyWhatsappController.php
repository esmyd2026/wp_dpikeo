<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Services\MetaEmbeddedSignupService;
use App\Services\MetaGraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CompanyWhatsappController extends Controller
{
    /**
     * El slug de la empresa viene de la URL: sin este chequeo, cualquier
     * admin autenticado podría ver/editar el WhatsApp de una empresa que no
     * le corresponde con solo cambiar el slug. isSuperAdmin() de Siglo
     * Tecnológico se salta esta restricción a propósito.
     */
    private function authorizeCompany(Company $company): void
    {
        abort_unless(auth()->user()?->canAccessCompany($company), 403, 'No tenés acceso a esta empresa.');
    }

    /**
     * Un $profile se resuelve por su id (route model binding), sin ningún
     * filtro de empresa en la query -- sin este segundo chequeo,
     * /admin/empresas/{cualquier-slug-autorizado}/whatsapp/{id-de-otra-empresa}
     * podría consultar/desconectar un perfil que no pertenece a $company.
     */
    private function authorizeProfile(Company $company, WhatsappBusinessProfile $profile): void
    {
        abort_unless((int) $profile->company_id === (int) $company->id, 404);
    }

    public function index()
    {
        $companies = auth()->user()->authorizedCompanies()->loadCount('whatsappAccounts');

        return view('admin.empresas.index', compact('companies'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
        ], [
            'name.required' => 'El nombre de la empresa es obligatorio.',
        ]);

        $slug = Str::slug($validated['name']);
        $baseSlug = $slug;
        $suffix = 1;
        while (Company::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-" . ++$suffix;
        }

        $company = Company::create([
            'uuid' => (string) Str::uuid(),
            'name' => $validated['name'],
            'slug' => $slug,
            'status' => 'active',
        ]);

        // Sin esto, un admin no-super_admin que crea una empresa quedaría
        // sin acceso a la que acaba de crear.
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $company->users()->attach($user->id);
        }

        return redirect()->route('admin.empresas.whatsapp', $company)
            ->with('success', 'Empresa creada. Ahora conectá su WhatsApp.');
    }

    /**
     * Solo el nombre visible -- el slug (usado en las URLs del panel, ver
     * getRouteKeyName() en Company) queda fijo a propósito, para no romper
     * ningún enlace interno ya guardado si alguien corrige un typo en el nombre.
     */
    public function updateCompany(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $company->update(['name' => trim($validated['name'])]);

        return redirect()->route('admin.empresas.whatsapp', $company)
            ->with('success', 'Nombre de la empresa actualizado.');
    }

    public function show(Company $company)
    {
        $this->authorizeCompany($company);

        $accounts = $company->whatsappAccounts()->orderByDesc('connected_at')->orderBy('id')->get();

        $metaAppId = config('services.meta.app_id');
        $metaConfigId = config('services.meta.embedded_signup_config_id');
        $metaGraphApiVersion = config('services.meta.graph_api_version');
        $embeddedSignupReady = filled($metaAppId) && filled($metaConfigId);

        return view('admin.empresas.whatsapp', compact('company', 'accounts', 'metaAppId', 'metaConfigId', 'metaGraphApiVersion', 'embeddedSignupReady'));
    }

    /**
     * El frontend (botón "Conectar WhatsApp") manda acá únicamente lo que
     * Meta le devolvió en el navegador -- code, waba_id, phone_number_id --
     * nunca un token. El intercambio real pasa por MetaEmbeddedSignupService,
     * server-side, con el app_secret que solo vive en el backend.
     */
    public function embeddedSignup(Request $request, Company $company, MetaEmbeddedSignupService $signup)
    {
        $this->authorizeCompany($company);

        $validated = $request->validate([
            'code' => 'required|string',
            'waba_id' => 'required|string',
            'phone_number_id' => 'required|string',
            'connection_mode' => 'nullable|string|in:standard,coexistence',
        ]);

        try {
            $signup->connect(
                $company,
                $validated['code'],
                $validated['waba_id'],
                $validated['phone_number_id'],
                $validated['connection_mode'] ?? 'standard'
            );
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'WhatsApp conectado correctamente.']);
    }

    /**
     * Alta/edición manual de credenciales, para mientras Embedded Signup no
     * está integrado o como respaldo si hace falta corregir algo a mano.
     */
    public function updateCredentials(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $validated = $request->validate([
            'phone_number' => 'required|string|max:30',
            'phone_number_id' => 'required|string|max:60',
            'whatsapp_business_id' => 'nullable|string|max:60',
            'access_token' => 'nullable|string|max:1000',
        ], [
            'phone_number.required' => 'El número de WhatsApp es obligatorio.',
            'phone_number_id.required' => 'El Phone Number ID es obligatorio.',
        ]);

        $profile = WhatsappBusinessProfile::where('company_id', $company->id)
            ->where('phone_number_id', $validated['phone_number_id'])
            ->first();

        if (!$profile) {
            $profile = new WhatsappBusinessProfile([
                'company_id' => $company->id,
                'business_name' => $company->name,
                'display_name' => $company->name,
                'status' => WhatsappBusinessProfile::STATUS_PENDING,
            ]);
        }

        $profile->phone_number = $validated['phone_number'];
        $profile->phone_number_id = $validated['phone_number_id'];
        $profile->whatsapp_business_id = $validated['whatsapp_business_id'] ?: null;

        // El token no se muestra en el formulario por seguridad. Si el campo
        // llega vacío, se asume que el usuario no quiso cambiarlo y se deja
        // el que ya estaba guardado.
        if (!empty($validated['access_token'])) {
            $profile->access_token = $validated['access_token'];
            $profile->connected_at = now();
            $profile->status = WhatsappBusinessProfile::STATUS_CONNECTED;
            $profile->connection_type = 'manual';
        }

        $profile->save();

        return redirect()->route('admin.empresas.whatsapp', $company)
            ->with('success', 'Credenciales de WhatsApp guardadas correctamente.');
    }

    /**
     * Datos locales para el modal "Ver detalles". Nunca incluye access_token
     * (ni siquiera cifrado/parcial) -- eso nunca debe salir hacia el frontend.
     */
    public function profileDetails(Company $company, WhatsappBusinessProfile $profile)
    {
        $this->authorizeCompany($company);
        $this->authorizeProfile($company, $profile);

        return response()->json($this->serializeProfile($profile, $company));
    }

    /**
     * "Probar conexión": solo lectura contra Graph API (un GET al recurso del
     * número). No llama /register, no toca subscribed_apps, no envía
     * mensajes, no modifica nada en Meta. Persiste únicamente el resultado
     * del chequeo (last_verified_at/last_verification_status), nunca la
     * respuesta cruda de Meta.
     */
    public function testConnection(Company $company, WhatsappBusinessProfile $profile, MetaGraphService $graph)
    {
        $this->authorizeCompany($company);
        $this->authorizeProfile($company, $profile);

        if (!$profile->phone_number_id || !$profile->access_token) {
            return response()->json([
                'ok' => false,
                'message' => 'Esta conexión no tiene Phone Number ID o token guardado; no hay nada que probar.',
            ], 422);
        }

        try {
            $info = $graph->inspectPhoneNumber($profile->phone_number_id, $profile->access_token);

            $profile->forceFill([
                'last_verified_at' => now(),
                'last_verification_status' => 'ok',
            ])->save();

            return response()->json([
                'ok' => true,
                'message' => 'Conexión operativa.',
                'checked_at' => $profile->last_verified_at->toIso8601String(),
                'graph' => [
                    'display_phone_number' => $info['display_phone_number'] ?? null,
                    'verified_name' => $info['verified_name'] ?? null,
                    'quality_rating' => $info['quality_rating'] ?? null,
                    'code_verification_status' => $info['code_verification_status'] ?? null,
                ],
            ]);
        } catch (Throwable $e) {
            $profile->forceFill([
                'last_verified_at' => now(),
                'last_verification_status' => 'failed',
            ])->save();

            return response()->json([
                'ok' => false,
                'message' => 'Conexión con problemas: ' . $e->getMessage(),
                'checked_at' => $profile->last_verified_at->toIso8601String(),
            ]);
        }
    }

    /**
     * Desconexión LOCAL únicamente: no llama a Meta, no borra la fila. El
     * número sigue existiendo en Meta y en la app de WhatsApp Business tal
     * cual estaba -- esto solo hace que la plataforma deje de usarlo (ver
     * WhatsappService::useBusinessProfile/setWebhookPhoneNumberId, que
     * excluyen cualquier perfil que no esté "connected").
     */
    public function disconnect(Company $company, WhatsappBusinessProfile $profile)
    {
        $this->authorizeCompany($company);
        $this->authorizeProfile($company, $profile);

        if ($profile->status !== WhatsappBusinessProfile::STATUS_DISCONNECTED) {
            $profile->forceFill([
                'status' => WhatsappBusinessProfile::STATUS_DISCONNECTED,
                'disconnected_at' => now(),
                // Un perfil desconectado nunca puede seguir siendo el
                // principal -- si lo era, la empresa vuelve a "requiere
                // selección" (o al único usable que le quede, si hay uno).
                'is_primary' => false,
            ])->save();
        }

        return redirect()->route('admin.empresas.whatsapp', $company)
            ->with('success', 'Conexión desconectada de la plataforma. El número sigue existiendo en Meta/WhatsApp Business -- esto no lo elimina ni lo migra.');
    }

    /**
     * Único punto de escritura de is_primary: dentro de una transacción,
     * desmarca cualquier otro perfil DE LA MISMA EMPRESA y marca el elegido.
     * Nunca toca perfiles de otra empresa (el UPDATE está acotado por
     * company_id, no es un flag global).
     */
    public function setPrimary(Company $company, WhatsappBusinessProfile $profile)
    {
        $this->authorizeCompany($company);
        $this->authorizeProfile($company, $profile);

        abort_unless($profile->isUsable(), 422, 'No se puede marcar como principal una conexión desconectada o con error.');

        DB::transaction(function () use ($company, $profile) {
            WhatsappBusinessProfile::where('company_id', $company->id)
                ->where('id', '!=', $profile->id)
                ->update(['is_primary' => false]);

            $profile->forceFill(['is_primary' => true])->save();
        });

        return redirect()->route('admin.empresas.whatsapp', $company)
            ->with('success', "«{$profile->display_name}» ahora es el número principal de {$company->name}.");
    }

    /**
     * Borrado real, a diferencia de disconnect(): saca la fila para siempre,
     * junto con su catálogo/config/flujo propios (que sin el perfil no
     * sirven para nada). Bloqueado si sigue conectado (hay que desconectarlo
     * primero, a propósito -- no queremos borrar un número en uso de un
     * click) o si tiene contactos asociados (implica clientes/pedidos reales;
     * ahí la opción es dejarlo desconectado, no perder ese historial).
     */
    public function destroy(Company $company, WhatsappBusinessProfile $profile)
    {
        $this->authorizeCompany($company);
        $this->authorizeProfile($company, $profile);

        if ($profile->status === WhatsappBusinessProfile::STATUS_CONNECTED) {
            return back()->with('error', 'Desconectá este número antes de eliminarlo.');
        }

        if (\App\Models\WhatsappContact::where('business_profile_id', $profile->id)->exists()) {
            return back()->with('error', 'No se puede eliminar: este número tiene contactos o pedidos asociados. Para conservar ese historial, dejalo desconectado en vez de borrarlo.');
        }

        DB::transaction(function () use ($profile) {
            \App\Models\WhatsappPrice::where('business_profile_id', $profile->id)->delete();
            \App\Models\WhatsappMenuItem::where('business_profile_id', $profile->id)->delete();
            \App\Models\WhatsappMenu::where('business_profile_id', $profile->id)->delete();
            \App\Models\WhatsappChatbotConfig::where('business_profile_id', $profile->id)->delete();
            \App\Models\Franchise::where('business_profile_id', $profile->id)->delete();
            \App\Models\BusinessBranch::where('business_profile_id', $profile->id)->delete();

            foreach (\App\Models\MarketingFlow::where('business_profile_id', $profile->id)->get() as $flow) {
                \App\Models\MarketingFlowStep::where('flow_id', $flow->id)->delete();
                \App\Models\MarketingFlowEdge::where('flow_id', $flow->id)->delete();
                \App\Models\MarketingFlowNode::where('flow_id', $flow->id)->delete();
                \App\Models\MarketingFlowVersion::where('flow_id', $flow->id)->delete();
                $flow->delete();
            }

            $profile->delete();
        });

        return redirect()->route('admin.empresas.whatsapp', $company)
            ->with('success', 'Número eliminado correctamente.');
    }

    private function serializeProfile(WhatsappBusinessProfile $profile, Company $company): array
    {
        return [
            'id' => $profile->id,
            'business_name' => $profile->business_name,
            'display_name' => $profile->display_name,
            'phone_number' => $profile->phone_number,
            'phone_number_id' => $profile->phone_number_id,
            'whatsapp_business_id' => $profile->whatsapp_business_id,
            'connection_type' => $profile->connection_type,
            'status' => $profile->status,
            'is_primary' => (bool) $profile->is_primary,
            'connected_at' => optional($profile->connected_at)->toIso8601String(),
            'disconnected_at' => optional($profile->disconnected_at)->toIso8601String(),
            'last_verified_at' => optional($profile->last_verified_at)->toIso8601String(),
            'last_verification_status' => $profile->last_verification_status,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
        ];
    }
}
