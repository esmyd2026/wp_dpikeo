<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\WhatsappBusinessProfile;
use App\Services\MetaEmbeddedSignupService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

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
}
