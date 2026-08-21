<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappContact;
use App\Models\WhatsappContactNote;
use App\Services\ClientInsightsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function show(WhatsappContact $client, ClientInsightsService $insights): View
    {
        $detail = $insights->contactDetail($client);

        return view('admin.clients.show', array_merge($detail, [
            'insights' => $insights,
        ]));
    }

    public function update(Request $request, WhatsappContact $client): RedirectResponse
    {
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

    public function storeNote(Request $request, WhatsappContact $client): RedirectResponse
    {
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

    private function normalizeNationalId(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return preg_replace('/\s+/', '', trim($value));
    }
}
