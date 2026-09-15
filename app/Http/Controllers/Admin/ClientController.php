<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappCart;
use App\Models\WhatsappContact;
use App\Models\WhatsappContactNote;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\ClientInsightsService;
use App\Services\WhatsappService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request, ClientInsightsService $insights): View
    {
        $clients = $insights->paginate($request);
        $summary = $insights->summaryStats($request);
        $bestContactTimes = $insights->bestContactTimesForContacts(
            $clients->getCollection()->pluck('id')->all()
        );

        return view('admin.clients.index', [
            'clients' => $clients,
            'summary' => $summary,
            'bestContactTimes' => $bestContactTimes,
            'segments' => ClientInsightsService::SEGMENTS,
            'sortOptions' => ClientInsightsService::SORT_OPTIONS,
            'filters' => $request->only(['q', 'segment', 'sort', 'activity_from', 'activity_to', 'min_orders']),
            'insights' => $insights,
        ]);
    }

    /**
     * Pedido explícito: reactivar el bot para varios clientes a la vez desde
     * el listado. Ignora en silencio cualquier id en la lista negra o que no
     * pertenezca a la empresa activa, en vez de fallar todo el lote por uno
     * solo -- la casilla de selección ya no debería ofrecerlos, pero esto es
     * la defensa real del lado servidor.
     */
    public function bulkReactivateBot(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_ids' => ['required', 'array', 'min:1'],
            'client_ids.*' => ['integer'],
        ]);

        $companyId = CompanyContext::currentCompany()->id;
        $reactivated = WhatsappContact::query()
            ->whereIn('id', $validated['client_ids'])
            ->where('bot_blacklisted', false)
            ->whereHas('businessProfile', fn ($q) => $q->where('company_id', $companyId))
            ->update(['bot_enabled' => true]);

        return back()->with('success', $reactivated === 1
            ? 'Bot reactivado para 1 cliente.'
            : "Bot reactivado para {$reactivated} clientes.");
    }

    public function show(WhatsappContact $client, ClientInsightsService $insights): View
    {
        $this->authorizeClient($client);
        $detail = $insights->contactDetail($client);

        return view('admin.clients.show', array_merge($detail, [
            'insights' => $insights,
            // Totales reales (no solo "reportables") para el aviso de
            // confirmación antes de eliminar -- deben reflejar exactamente
            // lo que destroy() va a borrar.
            'deletionCounts' => [
                'orders' => WhatsappCart::where('contact_id', $client->id)->count(),
                'messages' => WhatsappMessage::where('contact_id', $client->id)->count(),
            ],
        ]));
    }

    public function update(Request $request, WhatsappContact $client): RedirectResponse
    {
        $this->authorizeClient($client);
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'national_id' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('whatsapp_contacts', 'national_id')->ignore($client->id),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'billing_type' => ['nullable', 'string', 'in:cedula,ruc'],
            'billing_id' => ['nullable', 'string', 'max:20'],
            'billing_legal_name' => ['nullable', 'string', 'max:255'],
        ]);

        $client->update([
            'name' => $validated['name'] !== null && $validated['name'] !== '' ? trim($validated['name']) : null,
            'national_id' => $this->normalizeNationalId($validated['national_id'] ?? null),
            'address' => $validated['address'] !== null && $validated['address'] !== '' ? trim($validated['address']) : null,
            'birth_date' => $validated['birth_date'] ?? null,
            'billing_type' => $validated['billing_type'] ?? null,
            'billing_id' => $this->normalizeNationalId($validated['billing_id'] ?? null),
            'billing_legal_name' => isset($validated['billing_legal_name']) && trim($validated['billing_legal_name']) !== ''
                ? trim($validated['billing_legal_name'])
                : null,
        ]);

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Datos del cliente actualizados.');
    }

    /**
     * El cliente inicia sesión en "Mi cuenta" del micrositio con
     * teléfono+contraseña. Cuando no puede recibir el código de recuperación
     * por WhatsApp (o simplemente pide ayuda al negocio), un admin puede
     * generarle una contraseña nueva directamente desde aquí.
     */
    public function resetPassword(WhatsappContact $client, WhatsappService $whatsapp): RedirectResponse
    {
        $this->authorizeClient($client);
        $newPassword = Str::password(8, symbols: false);
        $client->password = Hash::make($newPassword);
        $client->save();

        $sent = false;
        if ($client->businessProfile) {
            $whatsapp->useBusinessProfile($client->businessProfile);
            $sent = $whatsapp->sendTextMessage(
                $client,
                "🔐 Tu contraseña de \"Mi cuenta\" fue restablecida.\n\nNueva contraseña: *{$newPassword}*\n\nIngresa con tu número de WhatsApp y esta contraseña."
            ) === true;
        }

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', $sent
                ? "Contraseña restablecida y enviada por WhatsApp al cliente. Nueva contraseña: {$newPassword}"
                : "Contraseña restablecida, pero no se pudo enviar por WhatsApp. Compártela manualmente: {$newPassword}");
    }

    public function storeNote(Request $request, WhatsappContact $client): RedirectResponse
    {
        $this->authorizeClient($client);
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        WhatsappContactNote::create([
            'contact_id' => $client->id,
            'user_id' => auth()->id(),
            'body' => trim($validated['body']),
        ]);

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Observación registrada.');
    }

    /**
     * Borrado definitivo del cliente y todo su historial (mensajes, pedidos,
     * notas) -- pedido explícito en vivo para poder limpiar fichas de
     * prueba. Requiere permiso aparte (clients.delete, no incluido en
     * ningún rol por defecto salvo super_admin) precisamente porque no hay
     * vuelta atrás. El contacto en sí y sus relaciones con cascadeOnDelete
     * a nivel de base de datos (carritos, notas, direcciones guardadas,
     * tokens de pedido) se van con $client->delete(); los mensajes y
     * conversaciones se borran aparte porque esas dos tablas nunca tuvieron
     * cascade configurado en su migración original.
     */
    public function destroy(WhatsappContact $client): RedirectResponse
    {
        $this->authorizeClient($client);

        DB::transaction(function () use ($client) {
            WhatsappMessage::where('contact_id', $client->id)->delete();
            WhatsappConversation::where('contact_id', $client->id)->delete();
            $client->delete();
        });

        return redirect()
            ->route('admin.clients.index')
            ->with('success', 'Cliente y todo su historial (pedidos, mensajes y notas) fueron eliminados definitivamente.');
    }

    /**
     * Un contacto cuelga de un WhatsappBusinessProfile, que a su vez es de
     * una sola empresa -- sin esto, cualquier admin podía ver/editar la
     * ficha de un cliente de OTRA empresa con solo cambiar el id en la URL
     * (el listado ya filtraba por empresa, pero estas rutas no).
     */
    private function authorizeClient(WhatsappContact $client): void
    {
        // currentCompany() (no current()): una empresa con 2+ números y
        // ninguno marcado principal no debe romper esta pantalla solo por
        // esa ambigüedad -- aquí no hace falta resolver un número puntual.
        abort_unless(
            $client->business_profile_id
                && $client->businessProfile?->company_id === CompanyContext::currentCompany()->id,
            404
        );
    }

    private function normalizeNationalId(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return preg_replace('/\s+/', '', trim($value));
    }
}
